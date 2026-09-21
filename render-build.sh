#!/bin/bash
set -e

echo "==> Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

echo "==> Caching config, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Running database migrations..."
php artisan migrate --force

echo "==> Build complete!"
