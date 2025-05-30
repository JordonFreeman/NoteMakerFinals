FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory contents
COPY . /var/www/html/

# Make upload directory writable
RUN mkdir -p /var/www/html/uploads/avatars
RUN chmod -R 777 /var/www/html/uploads

# Run composer install
RUN composer install --no-interaction --no-dev --prefer-dist

# Configure Apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

# Expose port 80
EXPOSE 80

# Start Apache service
CMD ["apache2-foreground"]