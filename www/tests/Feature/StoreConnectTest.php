<?php

namespace Tests\Feature;

use App\Enums\Store;
use App\Filament\App\Resources\StoreAccounts\Pages\ListStoreAccounts;
use App\Jobs\SyncStoreAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Stores\StoreRegistry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class StoreConnectTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'gcid', 'services.google.client_secret' => 'gsecret']);
        Http::preventStrayRequests();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::create(['name' => 'Acme', 'owner_id' => $this->user->id]);
        $this->workspace->users()->attach($this->user, ['role' => 'owner']);
    }

    public function test_redirect_asks_google_for_play_scopes_and_offline_access(): void
    {
        $response = $this->actingAs($this->user)->get("/connect/google_play/{$this->workspace->slug}");

        $location = $response->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://accounts.google.com/', $location);
        $this->assertStringContainsString('auth/androidpublisher', $query['scope']);
        $this->assertStringContainsString('auth/playdeveloperreporting', $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertSame(route('store.connect.callback', 'google_play'), $query['redirect_uri']);
    }

    public function test_cannot_connect_to_foreign_workspace(): void
    {
        $other = Workspace::create(['name' => 'Other', 'owner_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->get("/connect/google_play/{$other->slug}")->assertForbidden();
    }

    public function test_callback_asks_for_developer_accounts_instead_of_creating_email_account(): void
    {
        Queue::fake();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.user', 'expires_in' => 3600]),
            'https://playdeveloperreporting.googleapis.com/v1beta1/apps:search*' => Http::response(['apps' => []]),
            'https://storage.googleapis.com/storage/v1/b/*' => Http::response(['items' => []]),
        ]);

        $this->fakeGoogleUser('refresh-1', 'refresh-2');
        $this->actingAs($this->user)->get("/connect/google_play/{$this->workspace->slug}");
        $this->get('/connect/google_play/callback?code=c&state=s')
            ->assertRedirect("/app/{$this->workspace->slug}/store-accounts");

        // Bağlantı e-posta adıyla kayda dönüşmez; geliştirici hesabı seçimi bekler.
        $this->assertSame(0, $this->workspace->storeAccounts()->count());

        Filament::setCurrentPanel('app');
        Filament::setTenant($this->workspace);

        Livewire::test(ListStoreAccounts::class)
            ->assertActionVisible('addPlayDeveloperAccounts')
            ->callAction('addPlayDeveloperAccounts', data: ['developer_accounts' => [
                ['label' => 'Acme Games', 'reports_bucket' => 'gs://pubsite_prod_rev_1/stats/installs/'],
                ['label' => 'Acme Tools', 'reports_bucket' => 'pubsite_prod_2'],
            ]])
            ->assertHasNoActionErrors();

        $accounts = $this->workspace->storeAccounts()->orderBy('id')->get();
        $this->assertSame(['Acme Games', 'Acme Tools'], $accounts->pluck('label')->all());
        $this->assertSame(['active', 'active'], $accounts->pluck('status')->all());
        $this->assertSame(
            ['auth' => 'oauth', 'email' => 'dev@acme.test', 'refresh_token' => 'refresh-1', 'reports_bucket' => 'pubsite_prod_rev_1'],
            $accounts[0]->credentials,
        );
        $this->assertSame('pubsite_prod_2', $accounts[1]->credentials['reports_bucket']);
        Queue::assertPushed(SyncStoreAccount::class, 2);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'refresh-1'
            && $request['client_id'] === 'gcid');

        // Bekleyen bağlantı kullanıldı; modal tekrar açılmaz.
        Livewire::test(ListStoreAccounts::class)->assertActionHidden('addPlayDeveloperAccounts');

        // Aynı Google hesabıyla tekrar bağlanınca yeni kayıt açılmaz, tüm geliştirici hesaplarının token'ı yenilenir.
        $this->get("/connect/google_play/{$this->workspace->slug}");
        $this->get('/connect/google_play/callback?code=c&state=s');

        $this->assertSame(2, $this->workspace->storeAccounts()->count());
        foreach ($this->workspace->storeAccounts as $account) {
            $this->assertSame('refresh-2', $account->credentials['refresh_token']);
        }
        $this->assertSame('pubsite_prod_rev_1', $accounts[0]->fresh()->credentials['reports_bucket']);
    }

    public function test_revoked_access_gives_reconnect_hint(): void
    {
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $account = $this->workspace->storeAccounts()->create([
            'store' => Store::GooglePlay,
            'label' => 'dev@acme.test',
            'credentials' => ['auth' => 'oauth', 'email' => 'dev@acme.test', 'refresh_token' => 'revoked'],
        ]);

        $this->expectExceptionMessage('yeniden bağlayın');
        app(StoreRegistry::class)->for(Store::GooglePlay)->verify($account);
    }

    /**
     * Her callback sırayla bir sonraki refresh token'ı döndürür.
     */
    private function fakeGoogleUser(string ...$refreshTokens): void
    {
        $users = array_map(function (string $token) {
            $user = (new SocialiteUser)->map(['id' => 'g-dev', 'email' => 'dev@acme.test', 'name' => 'Dev']);
            $user->refreshToken = $token;

            return $user;
        }, $refreshTokens);

        $driver = Mockery::mock();
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn(...$users);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }
}
