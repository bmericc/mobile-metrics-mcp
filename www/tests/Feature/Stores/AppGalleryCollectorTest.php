<?php

namespace Tests\Feature\Stores;

use App\Actions\VerifyStoreAccount;
use App\Enums\Store;
use App\Jobs\SyncAppMetrics;
use App\Jobs\SyncAppReviews;
use App\Jobs\SyncStoreAccount;
use App\Models\DailyMetric;
use App\Models\RawReport;
use App\Models\StoreAccount;
use App\Models\StoreApp;
use App\Models\User;
use App\Models\Workspace;
use App\Stores\StoreRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppGalleryCollectorTest extends TestCase
{
    use RefreshDatabase;

    private const CN = 'https://connect-api.cloud.huawei.com';

    private const DE = 'https://connect-api-dre.cloud.huawei.com';

    private StoreAccount $account;

    private StoreApp $storeApp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::preventStrayRequests();
        $this->travelTo('2026-10-23 08:00:00');

        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Acme', 'owner_id' => $user->id]);

        $this->account = $workspace->storeAccounts()->create([
            'store' => Store::AppGallery,
            'label' => 'Acme Huawei',
            'credentials' => [
                'client_id' => 'cid', 'client_secret' => 'secret',
                'package_names' => "com.acme.app\ncom.acme.other",
                'review_site' => 'de', 'review_countries' => 'TR, DE',
            ],
        ]);

        $this->storeApp = $this->account->storeApps()->create([
            'workspace_id' => $workspace->id,
            'store' => Store::AppGallery,
            'external_id' => '100200300',
            'name' => 'Acme',
        ]);
    }

    public function test_verify_and_discover_apps_by_package_name(): void
    {
        Queue::fake();
        $this->fakeHuawei();

        $this->assertTrue(app(VerifyStoreAccount::class)($this->account));
        (new SyncStoreAccount($this->account))->handle(app(StoreRegistry::class));

        $this->assertEqualsCanonicalizing(
            ['100200300' => 'Acme', '100200301' => 'Acme Other'],
            $this->account->storeApps()->pluck('name', 'external_id')->all(),
        );
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'appid-list')
            && $request->header('client_id')[0] === 'cid'
            && $request->header('Authorization')[0] === 'Bearer cn-token');
    }

    public function test_api_error_in_body_fails_verification(): void
    {
        Http::fake([
            self::CN.'/api/oauth2/v1/token' => Http::response(['access_token' => 'cn-token', 'expires_in' => 172800]),
            self::CN.'/api/publish/v2/appid-list*' => Http::response(['ret' => ['code' => 204144660, 'msg' => 'no permission']]),
        ]);

        $this->assertFalse(app(VerifyStoreAccount::class)($this->account));
        $this->assertStringContainsString('no permission', $this->account->fresh()->last_error);
    }

    public function test_metrics_combine_daily_totals_and_per_day_country_reports(): void
    {
        $this->fakeHuawei();

        (new SyncAppMetrics($this->storeApp, days: 2))->handle(app(StoreRegistry::class));

        $value = fn (string $metric, string $country = 'ZZ', string $date = '2026-10-21') => DailyMetric::where([
            'store_app_id' => $this->storeApp->id, 'metric' => $metric, 'country' => $country,
        ])->whereDate('date', $date)->value('value');

        $this->assertEquals(1200, $value('installs'));
        $this->assertEquals(300, $value('updates'));
        $this->assertEquals(40, $value('uninstalls'));
        $this->assertEquals(5000, $value('page_views'));
        $this->assertEquals(900, $value('installs', 'ZZ', '2026-10-22'));
        $this->assertEquals(700, $value('installs', 'TR'));
        $this->assertEquals(500, $value('installs', 'DE'));
        // Boş gelen 22 Ekim ülke raporu hata vermez.
        $this->assertSame(0, DailyMetric::whereDate('date', '2026-10-22')->where('country', '!=', 'ZZ')->count());
        $this->assertSame(3, RawReport::whereNotNull('processed_at')->count());

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'appDownloadExport/100200300')
            && $request['groupBy'] === 'countryId' && $request['startTime'] === '20261021' && $request['endTime'] === '20261021');
    }

    public function test_reviews_use_site_domain_and_paginate(): void
    {
        $this->fakeHuawei();

        (new SyncAppReviews($this->storeApp))->handle(app(StoreRegistry::class));

        $this->assertSame(['h1', 'h2'], $this->storeApp->reviews()->orderBy('store_review_id')->pluck('store_review_id')->all());
        $h1 = $this->storeApp->reviews()->where('store_review_id', 'h1')->first();
        $this->assertSame('TR', $h1->country);
        $this->assertSame('5.1.0', $h1->app_version);
        $this->assertSame('Teşekkürler', $h1->reply_body);
        $this->assertSame('2026-10-21 10:00:00', $h1->reviewed_at->utc()->toDateTimeString());

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), self::DE.'/api/reviews')
            && $request['countries'] === 'TR,DE'
            && $request->header('Authorization')[0] === 'Bearer de-token');
    }

    public function test_reviews_are_skipped_without_countries(): void
    {
        $this->account->update(['credentials' => [...$this->account->credentials, 'review_countries' => '']]);
        Http::fake();

        (new SyncAppReviews($this->storeApp->fresh()))->handle(app(StoreRegistry::class));

        Http::assertNothingSent();
    }

    private function fakeHuawei(): void
    {
        $csv = fn (string $header, array $lines) => "\xEF\xBB\xBF".$header."\n".implode("\n", $lines)."\n";

        Http::fake([
            self::CN.'/api/oauth2/v1/token' => Http::response(['access_token' => 'cn-token', 'expires_in' => 172800]),
            self::DE.'/api/oauth2/v1/token' => Http::response(['access_token' => 'de-token', 'expires_in' => 172800]),
            self::CN.'/api/publish/v2/appid-list*' => Http::response([
                'ret' => ['code' => 0, 'msg' => 'success'],
                'appids' => [['key' => 'Acme', 'value' => '100200300'], ['key' => 'Acme Other', 'value' => '100200301']],
            ]),
            self::CN.'/api/report/*' => function (Request $request) {
                $file = $request['groupBy'] === 'date' ? 'date' : 'country-'.$request['startTime'];

                return Http::response(['ret' => ['code' => 0, 'msg' => 'success'], 'fileURL' => "https://obs.example/{$file}.csv", 'packName' => 'com.acme.app']);
            },
            'https://obs.example/date.csv' => Http::response($csv(
                'Time,Impressions,App details page views,Total downloads,Update downloads,New downloads,Uninstalls,CTR',
                ['20261021,"10,000","5,000",1500,300,"1,200",40,12.5%', '20261022,9000,4000,1100,200,900,30,11%', '20261001,1,1,1,1,999,1,1%'],
            )),
            'https://obs.example/country-20261021.csv' => Http::response($csv(
                'Country/Region,Impressions,App details page views,Total downloads,Update downloads,New downloads,Uninstalls',
                ['TR,6000,3000,800,100,700,20', 'DE,4000,2000,700,200,500,20', 'Unknown,0,0,0,0,0,0'],
            )),
            'https://obs.example/country-20261022.csv' => Http::response(''),
            self::DE.'/api/reviews/*' => fn (Request $request) => Http::response(['ret' => ['rtnCode' => 0], 'data' => [
                'reviewList' => $request['page'] == 1
                    ? [['reviewId' => 'h1', 'content' => 'İyi', 'apkVersion' => '5.1.0', 'rating' => 4, 'dateline' => 1792576800000, 'devReplyContent' => 'Teşekkürler', 'countryCode' => 'TR']]
                    : [['reviewId' => 'h2', 'content' => 'Kötü', 'apkVersion' => '5.0.0', 'rating' => 1, 'dateline' => 1792490400000, 'devReplyContent' => null, 'countryCode' => 'DE']],
                'hasNext' => $request['page'] == 1 ? 1 : 0,
            ]]),
        ]);
    }
}
