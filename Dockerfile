# Stage 1: Build Vue assets
FROM node:20-alpine AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: PHP Application
FROM php:8.3-fpm-alpine
WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache nginx libpng-dev libxml2-dev zip unzip git

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql bcmath gd

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
