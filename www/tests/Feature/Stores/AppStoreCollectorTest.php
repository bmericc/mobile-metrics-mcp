<?php

namespace Tests\Feature\Stores;

use App\Actions\VerifyStoreAccount;
use App\Enums\Store;
use App\Jobs\SyncAppMetrics;
use App\Jobs\SyncAppReviews;
use App\Models\DailyMetric;
use App\Models\RawReport;
use App\Models\StoreAccount;
use App\Models\StoreApp;
use App\Models\User;
use App\Models\Workspace;
use App\Stores\AppStore\AppStoreCollector;
use App\Stores\StoreRegistry;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppStoreCollectorTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.appstoreconnect.apple.com';

    private StoreAccount $account;

    private StoreApp $storeApp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::preventStrayRequests();
        $this->travelTo('2026-10-23 08:00:00');

        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($key, $pem);

        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Acme', 'owner_id' => $user->id]);

        $this->account = $workspace->storeAccounts()->create([
            'store' => Store::AppStore,
            'label' => 'Acme Apple',
            'credentials' => ['issuer_id' => 'issuer-1', 'key_id' => 'KEY123', 'private_key' => $pem],
        ]);

        $this->storeApp = $this->account->storeApps()->create([
            'workspace_id' => $workspace->id,
            'store' => Store::AppStore,
            'external_id' => '6400000001',
            'name' => 'Acme',
        ]);
    }

    public function test_verify_marks_account_active_and_sends_signed_jwt(): void
    {
        Http::fake([self::API.'/v1/apps*' => Http::response(['data' => []])]);

        $this->assertTrue(app(VerifyStoreAccount::class)($this->account));
        $this->assertSame('active', $this->account->fresh()->status);

        Http::assertSent(function (Request $request) {
            [$header, $payload] = array_map(
                fn ($part) => json_decode(base64_decode(strtr($part, '-_', '+/')), true),
                array_slice(explode('.', str($request->header('Authorization')[0])->after('Bearer ')->toString()), 0, 2),
            );

            return $header['alg'] === 'ES256' && $header['kid'] === 'KEY123'
                && $payload['iss'] === 'issuer-1' && $payload['aud'] === 'appstoreconnect-v1';
        });
    }

    public function test_verify_failure_is_stored_with_readable_error(): void
    {
        Http::fake([self::API.'/v1/apps*' => Http::response(['errors' => [['status' => '401']]], 401)]);

        $this->assertFalse(app(VerifyStoreAccount::class)($this->account));
        $this->assertSame('failed', $this->account->fresh()->status);
        $this->assertStringContainsString('.p8', $this->account->fresh()->last_error);
    }

    public function test_discover_apps_follows_pagination(): void
    {
        Http::fake([self::API.'/v1/apps*' => fn (Request $request) => str_contains($request->url(), 'cursor=2')
            ? Http::response(['data' => [['id' => '2', 'attributes' => ['name' => 'Two']]]])
            : Http::response([
                'data' => [['id' => '1', 'attributes' => ['name' => 'One']]],
                'links' => ['next' => self::API.'/v1/apps?cursor=2&limit=200'],
            ]),
        ]);

        $apps = iterator_to_array(app(AppStoreCollector::class)->discoverApps($this->account), false);

        $this->assertSame(['1', '2'], array_map(fn ($app) => $app->externalId, $apps));
    }

    public function test_metrics_creates_ongoing_request_when_missing(): void
    {
        Http::fake([
            self::API.'/v1/apps/6400000001/analyticsReportRequests*' => Http::response(['data' => []]),
            self::API.'/v1/analyticsReportRequests' => Http::response(['data' => ['id' => 'req-1']], 201),
        ]);

        $rows = app(AppStoreCollector::class)->fetchMetrics($this->storeApp, CarbonPeriod::create('2026-10-16', '2026-10-22'));

        $this->assertSame([], iterator_to_array($rows));
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['data']['attributes']['accessType'] === 'ONGOING'
            && $request['data']['relationships']['app']['data']['id'] === '6400000001');
    }

    public function test_metrics_fall_back_to_sales_reports_without_admin_key(): void
    {
        $this->account->update(['credentials' => [...$this->account->credentials, 'vendor_number' => '81234567']]);
        $header = "Provider\tSKU\tProduct Type Identifier\tUnits\tDeveloper Proceeds\tCountry Code\tApple Identifier\tParent Identifier";
        $report = implode("\n", [
            $header,
            "APPLE\tacme\t1F\t5\t0\tTR\t6400000001\t",
            "APPLE\tacme\t3\t2\t0\tUS\t6400000001\t",
            "APPLE\tacme\t7\t9\t0\tTR\t6400000001\t",
            "APPLE\tacme.pro\tIA1\t1\t2.5\tTR\t6400009999\tacme",
            "APPLE\tother\t1\t4\t0\tTR\t6400000002\t",
        ])."\n";
        Http::fake([
            self::API.'/v1/apps/6400000001/analyticsReportRequests*' => Http::response(['data' => []]),
            self::API.'/v1/analyticsReportRequests' => Http::response(['errors' => [['status' => '403', 'code' => 'FORBIDDEN_ERROR']]], 403),
            self::API.'/v1/salesReports*' => fn (Request $request) => str_contains(urldecode($request->url()), 'filter[reportDate]=2026-10-18')
                ? Http::response(gzencode($report), 200, ['Content-Type' => 'application/a-gzip'])
                : Http::response(['errors' => [['status' => '404']]], 404),
        ]);

        $rows = collect(app(AppStoreCollector::class)->fetchMetrics($this->storeApp, CarbonPeriod::create('2026-10-17', '2026-10-22')))
            ->mapWithKeys(fn ($row) => ["{$row->date->toDateString()}|{$row->country}|{$row->metric}" => $row->value])
            ->all();

        $this->assertEquals([
            '2026-10-18|ZZ|installs' => 7.0,
            '2026-10-18|TR|installs' => 5.0,
            '2026-10-18|US|installs' => 2.0,
            '2026-10-18|ZZ|updates' => 9.0,
            '2026-10-18|TR|updates' => 9.0,
        ], $rows);
        Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'filter[vendorNumber]=81234567')
            && $request->header('Accept') === ['application/a-gzip']);

        // Satış olmayan geçmiş gün boş kayıtla işaretlenir; yayınlanmamış son günler işaretlenmez.
        $this->assertTrue(RawReport::where('path', 'like', '%/sales/2026-10-17.tsv')->exists());
        $this->assertFalse(RawReport::where('path', 'like', '%/sales/2026-10-22.tsv')->exists());
    }

    public function test_forbidden_analytics_without_vendor_number_explains_options(): void
    {
        Http::fake([
            self::API.'/v1/apps/6400000001/analyticsReportRequests*' => Http::response(['data' => []]),
            self::API.'/v1/analyticsReportRequests' => Http::response(['errors' => [['status' => '403']]], 403),
        ]);

        $this->expectExceptionMessage('Vendor number');
        app(AppStoreCollector::class)->fetchMetrics($this->storeApp, CarbonPeriod::create('2026-10-16', '2026-10-22'));
    }

    public function test_metrics_aggregate_segments_into_daily_rows(): void
    {
        $this->fakeAnalytics();

        (new SyncAppMetrics($this->storeApp))->handle(app(StoreRegistry::class));

        $value = fn (string $metric, string $country = 'ZZ', string $date = '2026-10-21') => (float) DailyMetric::where([
            'store_app_id' => $this->storeApp->id, 'metric' => $metric, 'country' => $country,
        ])->whereDate('date', $date)->value('value');

        // İki farklı instance'tan gelen 21 Ekim verisi toplanır (40 + 10 TR, 50 US).
        $this->assertSame(100.0, $value('installs'));
        $this->assertSame(50.0, $value('installs', 'TR'));
        $this->assertSame(50.0, $value('installs', 'US'));
        $this->assertSame(7.0, $value('updates'));
        $this->assertSame(3.0, $value('uninstalls'));
        // Pencere dışındaki tarih (2026-10-01) yazılmaz.
        $this->assertSame(0, DailyMetric::whereDate('date', '2026-10-01')->count());
        $this->assertSame(3, RawReport::whereNotNull('processed_at')->count());

        // İkinci çalışmada segmentler yeniden indirilmez.
        (new SyncAppMetrics($this->storeApp))->handle(app(StoreRegistry::class));
        $this->assertCount(3, Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'https://segments.example')));
    }

    public function test_unexpected_report_columns_fail_loudly(): void
    {
        $this->fakeAnalytics(downloadsHeader: "Date\tTerritory\tSomething Else");

        try {
            (new SyncAppMetrics($this->storeApp))->handle(app(StoreRegistry::class));
            $this->fail('Beklenen exception fırlatılmadı.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Download Type', $e->getMessage());
        }

        $this->assertNotNull(RawReport::whereNotNull('error')->first());
        $this->assertSame('failed', $this->storeApp->storeAccount->syncRuns()->first()->status->value);
    }

    public function test_reviews_map_responses_territory_and_stop_at_known_review(): void
    {
        $this->storeApp->reviews()->create([
            'store_review_id' => 'old', 'rating' => 5, 'reviewed_at' => '2026-10-20 10:00:00',
        ]);

        Http::fake([self::API.'/v1/apps/6400000001/customerReviews*' => Http::response([
            'data' => [
                $this->review('r2', 2, '2026-10-22T09:00:00-07:00', 'TUR', 'resp-1'),
                $this->review('r1', 4, '2026-10-21T09:00:00-07:00', 'USA'),
                $this->review('older', 1, '2026-10-19T09:00:00-07:00', 'USA'),
            ],
            'included' => [[
                'type' => 'customerReviewResponses',
                'id' => 'resp-1',
                'attributes' => ['responseBody' => 'Teşekkürler', 'lastModifiedDate' => '2026-10-22T12:00:00-07:00'],
            ]],
        ])]);

        (new SyncAppReviews($this->storeApp))->handle(app(StoreRegistry::class));

        $this->assertSame(['old', 'r1', 'r2'], $this->storeApp->reviews()->orderBy('store_review_id')->pluck('store_review_id')->all());
        $r2 = $this->storeApp->reviews()->where('store_review_id', 'r2')->first();
        $this->assertSame('TR', $r2->country);
        $this->assertSame('Teşekkürler', $r2->reply_body);
        $this->assertNull($this->storeApp->reviews()->where('store_review_id', 'r1')->first()->replied_at);
    }

    private function review(string $id, int $rating, string $date, string $territory, ?string $responseId = null): array
    {
        return [
            'type' => 'customerReviews',
            'id' => $id,
            'attributes' => [
                'rating' => $rating, 'title' => "T {$id}", 'body' => "B {$id}",
                'reviewerNickname' => 'nick', 'createdDate' => $date, 'territory' => $territory,
            ],
            'relationships' => ['response' => ['data' => $responseId ? ['type' => 'customerReviewResponses', 'id' => $responseId] : null]],
        ];
    }

    private function fakeAnalytics(string $downloadsHeader = "Date\tApp Name\tDownload Type\tTerritory\tCounts"): void
    {
        $tsv = fn (string $header, array $lines) => gzencode($header."\n".implode("\n", $lines)."\n");

        Http::fake([
            self::API.'/v1/apps/6400000001/analyticsReportRequests*' => Http::response(['data' => [
                ['id' => 'req-stopped', 'attributes' => ['accessType' => 'ONGOING', 'stoppedDueToInactivity' => true]],
                ['id' => 'req-1', 'attributes' => ['accessType' => 'ONGOING', 'stoppedDueToInactivity' => false]],
            ]]),
            self::API.'/v1/analyticsReportRequests/req-1/reports*' => Http::response(['data' => [
                ['id' => 'rep-dl', 'attributes' => ['name' => 'App Downloads Standard']],
                ['id' => 'rep-del', 'attributes' => ['name' => 'App Store Installation and Deletion Standard']],
                ['id' => 'rep-other', 'attributes' => ['name' => 'App Downloads Detailed']],
            ]]),
            self::API.'/v1/analyticsReports/rep-dl/instances*' => Http::response(['data' => [
                ['id' => 'inst-old', 'attributes' => ['granularity' => 'DAILY', 'processingDate' => '2026-10-01']],
                ['id' => 'inst-21', 'attributes' => ['granularity' => 'DAILY', 'processingDate' => '2026-10-21']],
                ['id' => 'inst-22', 'attributes' => ['granularity' => 'DAILY', 'processingDate' => '2026-10-22']],
            ]]),
            self::API.'/v1/analyticsReports/rep-del/instances*' => Http::response(['data' => [
                ['id' => 'inst-del-22', 'attributes' => ['granularity' => 'DAILY', 'processingDate' => '2026-10-22']],
            ]]),
            self::API.'/v1/analyticsReportInstances/*/segments*' => fn (Request $request) => Http::response(['data' => [[
                'id' => 'seg-'.str($request->url())->between('analyticsReportInstances/', '/segments'),
                'attributes' => ['url' => 'https://segments.example/'.str($request->url())->between('analyticsReportInstances/', '/segments'), 'checksum' => 'x'],
            ]]]),
            'https://segments.example/inst-21' => Http::response($tsv($downloadsHeader, [
                "2026-10-21\tAcme\tFirst-time download\tTR\t40",
                "2026-10-21\tAcme\tManual update\tTR\t7",
                "2026-10-01\tAcme\tFirst-time download\tTR\t999",
            ])),
            'https://segments.example/inst-22' => Http::response($tsv($downloadsHeader, [
                "2026-10-21\tAcme\tRedownload\tTR\t10",
                "2026-10-21\tAcme\tFirst-time download\tUS\t50",
                "2026-10-21\tAcme\tRestore\tUS\t500",
            ])),
            'https://segments.example/inst-del-22' => Http::response($tsv("Date\tEvent\tDownload Type\tTerritory\tCounts", [
                "2026-10-21\tDelete\tFirst-time download\tTR\t3",
                "2026-10-21\tInstall\tFirst-time download\tTR\t60",
            ])),
        ]);
    }
}
