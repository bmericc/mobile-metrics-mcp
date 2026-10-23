<?php

namespace App\Http\Controllers;

use App\Actions\VerifyStoreAccount;
use App\Enums\Store;
use App\Filament\App\Resources\StoreAccounts\StoreAccountResource;
use App\Jobs\SyncStoreAccount;
use App\Models\Workspace;
use App\Stores\GooglePlay\GoogleClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * Geliştirici hesabını anahtar yapıştırmadan, mağazanın kendi OAuth onayıyla bağlar.
 * Giriş (SocialLoginController) ile aynı OAuth istemcisini kullanır ama ayrı callback ve ek scope'larla.
 */
class StoreConnectController extends Controller
{
    private const SESSION_KEY = 'store_connect.workspace_id';

    public function redirect(Request $request, string $store, Workspace $workspace): SymfonyRedirect
    {
        abort_unless($request->user()->canAccessTenant($workspace), 403);

        $request->session()->put(self::SESSION_KEY, $workspace->id);

        return match (Store::from($store)) {
            Store::GooglePlay => Socialite::driver('google')
                ->redirectUrl(route('store.connect.callback', $store))
                ->scopes(GoogleClient::SCOPES)
                // Refresh token yalnızca offline erişim + açık onayla döner.
                ->with(['access_type' => 'offline', 'prompt' => 'consent'])
                ->redirect(),
            default => abort(404),
        };
    }

    public function callback(Request $request, string $store, VerifyStoreAccount $verify): RedirectResponse
    {
        $workspace = Workspace::findOrFail($request->session()->pull(self::SESSION_KEY));
        abort_unless($request->user()->canAccessTenant($workspace), 403);

        $credentials = match (Store::from($store)) {
            Store::GooglePlay => $this->googlePlayCredentials($store),
            default => abort(404),
        };

        // Aynı Google hesabı yeniden bağlanırsa mevcut kayıtlar güncellenir, yinelenmez. Google Play'de bir
        // bağlantı birden çok geliştirici hesabı kaydı tarafından paylaşılır; hepsinin anahtarı yenilenir.
        $accounts = $workspace->storeAccounts()
            ->where('store', $store)
            ->get()
            ->filter(fn ($account) => ($account->credentials['email'] ?? null) === $credentials['email']);

        foreach ($accounts as $existing) {
            $existing->update(['credentials' => [...$existing->credentials, ...$credentials]]);
        }

        $account = $accounts->first() ?? $workspace->storeAccounts()->create([
            'store' => $store,
            'label' => $credentials['email'],
            'credentials' => $credentials,
        ]);

        $url = StoreAccountResource::getUrl('index', tenant: $workspace, panel: 'app');

        if (! $verify($account)) {
            return redirect($url)->with('store_connect.error', $account->last_error);
        }

        SyncStoreAccount::dispatch($account);

        return redirect($url)->with('store_connect.success', $account->label);
    }

    /**
     * @return array<string, mixed>
     */
    private function googlePlayCredentials(string $store): array
    {
        $user = Socialite::driver('google')->redirectUrl(route('store.connect.callback', $store))->user();

        abort_if(blank($user->refreshToken), 422, 'Google yenileme anahtarı vermedi; bağlantıyı tekrar deneyin.');

        return [
            'auth' => 'oauth',
            'email' => $user->getEmail(),
            'refresh_token' => $user->refreshToken,
        ];
    }
}
