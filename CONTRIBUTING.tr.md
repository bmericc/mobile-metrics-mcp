# Katkı

[English](CONTRIBUTING.md) · **Türkçe**

Katkınız için teşekkürler. Hata bildirimleri, düzeltmeler ve doküman iyileştirmeleri memnuniyetle karşılanır.

## Hata bildirme

Issue açın: <https://github.com/bmericc/mobile-metrics-mcp/issues>. Şunları ekleyin:

- sürüm veya image tag'i
- mağaza (Google Play, App Store veya AppGallery) ve hatanın olduğu adım (sync, yorumlar, MCP aracı)
- ne yaptığınız, ne beklediğiniz ve ne olduğu; ilgili log satırları
  (`storage/logs/laravel.log` ya da `docker logs`) ve sync hatalarında `raw_reports.error`

Paylaşmak istemediğiniz kimlik bilgilerini, token'ları, uygulama kimliklerini ve e-posta adreslerini çıkarın.
**Güvenlik sorunları herkese açık issue ile değil, [SECURITY.tr.md](SECURITY.tr.md)'deki yolla bildirilir.**

## Geliştirme ortamı

PHP 8.4 veya üstü ve Composer:

```bash
cd www
```

```bash
composer install
```

```bash
cp .env.example .env && php artisan key:generate && php artisan passport:keys
```

Testler mağaza hesabı gerektirmez: mağaza API'leri `Http::fake()` ile taklit edilir, veritabanı
bellek içi SQLite'tır.

## Kontroller

CI her push ve pull request'te test paketini PHP 8.4 ve 8.5'te çalıştırır. PR açmadan önce çalıştırın:

```bash
php artisan test
```

## Pull request'ler

- Bir PR'da tek bir değişiklik olsun; testlerini ekleyin ya da güncelleyin.
- Kullanıcının göreceği değişiklikler için [CHANGELOG.md](CHANGELOG.md)'nin "Unreleased" bölümüne bir satır ekleyin.
- PR'lar squash ile birleştirilir.

Katkıda bulunarak katkınızın [GNU General Public License v3.0 veya sonrası](LICENSE)
(GPL-3.0-or-later) ile yayınlanmasını kabul etmiş olursunuz.
