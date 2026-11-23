# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# Application image of the Docker Compose profile (S-0923). One image serves
# the roles web, worker, scheduler, migrate, backup and restore (see
# entrypoint.sh). Build from the repository root:
#   docker build -f deploy/docker/Dockerfile -t projektrack:local .

# PHP with the extensions of infrastructure profiles 1.1.
FROM php:8.4-fpm-alpine AS base

RUN set -eux; \
    apk add --no-cache icu-libs libzip libpng libjpeg-turbo libwebp freetype libpq; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
        icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev postgresql-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" intl bcmath zip gd exif opcache pdo_mysql pdo_pgsql; \
    pecl install redis-6.2.0; \
    docker-php-ext-enable redis; \
    apk del .build-deps; \
    rm -rf /tmp/pear ~/.pearrc; \
    php -m | grep -qi sodium

# Dependencies and assets. The asset build runs artisan (route types), so it
# needs PHP and Node in one stage; nothing of this stage but /app is kept.
FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:24-alpine /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-alpine /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN apk add --no-cache libstdc++ libgcc \
    && ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
# Set by the release workflow (S-0924); shown to administrators under About.
ARG PROJEKTRACK_VERSION=
# A throwaway key for the build only; the file is gone before the layer ends.
RUN if [ -n "${PROJEKTRACK_VERSION}" ]; then printf '%s\n' "${PROJEKTRACK_VERSION}" > VERSION; fi \
    && mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --optimize --no-dev --no-interaction \
    && cp .env.example .env \
    && php artisan key:generate --no-interaction \
    && npm run build \
    && rm -f .env \
    && rm -rf node_modules storage/logs/* bootstrap/cache/strings

# Runtime: Nginx and PHP-FPM as www-data, code owned by root and read-only.
FROM base AS runtime

RUN set -eux; \
    apk add --no-cache nginx bash tini tar mariadb-client age; \
    rm -rf /etc/nginx/http.d /var/www/localhost; \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY deploy/docker/php.ini "$PHP_INI_DIR/conf.d/zz-projektrack.ini"
COPY deploy/docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-projektrack.conf
COPY deploy/docker/nginx.conf /etc/nginx/nginx.conf
COPY --chmod=0755 deploy/docker/entrypoint.sh /usr/local/bin/projektrack-entrypoint
COPY --chmod=0755 deploy/docker/backup.sh /usr/local/bin/projektrack-backup
COPY --chmod=0755 deploy/docker/restore.sh /usr/local/bin/projektrack-restore
COPY --chmod=0755 deploy/docker/snapshot.sh /usr/local/bin/projektrack-snapshot

COPY --from=build /app /app

# The framework caches are written at start into a tmpfs over bootstrap/cache;
# the package manifests of the build are copied from cache.dist.
RUN set -eux; \
    mv /app/bootstrap/cache /app/bootstrap/cache.dist; \
    mkdir -m 0700 /app/bootstrap/cache; \
    chown www-data:www-data /app/bootstrap/cache; \
    rm -rf /app/storage; \
    mkdir -m 0750 /app/storage /backups; \
    mkdir -m 0700 /snapshot; \
    chown www-data:www-data /app/storage /backups /snapshot

WORKDIR /app
USER www-data
EXPOSE 8080
STOPSIGNAL SIGTERM

ENTRYPOINT ["/sbin/tini", "-g", "--", "projektrack-entrypoint"]
CMD ["web"]
