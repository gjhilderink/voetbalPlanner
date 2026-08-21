#!/usr/bin/env bash
# Run this after every deploy to production (cPanel shared hosting)
set -e

echo "=== VoetbalPlanner Deploy ==="

# Met APP_DEBUG=true krijgt iedere bezoeker die een fout uitlokt de volledige
# stacktrace te zien, inclusief omgevingsvariabelen. Stop de deploy liever dan
# dat dit ongemerkt blijft staan. Alleen overrulen als je weet waarom:
#   ALLOW_DEBUG=1 ./deploy.sh
if [ -f .env ]; then
  if grep -qE '^APP_DEBUG=(true|1)' .env && [ "${ALLOW_DEBUG:-}" != "1" ]; then
    echo "STOP: APP_DEBUG staat op true in .env." >&2
    echo "      Zet APP_DEBUG=false en APP_ENV=production, of draai met ALLOW_DEBUG=1." >&2
    exit 1
  fi
  if ! grep -qE '^APP_ENV=production' .env; then
    echo "LET OP: APP_ENV staat niet op production in .env." >&2
  fi
fi

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

echo "2e. Seeding agenda-categorieën (per club, alleen als leeg)..."
php artisan db:seed --class=AgendaCategoriesSeeder --force

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
