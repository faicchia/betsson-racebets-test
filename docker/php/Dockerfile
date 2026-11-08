FROM php:8.5-fpm

# Match the host user so files written to the bind mount (var/, vendor/) stay editable on Linux hosts.
ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev \
    && docker-php-ext-install pdo_mysql bcmath intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN echo 'date.timezone = UTC' > "$PHP_INI_DIR/conf.d/timezone.ini"

RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g www-data www-data

COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

WORKDIR /var/www/app
USER www-data

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
