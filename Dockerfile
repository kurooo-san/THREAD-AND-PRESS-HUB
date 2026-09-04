FROM php:8.2-cli

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy application files
COPY . /var/www/html/
WORKDIR /var/www/html

# Set permissions
RUN chmod -R 755 /var/www/html

EXPOSE 8080

CMD php -S 0.0.0.0:${PORT:-8080} -t /var/www/html
