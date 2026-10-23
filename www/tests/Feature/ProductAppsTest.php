<?php

namespace Tests\Feature;

use App\Enums\Store;
use App\Filament\App\Resources\Products\Pages\CreateProduct;
use App\Filament\App\Resources\Products\Pages\EditProduct;
use App\Models\StoreApp;
use App\Models\User;
use App\Models\Workspace;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductAppsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    /** @var array<string, StoreApp> */
    private array $apps = [];

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->workspace = Workspace::create(['name' => 'Acme', 'owner_id' => $user->id]);
        $this->workspace->users()->attach($user, ['role' => 'owner']);

        foreach ([Store::GooglePlay, Store::AppStore, Store::AppGallery] as $store) {
            $account = $this->workspace->storeAccounts()->create(['store' => $store, 'label' => $store->label(), 'credentials' => []]);
            $this->apps[$store->value] = $account->storeApps()->create([
                'workspace_id' => $this->workspace->id, 'store' => $store, 'external_id' => "radyoc-{$store->value}", 'name' => 'Radyo C',
            ]);
        }

        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->workspace);
        // Panel isteğinde middleware'in yaptığı gibi: yeni kayıtlar aktif workspace'e bağlanır.
        Filament::getCurrentPanel()->bootTenancy();
    }

    public function test_create_product_links_selected_apps_from_each_platform(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Radyo C',
                'apps' => [
                    ['store' => 'google_play', 'store_app_id' => $this->apps['google_play']->id],
                    ['store' => 'app_store', 'store_app_id' => $this->apps['app_store']->id],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = $this->workspace->products()->sole();
        $this->assertEqualsCanonicalizing(
            [$this->apps['google_play']->id, $this->apps['app_store']->id],
            $product->storeApps()->pluck('id')->all(),
        );
        $this->assertNull($this->apps['app_gallery']->fresh()->product_id);
    }

    public function test_edit_shows_linked_apps_and_unlinks_removed_ones(): void
    {
        $product = $this->workspace->products()->create(['name' => 'Radyo C']);
        $this->apps['google_play']->update(['product_id' => $product->id]);
        $this->apps['app_store']->update(['product_id' => $product->id]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSchemaStateSet(fn (array $state) => $this->assertCount(2, $state['apps']) ?? [])
            ->fillForm([
                'apps' => [
                    ['store' => 'app_gallery', 'store_app_id' => $this->apps['app_gallery']->id],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$this->apps['app_gallery']->id], $product->storeApps()->pluck('id')->all());
        $this->assertNull($this->apps['google_play']->fresh()->product_id);
    }
}
