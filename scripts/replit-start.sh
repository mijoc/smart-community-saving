#!/usr/bin/env bash

# Portable Replit bootstrap for the Laravel VSLA application.
# It is intentionally safe to run on every workflow restart:
# existing databases are migrated but never reseeded or replaced.

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# Keep all paths relative to this copy of the project. Replit may place an
# uploaded project in a different workspace on another account.
export PATH="$ROOT_DIR/vendor/bin:$PATH"

export APP_ENV="${APP_ENV:-local}"
export APP_DEBUG="${APP_DEBUG:-true}"
export APP_URL="${APP_URL:-http://localhost:5000}"

# This project is designed to run without an external database or cache.
# Set these here as well as in .replit so a copied/uploaded project still
# works when Replit user environment variables were not copied with it.
export DB_CONNECTION="sqlite"
export DB_DATABASE="${DB_DATABASE:-$ROOT_DIR/database/database.sqlite}"
export SESSION_DRIVER="file"
export CACHE_STORE="file"
export QUEUE_CONNECTION="sync"
export SESSION_SECURE_COOKIE="${SESSION_SECURE_COOKIE:-false}"
export SESSION_SAME_SITE="${SESSION_SAME_SITE:-lax}"

mkdir -p \
    "$ROOT_DIR/database" \
    "$ROOT_DIR/storage/app/private" \
    "$ROOT_DIR/storage/app/public" \
    "$ROOT_DIR/storage/framework/cache/data" \
    "$ROOT_DIR/storage/framework/sessions" \
    "$ROOT_DIR/storage/framework/testing" \
    "$ROOT_DIR/storage/framework/views" \
    "$ROOT_DIR/storage/logs" \
    "$ROOT_DIR/bootstrap/cache"

# A copied Laravel project may not include vendor/ because it is normally
# ignored. Install the locked dependencies before the first artisan command.
if [[ ! -f "$ROOT_DIR/vendor/autoload.php" ]]; then
    command -v composer >/dev/null 2>&1 || {
        echo "Composer is required but was not found in the Replit PHP environment." >&2
        exit 1
    }
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
fi

# Clear stale cached configuration before any Artisan command that touches the
# database. A copied project can contain config cached for another account,
# including a different database driver or absolute path.
php artisan config:clear >/dev/null
php artisan route:clear >/dev/null
php artisan view:clear >/dev/null

# Create a local .env from the portable template if the upload did not include
# one. APP_KEY is generated only when it is missing; it never changes an
# existing application's key or invalidates existing sessions.
if [[ ! -f "$ROOT_DIR/.env" ]]; then
    cp "$ROOT_DIR/.env.example" "$ROOT_DIR/.env"
fi

if ! grep -q '^APP_KEY=base64:' "$ROOT_DIR/.env"; then
    php artisan key:generate --force --no-interaction
fi

# Relative SQLite paths are portable between Replit accounts. Export the
# absolute path after .env exists so this process always uses this workspace.
export DB_DATABASE="$ROOT_DIR/database/database.sqlite"
touch "$DB_DATABASE"

# Apply schema changes on every start. This is non-destructive for existing
# data and makes a fresh upload usable without a manual migration command.
php artisan migrate --force --no-interaction

# Seed only when the database has no users. DemoSeeder is idempotent in many
# places, but rerunning it on every restart would still mutate demo data.
USER_COUNT="$(php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -n 1 | tr -d '[:space:]')"
if [[ "$USER_COUNT" == "0" ]]; then
    php artisan db:seed --force --no-interaction
fi

# Make uploaded avatars/documents available through /storage. Recreate stale
# absolute symlinks when this project is copied to another Replit account.
STORAGE_LINK="$ROOT_DIR/public/storage"
if [[ -L "$STORAGE_LINK" ]]; then
    if [[ "$(readlink "$STORAGE_LINK")" != "$ROOT_DIR/storage/app/public" ]]; then
        rm -f "$STORAGE_LINK"
    fi
fi

# If an empty directory was copied in place of the symlink, replace it.
if [[ -d "$ROOT_DIR/public/storage" && ! -L "$ROOT_DIR/public/storage" ]]; then
    if [[ -z "$(find "$ROOT_DIR/public/storage" -mindepth 1 -print -quit 2>/dev/null)" ]]; then
        rmdir "$ROOT_DIR/public/storage"
    fi
fi
if ! php artisan storage:link --quiet 2>/dev/null; then
    echo "Warning: could not create the public storage link; uploaded files may not display." >&2
fi

php -d variables_order=EGPCS artisan schedule:work &
SCHEDULER_PID=$!
trap 'kill "$SCHEDULER_PID" 2>/dev/null || true' EXIT INT TERM

PHP_CLI_SERVER_WORKERS=4 \
php -d variables_order=EGPCS \
    -d upload_max_filesize=5M \
    -d post_max_size=12M \
    -S 0.0.0.0:5000 \
    -t public public/index.php