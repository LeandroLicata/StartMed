#!/bin/sh
set -e

echo "entrypoint: arrancando con argumentos: $*"

if [ "$1" = "web" ]; then
    export PORT="${PORT:-10000}"
    echo "entrypoint: generando config de nginx para el puerto $PORT"
    envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

    # Los Secret Files de Render quedan montados de solo lectura y no sabemos
    # con qué permisos; php-fpm atiende los requests como www-data, no como
    # root. Copiamos el CA a un lugar que sí le pertenece antes de cachear la
    # config, así el valor cacheado ya apunta a la copia legible.
    if [ -n "$MYSQL_ATTR_SSL_CA" ] && [ -f "$MYSQL_ATTR_SSL_CA" ]; then
        echo "entrypoint: copiando el CA cert a una ruta legible por www-data"
        cp "$MYSQL_ATTR_SSL_CA" /tmp/mysql-ca.pem
        chmod 644 /tmp/mysql-ca.pem
        export MYSQL_ATTR_SSL_CA=/tmp/mysql-ca.pem
    fi

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
