FROM php:8.2-apache

# Install PostgreSQL client libraries, zip utilities, and PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Configure PHP settings for high-capacity (100MB) file uploads
RUN echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/custom-uploads.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/custom-uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/custom-uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom-uploads.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/custom-uploads.ini

# Set working directory
WORKDIR /var/www/html

# Copy application files into container
COPY . /var/www/html/

# Create uploads & logs directories and grant full permissions to Apache
RUN mkdir -p /var/www/html/uploads /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/uploads /var/www/html/logs

# Expose default HTTP port
EXPOSE 80

CMD ["apache2-foreground"]
