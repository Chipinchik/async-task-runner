#!/bin/sh
set -eu
if [ "$1" = "app" ]; then
    php artisan view:clear
    php artisan migrate --force
    php artisan rabbitmq:queue-declare "${RABBITMQ_QUEUE:-taskflow}"
    exec php artisan serve --host=0.0.0.0 --port=8000
elif [ "$1" = "worker" ]; then
    exec php artisan rabbitmq:consume rabbitmq --queue="${RABBITMQ_QUEUE:-taskflow}" --prefetch-count=1 --sleep=1 --timeout=60 --tries=0 --memory=192
elif [ "$1" = "publisher" ]; then
    exec php artisan taskflow:publish
fi
exec "$@"
