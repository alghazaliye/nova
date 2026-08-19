# Nova Messenger — production image (PHP 8.3 + Apache + SQLite)
# SQLite is the default database adapter (DB_TYPE=sqlite in backend/.env).
FROM php:8.3-apache

# Apache modules
RUN a2enmod rewrite headers && \
    rm -rf /var/www/html

# PHP extensions: SQLite + image processing + zip
RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip sqlite3 libsqlite3-dev pkg-config \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_sqlite gd zip opcache \
    && rm -rf /var/lib/apt/lists/*

# Apache config
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# Application code
COPY backend/ /var/www/html/backend/
COPY admin/ /var/www/html/admin/
COPY web_app/ /var/www/html/web_app/
COPY database/schema.sqlite.sql /var/www/html/database/schema.sqlite.sql
COPY database/schema.sql /var/www/html/database/schema.sql
COPY database/seed.sql /var/www/html/database/seed.sql
COPY database/migrate_otp.sql /var/www/html/database/migrate_otp.sql
COPY database/migrate_auth.sql /var/www/html/database/migrate_auth.sql

# Writable directories (uploads, storage, sqlite db, logs)
RUN mkdir -p /var/www/html/backend/config \
             /var/www/html/backend/storage \
             /var/www/html/backend/uploads \
             /var/www/html/backend/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Runtime defaults — override with real values in Render environment variables.
# Never commit real secrets to Git.
ENV DB_TYPE=sqlite \
    JWT_SECRET=REPLACE_ME \
    APP_ENV=production \
    OTP_PROVIDER=smtp \
    ENCRYPTION_KEY=REPLACE_ME \
    GMAIL_SMTP_USERNAME=REPLACE_ME \
    GMAIL_SMTP_PASSWORD=REPLACE_ME

# Startup: create SQLite DB from schema if missing, bind Apache to $PORT (Render), then run apache2-foreground
RUN { echo '#!/bin/sh'; \
      echo 'set -e'; \
      echo 'DB_PATH="${DB_PATH:-/var/www/html/backend/config/nova.sqlite}"'; \
      echo 'if [ ! -s "$DB_PATH" ]; then'; \
      echo '  echo "Bootstrapping SQLite schema..."'; \
      echo '  sqlite3 "$DB_PATH" < /var/www/html/database/schema.sqlite.sql'; \
      echo '  chown www-data:www-data "$DB_PATH"'; \
      echo '  [ -f /var/www/html/database/seed.sql ] && (sqlite3 "$DB_PATH" < /var/www/html/database/seed.sql 2>/dev/null || true);'; \
      echo 'fi'; \
      echo 'PORT="${PORT:-8080}"'; \
      echo 'sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf'; \
      echo 'sed -i "s/VirtualHost \*:8080/VirtualHost *:${PORT}/" /etc/apache2/sites-enabled/*.conf'; \
      echo 'exec apache2-foreground'; } > /startup.sh && chmod +x /startup.sh

EXPOSE 8080
CMD ["/startup.sh"]
