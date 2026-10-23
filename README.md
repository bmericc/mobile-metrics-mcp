# kalehost-metrics

Google Play, Apple App Store ve Huawei AppGallery'de yayınlanan uygulamaların istatistiklerini
(kurulum, kaldırma, aktif cihaz, gelir, crash/ANR, puan) ve kullanıcı yorumlarını toplayıp
MCP üzerinden LLM analizine açan sistem. Laravel 13 + Filament 5 + laravel/mcp.

## Depo yapısı

```
kalehost-metrics/
├── www/       ← Laravel uygulaması
├── deploy/    ← Docker Compose stack (db, app, worker, scheduler, nginx)
└── memory/    ← proje notları
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

## Durum

Üç mağaza adaptörü yazıldı ve sahte HTTP yanıtlarıyla test edildi; gerçek hesaplarla henüz denenmedi.

| Mağaza | Metrikler | Yorumlar | Bilinen eksik |
|---|---|---|---|
| App Store | Analytics Reports (Standard) | customerReviews | Gelir (Sales reports) yok |
| Google Play | GCS bulk raporlar + Reporting API (crash/ANR oranı) | reviews.list (son 7 gün) | Gelir (earnings zip) yok |
| AppGallery | appDownloadExport (günlük toplam + gün gün ülke) | Comments API (ülke listesi ve site gerekli) | Gelir yok; uygulamalar paket adı listesinden bulunur |

Ham rapor dosyaları `storage/app/private/raw-reports` altında saklanır; sütun adı uyuşmazlığında
sync açık hatayla durur ve gelen sütun listesini `raw_reports.error` alanına yazar.

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

## Production

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
