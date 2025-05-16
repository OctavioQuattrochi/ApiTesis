FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    unzip \
    curl \
    git \
    libzip-dev \
    zip \
    libonig-dev \
    libxml2-dev \
    libpng-dev \
    && docker-php-ext-install pdo_mysql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
