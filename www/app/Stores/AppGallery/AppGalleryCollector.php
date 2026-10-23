<?php

namespace App\Stores\AppGallery;

use App\Models\StoreAccount;
use App\Models\StoreApp;
use App\Stores\Contracts\StoreCollector;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use RuntimeException;

/**
 * Kaynaklar (AppGallery Connect API, https://connect-api.cloud.huawei.com/api):
 *  - Token: POST /oauth2/v1/token (client_credentials)
 *  - İndirme / kurulum: Report API, app download & installation export (CSV URL döner)
 *  - Yorumlar: AGC reviews API
 *
 * Kimlik: AGC'de oluşturulan API client — credentials.client_id, credentials.client_secret.
 * Huawei ID ile giriş bu API'ye erişim vermez.
 */
class AppGalleryCollector implements StoreCollector
{
    public function verify(StoreAccount $account): void
    {
        throw new RuntimeException('AppGallery verify henüz uygulanmadı.');
    }

    public function discoverApps(StoreAccount $account): iterable
    {
        throw new RuntimeException('AppGallery discoverApps henüz uygulanmadı.');
    }

    public function fetchMetrics(StoreApp $app, CarbonPeriod $period): iterable
    {
        throw new RuntimeException('AppGallery fetchMetrics henüz uygulanmadı.');
    }

    public function fetchReviews(StoreApp $app, ?CarbonImmutable $since): iterable
    {
        throw new RuntimeException('AppGallery fetchReviews henüz uygulanmadı.');
    }
}
