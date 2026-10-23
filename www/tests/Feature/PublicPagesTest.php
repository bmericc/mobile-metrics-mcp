<?php

namespace Tests\Feature;

use App\Enums\Store;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_and_legal_pages_are_public_in_both_languages(): void
    {
        $this->get('/')->assertOk()->assertSee('Kalehost Metrics')->assertSee('/privacy')->assertSee('/terms');
        $this->get('/privacy')->assertOk()->assertSee('Gizlilik Politikası')->assertSee('/en/privacy');
        $this->get('/en/privacy')->assertOk()->assertSee('Limited Use')->assertSee('/privacy');
        $this->get('/terms')->assertOk()->assertSee('Kullanım Koşulları');
        $this->get('/en/terms')->assertOk()->assertSee('Terms of Use');
    }

    public function test_login_page_links_to_legal_pages(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('legal.terms'))->assertSee(route('legal.privacy'));
    }

    public function test_deleting_a_store_account_removes_its_raw_reports(): void
    {
        Storage::fake('local');
        $workspace = Workspace::create(['name' => 'Acme', 'owner_id' => User::factory()->create()->id]);
        $account = $workspace->storeAccounts()->create(['store' => Store::AppStore, 'label' => 'A', 'credentials' => []]);
        $other = $workspace->storeAccounts()->create(['store' => Store::AppStore, 'label' => 'B', 'credentials' => []]);
        Storage::disk('local')->put("raw-reports/app_store/{$account->id}/1/x.tsv.gz", 'x');
        Storage::disk('local')->put("raw-reports/app_store/{$other->id}/1/x.tsv.gz", 'x');

        $account->delete();

        Storage::disk('local')->assertMissing("raw-reports/app_store/{$account->id}/1/x.tsv.gz");
        Storage::disk('local')->assertExists("raw-reports/app_store/{$other->id}/1/x.tsv.gz");
    }
}
