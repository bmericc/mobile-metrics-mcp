<?php

namespace App\Stores\Contracts;

use App\Models\StoreAccount;
use App\Models\StoreApp;
use App\Stores\Data\AppDescriptor;
use App\Stores\Data\MetricRow;
use App\Stores\Data\ReviewData;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

interface StoreCollector
{
    /**
     * Kimlik bilgilerini doğrular; geçersizse exception fırlatır.
     */
    public function verify(StoreAccount $account): void;

    /**
     * @return iterable<AppDescriptor>
     */
    public function discoverApps(StoreAccount $account): iterable;

    /**
     * Mağaza verisi T+1/T+2 geldiği için çağıran taraf son birkaç günü her seferinde yeniden ister.
     *
     * @return iterable<MetricRow>
     */
    public function fetchMetrics(StoreApp $app, CarbonPeriod $period): iterable;

    /**
     * @return iterable<ReviewData>
     */
    public function fetchReviews(StoreApp $app, ?CarbonImmutable $since): iterable;
}
