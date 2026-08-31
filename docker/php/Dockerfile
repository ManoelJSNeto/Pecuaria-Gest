FROM php:8.3-fpm-alpine

# Instala dependencias e extensoes PHP necessarias
RUN apk add --no-cache \
        libpq-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        libzip-dev \
        oniguruma-dev \
        exiftool \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        mbstring \
        gd \
        zip \
        exif \
    && rm -rf /var/cache/apk/*

# Permite que variaveis de ambiente do docker-compose cheguem ao PHP-FPM
RUN sed -i 's/^;clear_env = no/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# Copia o codigo da aplicacao
COPY public/ ./public/
COPY src/ ./src/

# Cria diretorios persistentes e ajusta permissoes
RUN mkdir -p /var/www/html/storage/data /var/www/html/storage/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage

EXPOSE 9000

CMD ["php-fpm"]
