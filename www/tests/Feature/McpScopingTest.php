<?php

namespace Tests\Feature;

use App\Enums\Store;
use App\Mcp\Servers\StoreMetricsServer;
use App\Mcp\Tools\GetMetricsTool;
use App\Mcp\Tools\ListAppsTool;
use App\Models\DailyMetric;
use App\Models\StoreApp;
use App\Models\User;
use App\Models\Workspace;
use App\Stores\Metric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpScopingTest extends TestCase
{
    use RefreshDatabase;

    private function userWithApp(string $appName, Store $store = Store::GooglePlay): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => $appName, 'owner_id' => $user->id]);
        $workspace->users()->attach($user, ['role' => 'owner']);

        $account = $workspace->storeAccounts()->create([
            'store' => $store,
            'label' => "{$appName} hesabı",
            'credentials' => ['client_id' => 'x'],
        ]);

        $app = $account->storeApps()->create([
            'workspace_id' => $workspace->id,
            'store' => $store,
            'external_id' => str($appName)->slug()->toString(),
            'name' => $appName,
        ]);

        return [$user, $app];
    }

    public function test_list_apps_only_returns_own_workspace_apps(): void
    {
        [$alice] = $this->userWithApp('Alice App');
        $this->userWithApp('Bob App');

        StoreMetricsServer::actingAs($alice)
            ->tool(ListAppsTool::class)
            ->assertOk()
            ->assertSee('Alice App')
            ->assertDontSee('Bob App');
    }

    public function test_get_metrics_groups_by_store_without_double_counting_countries(): void
    {
        [$alice, $play] = $this->userWithApp('Alice App');
        $ios = StoreApp::create([...$play->only(['workspace_id', 'store_account_id']), 'store' => Store::AppStore, 'external_id' => '123', 'name' => 'Alice iOS']);
        [, $bobApp] = $this->userWithApp('Bob App');

        foreach ([[$play, 'ZZ', 100], [$play, 'TR', 60], [$ios, 'ZZ', 40], [$bobApp, 'ZZ', 999]] as [$app, $country, $value]) {
            DailyMetric::create(['store_app_id' => $app->id, 'date' => '2026-10-01', 'country' => $country, 'metric' => Metric::INSTALLS, 'value' => $value]);
        }

        StoreMetricsServer::actingAs($alice)
            ->tool(GetMetricsTool::class, ['metric' => 'installs', 'from' => '2026-10-01', 'to' => '2026-10-01', 'group_by' => 'store'])
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('metric', 'installs')
                ->where('aggregate', 'sum')
                ->has('rows', 2)
                ->where('rows.0.group', 'app_store')->where('rows.0.value', 40)
                ->where('rows.1.group', 'google_play')->where('rows.1.value', 100)
                ->etc());
    }
}
