FROM php:8.4.14-cli-bookworm@sha256:8ee2296ac8fafec3f0a414430c4a6ae90fe9b9165b2eda1146e4e554d7b33daa

RUN apt-get update \
  && apt-get install -y --no-install-recommends git unzip libzip-dev libonig-dev \
  && docker-php-ext-install pdo_mysql mbstring \
  && rm -rf /var/lib/apt/lists/* \
  && printf 'opcache.enable=0\nopcache.enable_cli=0\n' > /usr/local/etc/php/conf.d/opcache-dev.ini

COPY --from=composer:2.8.8@sha256:3df281ca83119bff1332f0f5f311960cb16b99238f8273acd965be6bd508aa8e \
  /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY app/composer.json app/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader

COPY app/ ./
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
  && chmod -R ug+rwX storage bootstrap/cache \
  && composer dump-autoload --optimize \
  && php artisan package:discover --ansi \
  && if [ ! -f .env ]; then cp .env.example .env; fi \
  && php artisan key:generate --force

EXPOSE 3000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=3000"]
