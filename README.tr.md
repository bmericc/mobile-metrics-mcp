# Mobile Metrics MCP

<p align="center">
  <a href="https://github.com/bmericc/mobile-metrics-mcp/actions/workflows/tests.yml"><img src="https://github.com/bmericc/mobile-metrics-mcp/actions/workflows/tests.yml/badge.svg" alt="tests"></a>
  <a href="https://scorecard.dev/viewer/?uri=github.com/bmericc/mobile-metrics-mcp"><img src="https://api.scorecard.dev/projects/github.com/bmericc/mobile-metrics-mcp/badge" alt="OpenSSF Scorecard"></a>
  <a href="https://github.com/bmericc/mobile-metrics-mcp/actions/workflows/docker.yml"><img src="https://img.shields.io/github/actions/workflow/status/bmericc/mobile-metrics-mcp/docker.yml?label=docker&logo=docker&logoColor=white" alt="Docker image"></a>
  <a href="https://github.com/bmericc/mobile-metrics-mcp/releases/latest"><img src="https://img.shields.io/github/v/release/bmericc/mobile-metrics-mcp" alt="Latest release"></a>
  <a href="https://github.com/bmericc/mobile-metrics-mcp/actions/workflows/tests.yml"><img src="https://img.shields.io/badge/PHP-8.4%20%7C%208.5%20tested-777BB4?logo=php&logoColor=white" alt="PHP 8.4 | 8.5 tested"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13"></a>
  <a href="https://modelcontextprotocol.io"><img src="https://img.shields.io/badge/MCP-server-6E56CF" alt="Model Context Protocol server"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPL--3.0-blue" alt="GPL-3.0"></a>
  <a href="https://github.com/sponsors/bmericc"><img src="https://img.shields.io/badge/sponsor-%E2%9D%A4-ea4aaa?logo=githubsponsors" alt="Sponsor"></a>
</p>

[English](README.md) · **Türkçe**

Google Play, Apple App Store ve Huawei AppGallery'de yayınlanan uygulamaların istatistiklerini
(kurulum, kaldırma, aktif cihaz, gelir, crash/ANR, puan) ve kullanıcı yorumlarını toplayıp
MCP üzerinden LLM analizine açan sistem. Laravel 13 + Filament 5 + laravel/mcp.

## Depo yapısı

```
mobile-metrics-mcp/
├── www/         ← Laravel uygulaması
├── deploy/      ← production'da kullanılan Docker Compose stack (db, app, worker, scheduler, nginx)
└── Dockerfile   ← kendi başına çalışan production image'ı (FrankenPHP)
```

## Mimari

- **Giriş:** Google, Apple veya Huawei ID ile (`/login`). Parola yok. Aynı e-postayla farklı
  sağlayıcıdan gelen kullanıcı tek hesapta birleşir. İlk girişte kişisel workspace açılır.
- **Workspace (tenant):** Kullanıcı birden fazla workspace'e üye olabilir; her workspace'te
  mağaza başına birden fazla geliştirici hesabı (`store_accounts`) tutulabilir.
- **Giriş kimliği ≠ mağaza erişimi.** Mağaza verisi için ayrıca kimlik bilgisi girilir:
  - Google Play: service account JSON + rapor bucket'ı (`pubsite_prod_rev_*`)
  - App Store Connect: Issuer ID, Key ID, `.p8` anahtarı (+ satış raporları için vendor number)
  - AppGallery Connect: API client (client_id / client_secret)
- **Toplama:** `app/Stores/{GooglePlay,AppStore,AppGallery}` adaptörleri `StoreCollector`
  arayüzünü uygular. Scheduler her gün 06:00'da aktif hesaplar için `SyncStoreAccount` →
  `SyncAppMetrics` (son 7 günü yeniden çeker) + `SyncAppReviews` job'larını kuyruğa atar.
  Her çalışma `sync_runs` tablosuna yazılır.
- **Veri:** `daily_metrics` long format (`store_app_id, date, country, metric, value`).
  `country = 'ZZ'` ülke kırılımsız toplamdır. Metrik adları `App\Stores\Metric`.
  `products` aynı uygulamanın mağazalardaki karşılıklarını gruplar.
