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
use App\Stores\GooglePlay\GooglePlayCollector;
use App\Stores\StoreRegistry;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GooglePlayCollectorTest extends TestCase
{
    use RefreshDatabase;

    private const GCS = 'https://storage.googleapis.com/storage/v1/b/pubsite_prod_rev_1/o';

    private const PKG = 'com.acme.app';

    private StoreAccount $account;

    private StoreApp $storeApp;

    /** @var array<string, string> GCS nesne adı => içerik */
    private array $objects = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::preventStrayRequests();
        $this->travelTo('2026-10-23 08:00:00');

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);

        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Acme', 'owner_id' => $user->id]);

        $this->account = $workspace->storeAccounts()->create([
            'store' => Store::GooglePlay,
            'label' => 'Acme Play',
            'credentials' => [
                'service_account_json' => json_encode(['client_email' => 'sync@acme.iam.gserviceaccount.com', 'private_key' => $pem]),
                'reports_bucket' => 'gs://pubsite_prod_rev_1/',
            ],
        ]);

        $this->storeApp = $this->account->storeApps()->create([
            'workspace_id' => $workspace->id,
            'store' => Store::GooglePlay,
            'external_id' => self::PKG,
            'name' => 'Acme',
        ]);
    }

    public function test_verify_exchanges_jwt_for_token_and_reads_bucket(): void
    {
        $this->fakeGoogle();

        $this->assertTrue(app(VerifyStoreAccount::class)($this->account));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && json_decode(base64_decode(strtr(explode('.', $request['assertion'])[1], '-_', '+/')), true)['iss'] === 'sync@acme.iam.gserviceaccount.com');
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), self::GCS)
            && $request->header('Authorization')[0] === 'Bearer ya29.test');
    }

    public function test_verify_explains_missing_bucket_permission(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'https://playdeveloperreporting.googleapis.com/v1beta1/apps:search*' => Http::response(['apps' => []]),
            self::GCS.'*' => Http::response(['error' => ['code' => 403]], 403),
        ]);

        $this->assertFalse(app(VerifyStoreAccount::class)($this->account));
        $this->assertStringContainsString('Play Console', $this->account->fresh()->last_error);
    }

    public function test_discover_apps_reads_packages_from_report_names_and_keeps_edited_names(): void
    {
        Queue::fake();
        $this->objects = [
            'stats/installs/installs_com.acme.app_202609_overview.csv' => '',
            'stats/installs/installs_com.acme.app_202610_overview.csv' => '',
            'stats/installs/installs_com.acme.app_202610_country.csv' => '',
            'stats/installs/installs_com.acme.other_app_202610_overview.csv' => '',
        ];
        $this->fakeGoogle();

        (new SyncStoreAccount($this->account))->handle(app(StoreRegistry::class));

        $this->assertEqualsCanonicalizing(
            ['com.acme.app' => 'Acme', 'com.acme.other_app' => 'com.acme.other_app'],
            $this->account->storeApps()->pluck('name', 'external_id')->all(),
        );
        Queue::assertPushed(SyncAppMetrics::class, 2);
    }

    public function test_new_app_without_reports_is_taken_by_account_of_same_developer(): void
    {
        Queue::fake();
        $this->objects = ['stats/installs/installs_com.acme.app_202610_overview.csv' => ''];
        $this->fakeGoogle(apps: [
            'com.acme.app' => 'Acme Play',
            'com.acme.brand_new' => 'Brand New',
            'com.other.app' => 'Other Developer',
            'com.acme.unlisted' => 'Not Published',
        ], listings: [
            'com.acme.app' => '/store/apps/dev?id=111',
            'com.acme.brand_new' => '/store/apps/dev?id=111',
            'com.other.app' => '/store/apps/dev?id=222',
        ]);

        (new SyncStoreAccount($this->account))->handle(app(StoreRegistry::class));

        $this->assertEqualsCanonicalizing(
            ['com.acme.app', 'com.acme.brand_new'],
            $this->account->storeApps()->pluck('external_id')->all(),
        );
        $this->assertSame('Brand New', StoreApp::where('external_id', 'com.acme.brand_new')->value('name'));
    }

    public function test_apps_already_owned_in_workspace_are_not_looked_up_on_play(): void
    {
        Queue::fake();
        $this->objects = ['stats/installs/installs_com.acme.app_202610_overview.csv' => ''];
        $this->fakeGoogle();

        (new SyncStoreAccount($this->account))->handle(app(StoreRegistry::class));

        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://play.google.com/'));
    }

    public function test_developer_account_with_bucket_takes_over_its_apps_from_shared_connection(): void
    {
        Queue::fake();
        $shared = $this->account->workspace->storeAccounts()->create([
            'store' => Store::GooglePlay,
            'label' => 'acme@gmail.com',
            'credentials' => $this->account->credentials + [],
        ]);
        $shared->update(['credentials' => [...$shared->credentials, 'reports_bucket' => null]]);
        $moved = $shared->storeApps()->create([
            'workspace_id' => $shared->workspace_id,
            'store' => Store::GooglePlay,
            'external_id' => 'com.acme.other_app',
            'name' => 'Other',
        ]);
        $this->objects = [
            'stats/installs/installs_com.acme.other_app_202610_overview.csv' => '',
            // Başka hesaba aktarılmış uygulamanın eski raporu sahiplik saymaz.
            'stats/installs/installs_com.acme.transferred_202501_overview.csv' => '',
        ];
        $this->fakeGoogle();

        (new SyncStoreAccount($this->account))->handle(app(StoreRegistry::class));

        $this->assertSame($this->account->id, $moved->fresh()->store_account_id);
        $this->assertSame('Other', $moved->fresh()->name);
        $this->assertSame(1, StoreApp::where('external_id', 'com.acme.other_app')->count());
        $this->assertFalse(StoreApp::where('external_id', 'com.acme.transferred')->exists());

        // Bucket'sız paylaşımlı kayıt, sahiplenilmiş uygulamaları geri almaz.
        (new SyncStoreAccount($shared->fresh()))->handle(app(StoreRegistry::class));
        $this->assertSame($this->account->id, $moved->fresh()->store_account_id);
    }

    public function test_metrics_from_bulk_reports_and_vitals(): void
    {
        $this->objects = [
            'stats/installs/installs_com.acme.app_202610_overview.csv' => $this->utf16(
                "Date,Package Name,Daily Device Installs,Daily User Installs,Daily User Uninstalls,Active Device Installs,Install events,Update events,Uninstall events\n".
                "2026-10-01,com.acme.app,1,2,3,4,5,6,7\n".
                "2026-10-21,com.acme.app,90,80,9,5000,100,40,12\n"
            ),
            'stats/installs/installs_com.acme.app_202610_country.csv' => $this->utf16(
                "Date,Package Name,Country,Daily User Installs,Daily User Uninstalls,Active Device Installs\n".
                "2026-10-21,com.acme.app,TR,60,5,3000\n".
                "2026-10-21,com.acme.app,US,20,4,2000\n"
            ),
            'stats/ratings/ratings_com.acme.app_202610_overview.csv' => $this->utf16(
                "Date,Package Name,Daily Average Rating,Total Average Rating\n".
                "2026-10-21,com.acme.app,NA,4.31\n".
                "2026-10-22,com.acme.app,5,NA\n"
            ),
        ];
        $this->fakeGoogle();

        (new SyncAppMetrics($this->storeApp))->handle(app(StoreRegistry::class));

        $value = fn (string $metric, string $country = 'ZZ', string $date = '2026-10-21') => DailyMetric::where([
            'store_app_id' => $this->storeApp->id, 'metric' => $metric, 'country' => $country,
        ])->whereDate('date', $date)->value('value');

        // Yeni "events" sütunları eski "Daily User" sütunlarına tercih edilir.
        $this->assertEquals(100, $value('installs'));
        $this->assertEquals(12, $value('uninstalls'));
        $this->assertEquals(40, $value('updates'));
        $this->assertEquals(5000, $value('active_devices'));
        // Ülke dosyasında yalnızca eski sütunlar var.
        $this->assertEquals(60, $value('installs', 'TR'));
        $this->assertEquals(2000, $value('active_devices', 'US'));
        $this->assertEquals(4.31, $value('rating_avg'));
        $this->assertNull($value('rating_avg', 'ZZ', '2026-10-22'));
        $this->assertEquals(0.0123, $value('crash_rate'));
        $this->assertEquals(0.004, $value('anr_rate'));
        $this->assertSame(0, DailyMetric::whereDate('date', '2026-10-01')->count());

        // Vitals sorgusu tazelik sınırına (22 Ekim, hariç) kırpılır ve pencere uzunluğu korunur (7 gün geriye).
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'crashRateMetricSet:query')
            && $request['timelineSpec']['endTime']['day'] === 22
            && $request['timelineSpec']['startTime']['day'] === 15
            && $request['timelineSpec']['startTime']['timeZone']['id'] === 'America/Los_Angeles');

        // Generation değişmediyse ikinci çalışmada dosyalar yeniden indirilmez.
        $downloads = fn () => count(Http::recorded(fn (Request $request) => str_contains($request->url(), 'alt=media')));
        $before = $downloads();
        (new SyncAppMetrics($this->storeApp))->handle(app(StoreRegistry::class));
        $this->assertSame($before, $downloads());
        $this->assertSame(3, RawReport::whereNotNull('processed_at')->count());
    }

    public function test_metrics_cover_every_month_in_period(): void
    {
        $this->fakeGoogle();

        iterator_to_array(app(GooglePlayCollector::class)->fetchMetrics($this->storeApp, CarbonPeriod::create('2026-09-28', '2026-10-02')));

        foreach (['202609', '202610'] as $month) {
            Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), "installs_com.acme.app_{$month}_overview.csv"));
        }
    }

    public function test_reviews_map_user_and_developer_comments(): void
    {
        $this->fakeGoogle();

        (new SyncAppReviews($this->storeApp))->handle(app(StoreRegistry::class));

        $review = $this->storeApp->reviews()->where('store_review_id', 'gp-1')->first();
        $this->assertSame(2, $review->rating);
        $this->assertSame('Çok yavaş', $review->body);
        $this->assertSame('tr_TR', $review->language);
        $this->assertSame('TR', $review->country);
        $this->assertSame('3.2.0', $review->app_version);
        $this->assertSame('Düzelttik', $review->reply_body);
        $this->assertNull($this->storeApp->reviews()->where('store_review_id', 'gp-2')->first()->country);
        $this->assertSame(2, $this->storeApp->reviews()->count());
    }

    private function utf16(string $csv): string
    {
        return "\xFF\xFE".mb_convert_encoding($csv, 'UTF-16LE', 'UTF-8');
    }

    /**
     * @param  array<string, string>  $apps  Reporting API apps:search sonucu (paket => ad)
     * @param  array<string, string>  $listings  paket => mağaza sayfasındaki geliştirici bağlantısı
     */
    private function fakeGoogle(array $apps = ['com.acme.app' => 'Acme Play'], array $listings = []): void
    {
        $objects = $this->objects;
        $vitalsBase = 'https://playdeveloperreporting.googleapis.com/v1beta1/apps/'.self::PKG;

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'https://playdeveloperreporting.googleapis.com/v1beta1/apps:search*' => Http::response(['apps' => collect($apps)
                ->map(fn ($name, $package) => ['name' => 'apps/'.$package, 'packageName' => $package, 'displayName' => $name])
                ->values()->all()]),
            'https://play.google.com/store/apps/details*' => function (Request $request) use ($listings) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                return isset($listings[$query['id']])
                    ? Http::response('<a href="'.$listings[$query['id']].'"><span>Dev</span></a>')
                    : Http::response('Not found', 404);
            },
            self::GCS.'*' => function (Request $request) use ($objects) {
                $url = urldecode($request->url());

                if (str_contains($url, 'alt=media')) {
                    $name = str($url)->after('/o/')->before('?alt=media')->toString();

                    return Http::response($objects[$name] ?? '', isset($objects[$name]) ? 200 : 404);
                }

                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $items = collect($objects)->keys()
                    ->filter(fn ($name) => str_starts_with($name, $query['prefix'] ?? ''))
                    ->map(fn ($name) => ['name' => $name, 'generation' => '1700000000'])
                    ->values()->all();

                return Http::response(['items' => $items]);
            },
            $vitalsBase.'/*:query' => fn (Request $request) => Http::response(['rows' => [[
                'startTime' => ['year' => 2026, 'month' => 10, 'day' => 21],
                'metrics' => str_contains($request->url(), 'crashRate')
                    ? [['metric' => 'crashRate', 'decimalValue' => ['value' => '0.0123']]]
                    : [['metric' => 'anrRate', 'decimalValue' => ['value' => '0.004']]],
            ]]]),
            $vitalsBase.'/*' => Http::response(['freshnessInfo' => ['freshnesses' => [
                ['aggregationPeriod' => 'HOURLY', 'latestEndTime' => ['year' => 2026, 'month' => 10, 'day' => 23]],
                ['aggregationPeriod' => 'DAILY', 'latestEndTime' => ['year' => 2026, 'month' => 10, 'day' => 22]],
            ]]]),
            'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/'.self::PKG.'/reviews*' => fn (Request $request) => str_contains($request->url(), 'token=p2')
                ? Http::response(['reviews' => [[
                    'reviewId' => 'gp-2',
                    'authorName' => 'B',
                    'comments' => [['userComment' => ['text' => ' Güzel ', 'starRating' => 5, 'reviewerLanguage' => 'en', 'lastModified' => ['seconds' => '1792400000']]]],
                ]]])
                : Http::response([
                    'reviews' => [[
                        'reviewId' => 'gp-1',
                        'authorName' => 'A',
                        'comments' => [
                            ['userComment' => ['text' => 'Çok yavaş', 'starRating' => 2, 'reviewerLanguage' => 'tr_TR', 'appVersionName' => '3.2.0', 'lastModified' => ['seconds' => '1792500000']]],
                            ['developerComment' => ['text' => 'Düzelttik', 'lastModified' => ['seconds' => '1792600000']]],
                        ],
                    ]],
                    'tokenPagination' => ['nextPageToken' => 'p2'],
                ]),
        ]);
    }
}
