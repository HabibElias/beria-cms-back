FROM composer:2 AS composer
FROM php:8.3-apache-bookworm

RUN --mount=type=secret,id=build_ca \
    if [ -s /run/secrets/build_ca ]; then \
      cp /run/secrets/build_ca /usr/local/share/ca-certificates/build-proxy.crt; \
      update-ca-certificates; \
    fi
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update && apt-get install -y --no-install-recommends \
      libffi-dev libpq-dev unzip \
    && docker-php-ext-install -j2 ffi pdo_pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
WORKDIR /var/www/html
COPY . .
RUN chmod -R a+rX . \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/start.sh

ENV PORT=10000
EXPOSE 10000
CMD ["./docker/start.sh"]
