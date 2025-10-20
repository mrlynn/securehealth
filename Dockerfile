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
    wget \
    && rm -rf /var/lib/apt/lists/*

# Note: crypt_shared library installation removed due to download URL issues
# MongoDB encryption will use mongocryptd fallback for queryable encryption

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
RUN mkdir -p var/cache var/log var/cache/sessions \
    && chmod -R 755 var \
    && chmod -R 755 public

# Expose port (Railway will set PORT environment variable)
EXPOSE $PORT

# Start PHP built-in server with router script to serve index.html by default
# Railway will override this with the startCommand from railway.json
CMD ["sh", "-c", "php -S 0.0.0.0:$PORT -t public public/router.php"]
