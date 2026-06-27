#!/bin/sh
set -e

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-capstone}"
DB_USER="${DB_USER:-capstone}"
DB_PASS="${DB_PASS:-capstone_secret}"

echo "-> Waiting for database at ${DB_HOST}:${DB_PORT}..."
max_tries=30
try=0
until php -r "
    \$pdo = new PDO(
        'mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_NAME}',
        '${DB_USER}',
        '${DB_PASS}',
        [PDO::ATTR_TIMEOUT => 2]
    );
" 2>/dev/null; do
    try=$((try + 1))
    if [ "$try" -ge "$max_tries" ]; then
        echo "X Database not reachable after ${max_tries} attempts - exiting"
        exit 1
    fi
    echo "  (attempt $try/$max_tries)..."
    sleep 2
done
echo "V Database ready"

exec frankenphp run --config /etc/caddy/Caddyfile
