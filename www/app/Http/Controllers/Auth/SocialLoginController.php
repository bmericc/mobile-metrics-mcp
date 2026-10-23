<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class SocialLoginController extends Controller
{
    public const PROVIDERS = ['google', 'apple', 'huawei'];

    public const LABELS = ['google' => 'Google', 'apple' => 'Apple', 'huawei' => 'Huawei ID'];

    /**
     * Client ID'si tanımlı sağlayıcılar; giriş sayfası yalnızca bunları gösterir.
     *
     * @return array<string>
     */
    public static function enabledProviders(): array
    {
        return array_values(array_filter(self::PROVIDERS, fn (string $provider) => filled(config("services.{$provider}.client_id"))));
    }

    public function redirect(string $provider): SymfonyRedirect
    {
        abort_unless(in_array($provider, self::enabledProviders(), true), 404);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Apple, e-posta/isim istendiğinde callback'i cross-site form_post (POST) ile yapar. SameSite=Lax
     * oturum çerezi bu istekte gönderilmediği için state doğrulaması burada yapılamaz. Bu route oturum
     * middleware'i olmadan çalışır (yeni boş oturum çerezi gerçek oturumu ezmesin) ve parametreleri
     * GET callback'e taşır; top-level GET yönlendirmede Lax çerez gönderilir, state normal doğrulanır.
     */
    public function appleFormPost(Request $request): RedirectResponse
    {
        $query = http_build_query($request->only(['code', 'state', 'user', 'error']));

        return redirect()->to(route('social.callback', 'apple').'?'.$query, 303);
    }

    public function callback(string $provider): RedirectResponse
    {
        $socialUser = Socialite::driver($provider)->user();

        $user = DB::transaction(fn () => $this->resolveUser($provider, $socialUser));

        Auth::login($user, remember: true);

        // MCP OAuth akışından gelindiyse Passport authorize sayfasına geri döner.
        return redirect()->intended('/app');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function resolveUser(string $provider, SocialUser $socialUser): User
    {
        $identity = SocialIdentity::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $socialUser->getId())
            ->first();

        $tokens = [
            'email' => $socialUser->getEmail(),
            'token' => $socialUser->token ?? null,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'token_expires_at' => isset($socialUser->expiresIn) ? now()->addSeconds($socialUser->expiresIn) : null,
        ];

        if ($identity) {
            $identity->update(array_filter($tokens));

            return $identity->user;
        }

        // Aynı e-posta ile farklı sağlayıcıdan gelen kullanıcı tek hesapta birleşir.
        $user = $socialUser->getEmail()
            ? User::where('email', $socialUser->getEmail())->first()
            : null;

        $user ??= $this->createUser($socialUser);

        $user->socialIdentities()->create([
            'provider' => $provider,
            'provider_user_id' => $socialUser->getId(),
            ...$tokens,
        ]);

        return $user;
    }

    private function createUser(SocialUser $socialUser): User
    {
        $name = $socialUser->getName() ?: str($socialUser->getEmail() ?? 'Kullanıcı')->before('@')->toString();

        $user = User::create([
            'name' => $name,
            // Apple "e-postamı gizle" seçilirse relay adresi gelir; hiç gelmezse benzersiz yer tutucu.
            'email' => $socialUser->getEmail() ?? $socialUser->getId().'@users.noreply',
            'avatar_url' => $socialUser->getAvatar(),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $workspace = Workspace::create(['name' => $name, 'owner_id' => $user->id]);
        $workspace->users()->attach($user->id, ['role' => 'owner']);

        return $user;
    }
}
