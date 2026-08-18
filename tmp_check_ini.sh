#!/bin/sh
curl -s -m 8 'http://localhost:8082/api/v1/phpinfo' -o /tmp/pi2.html
grep -o 'display_errors</td><td class="v">[^<]*' /tmp/pi2.html | head -1
grep -o 'log_errors</td><td class="v">[^<]*' /tmp/pi2.html | head -1
grep -o 'error_log</td><td class="v">[^<]*' /tmp/pi2.html | head -1
echo "---"
curl -s -m 15 -X POST http://localhost:8082/api/v1/auth/verify-email-otp -H 'Content-Type: application/json' -d '{"email":"docktest@example.com","otp":"123456"}' -o /tmp/ver2.json -w 'verify HTTP:%{http_code} size:%{size_download}\n'
head -c 300 /tmp/ver2.json; echo
echo "---php_errors.log---"
cat /tmp/php_errors.log 2>/dev/null | tail -10
