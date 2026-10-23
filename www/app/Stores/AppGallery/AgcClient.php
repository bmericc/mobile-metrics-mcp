<?php

namespace App\Stores\AppGallery;

use App\Models\StoreAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use phpseclib4\Crypt\PublicKeyLoader;
use phpseclib4\Crypt\RSA;
use RuntimeException;

/**
 * AppGallery Connect API istemcisi. İki kimlik modu:
 *  - Service Account (Huawei'nin önerdiği): credentials.service_account_json; PS256 imzalı JWT
 *    doğrudan Bearer token olarak kullanılır, token uç noktasına gidilmez.
 *  - API client: credentials.client_id + client_secret; client_credentials ile token alınır.
 *
 * Huawei hataları çoğunlukla HTTP 200 ile, gövdede ret.code / ret.rtnCode olarak döner.
 * Token, çağrılan API ile aynı alan adından alınmalı; bu yüzden token site başına tutulur.
 */
class AgcClient
{
    public const SITES = [
        'cn' => 'https://connect-api.cloud.huawei.com',
        'de' => 'https://connect-api-dre.cloud.huawei.com',
        'sg' => 'https://connect-api-dra.cloud.huawei.com',
        'ru' => 'https://connect-api-drru.cloud.huawei.com',
    ];

    /** @var array<string, array{token: string, expires_at: int}> */
    private array $tokens = [];

    public function __construct(private readonly StoreAccount $account) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = [], string $site = 'cn'): array
    {
        $response = $this->request($site)->get(self::SITES[$site].$path, $query ?: null)->throw()->json();
        $this->assertOk($response);

        return $response;
    }

    /**
     * Rapor dosyası URL'i önceden imzalı; AGC başlıkları gönderilmez.
     */
    public function download(string $url): string
    {
        return Http::timeout(120)->retry(3, 1000)->get($url)->throw()->body();
    }

    public function clientId(): string
    {
        return (string) ($this->account->credentials['client_id'] ?? '');
    }

    public function usesServiceAccount(): bool
    {
        return filled($this->account->credentials['service_account_json'] ?? null);
    }

    /**
     * @return array{key_id: string, private_key: string, sub_account: string}
     */
    public function serviceAccount(): array
    {
        $json = json_decode($this->account->credentials['service_account_json'] ?? '', true);

        if (! is_array($json) || empty($json['key_id']) || empty($json['private_key']) || empty($json['sub_account'])) {
            throw new RuntimeException('Service Account dosyası geçersiz: key_id, private_key ve sub_account alanları bulunamadı.');
        }

        return $json;
    }

    private function request(string $site): PendingRequest
    {
        return Http::withToken($this->token($site))
            ->withHeaders($this->usesServiceAccount() ? [] : ['client_id' => $this->clientId()])
            ->acceptJson()
            ->timeout(60)
            ->retry(3, 2000, fn ($exception) => ! $exception instanceof RequestException
                || in_array($exception->response->status(), [429, 500, 502, 503, 504], true), throw: false);
    }

    private function token(string $site): string
    {
        // Service Account JWT'si alan adından bağımsız; tek token tüm sitelerde geçer.
        $site = $this->usesServiceAccount() ? 'service_account' : $site;
        $cached = $this->tokens[$site] ?? null;

        if ($cached && time() < $cached['expires_at'] - 300) {
            return $cached['token'];
        }

        if ($site === 'service_account') {
            $this->tokens[$site] = ['token' => $this->serviceAccountJwt(), 'expires_at' => time() + 3600];

            return $this->tokens[$site]['token'];
        }

        $response = Http::acceptJson()->post(self::SITES[$site].'/api/oauth2/v1/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId(),
            'client_secret' => $this->account->credentials['client_secret'] ?? '',
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new RuntimeException('AppGallery Connect token alınamadı: '.($response->json('ret.msg') ?? 'HTTP '.$response->status()).'. Client ID / secret\'ın Connect API istemcisine ait olduğunu kontrol edin.');
        }

        $this->tokens[$site] = [
            'token' => $response->json('access_token'),
            'expires_at' => time() + (int) $response->json('expires_in', 3600),
        ];

        return $this->tokens[$site]['token'];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function assertOk(array $response): void
    {
        $ret = $response['ret'] ?? [];
        $code = $ret['code'] ?? $ret['rtnCode'] ?? 0;

        if ((int) $code !== 0) {
            throw new RuntimeException(sprintf('AppGallery Connect hatası %s: %s', $code, $ret['msg'] ?? $ret['rtnDesc'] ?? ''));
        }
    }

    /**
     * Huawei PS256 (RSASSA-PSS, SHA-256, MGF1 SHA-256, 32 bayt salt) istiyor; lcobucci/jwt PSS desteklemediği
     * için imza phpseclib ile atılır.
     */
    private function serviceAccountJwt(): string
    {
        $account = $this->serviceAccount();
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        $now = time();

        $input = $encode(['kid' => $account['key_id'], 'typ' => 'JWT', 'alg' => 'PS256'])
            .'.'.$encode([
                'aud' => 'https://oauth-login.cloud.huawei.com/oauth2/v3/token',
                'iss' => (string) $account['sub_account'],
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

        $key = PublicKeyLoader::loadPrivateKey($account['private_key'])
            ->withPadding(RSA::SIGNATURE_PSS)
            ->withHash('sha256')
            ->withMGFHash('sha256')
            ->withSaltLength(32);

        return $input.'.'.rtrim(strtr(base64_encode($key->sign($input)), '+/', '-_'), '=');
    }
}
