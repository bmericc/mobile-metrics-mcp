<?php

namespace App\Stores\AppStore;

use App\Models\StoreAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * App Store Connect API için ince JSON:API istemcisi. Her hesap için bir örnek oluşturulur.
 */
class AppStoreClient
{
    public const BASE_URL = 'https://api.appstoreconnect.apple.com';

    private ?string $token = null;

    private ?int $tokenIssuedAt = null;

    public function __construct(
        private readonly StoreAccount $account,
        private readonly JwtSigner $signer,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        // Boş dizi gönderilirse Guzzle URL'deki query'yi (next linkindeki cursor) siler.
        return $this->request()->get($this->url($path), $query ?: null)->throw()->json();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $path, array $body): array
    {
        return $this->request()->post($this->url($path), $body)->throw()->json();
    }

    /**
     * links.next takip ederek tüm sayfaları gezer. Callback false dönerse durur.
     *
     * @param  array<string, mixed>  $query
     * @return iterable<array{data: array<string, mixed>, included: array<int, array<string, mixed>>}>
     */
    public function paginate(string $path, array $query = []): iterable
    {
        $page = $this->get($path, $query);
        $seen = [];

        while (true) {
            yield ['data' => $page['data'] ?? [], 'included' => $page['included'] ?? []];

            $next = $page['links']['next'] ?? null;

            // Aynı sayfaya geri dönen bir next linki sonsuz döngüye sokmasın.
            if (! $next || isset($seen[$next])) {
                return;
            }

            $seen[$next] = true;

            // next URL tüm query parametrelerini zaten içeriyor.
            $page = $this->get($next);
        }
    }

    /**
     * JSON olmayan (gzip TSV) yanıt dönen uç noktalar, ör. salesReports.
     *
     * @param  array<string, mixed>  $query
     */
    public function raw(string $path, array $query): string
    {
        return $this->request('application/a-gzip')->get($this->url($path), $query)->throw()->body();
    }

    /**
     * Segment URL'leri önceden imzalı (S3); Authorization başlığı gönderilmemeli.
     */
    public function download(string $url): string
    {
        return Http::timeout(120)->retry(3, 1000)->get($url)->throw()->body();
    }

    private function request(string $accept = 'application/json'): PendingRequest
    {
        return Http::withToken($this->token())
            ->accept($accept)
            ->timeout(60)
            // 429 ve 5xx için yeniden dene; 4xx (yetki, doğrulama) hemen düşsün.
            ->retry(3, 2000, fn ($exception) => ! $exception instanceof RequestException
                || in_array($exception->response->status(), [429, 500, 502, 503, 504], true), throw: false);
    }

    private function token(): string
    {
        // Token 15 dk geçerli; 10 dk'dan eskiyse yenile.
        if (! $this->token || time() - $this->tokenIssuedAt > 600) {
            $this->token = $this->signer->token($this->account);
            $this->tokenIssuedAt = time();
        }

        return $this->token;
    }

    private function url(string $path): string
    {
        return str_starts_with($path, 'https://') ? $path : self::BASE_URL.'/'.ltrim($path, '/');
    }
}
