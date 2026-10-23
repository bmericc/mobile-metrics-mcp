<?php

namespace App\Stores\GooglePlay;

use App\Models\StoreAccount;
use App\Models\StoreApp;
use App\Stores\Contracts\StoreCollector;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use RuntimeException;

/**
 * Kaynaklar:
 *  - Kurulum / kaldırma / puan / gelir: Play Console bulk reports, GCS bucket
 *    (gs://pubsite_prod_rev_<id>/stats/installs/installs_<package>_<YYYYMM>_overview.csv vb.)
 *  - Crash / ANR: Play Developer Reporting API (playdeveloperreporting scope)
 *  - Yorumlar: Android Publisher API reviews.list (yalnızca son 7 gün döner)
 *
 * Kimlik: service account JSON (credentials.service_account_json) + credentials.reports_bucket.
 */
class GooglePlayCollector implements StoreCollector
{
    public function verify(StoreAccount $account): void
    {
        throw new RuntimeException('Google Play verify henüz uygulanmadı.');
    }

    public function discoverApps(StoreAccount $account): iterable
    {
        // Android Publisher API uygulama listeleme sunmuyor; paketler bucket'taki
        // rapor dosya adlarından (installs_<package>_...) çıkarılır.
        throw new RuntimeException('Google Play discoverApps henüz uygulanmadı.');
    }

    public function fetchMetrics(StoreApp $app, CarbonPeriod $period): iterable
    {
        throw new RuntimeException('Google Play fetchMetrics henüz uygulanmadı.');
    }

    public function fetchReviews(StoreApp $app, ?CarbonImmutable $since): iterable
    {
        throw new RuntimeException('Google Play fetchReviews henüz uygulanmadı.');
    }
}
