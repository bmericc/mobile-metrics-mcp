<?php

namespace App\Filament\App\Resources\StoreAccounts\Actions;

use App\Actions\VerifyStoreAccount;
use App\Enums\Store;
use App\Jobs\SyncStoreAccount;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
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
            ])
            ->action(function (array $data, VerifyStoreAccount $verify) {
                self::create(Store::AppGallery, $data['label'], [
                    'service_account_json' => trim(self::file($data['key_file'])->get()),
                    'package_names' => $data['package_names'],
                    'review_site' => $data['review_site'] ?? 'de',
                    'review_countries' => $data['review_countries'] ?? null,
                ], $verify);
            });
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
