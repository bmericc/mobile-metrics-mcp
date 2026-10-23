<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProvider(string $provider, string $id, ?string $email): void
    {
        $socialUser = (new SocialiteUser)->map(['id' => $id, 'name' => 'Ada Lovelace', 'email' => $email, 'avatar' => null]);
        $socialUser->token = 'token';

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($socialUser);
        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    public function test_first_login_creates_user_with_personal_workspace(): void
    {
        $this->fakeProvider('google', 'g-1', 'ada@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/app');

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertCount(1, $user->workspaces);
        $this->assertSame('owner', $user->workspaces->first()->pivot->role);
    }

    public function test_second_provider_with_same_email_links_to_existing_user(): void
    {
        $this->fakeProvider('google', 'g-1', 'ada@example.com');
        $this->get('/auth/google/callback');
        auth()->logout();

        $this->fakeProvider('apple', 'a-1', 'ada@example.com');
        $this->get('/auth/apple/callback')->assertRedirect('/app');

        $this->assertSame(1, User::count());
        $this->assertEqualsCanonicalizing(['google', 'apple'], User::first()->socialIdentities->pluck('provider')->all());
        $this->assertCount(1, User::first()->workspaces);
    }

    public function test_unknown_or_unconfigured_provider_is_rejected(): void
    {
        config(['services.huawei.client_id' => null]);

        $this->get('/auth/facebook/redirect')->assertNotFound();
        $this->get('/auth/huawei/redirect')->assertNotFound();
    }

    public function test_apple_form_post_is_bounced_to_get_without_touching_the_session(): void
    {
        $response = $this->post('/auth/apple/callback', [
            'code' => 'c-123',
            'state' => 's-456',
            'user' => '{"name":{"firstName":"Ada"}}',
            'ignored' => 'x',
        ]);

        $response->assertStatus(303)->assertCookieMissing(config('session.cookie'));
        $location = $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertStringEndsWith('/auth/apple/callback?'.parse_url($location, PHP_URL_QUERY), $location);
        $this->assertSame(['code' => 'c-123', 'state' => 's-456', 'user' => '{"name":{"firstName":"Ada"}}'], $query);
    }
}
