<?php

namespace App\Stores\AppStore;

use App\Models\StoreAccount;
use App\Models\StoreApp;
use App\Stores\Contracts\StoreCollector;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use RuntimeException;

/**
 * Kaynaklar (App Store Connect API, https://api.appstoreconnect.apple.com/v1):
 *  - Uygulamalar: GET /apps
 *  - İndirme / oturum / sayfa görüntüleme: Analytics Reports API
 *    (POST /analyticsReportRequests accessType=ONGOING, sonra reports → instances → segments indir)
 *  - Satış / gelir: GET /salesReports (gz TSV, credentials.vendor_number gerekir)
 *  - Yorumlar: GET /apps/{id}/customerReviews
 *
 * Kimlik: credentials.issuer_id, credentials.key_id, credentials.private_key (.p8 içeriği).
 */
class AppStoreCollector implements StoreCollector
{
    public function __construct(private JwtSigner $signer) {}

    public function verify(StoreAccount $account): void
    {
        throw new RuntimeException('App Store verify henüz uygulanmadı.');
    }

    public function discoverApps(StoreAccount $account): iterable
    {
        throw new RuntimeException('App Store discoverApps henüz uygulanmadı.');
    }

    public function fetchMetrics(StoreApp $app, CarbonPeriod $period): iterable
    {
        throw new RuntimeException('App Store fetchMetrics henüz uygulanmadı.');
    }

    public function fetchReviews(StoreApp $app, ?CarbonImmutable $since): iterable
    {
        throw new RuntimeException('App Store fetchReviews henüz uygulanmadı.');
    }
}
