<?php

namespace App\Filament\App\Resources\StoreAccounts\Actions;

use App\Actions\VerifyStoreAccount;
use App\Enums\Store;
use App\Filament\App\Resources\StoreAccounts\PendingPlayConnection;
use App\Filament\App\Resources\StoreAccounts\Schemas\StoreAccountForm;
use App\Jobs\SyncStoreAccount;
use App\Stores\GooglePlay\GoogleClient;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * OAuth ile bağlanamayan mağazalar için rehberli ekleme: kullanıcı mağaza konsolunda anahtar
 * dosyasını oluşturur, buraya yükler; hesap anında doğrulanır ve senkronize edilir.
 * Yüklenen dosya diske yazılmaz, yalnızca içeriği şifreli credentials alanına girer.
 */
class AddStoreAccountAction
{
    public static function appStore(): Action
    {
        return Action::make('addAppStore')
            ->label('App Store hesabı ekle')
            ->icon('heroicon-o-key')
            ->color('gray')
            ->modalHeading('App Store Connect hesabı ekle')
            ->modalSubmitActionLabel('Ekle ve doğrula')
            ->schema([
                self::steps('Apple OAuth sunmuyor; App Store Connect\'te bir API anahtarı oluşturup .p8 dosyasını buraya yükleyin.', [
                    '<a href="https://appstoreconnect.apple.com/access/integrations/api" target="_blank" class="underline">App Store Connect → Kullanıcılar ve Erişim → Entegrasyonlar → Ekip Anahtarları</a> sayfasını açın.',
                    '"API Anahtarı Oluştur" ile <b>Admin</b> erişimli bir anahtar oluşturun (rapor isteği açmak Admin ister).',
                    '.p8 dosyasını indirin (yalnızca bir kez indirilebilir) ve aşağıya sürükleyin.',
                    'Aynı sayfanın üstündeki <b>Issuer ID</b>\'yi kopyalayın.',
                ]),
                TextInput::make('issuer_id')
                    ->label('Issuer ID')
                    ->placeholder('69a6de7e-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
                    ->uuid()
                    ->required(),
                FileUpload::make('key_file')
                    ->label('.p8 anahtar dosyası')
                    ->storeFiles(false)
                    ->maxSize(16)
                    ->required(),
                TextInput::make('key_id')
                    ->label('Key ID')
                    ->helperText('Boş bırakılırsa dosya adından (AuthKey_XXXXXXXXXX.p8) alınır.'),
                TextInput::make('label')
                    ->label('Hesap adı')
                    ->placeholder('Şirket / takım adı')
                    ->required(),
                TextInput::make('vendor_number')
                    ->label('Vendor number (opsiyonel)')
                    ->helperText('Satış ve gelir raporları için; Ödemeler ve Finansal Raporlar sayfasında.'),
            ])
            ->action(function (array $data, VerifyStoreAccount $verify) {
                $file = self::file($data['key_file']);
                $keyId = filled($data['key_id'] ?? null)
                    ? trim($data['key_id'])
                    : str($file->getClientOriginalName())->match('/AuthKey_([A-Z0-9]+)\.p8/')->toString();

                self::create(Store::AppStore, $data['label'], [
                    'issuer_id' => trim($data['issuer_id']),
                    'key_id' => $keyId,
                    'private_key' => trim($file->get()),
                    'vendor_number' => $data['vendor_number'] ?? null,
                ], $verify);
            });
    }

    public static function appGallery(): Action
    {
        return Action::make('addAppGallery')
            ->label('Huawei hesabı ekle')
            ->icon('heroicon-o-key')
            ->color('gray')
            ->modalHeading('AppGallery Connect hesabı ekle')
            ->modalSubmitActionLabel('Ekle ve doğrula')
            ->schema([
                self::steps('Huawei bu API için OAuth\'u yalnızca onaylı platform geliştiricilerine açıyor; Service Account dosyası yükleyin.', [
                    '<a href="https://developer.huawei.com/consumer/en/service/josp/agc/index.html#/userPermission" target="_blank" class="underline">AppGallery Connect → Kullanıcılar ve izinler</a> → API key → Connect API → <b>Service Account</b> sekmesi → Create.',
                    'Tür: <b>Developer-level</b>. Roller: <b>Operations</b> ve <b>Customer service</b> (raporlar ve yorumlar).',
                    'Oluşturunca inen <b>…private.json</b> dosyasını aşağıya sürükleyin.',
                ]),
                TextInput::make('label')
                    ->label('Hesap adı')
                    ->required(),
                FileUpload::make('key_file')
                    ->label('Service Account JSON dosyası')
                    ->storeFiles(false)
                    ->acceptedFileTypes(['application/json', 'text/plain'])
                    ->maxSize(16)
                    ->required(),
                Textarea::make('package_names')
                    ->label('Paket adları')
                    ->helperText('AppGallery uygulama listelemiyor; virgül veya satırla ayırın.')
                    ->rows(3)
                    ->required(),
                Select::make('review_site')
                    ->label('Yorum sitesi')
                    ->options(['de' => 'Avrupa (Almanya)', 'sg' => 'Asya-Afrika-Latin Amerika (Singapur)', 'ru' => 'Rusya', 'cn' => 'Çin'])
                    ->default('de'),
                TextInput::make('review_countries')
                    ->label('Yorum ülkeleri')
                    ->placeholder('TR,DE,FR')
                    ->helperText('Boş bırakılırsa yorumlar senkronize edilmez.'),
                TextInput::make('client_id')
                    ->label('Yorumlar için API client ID (opsiyonel)')
                    ->helperText('Huawei yorum API\'si Service Account kabul etmiyor. Aynı Connect API sayfasının "API client" sekmesinde, proje N/A ve Customer service rolüyle bir client oluşturun.'),
                TextInput::make('client_secret')
                    ->label('API client secret')
                    ->password()
                    ->requiredWith('client_id'),
            ])
            ->action(function (array $data, VerifyStoreAccount $verify) {
                self::create(Store::AppGallery, $data['label'], [
                    'service_account_json' => trim(self::file($data['key_file'])->get()),
                    'package_names' => $data['package_names'],
                    'review_site' => $data['review_site'] ?? 'de',
                    'review_countries' => $data['review_countries'] ?? null,
                    'client_id' => $data['client_id'] ?? null,
                    'client_secret' => $data['client_secret'] ?? null,
                ], $verify);
            });
    }

    /**
     * Google Play bağlantısından sonra açılır: bağlanan Google hesabının eriştiği geliştirici hesaplarından
     * istenenler, her biri kendi rapor bucket'ıyla ayrı kayıt olarak eklenir.
     */
    public static function googlePlayDeveloperAccounts(): Action
    {
        $pending = fn () => PendingPlayConnection::get(Filament::getTenant());

        return Action::make('addPlayDeveloperAccounts')
            ->label('Play geliştirici hesaplarını ekle')
            ->icon('heroicon-o-plus')
            ->visible(fn () => $pending() !== null)
            ->modalHeading('Play geliştirici hesaplarını ekle')
            ->modalDescription(fn () => ($pending()['email'] ?? 'Google hesabı').' bağlandı. Bu hesapla eriştiğin her Play Console geliştirici hesabını ayrı ekle.')
            ->modalSubmitActionLabel('Ekle ve doğrula')
            ->schema([
                Repeater::make('developer_accounts')
                    ->hiddenLabel()
                    ->schema([
                        TextInput::make('label')
                            ->label('Geliştirici hesabı adı')
                            ->placeholder('ör. Tartı Medikal')
                            ->required(),
                        TextInput::make('reports_bucket')
                            ->label('Cloud Storage URI')
                            ->placeholder('gs://pubsite_prod_rev_01234567890123456789')
                            ->required(),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel('Başka geliştirici hesabı')
                    ->reorderable(false),
                Callout::make('URI nerede?')
                    ->info()
                    ->description(StoreAccountForm::bucketHelp()),
            ])
            ->action(function (array $data, VerifyStoreAccount $verify) use ($pending) {
                $connection = $pending();

                foreach ($data['developer_accounts'] as $row) {
                    self::googlePlayDeveloperAccount($connection, $row['label'], $row['reports_bucket'], $verify);
                }

                PendingPlayConnection::forget();
            });
    }

    /**
     * Bir Google bağlantısını (auth, email, refresh_token) tek bir Play geliştirici hesabına bağlar.
     *
     * @param  array<string, mixed>  $connection
     */
    public static function googlePlayDeveloperAccount(array $connection, string $label, string $bucket, VerifyStoreAccount $verify): void
    {
        self::create(Store::GooglePlay, $label, [
            ...array_intersect_key($connection, array_flip(['auth', 'email', 'refresh_token'])),
            'reports_bucket' => GoogleClient::bucketName($bucket),
        ], $verify);
    }

    /**
     * @param  array<string>  $steps
     */
    private static function steps(string $intro, array $steps): Callout
    {
        $items = collect($steps)->map(fn (string $step) => "<li>{$step}</li>")->implode('');

        return Callout::make('Nasıl alınır?')
            ->info()
            ->description(new HtmlString("{$intro}<ol class=\"list-decimal ms-5 mt-2 space-y-1\">{$items}</ol>"));
    }

    private static function file(mixed $state): TemporaryUploadedFile
    {
        return is_array($state) ? array_values($state)[0] : $state;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private static function create(Store $store, string $label, array $credentials, VerifyStoreAccount $verify): void
    {
        $account = Filament::getTenant()->storeAccounts()->create([
            'store' => $store,
            'label' => $label,
            'credentials' => array_filter($credentials, fn ($value) => filled($value)),
        ]);

        if ($verify($account)) {
            SyncStoreAccount::dispatch($account);
            Notification::make()->title("{$label} eklendi, ilk senkronizasyon başladı")->success()->send();

            return;
        }

        Notification::make()
            ->title('Hesap eklendi ama doğrulanamadı')
            ->body($account->last_error)
            ->danger()
            ->persistent()
            ->send();
    }
}
