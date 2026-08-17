#!/bin/bash
# اختبار صفحة admin مع أخطاء ظاهرة
SID=$(grep PHPSESSID /tmp/admincookies.txt | awk '{print $NF}')
cd /home/ubuntu/nova_new
SID=$SID php -d display_errors=1 errwrap.php "admin/$1.php" > "/tmp/admin_test_${1}.out" 2>&1
echo "=== $1 ==="
grep -E "ERR\[|FATAL" "/tmp/admin_test_${1}.out" | head -5 || echo "NO PHP ERRORS"
echo "size: $(wc -c < "/tmp/admin_test_${1}.out")"
