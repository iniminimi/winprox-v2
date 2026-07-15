#!/usr/bin/env bash
# WinProx productie-deploy — geen .git/packs op de server.
# Gebruik: ./pull-deploy.sh   (vanuit ~/httpdocs/winprox)
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_DIR"

REPO_URL="${WINPROX_REPO_URL:-https://github.com/iniminimi/winprox-v2.git}"
BRANCH="${WINPROX_DEPLOY_BRANCH:-main}"
CHECKOUT="$(mktemp -d)"

cleanup() {
    rm -rf "$CHECKOUT"
}
trap cleanup EXIT

echo "==> Fetch ${BRANCH} (depth 1, geen packs op server)"
git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$CHECKOUT"

echo "==> Sync code (behoud .env, storage, mysql-data)"
rsync -a --delete \
    --exclude '.env' \
    --exclude 'storage/' \
    --exclude 'mysql-data/' \
    --exclude 'node_modules/' \
    --exclude '.git/' \
    "$CHECKOUT/" "$APP_DIR/"

if [[ -f composer.lock ]]; then
    echo "==> composer install"
    composer install --no-dev --optimize-autoloader --no-interaction
fi

echo "==> Laravel"
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Oude volledige clone opruimen (eenmalig effect + geen packs meer)
if [[ -d .git ]]; then
    echo "==> Verwijder .git (packs)"
    rm -rf .git
fi

echo "==> Klaar."
