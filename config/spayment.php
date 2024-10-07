<?php

return [
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
    ],
];
