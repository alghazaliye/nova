#!/bin/bash
# يولّد hash "password123" عبر PHP ويحدّث users في container nova512
set -e
HASH=$(php -r 'echo password_hash("password123", PASSWORD_BCRYPT);')
sudo docker exec nova512 mysql -h127.0.0.1 -unova_user -prender2026 nova -e "UPDATE users SET password_hash='$HASH', username='testuser2' WHERE email='test2@example.com';" 2>/dev/null
sudo docker exec nova512 mysql -h127.0.0.1 -unova_user -prender2026 nova -e "SELECT id,email,username,password_hash IS NOT NULL AS has_pw FROM users WHERE email='test2@example.com';" 2>/dev/null
echo "OK"
