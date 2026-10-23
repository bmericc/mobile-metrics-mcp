#!/usr/bin/env sh
# Rutin production deploy — ~/kalehost-metrics/deploy içinden çalıştır: ./deploy.sh
#
# git pull + image build + migrate + cache temizliği + servis restart, artından
# Passport OAuth key izin düzeltmesi (bkz. aşağıdaki not).
set -e

cd "$(dirname "$0")/.."
git pull
cd deploy

docker compose up -d --build

# vendor/ ve Filament asset'leri git'te değil.
docker compose exec -T app composer install --no-dev --no-interaction --optimize-autoloader
docker compose exec -T app php artisan filament:assets

# Bind-mount edilen www/ host kullanıcısına ait; php-fpm (www-data) storage ve cache'e yazabilmeli.
# raw-reports named volume'u ilk oluşturulduğunda root sahipli gelir.
docker compose exec -T -u root app sh -c '
  chmod -R a+rwX storage bootstrap/cache
  chown -R www-data:www-data storage/app/private/raw-reports
'

# db container yeniden oluşturulduysa MariaDB birkaç saniye bağlantı kabul etmiyor;
# migrate "Connection refused" ile düşüp deploy'u yarıda bırakıyordu. DB hazır olana
# kadar (en fazla ~60 sn) migrate'i tekrar dene.
i=0
until docker compose exec -T app php artisan migrate --force; do
  i=$((i + 1))
  if [ "$i" -ge 12 ]; then
    echo "HATA: veritabanı 60 sn içinde hazır olmadı, deploy durduruldu." >&2
    exit 1
  fi
  echo "Veritabanı henüz hazır değil, 5 sn sonra tekrar denenecek ($i/12)..."
  sleep 5
done
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan view:clear
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose restart app worker scheduler nginx

# --- Passport OAuth key izinleri ---------------------------------------------
# storage/oauth-*.key dosyaları www-data:www-data + 600 olmalı. League OAuth2
# Server (CryptKey) 600/660 dışındaki izni reddeder; olmazsa `auth:api` guard'ı
# patlar ve /mcp ile Passport korumalı her uç 500 döner. Bu izin, storage'a
# yapılan `chmod -R` / clone / cp gibi işlemlerle sık sık 0666'ya bozuluyor,
# bu yüzden her deploy'da yeniden zorlanıyor.
docker compose exec -T -u root app sh -c '
  for k in storage/oauth-private.key storage/oauth-public.key; do
    [ -f "$k" ] && chown www-data:www-data "$k" && chmod 600 "$k"
  done
'

echo "OK — deploy tamamlandı."
