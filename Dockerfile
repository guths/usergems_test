FROM php:8.2-fpm as base 
RUN cp ${PHP_INI_DIR}/php.ini-production ${PHP_INI_DIR}/php.ini  
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    libicu-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libxml2-dev\
    libonig-dev \
    vim \
    unzip \
    git \
    openssl \
    curl \
    libzip-dev \
    libgd-dev
RUN apt-get clean && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install mbstring
RUN docker-php-ext-install curl
RUN docker-php-ext-install xml
RUN docker-php-ext-install zip
RUN docker-php-ext-install gd
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install bcmath
# RUN pecl install xdebug

FROM base as dev 
# RUN cp ${PHP_INI_DIR}/php.ini-development ${PHP_INI_DIR}/php.ini  
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug

FROM dev as ci 
WORKDIR /app
COPY . .
RUN composer install --prefer-dist --optimize-autoloader --no-scripts --no-interaction

FROM ci as production
WORKDIR /app
RUN composer install --prefer-dist --optimize-autoloader --no-scripts --no-interaction --no-dev
RUN touch .env
RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan migrate


FROM base AS shippment
WORKDIR /var/www/html
COPY --from=production --chown=www-data:www-data /app .

USER www-data
