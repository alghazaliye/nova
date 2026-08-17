<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_error_handler(function($errno,$errstr,$errfile,$errline){
    error_log("ERR[$errno] $errstr in $errfile:$errline");
    echo "\n<!-- ERR[$errno] $errstr in $errfile:$errline -->";
    return true;
});
register_shutdown_function(function(){
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR,E_CORE_ERROR,E_COMPILE_ERROR,E_PARSE])) {
        error_log("FATAL: {$e['message']} in {$e['file']}:{$e['line']}");
        echo "\n<!-- FATAL: {$e['message']} in {$e['file']}:{$e['line']} -->";
    }
});
session_id($_COOKIE['PHPSESSID'] ?? '');
$_SERVER['REQUEST_URI'] = $_SERVER['argv'][1];
require '/home/ubuntu/nova_new/admin/includes/config.php';
$files = ['/home/ubuntu/nova_new/admin/includes/auth.php'];
// check what login.php includes then require page
require $_SERVER['argv'][1];
