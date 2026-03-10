#!/bin/sh
set -e

echo "⏳ Esperando a MySQL..."
until nc -z mysql 3306; do
  sleep 1
done
echo "✅ MySQL listo"

echo "🔄 Ejecutando migraciones..."
php artisan migrate --force

echo "⚡ Optimizando..."
php artisan config:cache
php artisan route:cache
php artisan event:cache

echo "🔗 Storage link..."
php artisan storage:link 2>/dev/null || true

echo "🚀 Iniciando PHP-FPM..."
exec php-fpm
