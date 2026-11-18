# Production image: the Laravel app on FrankenPHP (PHP + Caddy web server).
# The same image also runs the queue worker and the scheduler:
#   docker run ... IMAGE php artisan queue:work
#   docker run ... IMAGE php artisan schedule:work
# Configuration comes from environment variables (or an env_file); see docs/.env.example.
# On start, the web server container runs the migrations and creates the OAuth keys
# (see docker/entrypoint.sh; AUTO_MIGRATE=false turns the migrations off).

FROM composer:2@sha256:0c8e663f14c94d07b3c45b175c51f5a14718508f21fe4dd7e54f09488dd0ed71 AS vendor
WORKDIR /app
COPY www/composer.json www/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY www/ ./
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM dunglas/frankenphp:1-php8.4@sha256:8f0d509ad824fa86da88b55c5ea9f1605380f423ab55ec3a72864cc223491bfd

RUN install-php-extensions pdo_mysql pcntl bcmath zip intl opcache

# Plain HTTP on port 80; put TLS in front (reverse proxy) or set SERVER_NAME to a domain.
ENV SERVER_NAME=:80

WORKDIR /app
COPY --from=vendor /app /app

RUN php artisan package:discover --ansi \
    && php artisan filament:assets \
    && chmod -R a+rwX storage bootstrap/cache

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/app-entrypoint
ENTRYPOINT ["app-entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]

EXPOSE 80
