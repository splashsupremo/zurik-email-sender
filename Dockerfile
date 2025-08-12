FROM php:8.2-cli

# Install required PHP extensions
RUN apt-get update && apt-get install -y unzip libzip-dev && docker-php-ext-install zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy app files
WORKDIR /app
COPY . /app

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port for Render
EXPOSE 10000

# Start the PHP server
CMD ["php", "-S", "0.0.0.0:10000", "-t", "."]
