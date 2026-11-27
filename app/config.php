<?php
/**
 * Default configuration. DO NOT put secrets here – this file is committed to Git.
 *
 * Real values go in ONE of these files (first found wins), which must never be committed:
 *   1. ../usvesta-config.php        (one folder above the website root – recommended on Hostinger)
 *   2. ./config.local.php           (website root; blocked from the web by .htaccess)
 * Both return an array with the same shape as below (only the keys you want to override).
 */

$defaults = [
    'debug' => false,
    'site_url' => 'https://www.usvesta.com',
    'timezone' => 'Asia/Kolkata',
    'login_url' => '/login-register',

    'db' => [
        'driver' => 'mysql',          // mysql | sqlite
        'host' => '127.0.0.1',        // Hostinger: use 127.0.0.1 / localhost
        'port' => 3306,
        'name' => '',
        'user' => '',
        'pass' => '',
        'path' => '',                 // sqlite only
    ],

    'mail' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',        // tls | ssl
        'user' => '',
        'pass' => '',
        'from' => '',
    ],

    'razorpay' => ['key_id' => '', 'key_secret' => ''],
    'cashfree' => ['app_id' => '', 'secret_key' => ''],
    'veno' => ['gateway_url' => 'https://payzen.cloud/api/v1/payment/orders/', 'master_api_key' => ''],
    'delhivery' => ['api_token' => '', 'base_url' => 'https://track.delhivery.com'],
    'google' => ['client_id' => '', 'client_secret' => ''],
    'msg91' => ['auth_key' => '', 'flow_id' => '', 'sender_id' => 'MSGIND'],
    'app_id' => '',
    'app_key' => '',
];

$local = [];
foreach ([dirname(BASE_PATH) . '/usvesta-config.php', BASE_PATH . '/config.local.php'] as $f) {
    if (is_file($f)) { $local = require $f; break; }
}

return array_replace_recursive($defaults, is_array($local) ? $local : []);
