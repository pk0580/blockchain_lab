FROM php:8.4-fpm-alpine

ARG UID=1000
ARG GID=1000

# Use explicit apk update + add (not --no-cache) so package triggers can read
# the index. Final cleanup of /var/cache/apk happens after triggers finish.
RUN set -eux \
    && apk update \
    && apk add --virtual .build-deps \
        autoconf \
        g++ \
        make \
        linux-headers \
    && apk add \
        bash \
        git \
        curl \
        icu-dev \
        libpq-dev \
        libzip-dev \
        gmp-dev \
        oniguruma-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gmp \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        sockets \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# Convenience CLIs in a second RUN so a trigger failure here does not invalidate
# the long extension-build cache layer above.
RUN set -eux \
    && apk update \
    && apk add postgresql-client redis \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN addgroup -g "${GID}" app \
    && adduser -D -u "${UID}" -G app -s /bin/bash app \
    && mkdir -p /home/app/.composer /var/www/html \
    && chown -R app:app /home/app /var/www/html

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER app
WORKDIR /var/www/html

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
