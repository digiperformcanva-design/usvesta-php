<?php
/**
 * Copy this file to ../usvesta-config.php (one folder ABOVE public_html) or to
 * ./config.local.php, then fill in your values. Never commit the real file to Git.
 */
return [
    'debug' => false,
    'site_url' => 'https://www.usvesta.com',

    'db' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'u123456789_usvesta',
        'user' => 'u123456789_usvesta',
        'pass' => 'CHANGE_ME',
    ],

    'mail' => [
        'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls',
        'user' => 'info@example.com', 'pass' => 'app-password', 'from' => 'info@example.com',
    ],

    'razorpay' => ['key_id' => 'rzp_live_xxx', 'key_secret' => 'xxx'],
    'cashfree' => ['app_id' => 'xxx', 'secret_key' => 'cfsk_ma_prod_xxx'],
    'veno' => ['gateway_url' => 'https://payzen.cloud/api/v1/payment/orders/', 'master_api_key' => 'pub_live_xxx'],
    'delhivery' => ['api_token' => 'xxx', 'base_url' => 'https://track.delhivery.com'],
    'google' => ['client_id' => 'xxx.apps.googleusercontent.com', 'client_secret' => 'xxx'],
    'msg91' => ['auth_key' => '', 'flow_id' => '', 'sender_id' => 'MSGIND'],
    'app_id' => '',
    'app_key' => '',
];
