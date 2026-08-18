#!/bin/bash
set -e

# --- MariaDB bootstrap (data stored in /data/mysql) ---
DATADIR=/data/mysql
mkdir -p "$DATADIR"
chown -R mysql:mysql "$DATADIR" 2>/dev/null || true

if [ ! -d "$DATADIR/mysql" ]; then
    echo "Initializing MariaDB data directory..."
    mariadb-install-db --user=mysql --datadir="$DATADIR" --auth-root-authentication-method=normal > /tmp/install_db.log 2>&1 || mariadbd-install-db --user=mysql --datadir="$DATADIR" > /tmp/install_db.log 2>&1
    # Short-lived mysqld to run init script
    mysqld_safe --datadir="$DATADIR" --socket=/run/mysqld/mysqld.sock --port=3306 &
    MYSQLPID=$!
    for i in $(seq 1 30); do
        if mysqladmin ping -h127.0.0.1 -P3306 --silent 2>/dev/null; then break; fi
        sleep 1
    done
    echo "Running schema import..."
    mysql -h127.0.0.1 -P3306 -uroot -e "CREATE DATABASE IF NOT EXISTS \`$MYSQL_DATABASE\`; CREATE USER IF NOT EXISTS '$MYSQL_USER'@'%' IDENTIFIED BY '$MYSQL_PASSWORD'; CREATE USER IF NOT EXISTS '$MYSQL_USER'@'127.0.0.1' IDENTIFIED BY '$MYSQL_PASSWORD'; GRANT ALL PRIVILEGES ON \`$MYSQL_DATABASE\`.* TO '$MYSQL_USER'@'%'; GRANT ALL PRIVILEGES ON \`$MYSQL_DATABASE\`.* TO '$MYSQL_USER'@'127.0.0.1'; FLUSH PRIVILEGES;"
    SCHEMA=""
    [ -f /var/www/database/schema.sql ] && SCHEMA=/var/www/database/schema.sql
    [ -f /var/www/html/../database/schema.sql ] && SCHEMA=/var/www/html/../database/schema.sql
    if [ -n "$SCHEMA" ]; then
        mysql -h127.0.0.1 -P3306 -uroot "$MYSQL_DATABASE" < "$SCHEMA" && echo "Schema imported into $MYSQL_DATABASE"
        SEED="${SCHEMA%schema.sql}seed.sql"
        [ ! -f "$SEED" ] && [ -f /var/www/database/seed.sql ] && SEED=/var/www/database/seed.sql
        [ -f "$SEED" ] && mysql -h127.0.0.1 -P3306 -uroot "$MYSQL_DATABASE" < "$SEED" && echo "Seed imported"
    fi
    kill $MYSQLPID 2>/dev/null
    mysqladmin -h127.0.0.1 -P3306 -uroot shutdown 2>/dev/null
    sleep 3
    echo "MariaDB initialized."
fi

# Start MariaDB in background
echo "Starting MariaDB..."
mysqld_safe --datadir="$DATADIR" --socket=/run/mysqld/mysqld.sock --port=3306 &
for i in $(seq 1 30); do
    if mysqladmin ping -h127.0.0.1 -P3306 --silent 2>/dev/null; then break; fi
    sleep 1
done
echo "MariaDB ready."

# --- Write .env from environment variables ---
echo "Writing .env from environment..."
cat > /var/www/html/.env <<EOF
APP_ENV=production
APP_URL=${APP_URL:-http://localhost:8080}
CORS_ALLOWED_ORIGINS=${CORS_ALLOWED_ORIGINS:-*}
DB_HOST=${MYSQL_HOST:-127.0.0.1}
DB_PORT=${MYSQL_PORT:-3306}
DB_NAME=${MYSQL_DATABASE:-nova}
DB_USER=${MYSQL_USER:-nova_user}
DB_PASSWORD=${MYSQL_PASSWORD}
JWT_SECRET=${JWT_SECRET:-nova-dev-secret-key-2026-xyz}
OTP_BYPASS=${OTP_BYPASS:-123456}
OTP_PROVIDER=test
OTP_TEST_CODE=${OTP_TEST_CODE:-123456}
EOF
chmod 640 /var/www/html/.env

# --- Start Apache ---
echo "Starting Apache on port 8080..."
apache2-foreground
