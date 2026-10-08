#!/bin/bash
set -e

echo "Waiting for MySQL to be ready..."
until mysqladmin ping -h "${DB_HOST:-db}" -u "${DB_USERNAME:-bts_user}" -p"${DB_PASSWORD:-bts_password}" --silent 2>/dev/null; do
    echo "MySQL is not ready yet. Retrying in 2 seconds..."
    sleep 2
done
echo "MySQL is ready."

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

echo "Running migrations..."
php artisan migrate --force

echo "Generating Swagger docs..."
php artisan l5-swagger:generate || echo "Swagger generation skipped."

echo "Starting Laravel server on port 8000..."
exec php artisan serve --host=0.0.0.0 --port=8000
