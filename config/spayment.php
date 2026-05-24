<?php

return [
    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'nagad'),

    'mode' => env('PAYMENT_GATEWAY_MODE', 'sandbox'), // 'sandbox' or 'production'

    'gateways' => [
        'nagad' => [
            'mode' => env('NAGAD_MODE', 'sandbox'),
            'merchant_id' => env('NAGAD_MERCHANT_ID', 'your-merchant-id'),
            'merchant_public_key' => env('NAGAD_PG_PUBLIC_KEY', 'your-merchant-public-key'),
            'merchant_private_key' => env('NAGAD_MERCHANT_PRIVATE_KEY', 'merchant-private-key'),
            'merchant_number' => env('NAGAD_MERCHANT_NUMBER', 'your-merchant-number'),
            'tokenization' => env('NAGAD_TOKENIZATION', false),
            'ssl_verify' => env('NAGAD_SSL_VERIFY', false), // on production set it to true
            'merchant_hex' => env('NAGAD_MERCHANT_HEX', 'your-merchant-hex'),
            'merchant_iv' => env('NAGAD_MERCHANT_IV', 'your-merchant-iv'),
        ],
        'bkash' => [
            'mode' => env('BKASH_MODE', 'sandbox'),
            'username' => env('BKASH_USERNAME', 'bkash-username'),
            'password' => env('BKASH_PASSWORD', 'bkash-password'),
            'app_key' => env('BKASH_APP_KEY', 'bkash-app-key'),
            'app_secret' => env('BKASH_APP_SECRET', 'bkash-app-secret'),
            'ssl_verify' => env('SSL_VERIFY', false),
            'api_endpoint' => env('BKASH_API_BASE_URL', ''),
            'callback_url' => env('BKASH_CALLBACK_URL', ''),
            'agreement_callback_url' => env('BKASH_AGREEMENT_CALLBACK_URL', ''),
        ],
    ],
];
