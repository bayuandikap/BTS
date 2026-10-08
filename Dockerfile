FROM php:8.3-fpm

# Install system dependencies required by PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    xml \
    zip \
    bcmath

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application source code
COPY . .

# Install PHP dependencies (no dev, optimise autoloader)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Ensure storage and cache directories are writable
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Make the entrypoint script executable
RUN chmod +x docker/entrypoint.sh

EXPOSE 8000

CMD ["bash", "docker/entrypoint.sh"]
