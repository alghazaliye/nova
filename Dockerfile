# Nova Messenger — production image (PHP 8.3 + MariaDB + Apache)
FROM php:8.3-apache

# Apache modules
RUN a2enmod rewrite headers && \
    rm -rf /var/www/html

# PHP extensions for MariaDB + image processing
RUN apt-get update && apt-get install -y --no-install-recommends \
        libmariadb-dev-compat libmariadb-dev unzip mariadb-server \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mysqli gd zip opcache \
    && rm -rf /var/lib/apt/lists/*

# Server config: avoid reverse-DNS matching (host '127.0.0.1' != 'localhost')
COPY docker/99-nova.cnf /etc/mysql/mariadb.conf.d/99-nova.cnf

# Apache config
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# Application code
COPY backend/ /var/www/html/
COPY admin/ /var/www/admin/
COPY web_app/ /var/www/html/public/web_app/
COPY database/schema.sql /var/www/database/schema.sql
COPY database/seed.sql /var/www/database/seed.sql

# Permissions
RUN chown -R www-data:www-data /var/www/html /var/www/admin \
    && chmod -R 755 /var/www/html /var/www/admin \
    && mkdir -p /var/www/html/storage /var/www/html/uploads /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/uploads /var/www/html/logs

# Startup script that boots MariaDB then Apache
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
