# =====================================================================
# Service 3 - Payment & Ticket Issuing (Laravel 12 + Lighthouse GraphQL + Worker)
# Base: PHP 8.3 CLI. Aplikasi dijalankan via `php artisan serve`.
# Container kedua (issuing_worker) menjalankan `php artisan queue:work`.
# =====================================================================
FROM php:8.3-cli

# Ekstensi yang dibutuhkan Laravel + RabbitMQ + MySQL + PostgreSQL + Lighthouse.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpq-dev \
        zip \
        unzip \
        git \
    && docker-php-ext-install pdo_mysql pdo_pgsql zip bcmath opcache pcntl sockets \
    && rm -rf /var/lib/apt/lists/*

# Composer (copy dari image resmi agar versi konsisten).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy seluruh kode aplikasi terlebih dahulu (agar artisan tersedia).
COPY . .

# Install dependensi. Gunakan --no-scripts dulu, lalu jalankan scripts setelahnya.
# Jika composer.lock ada dan sinkron, gunakan install. Jika tidak, fallback ke update.
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts 2>/dev/null \
    || composer update --no-dev --optimize-autoloader --no-interaction --no-scripts

# Jalankan post-install scripts sekarang (artisan sudah tersedia).
RUN composer dump-autoload --optimize 2>/dev/null || true

# Pastikan folder runtime ada & writable.
RUN mkdir -p storage/framework/{cache,sessions,views} \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# Startup: cache config, migrasi, lalu serve.
CMD ["sh", "-c", "cp -n .env.example .env 2>/dev/null; php artisan key:generate --force 2>/dev/null; php artisan config:cache && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
