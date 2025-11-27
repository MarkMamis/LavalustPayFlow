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

# Copy the entire LavaLust folder into the container
COPY LavaLust/ /var/www/html/LavaLust/

# Set working directory to where composer.json is
WORKDIR /var/www/html/LavaLust/app

# Install dependencies (this will create vendor/ here)
RUN composer install --optimize-autoloader --no-dev

# Configure Apache to serve from LavaLust/public
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/LavaLust/public|g' /etc/apache2/sites-available/000-default.conf

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]