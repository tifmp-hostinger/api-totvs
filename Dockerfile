FROM php:8.3-apache

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git unzip curl \
        libxml2-dev \
        libcurl4-openssl-dev; \
    docker-php-ext-install -j"$(nproc)" curl soap; \
    rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite headers

WORKDIR /var/www/html

# COPIA CORRETA
COPY www/api/ /var/www/html/

# instala dependências (evita erro de vendor)
RUN composer install --no-dev --optimize-autoloader

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf

RUN printf "\n<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n" >> /etc/apache2/apache2.conf