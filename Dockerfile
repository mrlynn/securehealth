FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libssl-dev \
    pkg-config \
    libcrypto++-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath

# Install MongoDB PHP extension
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy all files first
COPY . .

# Install dependencies (skip scripts to avoid .env requirement)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Create necessary directories and set permissions
RUN mkdir -p var/cache var/log \
    && chmod -R 755 var \
    && chmod -R 755 public

# Expose port
EXPOSE 9000

# Start PHP built-in server with router script to serve index.html by default
CMD ["php", "-S", "0.0.0.0:9000", "-t", "public", "public/router.php"]