#!/bin/sh
set -e

echo "entrypoint: arrancando con argumentos: $*"

if [ "$1" = "web" ]; then
    export PORT="${PORT:-10000}"
    echo "entrypoint: generando config de nginx para el puerto $PORT"
    envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

    echo "entrypoint: cacheando config/rutas/vistas"
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    # El plan free de Render no soporta preDeployCommand, así que migrate/seed
    # corren acá en cada arranque. Ambos son idempotentes (Laravel trackea qué
    # migró; los seeders usan firstOrCreate), así que repetirlos no rompe nada.
    echo "entrypoint: corriendo migraciones y seeders"
    php artisan migrate --force
    php artisan db:seed --force

    echo "entrypoint: levantando nginx + php-fpm"
    exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi

# Cron / one-off commands (p.ej. "php artisan schedule:run") entran por acá,
# sin levantar nginx/php-fpm.
echo "entrypoint: ejecutando comando directo: $*"
exec "$@"
