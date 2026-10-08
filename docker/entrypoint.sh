#!/bin/bash
set -e

echo "==> Waiting for MySQL to be ready..."
until php -r "new PDO('mysql:host=${DB_HOST:-db};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-bts}', '${DB_USERNAME:-bts_user}', '${DB_PASSWORD:-secret}');" 2>/dev/null; do
    echo "    MySQL not ready yet — retrying in 2 s..."
    sleep 2
done
echo "==> MySQL is ready."

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Starting Laravel development server on 0.0.0.0:8000 ..."
php artisan serve --host=0.0.0.0 --port=8000
