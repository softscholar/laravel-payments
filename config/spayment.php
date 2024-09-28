<?php

return [
    'mode' => env('PAYMENT_GATEWAY_MODE', 'sandbox'), // 'sandbox' or 'production'

    'gateways' => [
        'nagad' => [
            'sandbox_mode' => env('NAGAD_MODE', 'sandbox'),
            'merchant_id' => env('NAGAD_MERCHANT_ID', '683002007104225'),
            'merchant_number' => env('NAGAD_MERCHANT_NUMBER', '1234567889'),
            'callback_url' => env('NAGAD_CALLBACK_URL', env('APP_URL').'/nagad/callback'),
            'tokenization' => env('NAGAD_TOKENIZATION', false),
            'ssl_verify' => env('NAGAD_SSL_VERIFY', false),
            'merchant_hex' => env('NAGAD_MERCHANT_HEX', '683002007104225'),
            'merchant_iv' => env('NAGAD_MERCHANT_IV', '1234567889'),
        ],
    ],
];
