<?php
declare(strict_types=0);

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/core/helpers.php';
require BASE_PATH . '/app/core/Template.php';
require BASE_PATH . '/app/core/DB.php';
require BASE_PATH . '/app/core/Model.php';
require BASE_PATH . '/app/core/Http.php';
require BASE_PATH . '/app/core/Migrator.php';
require BASE_PATH . '/app/core/AdminBootstrap.php';
require BASE_PATH . '/app/models.php';
require BASE_PATH . '/app/lib/Delhivery.php';
require BASE_PATH . '/app/lib/Xlsx.php';
require BASE_PATH . '/app/views/context.php';
foreach (glob(BASE_PATH . '/app/views/*.php') as $f) require_once $f;

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', config('debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-errors.log');
