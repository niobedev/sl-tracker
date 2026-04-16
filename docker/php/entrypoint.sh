#!/bin/sh
set -e

# Install dependencies if vendor is missing or incomplete
if [ ! -f vendor/autoload.php ]; then
    echo "Installing composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Run migrations (MySQL is already healthy per docker-compose depends_on)
echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

exec "$@"
