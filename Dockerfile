FROM php:8.2-fpm

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
    libcrypto++-dev

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath

# Install MongoDB PHP extension
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Configure non-root user
RUN groupadd -g 1000 www && \
    useradd -u 1000 -g www -m www

# Change ownership
RUN chown -R www:www /var/www

# Switch to non-root user
USER www

# Expose port 9000 (PHP-FPM)
EXPOSE 9000

CMD ["php-fpm"]