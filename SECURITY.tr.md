# Güvenlik politikası

[English](SECURITY.md) · **Türkçe**

## Desteklenen sürümler

Güvenlik düzeltmeleri yalnızca son sürüme girer. Bildirmeden önce en yeni image'a veya tag'e güncelleyin.

| Sürüm | Destek |
| --- | --- |
| 0.1.x (son) | ✓ |
| daha eski | ✗ |

## Açık bildirme

**Herkese açık issue açmayın.** GitHub üzerinden gizli olarak bildirin:

<https://github.com/bmericc/mobile-metrics-mcp/security/advisories/new>

Sürümü veya image tag'ini, nasıl çalıştırdığınızı (Docker image, Compose stack ya da yerel),
tekrar üretme adımlarını ve etkisini yazın. Birkaç gün içinde yanıt alırsınız. Düzeltme
yayınlanınca, aksini istemediğiniz sürece adınızla birlikte duyurulur.

## Kapsam

Kapsamda: `www/` altındaki Laravel uygulaması, MCP ucu ve yayınlanan Docker image'ları. Örneğin:

- girişi veya OAuth'u atlatmak, bir MCP token'ıyla başka bir kullanıcının workspace'ini okumak
- kayıtlı mağaza kimlik bilgilerini (service account JSON, `.p8` anahtarları, client secret) okumak
- gizli bilgilerin loglara, panellere veya MCP araç çıktısına sızması
- panellerde CSRF, XSS veya oturum sorunları

Kapsam dışı: Google Play, App Store Connect veya AppGallery Connect'in kendi açıkları; HTTPS
olmadan çalışan kurulumlar; bir workspace üyesinin zaten yapabilmesi gereken işlemler.

## Güvenli çalıştırma

- Yalnızca HTTPS üzerinden sunun.
- `.env`, `storage/oauth-*.key` ve veritabanını gizli tutun, birlikte yedekleyin.
  İlk açılıştan sonra `APP_KEY`'i asla değiştirmeyin: kayıtlı mağaza kimlik bilgileri onunla şifrelenir.
- Mağaza izin veriyorsa hesaplara salt okunur rol verin.
- Yeni sürümlere güncelleyin; bu repoda Dependabot uyarıları açık.
