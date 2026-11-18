# Kurulum

[English](installation.md) · **Türkçe**

Bu rehber Mobile Metrics MCP'yi yayınlanmış Docker image'ından kurar. Bir şey build etmeniz ya da
PHP kurmanız gerekmez.

Stack dört container'dan oluşur. Veritabanı dışındakilerin hepsi aynı uygulama image'ını kullanır:

| Servis | Ne yapar |
|---|---|
| `app` | Web paneli, giriş ve MCP ucu (container içinde 80 portu) |
| `worker` | Mağaza raporlarını ve yorumları indiren sync job'larını çalıştırır |
| `scheduler` | Her gün 06:00'da her aktif mağaza hesabı için bir sync'i kuyruğa atar |
| `db` | MariaDB |

## 1. Gereksinimler

- Docker ve Docker Compose kurulu bir sunucu.
- Bir alan adı ve HTTPS'li bir reverse proxy (nginx, Caddy, Traefik, Nginx Proxy Manager...).
  Giriş sağlayıcıları ve MCP istemcileri herkese açık bir HTTPS adresi ister.
- En az bir giriş sağlayıcısı: Google, Apple veya Huawei. Uygulamada parola yoktur.
  Google Play hesabı bağlayacaksanız Google zaten gerekir.

## 2. Dosyaları indirin

```bash
mkdir mobile-metrics-mcp && cd mobile-metrics-mcp
```

```bash
curl -fsSLO https://raw.githubusercontent.com/bmericc/mobile-metrics-mcp/main/docs/docker-compose.yml
```

```bash
curl -fsSL -o .env https://raw.githubusercontent.com/bmericc/mobile-metrics-mcp/main/docs/.env.example
```

Image Docker Hub'da da var: `bmericc/mobile-metrics-mcp`. İsterseniz `docker-compose.yml`
içindeki `image:` satırını değiştirin.

## 3. `.env` dosyasını doldurun

Uygulama anahtarını üretip `APP_KEY` satırına yapıştırın:

```bash
docker run --rm ghcr.io/bmericc/mobile-metrics-mcp:1 php artisan key:generate --show
```

Bu anahtarı saklayın ve asla değiştirmeyin: sonradan ekleyeceğiniz mağaza kimlik bilgileri onunla
şifrelenir. Kaybolursa bütün mağaza hesaplarını yeniden eklemeniz gerekir.

Sonra şunları ayarlayın:

- `APP_URL`: herkese açık HTTPS adresi, örneğin `https://metrics.example.com`.
- `DB_PASSWORD`: uzun, rastgele bir parola. Veritabanı container'ı ilk açılışta bununla oluşturulur.
- Giriş sağlayıcıları: bir sonraki adıma bakın.

## 4. Giriş sağlayıcısı kurun

En az bir sağlayıcıyı doldurun. Boş bırakılan sağlayıcı giriş sayfasında görünmez.

