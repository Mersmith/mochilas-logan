<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Izipay Environment
    |--------------------------------------------------------------------------
    | 'sandbox' or 'production'
    */
    'environment' => env('IZIPAY_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Izipay Credentials
    |--------------------------------------------------------------------------
    */
    'merchant_code' => env('IZIPAY_MERCHANT_CODE', '4004352'),
    'public_key' => env('IZIPAY_PUBLIC_KEY', 'VEtFRDAwMDIwNDkyODc4OTY5OTU4MjkwOTAwMDIzMjA='),
    'private_key' => env('IZIPAY_PRIVATE_KEY', ''),
    'client_id' => env('IZIPAY_CLIENT_ID', ''),
    'client_secret' => env('IZIPAY_CLIENT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Endpoints
    |--------------------------------------------------------------------------
    */
    'endpoints' => [
        'sandbox' => [
            'js_sdk' => 'https://sandbox-checkout.izipay.pe/payments/v1/js/index.js',
            'api_url' => 'https://sandbox-api-pw.izipay.pe',
        ],
        'production' => [
            'js_sdk' => 'https://checkout.izipay.pe/payments/v1/js/index.js',
            'api_url' => 'https://api-pw.izipay.pe',
        ],
    ],
];
