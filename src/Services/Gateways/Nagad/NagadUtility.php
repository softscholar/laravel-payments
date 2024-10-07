<?php

namespace Softscholar\Payment\Services\Gateways\Nagad;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NagadUtility
{
    /**
     * Generate Random string
     */
    public static function generateRandomString(int $length = 40): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    /**
     * Generate public key
     */
    public static function encryptDataWithPublicKey(string $pgPublicKey, string $data): string
    {
        $public_key = "-----BEGIN PUBLIC KEY-----\n".$pgPublicKey."\n-----END PUBLIC KEY-----";
        $key_resource = openssl_get_publickey($public_key);
        openssl_public_encrypt($data, $cryptText, $key_resource);

        return base64_encode($cryptText);
    }

    /**
     * Generate signature
     */
    public static function signatureGenerate(string $merchantPrivateKey, string $data): string
    {
        $private_key = "-----BEGIN RSA PRIVATE KEY-----\n".$merchantPrivateKey."\n-----END RSA PRIVATE KEY-----";

        openssl_sign($data, $signature, $private_key, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    public static function getClientIp(): ?string
    {
        return request()->ip();
    }

    public static function decryptDataWithPrivateKey(string $merchantPrivateKey, string $encryptedText): string
    {
        $private_key = "-----BEGIN RSA PRIVATE KEY-----\n".$merchantPrivateKey."\n-----END RSA PRIVATE KEY-----";
        openssl_private_decrypt(base64_decode($encryptedText), $plain_text, $private_key);

        return $plain_text;
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public static function post(string $url, array $data = [], string $token='', string $hex =null, string $iv=null ): array
    {
        // Prepare headers
        $headers = [
            'Content-Type' => 'application/json',
            'X-KM-Api-Version' => 'v-4.0.1',
            'X-KM-IP-V4' => self::getClientIp(),
            'X-KM-Client-Type' => 'PC_WEB',
        ];

        if ($token) {
            $token = (new NagadUtility)->decryptToken($token, trim($hex), trim($iv)); // not working with method parameter
            $headers['X-KM-Payment-Token'] = $token;
        }

        if (config('spayment.gateways.nagad.ssl_verify') === false) {
            $verify = false;
        } else {
            $verify = true;
        }

        // Make the POST request using Laravel Http client
        $response = Http::withHeaders($headers)
            ->withOptions(['verify' => $verify]) // To disable SSL verification
            ->post($url, $data);

        // Parse the response
        $ResultArray = $response->json();

        // Optionally, retrieve headers and body if needed
        $headers = $response->headers();
        $body = $response->body();

        return $ResultArray;
    }

    /**
     * @throws ConnectionException
     */
    public static function get(string $url, bool $verify = false): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-KM-Api-Version' => 'v-4.0.1',
            'X-KM-IP-V4' => self::getClientIp(),
            'X-KM-Client-Type' => 'PC_WEB',
        ];

        $options = [
            'verify' => $verify,
            'timeout' => 10,
            'allow_redirects' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/0 (Windows; U; Windows NT 0; zh-CN; rv:3)',
            ]];

        $response = Http::withHeaders($headers)
            ->withOptions($options)
            ->get($url);

        return $response->json();
    }

    /**
     * @throws Exception
     */
    public function decryptToken(string $token, string $hex, string $iv): string
    {
        $tokenBytes = hex2bin($token);
        $hexBytes = hex2bin($hex);
        $ivBytes = hex2bin($iv);

        // Perform decryption using AES-256-CBC
        $decryptedBytes = openssl_decrypt(
            $tokenBytes,           // Ciphertext to decrypt
            'aes-256-cbc',         // Cipher method
            $hexBytes,    // Symmetric key
            OPENSSL_RAW_DATA,      // Options: return raw decrypted data
            $ivBytes               // Initialization vector
        );

        if (strlen($hex) !== 64 || strlen($iv) !== 32) {
            throw new Exception('Invalid key or IV length.');
        }

        // Ensure decryption was successful
        if ($decryptedBytes === false) {
            throw new Exception('Decryption failed.');
        }

        // Encode decrypted bytes to UTF-8 string
        return mb_convert_encoding($decryptedBytes, 'UTF-8', 'UTF-8');
    }

    public static function createBalanceReference(string $key): true
    {
        Cache::rememberForever('app-activation', function () {
            return 'yes';
        });

        return true;
    }
}
