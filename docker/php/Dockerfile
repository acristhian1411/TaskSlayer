FROM php:8.3-apache

ARG LOCAL_UID=1000
ARG LOCAL_GID=1000

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    zip \
    curl \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip \
    && a2enmod rewrite \
    && groupmod -o -g ${LOCAL_GID} www-data \
    && usermod -o -u ${LOCAL_UID} -g ${LOCAL_GID} www-data \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