- **MCP:** `/mcp` (Passport OAuth, `Mcp::oauthRoutes()`). Araçlar salt okunur ve
  `ScopesToUser` ile yalnızca token sahibinin workspace'lerini görür:
  `list-apps`, `get-metrics`, `compare-periods`, `top-countries`, `get-reviews`, `sync-status`.
- **Paneller:** `/app/{workspace}` kullanıcı paneli, `/admin` sistem paneli (`users.is_admin`).
- **Herkese açık sayfalar:** `/` tanıtım, `/privacy` + `/terms` (TR), `/en/privacy` + `/en/terms` (EN).

## Durum

Üç mağaza adaptörü yazıldı ve sahte HTTP yanıtlarıyla test edildi; gerçek hesaplarla henüz denenmedi.

| Mağaza | Metrikler | Yorumlar | Bilinen eksik |
|---|---|---|---|
| App Store | Analytics Reports (Standard) | customerReviews | Gelir (Sales reports) yok |
| Google Play | GCS bulk raporlar + Reporting API (crash/ANR oranı) | reviews.list (son 7 gün) | Gelir (earnings zip) yok |
| AppGallery | appDownloadExport (günlük toplam + gün gün ülke) | Comments API (ülke listesi ve site gerekli) | Gelir yok; uygulamalar paket adı listesinden bulunur |

Ham rapor dosyaları `storage/app/private/raw-reports` altında saklanır; sütun adı uyuşmazlığında
sync açık hatayla durur ve gelen sütun listesini `raw_reports.error` alanına yazar.

## Docker ile çalıştırma

Her sürümde `linux/amd64` ve `linux/arm64` için image yayınlanır:

- GitHub Container Registry: `ghcr.io/bmericc/mobile-metrics-mcp`
- Docker Hub: `bmericc/mobile-metrics-mcp`

Image uygulamayı 80 portunda sunar (FrankenPHP). Ayarlar ortam değişkenleriyle verilir; şablon
[`www/.env.example`](www/.env.example). MySQL/MariaDB veritabanı gerekir. `storage/` klasörünü
bir volume'da tutun: Passport anahtarları ve ham raporlar orada.

```bash
docker run -d --name metrics -p 8080:80 --env-file .env -v metrics-storage:/app/storage ghcr.io/bmericc/mobile-metrics-mcp
```

```bash
docker exec metrics php artisan migrate --force
```

```bash
docker exec metrics php artisan passport:keys
```

Kuyruk işçisi ve scheduler aynı image'la, aynı env dosyası ve volume ile çalışır:

```bash
docker run -d --name metrics-worker --env-file .env -v metrics-storage:/app/storage ghcr.io/bmericc/mobile-metrics-mcp php artisan queue:work --tries=2 --timeout=600
```

```bash
docker run -d --name metrics-scheduler --env-file .env -v metrics-storage:/app/storage ghcr.io/bmericc/mobile-metrics-mcp php artisan schedule:work
```

HTTPS için önüne bir reverse proxy koyun ya da `SERVER_NAME`'i alan adınız yapın; FrankenPHP
sertifikayı kendisi alır.

## Yerel geliştirme

```bash
cd www
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan passport:keys
php artisan serve
```

Admin yapmak için: `php artisan tinker` → `User::where('email', '...')->update(['is_admin' => true])`.

Testler: `php artisan test`

## Production (Docker Compose)

`deploy/` kalehost ile aynı düzende, aynı VM'de (192.168.0.98) `~/kalehost-metrics` altında çalışır.
Subnet `172.25.0.0/16`, nginx `192.168.0.98:8084` (NPM proxy host `metrics.kalehost.net` → bu adres).

```bash
cd deploy
cp .env.example .env      # gerçek DB parolaları
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan passport:keys
```

Sonraki deploy'lar: `deploy/deploy.sh`.

## Katkı ve güvenlik

Bkz. [CONTRIBUTING.tr.md](CONTRIBUTING.tr.md). Güvenlik açıklarını [SECURITY.tr.md](SECURITY.tr.md)'de
anlatıldığı gibi gizli olarak bildirin. Değişiklikler [CHANGELOG.md](CHANGELOG.md)'de.

## Lisans

[GNU General Public License v3.0 veya sonrası](LICENSE) (GPL-3.0-or-later) ile lisanslanmıştır.
