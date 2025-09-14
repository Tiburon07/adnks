FROM php:8.2-fpm

# Installa le dipendenze di sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Installa le estensioni PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Imposta la directory di lavoro
WORKDIR /var/www/html

# Cambia il proprietario dei file
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

USER www-data

EXPOSE 9000

CMD ["php-fpm"]