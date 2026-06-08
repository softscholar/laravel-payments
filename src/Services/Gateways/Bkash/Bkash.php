<?php

declare(strict_types=1);

namespace Softscholar\Payment\Services\Gateways\Bkash;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Softscholar\Payment\Contracts\PaymentInterface;

class Bkash implements PaymentInterface
{
    public string $host = '';

    public string $token = '';

    public function __construct(
        private readonly array $config
    ) {
        $this->host = $this->baseUrl();
    }

    /**
     * Implement pay() method.
     *
     * @throws Exception
     */
    public function pay(array $data): string
    {
        return $this->createPayment($data);
    }

    /**
     * Implement checkout() method.
     *
     * @throws Exception
     */
    public function checkout(array $data, string $checkoutType = 'regular'): string
    {
        if ($checkoutType === 'authorize') {
            return $this->createAgreement($data);
        }

        if ($checkoutType === 'tokenized') {
            if (empty($data['agreementId'])) {
                throw new Exception('agreementId is required for tokenized checkout');
            }
            return $this->createPayment($data);
        }

        return $this->createPayment($data);
    }

    /**
     * Implement refund() method.
     *
     * @throws Exception
     */
    public function refund(array $data = []): array
    {
        return $this->refundPayment(
            $data['paymentId'] ?? '',
            $data['trxID'] ?? '',
            (float) ($data['amount'] ?? 0.0),
            $data['sku'] ?? 'sku',
            $data['reason'] ?? 'refund'
        );
    }

    /**
     * Implement cancel() method.
     *
     * @throws Exception
     */
    public function cancel(array $data = []): array
    {
        if (isset($data['agreementId'])) {
            return $this->cancelAgreement($data['agreementId']);
        }
        throw new Exception('Cancellation of this type is not supported or missing agreementId.');
    }

    /**
     * Implement verify() method.
     *
     * @throws Exception
     */
    public function verify(string $tnxId): array
    {
        return $this->searchTransaction($tnxId);
    }

    public function withToken(string|array $token): static
    {
        if (is_array($token)) {
            $this->token = $token['id_token'] ?? $token['idToken'] ?? '';
        } else {
            $this->token = $token;
        }

        return $this;
    }

    /**
     * Get or grant authorization token.
     *
     * @throws ConnectionException
     * @throws Exception
     */
    public function getToken(): string
    {
        if (empty($this->token)) {
            $data = $this->grantToken();
            $this->token = $data['id_token'] ?? $data['idToken'] ?? '';
            if (empty($this->token)) {
                throw new Exception('Failed to extract id_token from bKash token grant response.');
            }
        }

        return $this->token;
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function grantToken(): array
    {
        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'username' => $this->config['username'] ?? '',
            'password' => $this->config['password'] ?? '',
        ])
            ->withOptions(['verify' => $this->getVerifyOption()])
            ->post($this->buildUrl('auth/grant-token'), [
                'app_key' => $this->config['app_key'] ?? '',
                'app_secret' => $this->config['app_secret'] ?? '',
            ]);

