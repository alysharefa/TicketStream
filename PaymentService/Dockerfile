# =====================================================================
# Service 3 - Payment & Ticket Issuing (Laravel 12 + Lighthouse GraphQL + Worker)
# Base: PHP 8.3 CLI. Aplikasi dijalankan via `php artisan serve`.
# Container kedua (issuing_worker) menjalankan `php artisan queue:work`.
# =====================================================================
FROM php:8.3-cli

# Ekstensi yang dibutuhkan Laravel + RabbitMQ + MySQL + Lighthouse.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        zip \
        unzip \
        git \
    && docker-php-ext-install pdo_mysql pdo_pgsql zip bcmath opcache pcntl \
    && rm -rf /var/lib/apt/lists/*

# Composer (copy dari image resmi agar versi konsisten).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependensi lebih dulu (layer cache lebih baik).
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction || \
    composer install --no-interaction

# Copy seluruh kode aplikasi.
COPY . .

# Pastikan folder runtime ada & writable.
RUN mkdir -p storage/framework/{cache,sessions,views} \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# Startup: cache config, migrasi, lalu serve.
CMD ["sh", "-c", "php artisan config:cache && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
