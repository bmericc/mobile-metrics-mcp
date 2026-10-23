<?php

namespace App\Stores\GooglePlay;

use App\Models\StoreAccount;
use DateTimeImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use RuntimeException;

/**
 * Service account ile Google API istemcisi. Token, JWT bearer grant ile alınır
 * (google/auth paketine gerek kalmadan; Http::fake ile test edilebilir).
 */
class GoogleClient
{
    public const SCOPES = [
        'https://www.googleapis.com/auth/androidpublisher',
        'https://www.googleapis.com/auth/playdeveloperreporting',
        'https://www.googleapis.com/auth/devstorage.read_only',
    ];

    private ?string $token = null;

    private int $tokenExpiresAt = 0;

    public function __construct(private readonly StoreAccount $account) {}

    /**
     * @return array{client_email: string, private_key: string, token_uri: string}
     */
    public function serviceAccount(): array
    {
        $json = json_decode($this->account->credentials['service_account_json'] ?? '', true);

        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new RuntimeException('Service account JSON geçersiz: client_email ve private_key alanları bulunamadı.');
        }

        return [
            'client_email' => $json['client_email'],
            'private_key' => $json['private_key'],
            'token_uri' => $json['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        ];
    }

    public function bucket(): string
    {
        // Kullanıcı "gs://..." ile yapıştırabilir.
        return str($this->account->credentials['reports_bucket'] ?? '')->after('gs://')->trim('/')->toString();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $url, array $query = []): array
    {
        return $this->request()->get($url, $query ?: null)->throw()->json();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $url, array $body): array
    {
        return $this->request()->post($url, $body)->throw()->json();
    }

    public function download(string $url): string
    {
        return $this->request()->timeout(120)->get($url)->throw()->body();
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->token())
            ->acceptJson()
            ->timeout(60)
            ->retry(3, 2000, fn ($exception) => ! $exception instanceof RequestException
                || in_array($exception->response->status(), [429, 500, 502, 503, 504], true), throw: false);
    }

    private function token(): string
    {
        if ($this->token && time() < $this->tokenExpiresAt - 300) {
            return $this->token;
        }

        $account = $this->serviceAccount();
        $config = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText($account['private_key']),
            InMemory::plainText('unused'),
        );
        $now = new DateTimeImmutable;

        $assertion = $config->builder()
            ->issuedBy($account['client_email'])
            ->permittedFor($account['token_uri'])
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('scope', implode(' ', self::SCOPES))
            ->getToken($config->signer(), $config->signingKey())
            ->toString();

        $response = Http::asForm()->post($account['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Google token alınamadı: '.($response->json('error_description') ?? $response->json('error') ?? 'HTTP '.$response->status()));
        }

        $this->token = $response->json('access_token');
        $this->tokenExpiresAt = time() + (int) $response->json('expires_in', 3600);

        return $this->token;
    }
}
