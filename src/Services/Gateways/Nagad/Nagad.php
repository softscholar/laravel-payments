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
    public function __construct(
        private readonly string  $merchantId,
        private readonly string  $merchantPublicKey,
        private readonly string  $merchantPrivateKey,
        private readonly ?string $merchantHex = '',
        private readonly ?string $merchantIv = '',
        private readonly ?string $merchantNumber = '',
    )
    {

        date_default_timezone_set('Asia/Dhaka');

        $this->host = config('spayment.mode') === 'production'
            ? 'http://api.nagad.com/remote-payment-gateway-1.0/'
            : 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/';
    }

    /**
     * Initialize payment process.
     *
     * @throws Exception
     */
    public function initialize(string $orderId, string $purpose = 'ECOM_TXN', $token=''): array
    {
        $dateTime = now()->format('YmdHis');

        $sensitiveData = [
            'merchantId' => $this->merchantId,
            'datetime' => $dateTime,
            'orderId' => $orderId,
            'challenge' => NagadUtility::generateRandomString(),
        ];

        $checkoutData = [
            'dateTime' => $dateTime,
            'sensitiveData' => $this->getEncryptedData($sensitiveData),
            'signature' => $this->generateSignature($sensitiveData),
        ];

        $requestParams = ['purpose' => $purpose];

        $url = "{$this->host}api/dfs/check-out/initialize/{$this->merchantId}/{$orderId}";
        $url .= '?'.http_build_query($requestParams);

        if ($token) {
            info('called from nagad tokenized initialize');
            return NagadUtility::post($url, $checkoutData, $token, $this->merchantHex, $this->merchantIv);
        } else {
            info('called from nagad initialize');
            return NagadUtility::post($url, $checkoutData, $token);
        }
    }

    /**
     * Handle payment.
     *
     * @throws Exception
     */
    public function pay(array $data)
    {

    }

    /**
     * Complete checkout process.
     *
     * @throws Exception
     */
    public function completeCheckout(string $orderId, array $responseData, array $paymentData, $token=''): string
    {
        $paymentRefId = $responseData['paymentReferenceId'];
        $challenge = $responseData['challenge'];

        $sensitiveDataOrder = [
            'customerId' => $paymentData['customer_id'] ?? (string) rand(100000, 999999),
            'merchantId' => $this->merchantId,
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
            'sensitiveData' => $this->getEncryptedData($sensitiveDataOrder),
            'signature' => $this->generateSignature($sensitiveDataOrder),
            'merchantCallbackURL' => $paymentData['callback_url'],
            'additionalMerchantInfo' => (object) $this->merchantAdditionalInfo,
        ];

        $orderSubmitUrl = "{$this->host}api/dfs/check-out/complete/{$paymentRefId}";
        if ($token) {
            $resultDataOrder = NagadUtility::post($orderSubmitUrl, $postDataOrder, $token, $this->merchantHex, $this->merchantIv);
        } else {
            $resultDataOrder = NagadUtility::post($orderSubmitUrl, $postDataOrder, $token);
        }

        if (isset($resultDataOrder['status']) && $resultDataOrder['status'] === 'Success') {
            return  $resultDataOrder['callBackUrl'];
        } else {
            throw new Exception($resultDataOrder['message']);
        }
    }

    /**
     * @throws Exception
     */
    public function checkout(array $data, string $checkoutType = 'regular'): string
    {
        $merchantId = $this->merchantId;

        if (!$merchantId) {
            throw new Exception('Merchant ID is required');
        }

        $orderId = $data['order_id'] ?? 'Ord_'.now()->format('YmdH').rand(1000, 10000);

        if (! isset($data['callback_url'])) {
            throw new Exception('Callback URL is required');
        }

        $purpose = 'ECOM_TXN';
        $token = '';

        if ($checkoutType == 'authorize') {
            $purpose = 'ECOM_TOKEN_GEN';
            $data['amount'] = 0;
        } elseif ($checkoutType == 'tokenized') {
            $purpose = 'ECOM_TOKEN_TXN';

            if (!$data['token']) {
                throw new Exception('Token is required for tokenized checkout');
            }

            $token = $data['token'];
        }

        $initialResponse = $this->initialize($orderId, $purpose, $token);

        if (! empty($initialResponse['sensitiveData']) && ! empty($initialResponse['signature'])) {
            $responseData = $this->getDecryptedData($initialResponse['sensitiveData']);
            if (! empty($responseData['paymentReferenceId']) && ! empty($responseData['challenge'])) {
                if ($checkoutType == 'tokenized') {
                    info('called from nagad toknized');
                    return $this->completeCheckout($orderId, $responseData, $data, $token);
                } else {
                    info('called from nagad regular');
                    return $this->completeCheckout( $orderId, $responseData, $data);
                }
            }
        } else {
            throw new Exception($initialResponse['message']);
        }
    }

    public function isEligibleForTokenizedCheckout(string $token, array $data): bool
    {
        $postData = [
            'merchantId' => $this->merchantId,
            'customerId' => $data['customer_id'],
            'maskedAccNo' => $data['masked_ac_no'],
            'tokenType' => $data['token_type'],
            'amount' => $data['amount'],
            'dateTime' => now()->format('YmdHis'),
            'challenge' => NagadUtility::generateRandomString(),
        ];

        $postDataOrder = [
            'merchantId' => $this->merchantId,
            'sensitiveData' => $this->getEncryptedData($postData),
            'signature' => $this->generateSignature($postData),
        ];

        $url = "{$this->host}/api/dfs/purchase/check/eligibility";
        $response = NagadUtility::post($url, $postDataOrder, false, $token);

        if (isset($response['eligible']) && $response['eligible'] === true) {
            return true;
        }

        return false;
    }

    /**
     * @throws ConnectionException
     */
    public function cancelAuthorization(string $token, array $data): array
    {
        $postData = [
            'merchantId' => $this->merchantId,
            'customerId' => $data['customer_id'],
            'maskedAccNo' => $data['masked_ac_no'],
            'tokenType' => $data['token_type'],
            'dateTime' => now()->format('YmdHis'),
            'challenge' => NagadUtility::generateRandomString(),
        ];

        $postDataOrder = [
            'merchantId' => $this->merchantId,
            'sensitiveData' => $this->getEncryptedData($postData),
            'signature' => $this->generateSignature($postData),
        ];

        $url = "{$this->host}/api/dfs/authorization/cancel";

        return NagadUtility::post($url, $postDataOrder, $token, $this->merchantHex, $this->merchantIv);
    }

    /**
     * @throws ConnectionException
     */
    public function verify(string $tnxId): array
    {
        $url = "{$this->host}api/dfs/verify/payment/{$tnxId}";

        return NagadUtility::get($url);
    }

    public function refund(): void
    {
        // TODO: Implement refund() method.
    }

    public function cancel(): void
    {
        // TODO: Implement cancel() method.
    }

    public function generateSignature(array $data): string
    {
        return NagadUtility::signatureGenerate($this->merchantPrivateKey, json_encode($data));
    }

    private function getEncryptedData(array $data): string
    {
        return NagadUtility::encryptDataWithPublicKey($this->merchantPublicKey, json_encode($data));
    }

    private function getDecryptedData(string $encryptedText): array
    {
        return json_decode(NagadUtility::decryptDataWithPrivateKey($this->merchantPrivateKey, $encryptedText), true);
    }
}
