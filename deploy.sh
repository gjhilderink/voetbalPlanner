#!/usr/bin/env bash
# Run this after every deploy to production (cPanel shared hosting)
set -e

echo "=== VoetbalPlanner Deploy ==="

echo "1. Installing dependencies..."
composer install --no-dev --optimize-autoloader

echo "2. Running migrations..."
php artisan migrate --force

echo "2b. Seeding documentation..."
php artisan db:seed --class=DocumentationSeeder --force

echo "2c. Seeding release notes..."
php artisan db:seed --class=ReleaseNotesSeeder --force

echo "2d. Seeding onboarding slides (per club, alleen als leeg)..."
php artisan db:seed --class=OnboardingSlidesSeeder --force

echo "3. Publishing Filament assets..."
php artisan filament:assets

echo "4. Clearing and warming caches..."
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache

echo "5. Restarting queue workers..."
php artisan queue:restart

echo "=== Deploy complete ==="
