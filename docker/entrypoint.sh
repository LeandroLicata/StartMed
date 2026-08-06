#!/bin/sh
set -e

if [ "$1" = "web" ]; then
    export PORT="${PORT:-10000}"
    envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi

# Cron / one-off commands (p.ej. "php artisan schedule:run") entran por acá,
# sin levantar nginx/php-fpm.
exec "$@"
