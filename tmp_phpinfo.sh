#!/bin/sh
echo '<?php phpinfo();' > /var/www/html/phpinfo.php
curl -s -m 8 'http://127.0.0.1:8080/phpinfo.php' | grep -o '<tr><td class="e">Loaded Configuration File </td><td class="v">[^<]*' | head -1
curl -s -m 8 'http://127.0.0.1:8080/phpinfo.php' | grep -o '<tr><td class="e">Additional \.ini files parsed </td><td class="v">[^<]*' | head -1
curl -s -m 8 'http://127.0.0.1:8080/phpinfo.php' | grep -o 'display_errors</td><td class="v"><i>[^<]*' | head -2
curl -s -m 8 'http://127.0.0.1:8080/phpinfo.php' | grep -o 'log_errors</td><td class="v"><i>[^<]*' | head -2
rm /var/www/html/phpinfo.php