**Google** (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`)

1. [Google Cloud Console](https://console.cloud.google.com/apis/credentials)'da **Web application**
   türünde bir OAuth client oluşturun.
2. İki yönlendirme adresini de ekleyin:
   - `{APP_URL}/auth/google/callback` (giriş)
   - `{APP_URL}/connect/google_play/callback` (Google Play hesabı bağlama)
3. Google Play bağlayacaksanız aynı projede şu API'leri açın: **Google Play Android Developer API**,
   **Google Play Developer Reporting API** ve **Cloud Storage**. Bunlar hassas izinler kullanır:
   OAuth onay ekranı test modundayken Google hesaplarınızı test kullanıcısı olarak ekleyin.

**Apple** (`APPLE_CLIENT_ID`, `APPLE_TEAM_ID`, `APPLE_KEY_ID`, `APPLE_PRIVATE_KEY`)

1. Apple Developer portalında Sign in with Apple açık bir **Services ID** oluşturun (client ID budur).
   Alan adı olarak `APP_URL`'deki alan adını, dönüş adresi olarak `{APP_URL}/auth/apple/callback`
   girin.
2. Sign in with Apple yetkili bir anahtar oluşturup `.p8` dosyasını indirin.
3. İlk açılıştan sonra (5. adım) `.p8` dosyasını storage volume'una kopyalayın, örneğin
   `docker compose cp AuthKey_XXXX.p8 app:/app/storage/apple-signin.p8`, ve
   `APPLE_PRIVATE_KEY=/app/storage/apple-signin.p8` yazın.

**Huawei** (`HUAWEI_CLIENT_ID`, `HUAWEI_CLIENT_SECRET`)

AppGallery Connect'te Account Kit açık bir web uygulaması oluşturun ve onun OAuth client ID ve
secret'ını kullanın. Yönlendirme adresi `{APP_URL}/auth/huawei/callback`.

## 5. Başlatın ve hazırlayın

```bash
docker compose up -d
```

Tabloları ve MCP istemcilerinin token imzaladığı OAuth anahtarlarını oluşturun. İlk açılıştan sonra
veritabanının hazır olması için birkaç saniye bekleyin:

```bash
docker compose exec app php artisan migrate --force
```

```bash
docker compose exec app php artisan passport:keys
```

Migration çalışana kadar worker ve scheduler "table not found" hatası yazabilir; kendiliğinden
yeniden başlarlar.

Uygulama artık `127.0.0.1:8080`'de dinliyor. Reverse proxy'nizi bu adrese yönlendirin ve
`{APP_URL}/login` sayfasının HTTPS ile açıldığını kontrol edin.

## 6. İlk giriş

1. `{APP_URL}/login` sayfasını açıp giriş yapın. İlk giriş bir kullanıcı ve kişisel bir workspace
   oluşturur.
2. `/admin` sistem panelini (kullanıcılar ve sync çalışmaları) görmek için kendinizi yönetici yapın:

   ```bash
   docker compose exec app php artisan tinker --execute "App\Models\User::where('email', 'siz@example.com')->update(['is_admin' => true]);"
   ```

Giriş yapan her kullanıcının kendi workspace'i olur. Mağaza hesapları ve veriler workspace bazında
tutulur.

## 7. Mağaza hesaplarını ekleyin

Panelde **Store accounts** sayfasını açın ve her geliştirici hesabı için bir hesap ekleyin. Hesap
eklenirken doğrulanır ve hemen senkronize edilir; sonra her gün senkronize olur.

| Mağaza | Ne gerekir |
|---|---|
| Google Play | **Google Play hesabı bağla** ile Google'la giriş yapın (yukarıdaki Google kurulumu gerekir), sonra geliştirici hesabının adını ve Cloud Storage URI'sini girin: Play Console → Raporları indir → İstatistikler → **Cloud Storage URI'sini kopyala**. |
| App Store | **App Store hesabı ekle**. **Sales and Reports** ve **Customer Support** rollerinde bir App Store Connect ekip API anahtarı: Kullanıcılar ve Erişim → Entegrasyonlar → Ekip Anahtarları. `.p8` dosyasını yükleyip Issuer ID'yi girin. Vendor number (Ödemeler ve Finansal Raporlar) indirme sayılarını ekler. |
| AppGallery | **Huawei hesabı ekle**. AppGallery Connect **Service Account**'u (developer-level, Operations ve Customer service rolleri): Kullanıcılar ve izinler → API key → Connect API. İnen JSON dosyasını yükleyip paket adlarını yazın; AppGallery uygulamaları listelemez. Yorumlar için yorum ülkelerini ve bir API client da girin. |

Panel her formun yanında bu adımları gösterir.

## 8. AI asistanı bağlayın

MCP adresi:

```
{APP_URL}/mcp
```

Bunu asistanınıza uzak MCP sunucusu olarak ekleyin (Claude'da: Ayarlar → Bağlayıcılar → Özel
bağlayıcı ekle). Asistan kendini kaydeder, giriş sayfasını açar ve erişim onayı ister. Sonra yalnızca
sizin workspace'lerinizi görür ve yalnızca okuyabilir:

`list-apps`, `get-metrics`, `compare-periods`, `top-countries`, `get-reviews`, `sync-status`.

## Güncelleme

```bash
docker compose pull
```

```bash
docker compose up -d
```

```bash
docker compose exec app php artisan migrate --force
```

`:1` etiketi en son 1.x sürümünü izler. Yalnızca elle güncellemek için `:1.0.1` gibi tam bir sürüme
sabitleyin. Güncellemeden önce [CHANGELOG.md](../CHANGELOG.md)'yi okuyun.

## Yedekleme

Şunları birlikte yedekleyin:

- `db-data` volume'u (ya da veritabanının `mariadb-dump` çıktısı)
- `storage` volume'u: OAuth anahtarları, Apple anahtarı ve ham mağaza raporları
- `.env` dosyası, özellikle `APP_KEY`
