<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'], // Tambahkan login/logout jika tidak pakai prefix api/

    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://stokps.com',
        'https://www.stokps.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'X-Auth-Token',
        'Origin',
        'Authorization',
        'X-Requested-With',
        'X-Branch-ID',
        'X-XSRF-TOKEN', // Laravel CSRF
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
