<?php

namespace App\Stores\AppGallery;

use App\Models\StoreAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * AppGallery Connect API istemcisi (API client modu: client_id + client_secret).
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

    private function request(string $site): PendingRequest
    {
        return Http::withToken($this->token($site))
            ->withHeaders(['client_id' => $this->clientId()])
            ->acceptJson()
            ->timeout(60)
            ->retry(3, 2000, fn ($exception) => ! $exception instanceof RequestException
                || in_array($exception->response->status(), [429, 500, 502, 503, 504], true), throw: false);
    }

    private function token(string $site): string
    {
        $cached = $this->tokens[$site] ?? null;

        if ($cached && time() < $cached['expires_at'] - 300) {
            return $cached['token'];
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
}
