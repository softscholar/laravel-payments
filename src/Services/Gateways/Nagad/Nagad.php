<?php

namespace Softscholar\Payment\Services\Gateways\Nagad;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Softscholar\Payment\Contracts\PaymentInterface;

class Nagad implements PaymentInterface
{
    private string $host;

    private string $tnx = '';

    private array $merchantAdditionalInfo = [];

    public function __construct()
    {
        $this->host = config('spayment.mode') === 'production'
            ? 'http://api.nagad.com/remote-payment-gateway-1.0/'
            : 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/';
    }

    /**
     * Initialize payment process.
     *
     * @throws Exception
     */
    public function initialize(string $merchantId, string $orderId, string $purpose = 'ECOM_TXN'): array
    {
        $dateTime = now()->format('YmdHis');

        $sensitiveData = [
            'merchantId' => $merchantId,
            'datetime' => $dateTime,
            'orderId' => $orderId,
            'challenge' => NagadUtility::generateRandomString(),
        ];

        $checkoutData = [
            'accountNumber' => config('spayment.gateways.nagad.merchant_number'), // optional
            'dateTime' => $dateTime,
            'sensitiveData' => NagadUtility::EncryptDataWithPublicKey(json_encode($sensitiveData)),
            'signature' => NagadUtility::SignatureGenerate(json_encode($sensitiveData)),
        ];

        $requestParams = ['purpose' => $purpose];

        $url = "{$this->host}api/dfs/check-out/initialize/{$merchantId}/{$orderId}";
        $url .= '?'.http_build_query($requestParams);

        return NagadUtility::post($url, $checkoutData);
    }

    /**
     * Handle payment.
     *
     * @throws Exception
     */
    public function pay(array $data)
    {
        $orderId = $data['order_id'] ?? 'Ord_'.now()->format('YmdH').rand(1000, 10000);

        if (! isset($data['callback_url'])) {
            throw new Exception('Callback URL is required');
        }

        $merchantId = config('spayment.gateways.nagad.merchant_id');
        $purpose = 'ECOM_TXN';

        if (config('spayment.gateways.nagad.tokenization')) {
            $this->merchantAdditionalInfo['tokenization'] = true;
            $purpose = 'ECOM_TOKEN_GEN';
            $data['amount'] = 0;
        }

        $initialResponse = $this->initialize($merchantId, $orderId, $purpose);

        if (! empty($initialResponse['sensitiveData']) && ! empty($initialResponse['signature'])) {
            $responseData = json_decode(NagadUtility::decryptDataWithPrivateKey($initialResponse['sensitiveData']), true);
            if (! empty($responseData['paymentReferenceId']) && ! empty($responseData['challenge'])) {
                $this->completeCheckout($merchantId, $orderId, $responseData, $data);
            }
        } else {
            throw new Exception('Failed to initialize payment');
        }
    }

    /**
     * Complete checkout process.
     *
     * @throws Exception
     */
    public function completeCheckout(string $merchantId, string $orderId, array $responseData, array $paymentData): void
    {
        $paymentRefId = $responseData['paymentReferenceId'];
        $challenge = $responseData['challenge'];

        $sensitiveDataOrder = [
            'customerId' => $paymentData['customer_id'] ?? (string) rand(100000, 999999),
            'merchantId' => $merchantId,
            'orderId' => $orderId,
            'currencyCode' => '050',
            'amount' => $paymentData['amount'] ?? 0,
            'challenge' => $challenge,
        ];

        if ($this->tnx !== '') {
            $this->merchantAdditionalInfo['tnx_id'] = $this->tnx;
        }

        if (isset($paymentData['additional_info'])) {
            $this->merchantAdditionalInfo = array_merge($this->merchantAdditionalInfo, $paymentData['additional_info']);
        }

        $postDataOrder = [
            'sensitiveData' => NagadUtility::encryptDataWithPublicKey(json_encode($sensitiveDataOrder)),
            'signature' => NagadUtility::signatureGenerate(json_encode($sensitiveDataOrder)),
            'merchantCallbackURL' => $paymentData['callback_url'],
            'additionalMerchantInfo' => (object) $this->merchantAdditionalInfo,
        ];

        $orderSubmitUrl = "{$this->host}api/dfs/check-out/complete/{$paymentRefId}";
        $resultDataOrder = NagadUtility::post($orderSubmitUrl, $postDataOrder);

        if (isset($resultDataOrder['status']) && $resultDataOrder['status'] === 'Success') {
            $url = $resultDataOrder['callBackUrl'];
            echo "<script>window.open('$url', '_self')</script>";
        } else {
            throw new Exception('Failed: '.$resultDataOrder['message']);
        }
    }

    public function refund(): void
    {
        // TODO: Implement refund() method.
    }

    public function cancel(): void
    {
        // TODO: Implement cancel() method.
    }

    /**
     * @throws ConnectionException
     */
    public function verify(string $tnxId): array
    {
        $url = "{$this->host}api/dfs/verify/payment/{$tnxId}";

        return NagadUtility::post($url);
    }
}
