<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    private function member(bool $admin = false): array
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => $admin])->save();
        $workspace = Workspace::create(['name' => 'Acme', 'owner_id' => $user->id]);
        $workspace->users()->attach($user, ['role' => 'owner']);

        return [$user, $workspace];
    }

    public function test_guest_is_sent_to_social_login(): void
    {
        config(['services.google.client_id' => 'g', 'services.apple.client_id' => null, 'services.huawei.client_id' => 'h']);

        $this->get('/app')->assertRedirect('/login');
        $this->get('/login')->assertOk()
            ->assertSee('Google ile devam et')
            ->assertSee('Huawei ID ile devam et')
            ->assertDontSee('Apple ile devam et');
    }

    public function test_member_can_open_workspace_pages(): void
    {
        [$user, $workspace] = $this->member();

        foreach (['store-accounts', 'store-accounts/create', 'store-apps', 'products'] as $page) {
            $this->actingAs($user)->get("/app/{$workspace->slug}/{$page}")->assertOk();
        }
    }

    public function test_member_cannot_open_foreign_workspace(): void
    {
        [$user] = $this->member();
        [, $other] = $this->member();

        $this->actingAs($user)->get("/app/{$other->slug}/store-accounts")->assertNotFound();
    }

    public function test_admin_panel_requires_admin(): void
    {
        [$user] = $this->member();
        [$admin] = $this->member(admin: true);

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($admin)->get('/admin/sync-runs')->assertOk()
            ->assertSee(config('app.name'))->assertSee('fonts/brand/sora-700.woff2', false);
    }
}
