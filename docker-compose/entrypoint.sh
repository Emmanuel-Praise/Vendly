#!/bin/sh

# Exit on error
set -e

# Wait for database if needed
if [ "$DB_CONNECTION" = "mysql" ]; then
    echo "Waiting for mysql..."
    while ! nc -z $DB_HOST $DB_PORT; do
      sleep 1
    done
    echo "MySQL started"
fi

# Link storage
php artisan storage:link --force

# Generate key if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Run migrations
php artisan migrate --force

# Optimize for production
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Execute CMD
exec "$@"
