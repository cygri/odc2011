<?php
ini_set('display_errors', '1');

define('APP_ROOT', dirname(__FILE__) . '/');
include APP_ROOT . 'config.inc.php';
require_once APP_ROOT . 'lib/db.class.php';
require_once APP_ROOT . 'lib/planning.class.php';
require_once APP_ROOT . 'lib/response.class.php';
require_once APP_ROOT . 'lib/request.class.php';
require_once APP_ROOT . 'lib/site.class.php';
require_once APP_ROOT . 'lib/http_exception.class.php';

$db = new DB($config);
$planning = new Planning($db);

if (php_sapi_name() == 'cli') {
    set_error_handler('_cli_error_handler');
}

function _cli_error_handler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }
    switch ($errno) {
        case E_NOTICE: $msg = 'NOTICE'; break;
        case E_USER_NOTICE: $msg = 'NOTICE'; break;
        case E_WARNING: $msg = 'WARNING'; break;
        case E_USER_WARNING: $msg = 'WARNING'; break;
        case E_USER_ERROR: $msg = 'ERROR'; break;
        default: $msg = "ERROR $errno";
    }
    fputs (STDERR, "$msg $errstr [$errfile:$errline]\n");
    if ($errno == E_USER_ERROR) exit(1);
    return true;
}
