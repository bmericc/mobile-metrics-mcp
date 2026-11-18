<?php

namespace App\Stores\GooglePlay;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Play'deki herkese açık uygulama sayfası. API'ler bir uygulamanın hangi geliştirici hesabına ait
 * olduğunu söylemiyor; mağaza sayfasındaki geliştirici bağlantısı (/store/apps/dev?id=...) söylüyor.
 * Yayında olmayan uygulamanın sayfası yoktur. Sonuç (yoksa da) bir gün önbelleğe alınır; keşif her sync'te
 * sahipsiz uygulamalar için tekrar sorar.
 */
class PlayListing
{
    /** @var array<string, ?string> */
    private array $developerIds = [];

    public function developerId(string $package): ?string
    {
        if (array_key_exists($package, $this->developerIds)) {
            return $this->developerIds[$package];
        }

        $cached = Cache::remember('play-listing-developer:'.$package, now()->addDay(), fn () => $this->fetch($package) ?? '');

        return $this->developerIds[$package] = $cached !== '' ? $cached : null;
    }

    private function fetch(string $package): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0', 'Accept-Language' => 'en'])
                ->timeout(20)
                ->get('https://play.google.com/store/apps/details', ['id' => $package, 'hl' => 'en']);

            return $response->successful() && preg_match('#/store/apps/dev(?:eloper)?\?id=([^"&]+)#', $response->body(), $match)
                ? urldecode($match[1])
                : null;
        } catch (Throwable) {
            return null;
        }
    }
}