        if ($res->failed()) {
            throw new Exception('Failed to retrieve bKash token: '.$res->body());
        }
        return $res->json();
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function refreshToken(string $refreshToken): array
    {
        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'username' => $this->config['username'] ?? '',
            'password' => $this->config['password'] ?? '',
        ])
            ->withOptions(['verify' => $this->getVerifyOption()])
            ->post($this->buildUrl('auth/refresh-token'), [
                'app_key' => $this->config['app_key'] ?? '',
                'app_secret' => $this->config['app_secret'] ?? '',
                'refresh_token' => $refreshToken,
            ]);

        if ($res->failed()) {
            // Fallback to grant token if refresh fails
            return $this->grantToken();
        }

        return $res->json();
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function createPayment(array $data): string
    {
        $payload = [
            'mode' => isset($data['agreementId']) ? '1001' : '1011',
            'payerReference' => (string) ($data['payerReference'] ?? '1'),
            'callbackURL' => $data['callbackURL'] ?? $this->config['callback_url'] ?? '',
            'amount' => $data['amount'] ?? '',
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => (string) ($data['merchantInvoiceNumber'] ?? ''),
        ];

        if (isset($data['agreementId'])) {
            $payload['agreementId'] = $data['agreementId'];
        }

        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-APP-Key' => $this->config['app_key'] ?? '',
            'Authorization' => $this->getToken(),
        ])
            ->withOptions(['verify' => $this->getVerifyOption()])
            ->post($this->buildUrl('payment/create'), $payload);

        if ($res->failed()) {
            throw new Exception('Failed to create bKash payment: '.$res->body());
        }

        $response = $res->json();
        if (isset($response['bkashURL'])) {
            return $response['bkashURL'];
        }

        throw new Exception('bKash URL not found in response: '.$res->body());
    }

    /**
     * @throws ConnectionException
     */
    public function executePayment(string $paymentId, string $agreementId = null): array
    {
        $payload = [
            'paymentId' => $paymentId,
        ];

        if ($agreementId) {
            $payload['agreementId'] = $agreementId;
        }

        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-APP-Key' => $this->config['app_key'] ?? '',
            'Authorization' => $this->getToken(),
        ])
            ->withOptions(['verify' => $this->getVerifyOption()])
            ->post($this->buildUrl('payment/execute'), $payload);

        if ($res->failed()) {
            throw new Exception('Failed to execute bKash payment: '.$res->body());
        }

        return $res->json();
    }

    public function confirmPayment(string $paymentId, string $confirmationType = 'capture'): array
    {
        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-APP-Key' => $this->config['app_key'] ?? '',
            'Authorization' => $this->getToken(),
        ])
            ->withOptions(['verify' => $this->getVerifyOption()])
            ->post($this->buildUrl('payment/confirm'), [
                'paymentId' => $paymentId,
                'confirmationType' => $confirmationType,
            ]);

        if ($res->failed()) {
            throw new Exception('Failed to confirm bKash payment: '.$res->body());
        }

        return $res->json();
    }

    public function refundPayment(string $paymentId, string $trxID, float $amount, string $sku = 'sku', string $reason = 'refund'): array
    {
        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-APP-Key' => $this->config['app_key'] ?? '',
            'Authorization' => $this->getToken(),
        ])
            ->withOptions(['verify' => $this->getVerifyOption()])
            ->post($this->buildUrl('payment/refund'), [
                'paymentId' => $paymentId,
                'amount' => $amount,
                'trxID' => $trxID,
                'sku' => $sku,
                'reason' => $reason,
            ]);

        if ($res->failed()) {
            throw new Exception('Failed to refund bKash payment: '.$res->body());
        }

        return $res->json();
    }

    public function refundStatus(string $paymentId, string $trxID): array
    {
        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-APP-Key' => $this->config['app_key'] ?? '',
            'Authorization' => $this->getToken(),
        ])
            ->withOptions(['verify' => $this->getVerifyOption()])
            ->post($this->buildUrl('payment/refund/status'), [
                'paymentId' => $paymentId,
                'trxID' => $trxID,
            ]);

        if ($res->failed()) {
            throw new Exception('Failed to get bKash refund status: '.$res->body());
        }

        return $res->json();
    }

    public function createAgreement(array $data): string
    {
        $payload = array_merge([
            'mode' => '0000',
            'callbackURL' => $data['callbackURL'] ?? $this->config['agreement_callback_url'] ?? '',
            'payerReference' => $data['payerReference'] ?? '1',
        ], $data);

        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-APP-Key' => $this->config['app_key'] ?? '',
            'Authorization' => $this->getToken(),
        ])
            ->withOptions(['verify' => $this->getVerifyOption()])
            ->post($this->buildUrl('agreement/create'), $payload);

        if ($res->failed()) {
            throw new Exception('Failed to create bKash agreement: '.$res->body());
        }

        $response = $res->json();

        if (isset($response['bkashURL'])) {
            return $response['bkashURL'];
        }

        throw new Exception('bKash URL not found in agreement response: '.$res->body());
    }

    public function executeAgreement(string $agreementId): array
    {
        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-APP-Key' => $this->config['app_key'] ?? '',
            'Authorization' => $this->getToken(),
        ])
            ->withOptions(['verify' => $this->getVerifyOption()])
            ->post($this->buildUrl('agreement/execute'), [
                'agreementId' => $agreementId,
            ]);

        info('Agreement id: '.$agreementId);
        info('response: '.print_r($res->json(), true));

        if ($res->failed()) {
            throw new Exception('Failed to execute bKash agreement: '.$res->body());
        }

        return $res->json();
    }

    public function cancelAgreement(string $agreementId): array
    {
        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-APP-Key' => $this->config['app_key'] ?? '',
            'Authorization' => $this->getToken(),
        ])
            ->withOptions(['verify' => $this->getVerifyOption()])
            ->post($this->buildUrl('agreement/cancel'), [
                'agreementId' => $agreementId,
            ]);

        if ($res->failed()) {
            throw new Exception('Failed to cancel bKash agreement: '.$res->body());
        }

        return $res->json();
    }

    public function searchTransaction(string $trxID): array
    {
        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-APP-Key' => $this->config['app_key'] ?? '',
            'Authorization' => $this->getToken(),
        ])
            ->withOptions(['verify' => $this->getVerifyOption()])
            ->post($this->buildUrl('search/transaction'), [
                'trxID' => $trxID,
            ]);

        if ($res->failed()) {
            throw new Exception('Failed to search bKash transaction: '.$res->body());
        }

        return $res->json();
    }

    public function queryPayment(string $paymentId): array
    {
        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-APP-Key' => $this->config['app_key'] ?? '',
            'Authorization' => $this->getToken(),
        ])
            ->withOptions(['verify' => $this->getVerifyOption()])
            ->post($this->buildUrl('query/payment'), [
                'paymentId' => $paymentId,
            ]);

        if ($res->failed()) {
            throw new Exception('Failed to query bKash payment: '.$res->body());
        }

        return $res->json();
    }

    public function baseUrl(): string
    {
        if (! empty($this->config['api_endpoint'])) {
            return $this->config['api_endpoint'];
        }

        return ($this->config['mode'] ?? 'sandbox') === 'sandbox'
            ? 'https://sbdynamic.pay.bka.sh/v1'
            : 'https://sbdynamic.pay.bka.sh/v1';
    }

    public function buildUrl(string $path = ''): string
    {
        $baseUrl = $this->baseUrl();

        // If a custom API endpoint is provided, we assume the user has configured the complete prefix.
        // Otherwise, we default to prepending 'tokenized/checkout/' for standard v1.2.0-beta endpoints.
        if (empty($this->config['api_endpoint'])) {
            $path = match ($path) {
                'auth/grant-token' => 'tokenized/checkout/token/grant',
                'auth/refresh-token' => 'tokenized/checkout/token/refresh',
                'payment/create' => 'tokenized/checkout/create',
                'payment/execute' => 'tokenized/checkout/execute',
                'payment/confirm' => 'tokenized/checkout/confirm',
                'payment/refund' => 'tokenized/checkout/payment/refund',
                'payment/refund/status' => 'tokenized/checkout/payment/refund',
                'agreement/create' => 'tokenized/checkout/agreement/create',
                'agreement/execute' => 'tokenized/checkout/agreement/execute',
                'agreement/cancel' => 'tokenized/checkout/agreement/cancel',
                'search/transaction' => 'tokenized/checkout/general/searchTransaction',
                'query/payment' => 'tokenized/checkout/payment/status',
                default => 'tokenized/checkout/'.ltrim($path, '/'),
            };
        }

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }

    private function getVerifyOption(): bool
    {
        return (bool) ($this->config['ssl_verify'] ?? false);
    }
}
