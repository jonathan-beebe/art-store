FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# gd is what lets tests build real image bytes with UploadedFile::fake()->image(),
# which the listing upload rule needs to verify content rather than the declared
# mimetype.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl pdo_sqlite zip

# pcov defaults to instrumenting every request, so it is disabled here and
# turned back on with -d only for the composer scripts that collect coverage
# — see docker/pcov.ini.
RUN pecl install pcov && docker-php-ext-enable pcov
COPY docker/pcov.ini /usr/local/etc/php/conf.d/zz-pcov.ini

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/src

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
