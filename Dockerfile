# Use official PHP image with Apache
FROM php:8.2-apache

# Install system dependencies + GD dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zlib1g-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    pkg-config \
    default-libmysqlclient-dev \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Copy the entire LavaLust folder into the container
COPY LavaLust/ /var/www/html/LavaLust/

# Set working directory to where composer.json is
WORKDIR /var/www/html/LavaLust/app

# Install PHP extensions required by phpoffice/phpspreadsheet
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo_mysql

# Install dependencies
RUN composer install --optimize-autoloader --no-dev

# Configure Apache to serve from the project root and set ServerName
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/LavaLust|g' /etc/apache2/sites-available/000-default.conf \
    && printf "<Directory /var/www/html/LavaLust>\n    AllowOverride All\n</Directory>\n" > /etc/apache2/conf-available/lavalust-root.conf \
    && a2enconf lavalust-root.conf \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername.conf \
    && a2enmod rewrite

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]