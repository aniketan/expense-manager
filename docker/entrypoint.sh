#!/bin/sh
# Prepares .env, the SQLite database, and migrations, then starts Apache.
# Everything that must survive restarts (database, app key) lives in /data.
set -e

cd /var/www/html
DATA_DIR=/data
DB="$DATA_DIR/database.sqlite"
KEY_FILE="$DATA_DIR/app_key"

mkdir -p "$DATA_DIR"
[ -f "$DB" ] || touch "$DB"
[ -f "$KEY_FILE" ] || php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$KEY_FILE"

if [ -z "${APP_LOGIN_PASSWORD:-}" ]; then
    echo "WARNING: APP_LOGIN_PASSWORD is not set. The login page will refuse every password." >&2
    PASSWORD_LINE="APP_LOGIN_PASSWORD="
else
    # Store a bcrypt hash; single quotes stop dotenv from expanding its "$" segments.
    PASSWORD_LINE="APP_LOGIN_PASSWORD='$(php -r 'echo password_hash(getenv("APP_LOGIN_PASSWORD"), PASSWORD_BCRYPT);')'"
fi

cat > .env <<EOF
APP_NAME="Expense Manager"
APP_ENV=production
APP_DEBUG=${APP_DEBUG:-false}
APP_KEY=$(cat "$KEY_FILE")
APP_URL=${APP_URL:-http://localhost:8080}
$PASSWORD_LINE

LOG_CHANNEL=stderr
LOG_LEVEL=${LOG_LEVEL:-info}

DB_CONNECTION=sqlite
DB_DATABASE=$DB

SESSION_DRIVER=database
SESSION_LIFETIME=${SESSION_LIFETIME:-120}
CACHE_STORE=database
QUEUE_CONNECTION=sync

AI_PROVIDER=${AI_PROVIDER:-ollama}
AI_MODEL=${AI_MODEL:-qwen3.5:9b}
OLLAMA_URL=${OLLAMA_URL:-http://host.docker.internal:11434}
ANTHROPIC_API_KEY=${ANTHROPIC_API_KEY:-}
EOF

php artisan config:clear -q
php artisan migrate --force

# First start only: load the default category tree. Existing data is never reseeded.
if [ "$(php artisan tinker --execute='echo \App\Models\Category::count();' 2>/dev/null | tail -n1)" = "0" ]; then
    php artisan db:seed --force
fi

chown -R www-data:www-data "$DATA_DIR" storage bootstrap/cache

exec "$@"
