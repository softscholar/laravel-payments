# Laravel Payments by SoftScholar

**Laravel Payments** is a robust service package for integrating payment gateways into your Laravel application, developed by [SoftScholar](https://softscholar.com). This package currently supports **Nagad** (API v4.0.1) and **bKash** (Tokenized Checkout API v1.2.0-beta) payment gateways.

---

## Table of Contents
- [Installation](#installation)
- [Configuration](#configuration)
  - [Config File Structure](#config-file-structure)
  - [Environment Variables](#environment-variables)
- [Nagad Payment Gateway](#nagad-payment-gateway)
  - [Nagad Workflow & API Usage](#nagad-workflow--api-usage)
  - [Nagad Callback Example](#nagad-callback-example)
- [bKash Payment Gateway](#bkash-payment-gateway)
  - [bKash API Guide](#bkash-api-guide)
  - [bKash Token Management](#bkash-token-management)
  - [bKash Regular Checkout](#bkash-regular-checkout)
  - [bKash Agreements (Tokenized Checkout)](#bkash-agreements-tokenized-checkout)
  - [bKash Search & Query Payments](#bkash-search--query-payments)
  - [bKash Refunds](#bkash-refunds)
- [Supported Payment Gateways](#supported-payment-gateways)
- [License](#license)
- [Contributing](#contributing)

---

## Installation

Install the package via Composer in your Laravel project directory:

```bash
composer require softscholar/laravel-payments
```

---

## Configuration

Publish the package configuration file to set up your payment gateway credentials:

```bash
php artisan vendor:publish --provider="Softscholar\Payment\PaymentServiceProvider"
```

This will publish the configuration file to `config/spayment.php`.

### Config File Structure

The `config/spayment.php` configuration file contains setup sections for both Nagad and bKash:

```php
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
            'ssl_verify' => env('NAGAD_SSL_VERIFY', false), // Set to true on production
            'merchant_hex' => env('NAGAD_MERCHANT_HEX', 'your-merchant-hex'),
            'merchant_iv' => env('NAGAD_MERCHANT_IV', 'your-merchant-iv'),
        ],
        'bkash' => [
            'mode' => env('BKASH_MODE', 'sandbox'),
            'username' => env('BKASH_USERNAME', 'bkash-username'),
            'password' => env('BKASH_PASSWORD', 'bkash-password'),
            'app_key' => env('BKASH_APP_KEY', 'bkash-app-key'),
            'app_secret' => env('BKASH_APP_SECRET', 'bkash-app-secret'),
            'ssl_verify' => env('SSL_VERIFY', false), // Set to true on production
            'api_endpoint' => env('BKASH_API_BASE_URL', ''),
            'callback_url' => env('BKASH_CALLBACK_URL', ''),
            'agreement_callback_url' => env('BKASH_AGREEMENT_CALLBACK_URL', ''),
        ],
    ],
];
```

### Environment Variables

Add the following environment variables to your `.env` file and replace the values with credentials provided by your payment partners:

```dotenv
# General settings
PAYMENT_DEFAULT_GATEWAY=nagad
PAYMENT_GATEWAY_MODE=sandbox

# Nagad credentials
NAGAD_MODE=sandbox
NAGAD_MERCHANT_ID=your-merchant-id
NAGAD_PG_PUBLIC_KEY="your-pg-public-key"
NAGAD_MERCHANT_PRIVATE_KEY="your-merchant-private-key"
NAGAD_MERCHANT_NUMBER=your-merchant-number
NAGAD_SSL_VERIFY=false
NAGAD_MERCHANT_HEX=your-merchant-hex
NAGAD_MERCHANT_IV=your-merchant-iv

# bKash credentials
BKASH_MODE=sandbox
BKASH_USERNAME=your-bkash-username
BKASH_PASSWORD=your-bkash-password
BKASH_APP_KEY=your-bkash-app-key
BKASH_APP_SECRET=your-bkash-app-secret
SSL_VERIFY=false
BKASH_API_BASE_URL=https://sbdynamic.pay.bka.sh/v1 # Sandbox or Production endpoint URL
BKASH_CALLBACK_URL="https://yourdomain.com/payments/bkash/callback"
BKASH_AGREEMENT_CALLBACK_URL="https://yourdomain.com/payments/bkash/agreement/callback"
```

---

## Nagad Payment Gateway

### Nagad Workflow & API Usage

To initiate a payment using the Nagad gateway, you can call the `checkout` method on the `Payment` Facade. You can run **regular checkout**, **authorization** (for tokenized setups), or a **tokenized payment**.

```php
use Softscholar\Payment\Facades\Payment;

$merchantCallbackURL = route('gateways.nagad.callback');

$checkoutData = [
    'callback_url' => $merchantCallbackURL,
    'order_id' => $product->id . 'Ord' . time(),
    'customer_id' => $customerId, // Must be numeric, greater than 4 digits
    'additional_info' => [
        'additionalFieldNameBN' => 'পণ্যের নাম',
        'additionalFieldNameEN' => 'Product Name',
        'additionalFieldValue' => $product->name,
    ],
    'amount' => $product->price,
];

// 1. Regular Checkout
$redirectUrl = Payment::nagad()->checkout($checkoutData);
return redirect()->away($redirectUrl);

// 2. Authorization (Zero-amount setup for tokenization)
$checkoutData['amount'] = 0;
$redirectUrl = Payment::nagad()->checkout($checkoutData, 'authorize');
return redirect()->away($redirectUrl);

// 3. Tokenized checkout (using previously stored customer credentials/token)
$checkoutData['token'] = $storedUserToken;
$redirectUrl = Payment::nagad()->checkout($checkoutData, 'tokenized');
return redirect()->away($redirectUrl);
```

### Nagad Callback Example

Handle the redirect callback to verify the payment on your controller:

```php
use Softscholar\Payment\Facades\Payment;
use Illuminate\Http\Request;

public function callback(Request $request)
{
    if (!$request->order_id) {
        return redirect()->route('payment.failed');
    }

    $status = $request->status;
    $paymentRefId = $request->payment_ref_id;

    if ($status === 'Success') {
        // Verify payment with Nagad
        $response = Payment::nagad()->verify($paymentRefId);

        if (isset($response['amount']) && $response['amount'] > 0) {
            // Payment verified successfully! Update database...
            return redirect()->route('payment.success', ['paymentId' => $paymentRefId]);
        }
    }

    return redirect()->route('payment.failed');
}
```

---

## bKash Payment Gateway

### bKash API Guide

The bKash integration uses the Tokenized Checkout API v1.2.0-beta. The package provides a fluent interface to authenticate, initialize, execute, query, search, and refund payments.

The `Softscholar\Payment\Services\Gateways\Bkash\Bkash` class implements `PaymentInterface` and offers the following methods:

| Method Name | Parameters | Return Type | Description |
|---|---|---|---|
| `withToken($token)` | `string\|array $token` | `static` | Feeds an active `id_token` string or response array containing the token to the instance. |
| `grantToken()` | None | `array` | Calls bKash to obtain a fresh token payload. |
| `refreshToken($refreshToken)` | `string $refreshToken` | `array` | Refreshes the `id_token` using a `refresh_token`. |
| `checkout($data, $type)` | `array $data, string $type` | `string` | Entry point to create a payment (`regular` / `tokenized`) or agreement (`authorize`). Returns redirect URL. |
| `createPayment($data)` | `array $data` | `string` | Directly triggers bKash checkout payment creation and returns the checkout redirect URL. |
| `executePayment($paymentId, $agreementId)` | `string $paymentId, string $agreementId = null` | `array` | Executes/completes the payment after successful redirection. |
| `queryPayment($paymentId)` | `string $paymentId` | `array` | Queries payment details and status from bKash. |
| `searchTransaction($trxID)` | `string $trxID` | `array` | Searches for a transaction by bKash Transaction ID (`trxID`). |
| `createAgreement($data)` | `array $data` | `string` | Creates an agreement for tokenized checkout. Returns agreement redirect URL. |
| `executeAgreement($agreementId)` | `string $agreementId` | `array` | Executes/completes agreement enrollment. |
| `cancelAgreement($agreementId)` | `string $agreementId` | `array` | Cancels an active agreement. |
| `refund($data)` | `array $data` | `array` | Initiates refund of a captured payment. |
| `refundStatus($paymentId, $trxID)`| `string $paymentId, string $trxID` | `array` | Checks status of a refunded transaction. |

---

### bKash Token Management

bKash checkout requires authentication using a bearer `id_token`. You must obtain this token first, and it's highly recommended to store/cache it and refresh it before expiration.

Here is an example wrapper helper:

```php
use Softscholar\Payment\Facades\Payment;
use App\Models\PgToken; // Assuming you have a table/model to store tokens

public function getToken(): string
{
    $tokenRecord = PgToken::where('gateway', 'bkash')->first();

    if ($tokenRecord) {
        if (now()->lessThan($tokenRecord->expires_at)) {
            return $tokenRecord->id_token;
        }

        if ($tokenRecord->refresh_expires_at && now()->lessThan($tokenRecord->refresh_expires_at)) {
            $response = Payment::bkash()->refreshToken($tokenRecord->refresh_token);
            $this->saveToken($response);
            return $response['id_token'];
        }
    }

    $response = Payment::bkash()->grantToken();
    $this->saveToken($response);
    return $response['id_token'];
}

protected function saveToken(array $response): void
{
    PgToken::updateOrCreate(
        ['gateway' => 'bkash'],
        [
            'id_token' => $response['id_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds((int) $response['expires_in']),
            'refresh_expires_at' => now()->addDays(28),
        ]
    );
}
```

---

### bKash Regular Checkout

Regular checkout creates a single payment transaction.

#### 1. Initiate Checkout
```php
use Softscholar\Payment\Facades\Payment;

$token = $this->getToken(); // Retrieve your saved token

$payload = [
    'amount' => 100.00,
    'merchantInvoiceNumber' => 'INV-100234',
    'payerReference' => '01712345678', // e.g. Customer mobile number
    'callbackURL' => route('certificate.payments.callback.bkash.callback', ['subdomain' => $subdomain]),
];

// Returns bKash checkout URL to redirect the customer to
$redirectUrl = Payment::bkash()->withToken($token)->checkout($payload, 'regular');

return redirect()->away($redirectUrl);
```

#### 2. Execute Payment in Callback
After the customer inputs their PIN and verifies the OTP, bKash redirects to the `callbackURL` with query parameters. You **must** call `executePayment` to complete the transaction:

```php
use Softscholar\Payment\Facades\Payment;
use Illuminate\Http\Request;

public function callback(Request $request)
{
    $status = $request->query('status');
    $paymentId = $request->query('paymentId');

    if ($status === 'success' && $paymentId) {
        try {
            $token = $this->getToken();
            
            // Execute payment
            $response = Payment::bkash()->withToken($token)->executePayment($paymentId);

            if (isset($response['transactionStatus']) && $response['transactionStatus'] === 'Completed') {
                $trxID = $response['trxID']; // Transaction ID to store in DB
                $amount = $response['amount'];
                
                // Payment success logic goes here...
                return redirect()->route('payment.success', [
                    'paymentId' => $paymentId,
                    'gateway' => 'bkash'
                ]);
            }
        } catch (\Exception $e) {
            return redirect()->route('payment.failed')->with('error', $e->getMessage());
        }
    }

    return redirect()->route('payment.failed');
}
```

---

### bKash Agreements (Tokenized Checkout)

Tokenized Checkout allows customer account enrollment (creating an agreement) so that subsequent payments can be completed without entering OTP/PIN.

#### 1. Create Agreement
```php
use Softscholar\Payment\Facades\Payment;

$token = $this->getToken();

$payload = [
    'payerReference' => '01712345678',
    'callbackURL' => route('certificate.payments.callback.bkash.agreementCallback', ['subdomain' => $subdomain]),
];

// Returns bKash agreement checkout URL to redirect the customer to
$redirectUrl = Payment::bkash()->withToken($token)->checkout($payload, 'authorize');

return redirect()->away($redirectUrl);
```

#### 2. Execute Agreement in Callback
```php
use Softscholar\Payment\Facades\Payment;
use Illuminate\Http\Request;

public function agreementCallback(Request $request)
{
    $status = $request->query('status');
    $agreementId = $request->query('agreementId');

    if ($status === 'success' && $agreementId) {
        $token = $this->getToken();
        $response = Payment::bkash()->withToken($token)->executeAgreement($agreementId);

        if (isset($response['agreementStatus']) && $response['agreementStatus'] === 'Completed') {
            // Save the agreement ID in your database associated with the user/customer
            $user->paymentAccounts()->create([
                'token' => $agreementId,
                'gateway' => 'bkash',
                'account_no' => $response['customerMsisdn'] ?? '',
            ]);

            return redirect()->route('profile')->with('success', 'bKash Account Saved!');
        }
    }

    return redirect()->route('profile')->with('error', 'Agreement setup failed');
}
```

#### 3. Charge Using Tokenized Agreement
Once you have stored the active `agreementId`, you can run a checkout payment directly without requiring PIN/OTP:

```php
use Softscholar\Payment\Facades\Payment;

$token = $this->getToken();

$payload = [
    'agreementId' => $user->paymentAccounts()->where('gateway', 'bkash')->value('token'),
    'amount' => 250.00,
    'merchantInvoiceNumber' => 'INV-20050',
    'payerReference' => '01712345678',
    'callbackURL' => route('certificate.payments.callback.bkash.callback', ['subdomain' => $subdomain]), // Will redirect here on success
];

// Returns payment confirmation/checkout URL
$redirectUrl = Payment::bkash()->withToken($token)->checkout($payload, 'tokenized');

return redirect()->away($redirectUrl);
```

#### 4. Cancel Agreement
To delete/deregister a saved customer wallet:

```php
use Softscholar\Payment\Facades\Payment;

$token = $this->getToken();
$response = Payment::bkash()->withToken($token)->cancel([
    'agreementId' => $storedAgreementId
]);

if (isset($response['agreementStatus']) && $response['agreementStatus'] === 'Cancelled') {
    // Delete local record...
}
```

---

### bKash Search & Query Payments

#### Query Payment Status
Queries checkout status using the unique `paymentId`:

```php
$token = $this->getToken();
$response = Payment::bkash()->withToken($token)->queryPayment($paymentId);

// Returns structure containing trxID, transactionStatus, amount, merchantInvoiceNumber, etc.
```

#### Search Transaction details
Searches transaction information directly via bKash Transaction ID (`trxID`):

```php
$token = $this->getToken();
$response = Payment::bkash()->withToken($token)->verify($trxID);

// Returns payment info array (amount, transactionStatus, customerMsisdn, etc.)
```

---

### bKash Refunds

Refund an executed payment transaction:

```php
use Softscholar\Payment\Facades\Payment;

$token = $this->getToken();

$refundPayload = [
    'paymentId' => $paymentId,
    'trxID' => $trxID,
    'amount' => 100.00,
    'sku' => 'ItemSku',       // Optional, default is 'sku'
    'reason' => 'User request' // Optional, default is 'refund'
];

$response = Payment::bkash()->withToken($token)->refund($refundPayload);

if (isset($response['refundTrxID'])) {
    // Refund completed successfully
}
```

Check the status of an existing refund:

```php
$response = Payment::bkash()->withToken($token)->refundStatus($paymentId, $trxID);
```

---

## Supported Payment Gateways

The **Laravel Payments** package currently supports:
- **Nagad** (including regular, authorization and tokenized checkout)
- **bKash** (including regular checkout, agreement setup, tokenized checkout, search, and refund queries)

---

## License

The **Laravel Payments** package is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## Contributing

Open source contributions are welcome! If you would like to contribute, please fork the repository and submit a pull request.

For assistance, feel free to contact us at [SoftScholar](https://softscholar.com) or send an email to [atiq@softscholar.com](mailto:atiq@softscholar.com).
