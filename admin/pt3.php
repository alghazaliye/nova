<?php
require_once __DIR__ . '/includes/config.php';
echo "sapi=" . php_sapi_name() . "\n";
echo "post=" . json_encode($_POST) . "\n";
