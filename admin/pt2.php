<?php
echo "sapi=" . php_sapi_name() . "\n";
echo "auto_prepend=" . ini_get('auto_prepend_file') . "\n";
echo "auto_append=" . ini_get('auto_append_file') . "\n";
echo "post=" . json_encode($_POST) . "\n";
