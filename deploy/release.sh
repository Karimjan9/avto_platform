#!/usr/bin/env bash
set -Eeuo pipefail
umask 0027

if [[ $# -ne 3 || $EUID -ne 0 ]]; then
    echo 'Usage as root: bash release.sh /absolute/release.tar.gz /absolute/site.conf YYYYMMDDTHHMMSSZ' >&2
    exit 1
fi

archive=$(realpath -- "$1")
configuration=$(realpath -- "$2")
release_id=$3
[[ $release_id =~ ^[0-9]{8}T[0-9]{6}Z$ ]] || { echo 'Invalid release identifier' >&2; exit 1; }
source "$configuration"
[[ $APP_BASE == /var/www/avto-platform || $APP_BASE == /var/www/avto-showcase ]] || exit 1
[[ -f $APP_BASE/shared/.env ]] || { echo 'Provision the production environment first' >&2; exit 1; }
release="$APP_BASE/releases/$release_id"
[[ ! -e $release ]] || { echo 'Release already exists; use a new identifier' >&2; exit 1; }

install -d -o root -g root -m 0711 "$APP_BASE" "$APP_BASE/releases" "$APP_BASE/shared"
install -d -o "$APP_USER" -g "$APP_USER" -m 0711 "$APP_BASE/shared/storage" "$APP_BASE/shared/storage/app"
install -d -o "$APP_USER" -g "$APP_USER" -m 0755 "$APP_BASE/shared/storage/app/public"
install -d -o "$APP_USER" -g "$APP_USER" -m 0700 \
    "$APP_BASE/shared/storage/app/private" "$APP_BASE/shared/storage/framework" \
    "$APP_BASE/shared/storage/framework/cache" "$APP_BASE/shared/storage/framework/cache/data" \
    "$APP_BASE/shared/storage/framework/sessions" "$APP_BASE/shared/storage/framework/views" \
    "$APP_BASE/shared/storage/logs" "$APP_BASE/shared/.composer"
install -d -o "$APP_USER" -g "$APP_USER" -m 0750 "$release"
tar --no-same-owner -xzf "$archive" -C "$release"
install -d -o "$APP_USER" -g "$APP_USER" -m 0700 "$release/bootstrap/cache"
ln -s "$APP_BASE/shared/.env" "$release/.env"
ln -s "$APP_BASE/shared/storage" "$release/storage"
chown -Rh "$APP_USER:$APP_USER" "$release"

cd "$release"
runuser -u "$APP_USER" -- env COMPOSER_HOME="$APP_BASE/shared/.composer" \
    /usr/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
runuser -u "$APP_USER" -- "$PHP_BINARY" artisan migrate --force --no-interaction
runuser -u "$APP_USER" -- "$PHP_BINARY" artisan storage:link
runuser -u "$APP_USER" -- "$PHP_BINARY" artisan optimize

if [[ $APP_ID == avto-platform ]]; then
    # Preserve hashed assets for pages that remain open across releases.
    install -d -o root -g root -m 0755 "$APP_BASE/shared/assets"
    if [[ -d $APP_BASE/current/public/build/assets ]]; then
        install -m 0644 "$APP_BASE"/current/public/build/assets/* "$APP_BASE/shared/assets/"
    fi
    install -m 0644 "$release"/public/build/assets/* "$APP_BASE/shared/assets/"
fi

chown -Rh "root:$APP_USER" "$release"
find "$release" -type d -exec chmod 0750 {} +
find "$release" -type f -exec chmod 0640 {} +
chmod 0711 "$release"
find "$release/public" -type d -exec chmod 0755 {} +
find "$release/public" -type f -exec chmod 0644 {} +
chown -R "$APP_USER:$APP_USER" "$release/bootstrap/cache"
chmod 0700 "$release/bootstrap/cache"
find "$release/bootstrap/cache" -type f -exec chmod 0600 {} +

if [[ -L $APP_BASE/current ]]; then
    readlink -f "$APP_BASE/current" > "$APP_BASE/previous-release"
fi
ln -s "$release" "$APP_BASE/current.next"
mv -Tf "$APP_BASE/current.next" "$APP_BASE/current"
systemctl reload "$PHP_FPM_SERVICE"
if supervisorctl status "$APP_ID-worker" >/dev/null 2>&1; then
    supervisorctl restart "$APP_ID-worker"
fi
echo "Deployed $APP_DOMAIN: $release_id"
