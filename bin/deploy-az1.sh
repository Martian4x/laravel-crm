#!/usr/bin/env bash
#
# Atomic deploy of AzimioCustomers CRM onto az1.azimio.co.tz.
#
# Runs ON THE SERVER as root. Called either by the GitHub Actions deploy
# workflow or by hand:
#
#     ssh az1 'bash -s' < bin/deploy-az1.sh < /dev/null   # (tarball on stdin: see below)
#
# The code arrives as a tar stream on fd 3 so stdin stays free for the
# script itself. The workflow does:
#
#     git archive HEAD | ssh az1 "cat > /tmp/release.tar" && ssh az1 "bash /tmp/deploy.sh /tmp/release.tar"
#
# Why releases/current rather than git pull in place: a pull leaves the app
# half-updated while composer runs, and there is no way back. Here the live
# symlink only moves once the new release is fully built, and the previous
# release stays on disk to roll back to.
set -euo pipefail

TARBALL="${1:?usage: deploy-az1.sh /path/to/release.tar}"
APP_DIR=/var/www/clients
PHP=/usr/bin/php8.4
OWNER=clients
KEEP=5

REL="$(date +%Y%m%d%H%M%S)"
RELEASE_DIR="$APP_DIR/releases/$REL"

echo "==> release $REL"
install -d -o "$OWNER" -g "$OWNER" "$RELEASE_DIR"
tar xf "$TARBALL" -C "$RELEASE_DIR"

echo "==> shared state"
# .env and storage are shared, never shipped: the tarball deliberately has
# no .env, and storage holds uploads that must survive a deploy.
ln -sfn "$APP_DIR/shared/.env" "$RELEASE_DIR/.env"
rm -rf "$RELEASE_DIR/storage"
ln -sfn "$APP_DIR/shared/storage" "$RELEASE_DIR/storage"
chown -R "$OWNER":"$OWNER" "$RELEASE_DIR"

echo "==> dependencies"
cd "$RELEASE_DIR"
sudo -u "$OWNER" "$PHP" /usr/local/bin/composer install \
    --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> migrations"
# Run BEFORE the symlink flips. A migration that fails must leave the live
# site untouched rather than half-migrated against new code.
sudo -u "$OWNER" "$PHP" artisan migrate --force

echo "==> caches"
sudo -u "$OWNER" "$PHP" artisan config:cache
sudo -u "$OWNER" "$PHP" artisan route:cache
sudo -u "$OWNER" "$PHP" artisan view:cache

# Points at SHARED storage, not this release, so the link keeps working
# after the next deploy.
ln -sfn "$APP_DIR/shared/storage/app/public" "$RELEASE_DIR/public/storage"
chown -h "$OWNER":"$OWNER" "$RELEASE_DIR/public/storage"

echo "==> go live"
PREVIOUS="$(readlink -f "$APP_DIR/current" 2>/dev/null || true)"
ln -sfn "$RELEASE_DIR" "$APP_DIR/current"

# Reload rather than restart: in-flight requests finish on the old code.
systemctl reload php8.4-fpm

# The worker holds the old release's code in memory, so it must be bounced
# or queued jobs keep running the previous version.
systemctl restart clients-queue

echo "==> smoke test"
CODE="$(curl -sS -o /dev/null -w '%{http_code}' -k \
    --resolve clients.azimio.co.tz:443:127.0.0.1 \
    https://clients.azimio.co.tz/admin/login || echo 000)"

if [ "$CODE" != "200" ]; then
    echo "!! smoke test returned $CODE — rolling back"
    if [ -n "$PREVIOUS" ] && [ -d "$PREVIOUS" ]; then
        ln -sfn "$PREVIOUS" "$APP_DIR/current"
        systemctl reload php8.4-fpm
        systemctl restart clients-queue
        echo "!! rolled back to $PREVIOUS"
    fi
    exit 1
fi

echo "==> pruning old releases (keeping $KEEP)"
cd "$APP_DIR/releases"
ls -1dt */ | tail -n +$((KEEP + 1)) | xargs -r rm -rf

rm -f "$TARBALL"
echo "==> deployed $REL (HTTP $CODE)"
