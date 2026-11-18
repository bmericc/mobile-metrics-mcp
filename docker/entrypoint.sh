#!/bin/sh
# Entrypoint of the production image.
#
# When the container runs the web server (the default command), it first
#   - creates the storage directories, in case /app/storage is an empty volume or bind mount,
#   - runs the database migrations, retrying until the database accepts connections,
#   - creates the Passport OAuth keys if they do not exist yet.
# Set AUTO_MIGRATE=false to skip the migrations. Other commands (queue:work,
# schedule:work, artisan ...) start directly.
set -e

is_web_server() {
    case "$1" in
        -*|frankenphp) return 0 ;;
        *) return 1 ;;
    esac
}

prepare_storage() {
    mkdir -p storage/app/private storage/app/public \
        storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/logs
}

migrate() {
    tries=${MIGRATE_TRIES:-30}
    i=1
    until php artisan migrate --force; do
        if [ "$i" -ge "$tries" ]; then
            echo "entrypoint: migrations failed after $tries tries; is the database reachable?" >&2
            exit 1
        fi
        echo "entrypoint: database not ready, retrying in 2 s ($i/$tries)"
        i=$((i + 1))
        sleep 2
    done
}

oauth_keys() {
    if [ ! -f storage/oauth-private.key ] || [ ! -f storage/oauth-public.key ]; then
        php artisan passport:keys --force
    fi
    chmod 600 storage/oauth-private.key storage/oauth-public.key
}

if is_web_server "$@"; then
    prepare_storage
    if [ "${AUTO_MIGRATE:-true}" = "true" ]; then
        migrate
    fi
    oauth_keys
fi

exec docker-php-entrypoint "$@"
