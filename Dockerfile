# ── FreshMart – PHP Application Image ──────────────────────────
# Multi-stage: builder installs deps, final image is lean
FROM php:8.2-apache AS base

# Install system deps + PHP extensions
RUN apt-get update && apt-get install -y \
        libpng-dev libjpeg-dev libwebp-dev \
        zip unzip curl git \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql gd \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application source
COPY . .

# Create uploads directory with correct permissions
RUN mkdir -p uploads/products \
    && chown -R www-data:www-data uploads \
    && chmod -R 755 uploads

# Copy custom Apache vhost
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Use environment-based PHP config
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

EXPOSE 80

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/ || exit 1
