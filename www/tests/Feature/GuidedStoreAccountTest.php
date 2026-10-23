<?php

namespace Tests\Feature;

use App\Enums\Store;
use App\Filament\App\Resources\StoreAccounts\Pages\ListStoreAccounts;
use App\Jobs\SyncStoreAccount;
use App\Models\User;
use App\Models\Workspace;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use phpseclib4\Crypt\RSA;
use Tests\TestCase;

class GuidedStoreAccountTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $this->workspace = Workspace::create(['name' => 'Acme', 'owner_id' => $user->id]);
        $this->workspace->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->workspace);
    }

    public function test_app_store_key_upload_creates_verified_account_with_key_id_from_filename(): void
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($key, $pem);
        Http::fake(['https://api.appstoreconnect.apple.com/v1/apps*' => Http::response(['data' => []])]);

        Livewire::test(ListStoreAccounts::class)
            ->callAction('addAppStore', data: [
                'issuer_id' => '69a6de7e-1111-2222-3333-444455556666',
                'key_file' => UploadedFile::fake()->createWithContent('AuthKey_ABC123XYZ9.p8', $pem),
                'label' => 'Acme Apple',
            ])
            ->assertHasNoActionErrors();

        $account = $this->workspace->storeAccounts()->sole();
        $this->assertSame(Store::AppStore, $account->store);
        $this->assertSame('active', $account->status);
        $this->assertSame('ABC123XYZ9', $account->credentials['key_id']);
        $this->assertSame(trim($pem), $account->credentials['private_key']);
        Queue::assertPushed(SyncStoreAccount::class);
    }

    public function test_huawei_service_account_upload_signs_ps256_jwt(): void
    {
        $key = RSA::createKey(2048);
        $serviceAccount = [
            'key_id' => 'kid-123',
            'private_key' => $key->toString('PKCS8'),
            'sub_account' => '1009988',
            'token_uri' => 'https://oauth-login.cloud.huawei.com/oauth2/v3/token',
        ];
        Http::fake(['https://connect-api.cloud.huawei.com/api/publish/v2/appid-list*' => Http::response([
            'ret' => ['code' => 0], 'appids' => [['key' => 'Acme', 'value' => '100']],
        ])]);

        Livewire::test(ListStoreAccounts::class)
            ->callAction('addAppGallery', data: [
                'label' => 'Acme Huawei',
                'key_file' => UploadedFile::fake()->createWithContent('acme-private.json', json_encode($serviceAccount)),
                'package_names' => 'com.acme.app',
                'review_site' => 'de',
            ])
            ->assertHasNoActionErrors();

        $account = $this->workspace->storeAccounts()->sole();
        $this->assertSame('active', $account->status);
        $this->assertArrayNotHasKey('client_id', $account->credentials);

        Http::assertSent(function (Request $request) use ($key) {
            [$header, $payload, $signature] = explode('.', str($request->header('Authorization')[0])->after('Bearer ')->toString());
            $decode = fn (string $part) => base64_decode(strtr($part, '-_', '+/'));
            $h = json_decode($decode($header), true);
            $p = json_decode($decode($payload), true);

            $valid = $key->getPublicKey()
                ->withPadding(RSA::SIGNATURE_PSS)->withHash('sha256')->withMGFHash('sha256')->withSaltLength(32)
                ->verify("{$header}.{$payload}", $decode($signature));

            return $h['alg'] === 'PS256' && $h['kid'] === 'kid-123'
                && $p['iss'] === '1009988'
                && $p['aud'] === 'https://oauth-login.cloud.huawei.com/oauth2/v3/token'
                && $valid
                && ! $request->hasHeader('client_id');
        });
    }

    public function test_invalid_service_account_file_is_reported(): void
    {
        Livewire::test(ListStoreAccounts::class)
            ->callAction('addAppGallery', data: [
                'label' => 'Bad',
                'key_file' => UploadedFile::fake()->createWithContent('bad.json', '{"foo":1}'),
                'package_names' => 'com.acme.app',
            ]);

        $account = $this->workspace->storeAccounts()->sole();
        $this->assertSame('failed', $account->status);
        $this->assertStringContainsString('Service Account dosyası geçersiz', $account->last_error);
    }
}
