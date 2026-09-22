#!/bin/sh
set -e

# Make sure storage/cache directories exist and are writable, even if a
# mounted volume reset ownership/permissions at runtime.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Clear the config cache first — this is safe to run before migrations
# since it only touches files on disk, not the database.
php artisan config:clear || echo "Warning: config:clear failed, continuing anyway."

# Run migrations BEFORE clearing the app cache. CACHE_STORE=database means
# cache:clear needs the "cache" table to exist first — running it earlier
# fails on a fresh database (Laravel reports this generically as a
# "permissions" error even though the real cause is a missing table).
php artisan migrate --force

# Now it's safe to clear the cache.
php artisan cache:clear || echo "Warning: cache:clear failed, continuing anyway."

# Run seeders
php artisan db:seed --force

# Start Laravel
exec php artisan serve --host=0.0.0.0 --port=$PORT