FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-ansi --no-progress
COPY . .
RUN composer dump-autoload --optimize

FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx bash curl tzdata icu-dev oniguruma-dev libzip-dev \
 && docker-php-ext-install intl pdo pdo_mysql opcache

RUN sed -i 's|^;clear_env = no|clear_env = no|' /usr/local/etc/php-fpm.d/www.conf

COPY conf/nginx/default.conf /etc/nginx/http.d/default.conf

WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN addgroup -S app && adduser -S app -G app \
 && chown -R app:app /app \
 && mkdir -p /run/nginx

COPY scripts/start.sh /start.sh
RUN chmod +x /start.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    PORT=8080

EXPOSE 8080
CMD ["/start.sh"]
