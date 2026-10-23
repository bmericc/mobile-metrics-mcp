<?php

namespace Tests\Feature;

use App\Enums\Store;
use App\Filament\App\Resources\Products\ProductResource;
use App\Filament\App\Resources\Products\Widgets\ProductMetricsChart;
use App\Models\DailyMetric;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use App\Reports\ProductReport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-23 10:00:00');

        $this->user = User::factory()->create();
        $this->workspace = Workspace::create(['name' => 'Acme', 'owner_id' => $this->user->id]);
        $this->workspace->users()->attach($this->user, ['role' => 'owner']);
        $this->product = $this->workspace->products()->create(['name' => 'Radyo C']);

        foreach ([Store::GooglePlay, Store::AppGallery] as $store) {
            $account = $this->workspace->storeAccounts()->create(['store' => $store, 'label' => $store->label(), 'credentials' => []]);
            $app = $account->storeApps()->create([
                'workspace_id' => $this->workspace->id, 'store' => $store, 'external_id' => "x-{$store->value}",
                'name' => "Radyo C {$store->label()}", 'product_id' => $this->product->id,
            ]);

            foreach (['2026-10-20' => 5, '2026-10-22' => 7, '2026-09-01' => 999] as $date => $value) {
                DailyMetric::create(['store_app_id' => $app->id, 'date' => $date, 'metric' => 'installs', 'value' => $value]);
            }
            DailyMetric::create(['store_app_id' => $app->id, 'date' => '2026-10-22', 'country' => 'TR', 'metric' => 'installs', 'value' => 6]);
            $app->reviews()->create(['store_review_id' => "r-{$store->value}", 'rating' => 4, 'body' => "Güzel {$store->label()}", 'reviewed_at' => '2026-10-21 10:00:00']);
        }
    }

    public function test_report_aggregates_per_platform_within_the_window(): void
    {
        $report = new ProductReport($this->product, 7);

        $this->assertSame('2026-10-16', $report->from->toDateString());
        $this->assertSame('2026-10-22', $report->to->toDateString());

        $series = $report->series('installs');
        $this->assertCount(7, $series['labels']);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 5.0, 0.0, 7.0], $series['series']['google_play']);
        $this->assertArrayHasKey('app_gallery', $series['series']);

        $summary = collect($report->summary())->keyBy(fn ($row) => $row['store']->value);
        // Eylül verisi pencere dışında; ülke satırı (TR) toplama karışmaz.
        $this->assertSame(12.0, $summary['google_play']['totals']['installs']);
        $this->assertSame('22.10.2026', $summary['google_play']['latest']);

        $this->assertSame([['country' => 'TR', 'value' => 12.0]], $report->topCountries('installs'));
        $this->assertCount(2, $report->latestReviews());
    }

    public function test_report_page_renders_for_member_only(): void
    {
        $url = ProductResource::getUrl('report', ['record' => $this->product], panel: 'app', tenant: $this->workspace);

        $this->actingAs($this->user)->get($url)
            ->assertOk()
            ->assertSee('Radyo C — Rapor')
            ->assertSee('Platform özeti')
            ->assertSee('Google Play')
            ->assertSee('AppGallery')
            ->assertSee('Türkiye')
            ->assertSee('Güzel AppGallery');

        $stranger = User::factory()->create();
        $other = Workspace::create(['name' => 'Other', 'owner_id' => $stranger->id]);
        $other->users()->attach($stranger, ['role' => 'owner']);

        $this->actingAs($stranger)
            ->get(ProductResource::getUrl('report', ['record' => $this->product], panel: 'app', tenant: $other))
            ->assertNotFound();
    }

    public function test_chart_follows_page_filters(): void
    {
        $this->actingAs($this->user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->workspace);

        Livewire::test(ProductMetricsChart::class, ['record' => $this->product, 'pageFilters' => ['days' => 14, 'metric' => 'installs']])
            ->assertSee('Kurulum / indirme')
            ->assertOk();
    }
}
