#!/bin/sh
set -e

# First boot on a fresh clone: install dependencies into the bind-mounted project.
if [ ! -f vendor/autoload_runtime.php ]; then
    composer install --no-interaction --prefer-dist
fi

exec "$@"
