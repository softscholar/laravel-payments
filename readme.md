# Laravel Payments by SoftScholar

**Laravel Payments** is a service package for integrating payment gateways into your Laravel application, developed by [SoftScholar](https://softscholar.com). This package currently supports the Nagad Payment Gateway, with plans to add support for more gateways in the future.

> **Note:** This package uses Nagad API version 4.0.1.

## Table of Contents
- [Installation](#installation)
- [Configuration](#configuration)
    - [Configuration Options](#configuration-options)
    - [Setting Environment Variables](#setting-environment-variables)
- [Usage](#usage)
- [Supported Payment Gateways](#supported-payment-gateways)
- [License](#license)
- [Contributing](#contributing)

## Installation

To install the package, run the following command in your Laravel project directory:

```bash
composer require softscholar/laravel-payments

```
## Configuration

After installing the package, you need to publish the configuration file to set up credentials for the payment gateways. Run the following command to publish the configuration file:
```bash
php artisan vendor:publish --provider="Softscholar\Payment\PaymentServiceProvider"
```

This will create a configuration file named `spayment.php` in the `config` directory of your Laravel project. You can customize this file to configure credentials and settings for the Nagad Payment Gateway or any other gateways that will be supported in the future.

### Configuration Options

The configuration file will look something like this:

```php
return [
    'mode' => env('PAYMENT_GATEWAY_MODE', 'sandbox'),
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
    ...
];

```

### Setting Environment Variables

To use the Nagad Payment Gateway, add the necessary environment variables to your `.env` file:

```dotenv
NAGAD_MODE=sandbox
NAGAD_MERCHANT_ID=your-merchant-id
NAGAD_MERCHANT_SECRET=your-merchant-secret
NAGAD_PG_PUBLIC_KEY=your-public-key
NAGAD_MERCHANT_PRIVATE_KEY=your-private-key
NAGAD_MERCHANT_NUMBER=your-merchant-number
NAGAD_CALLBACK_URL=your-callback-url
NAGAD_SSL_VERIFY=false
NAGAD_MERCHANT_HEX=your-merchant-hex
NAGAD_MERCHANT_IV=your-merchant-iv
```

Replace your-merchant-id, your-merchant-secret, your-public-key, your-private-key, and your-callback-url with the actual credentials provided by Nagad.

After setting these variables, the package will be ready to use with the Nagad gateway. Additional gateways can be configured similarly in the future as they are added to the package.

## Usage

To use the Laravel Payments package, you can create a new instance of the `Payment` class and call the `purchase` method to initiate a payment. Here is an example of how you can use the package to make a payment using the Nagad gateway:

```php
use Softscholar\Payment\Services\Gateways\Nagad\Nagad;

        $merchantCallbackURL = route('gateways.nagad.callback');
        
        $checkoutData =  [
            'callback_url' => $merchantCallbackURL,
            'order_id' => $product->id . 'Ord' . time(),
            'customer_id' => $customerId, // must be greater than 4 digits
            'additional_info' => [
                'additionalFieldNameBN' => 'পণ্যের নাম',
                'additionalFieldNameEN' => 'Product Name',
                'additionalFieldValue' => $product->name,
            ],
            'amount' => $product->price,
        ];
        
    // create an instance of Nagad
    $nagad = new Nagad(
            $YourMerchantId,
            $YourMerchantPgKey,
            $YourMerchantPrivateKey,
            $YourMerchantHex,
            $YourMerchantIv,
            $YourMerchantNumber
        );

    /**
     * checkout method takes two parameters
        * 1. checkoutData
        * 2. checkoutType (optional) - default is 'regular'
        * Types: 'regular', 'authorize', 'tokenized'
        * For tokenized checkout, you will need to authorize the payment first by passing 'authorize' as the second parameter
        * After authorizing the payment, you will get a account details, store the thoose details and use them to tokenize the payment
        * once you have token then call the checkout method with 'tokenized' as the second parameter
     */
     
    // initiate the payment and get the redirect URL
     $redirectUrl = $nagad->checkout($checkoutData); // for regular checkout

    // for tokenization you will need to authorize the payment first
    // with zero(0) amount
    $checkoutData['amount'] = 0;
    $redirectUrl = $nagad->checkout($checkoutData, 'authorize');

    // store the account details and use them to tokenize the payment
    // once you have token then call the checkout method with 'tokenized' as the second parameter
    $redirectUrl = $nagad->checkout($checkoutData, 'tokenized');
```

The `purchase` method will return a redirect URL that you can use to redirect the user to the Nagad payment page. The user will be able to complete the payment on the Nagad website, and once the payment is completed, the user will be redirected back to the callback URL specified in the checkout data.

Callback data example
```php
'merchant' => 'your-merchant-id',
'order_id' => 'order-id',
'payment_ref_id' => 'payment-ref-id',
'status' => 'payment-status',
'status_code' => 'payment-status-code',
'message' => 'payment-message',
'payment_dt' => 'payment-date-time'
```

More detailed documentation will be added in the future as more gateways are added to the package.
You can also check the [Nagad API Documentation](https://nagad.com.bd) for more information on how to use the Nagad Payment Gateway.

You can get the example application of: [Laravel Payments Example](https://github.com/softscholar/laravel-payments-app) You can check the example application to see how to use the package.

## Supported Payment Gateways

The Laravel Payments package currently supports the following payment gateways:

- Nagad
- More gateways will be added in the future

## License

The Laravel Payments package is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Contributing
Open source contributions are welcome! If you would like to contribute to the package, please fork the repository and submit a pull request with your changes.

If you have any questions or need help with the package, please feel free to contact us at
[SoftScholar](https://softscholar.com) or email to [Email](mailto:atiq@softscholar.com).
