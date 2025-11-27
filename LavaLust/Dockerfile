# Use official PHP image with Apache
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install PHP extensions required by Laravel/CodeIgniter
RUN docker-php-ext-install pdo_mysql mysqli

# Install dependencies
RUN composer install --optimize-autoloader --no-dev

# Configure Apache to serve from public/
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Expose port 80 (Render will map it to $PORT)
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]