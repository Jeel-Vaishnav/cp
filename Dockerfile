FROM php:8.2-apache

# Install system dependencies & PDO MySQL extension
RUN apt-get update && apt-get install -y ca-certificates && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite & headers
RUN a2enmod rewrite headers

# Copy application files
COPY . /var/www/html/

# Ensure correct permissions for upload folder and web files
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads

# Configure Apache port (Render/Cloud uses $PORT or default 80)
ENV PORT 80
EXPOSE 80

CMD sed -i "s/80/$PORT/g" /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf && apache2-foreground
