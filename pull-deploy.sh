#!/usr/bin/env bash
# WinProx productie-deploy — geen .git in app-dir; cache buiten httpdocs.
# Gebruik: ./pull-deploy.sh   (vanuit ~/httpdocs/winprox)
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_DIR"

REPO_URL="${WINPROX_REPO_URL:-https://github.com/iniminimi/winprox-v2.git}"
BRANCH="${WINPROX_DEPLOY_BRANCH:-main}"
CACHE_DIR="${WINPROX_DEPLOY_CACHE:-$HOME/deploy-cache/winprox}"
LOCK_HASH_FILE="$CACHE_DIR/.composer-lock-sha256"
COMPOSER="$(command -v composer)"

echo "==> Fetch ${BRANCH}"
mkdir -p "$(dirname "$CACHE_DIR")"
if [[ -d "$CACHE_DIR/.git" ]]; then
    git -C "$CACHE_DIR" fetch --depth 1 origin "$BRANCH"
    git -C "$CACHE_DIR" reset --hard "origin/$BRANCH"
    git -C "$CACHE_DIR" clean -fdx
else
    rm -rf "$CACHE_DIR"
    git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$CACHE_DIR"
fi

echo "==> Sync code (behoud .env, storage, mysql-data, vendor)"
rsync -a --delete \
    --exclude '.env' \
    --exclude 'storage/' \
    --exclude 'mysql-data/' \
    --exclude 'node_modules/' \
    --exclude 'vendor/' \
    --exclude '.git/' \
    "$CACHE_DIR/" "$APP_DIR/"

if [[ -f composer.lock ]] && [[ -n "$COMPOSER" ]]; then
    LOCK_HASH="$(sha256sum composer.lock | awk '{print $1}')"
    if [[ ! -d vendor ]] || [[ ! -f "$LOCK_HASH_FILE" ]] || [[ "$(cat "$LOCK_HASH_FILE")" != "$LOCK_HASH" ]]; then
        echo "==> composer install (lock gewijzigd of vendor ontbreekt)"
        "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction
        echo "$LOCK_HASH" > "$LOCK_HASH_FILE"
    else
        echo "==> composer overgeslagen (lock ongewijzigd)"
    fi
fi

echo "==> Laravel"
php artisan migrate --force
php artisan schedule:clear-cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [[ -d .git ]]; then
    echo "==> Verwijder .git uit app-dir"
    rm -rf .git
fi

echo "==> Klaar."
