FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY public ./public
COPY vite.config.js postcss.config.js tailwind.config.js ./
RUN npm run build

FROM serversideup/php:8.3-fpm-nginx
USER root
RUN install-php-extensions gd
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader
COPY --chown=www-data:www-data . .
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache     && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-dev --optimize --no-interaction     && chown -R www-data:www-data storage bootstrap/cache
ENV APP_ENV=production     APP_DEBUG=false     AUTORUN_ENABLED=true     PHP_OPCACHE_ENABLE=1     SSL_MODE=off     NGINX_LISTEN_PORT=8080
USER www-data
EXPOSE 8080
