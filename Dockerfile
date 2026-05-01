# Vendor deps for Vite (Ziggy imports from vendor/tightenco/ziggy)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist

# Stage 1: Build Vue assets
FROM node:20-alpine AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# Stage 2: PHP Application
FROM php:8.3-fpm-alpine
WORKDIR /var/www/html

# Install system dependencies (postgresql-dev required for pdo_pgsql)
RUN apk add --no-cache nginx libpng-dev libxml2-dev postgresql-dev zip unzip git

# Install PHP extensions — default app DB is pgsql per config/database.php
RUN docker-php-ext-install pdo_mysql pdo_pgsql bcmath gd

# Copy application files
COPY . .
COPY --from=frontend-builder /app/public/build ./public/build

# Install Composer dependencies
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

# Copy Nginx config (you should have a simple nginx.conf in your project)
COPY .docker/nginx.conf /etc/nginx/http.d/default.conf

EXPOSE 80
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
