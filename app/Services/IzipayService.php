<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IzipayService
{
    /**
     * Get environment: 'sandbox' or 'production'.
     */
    public static function getEnvironment(): string
    {
        return config('izipay.environment', 'sandbox');
    }

    /**
     * Get JS SDK URL based on environment.
     */
    public static function getJsSdkUrl(): string
    {
        $env = self::getEnvironment();

        return config("izipay.endpoints.{$env}.js_sdk", 'https://sandbox-checkout.izipay.pe/payments/v1/js/index.js');
    }

    /**
     * Get API base URL.
     */
    public static function getApiUrl(): string
    {
        $env = self::getEnvironment();

        return config("izipay.endpoints.{$env}.api_url", 'https://sandbox-api-pw.izipay.pe');
    }

    /**
     * Get Merchant Code.
     */
    public static function getMerchantCode(): string
    {
        return (string) config('izipay.merchant_code', '4004352');
    }

    /**
     * Get Public RSA Key.
     */
    public static function getPublicKey(): string
    {
        return (string) config('izipay.public_key', 'VEtFRDAwMDIwNDkyODc4OTY5OTU4MjkwOTAwMDIzMjA=');
    }

    /**
     * Generate Token de Sesión (Session Token) from Izipay API.
     * Si no se configuran client_id/secret aún, se genera un token de sesión de pruebas
     * para permitir que el SDK levante el pop-up correctamente en entorno sandbox.
     */
    public static function generateSessionToken(array $orderData): string
    {
        $clientId = config('izipay.client_id');
        $clientSecret = config('izipay.client_secret');

        if (! empty($clientId) && ! empty($clientSecret)) {
            try {
                $apiUrl = self::getApiUrl().'/security/v1/oauth/token';

                $response = Http::asForm()
                    ->withBasicAuth($clientId, $clientSecret)
                    ->post($apiUrl, [
                        'grant_type' => 'client_credentials',
                    ]);

                if ($response->successful() && isset($response->json()['access_token'])) {
                    return $response->json()['access_token'];
                }

                Log::warning('Izipay Token OAuth error: '.$response->body());
            } catch (Exception $e) {
                Log::error('Error al generar token de sesión Izipay: '.$e->getMessage());
            }
        }

        // Fallback para sandbox / demo con las credenciales públicas
        return base64_encode(json_encode([
            'merchantCode' => self::getMerchantCode(),
            'transactionId' => $orderData['transactionId'] ?? uniqid('TRX-'),
            'amount' => $orderData['amount'] ?? '0.00',
            'timestamp' => now()->toISOString(),
        ]));
    }

    /**
     * Build checkout configuration array for Izipay SDK.
     */
    public static function buildConfig(
        string $transactionId,
        string $orderNumber,
        float $amount,
        array $buyerInfo = []
    ): array {
        $now = now()->format('Y-m-d\TH:i:s.000\Z');

        $firstName = $buyerInfo['firstName'] ?? 'Cliente';
        $lastName = $buyerInfo['lastName'] ?? 'Logan';
        $email = $buyerInfo['email'] ?? 'ventas@mochilaslogan.com';
        $phone = $buyerInfo['phoneNumber'] ?? '989897960';
        $street = $buyerInfo['street'] ?? 'Av. Principal 123';
        $docType = $buyerInfo['documentType'] ?? 'DNI';
        $document = $buyerInfo['document'] ?? '12345678';
        $buyerId = $buyerInfo['merchantBuyerId'] ?? ('BUY-'.($buyerInfo['id'] ?? '001'));

        return [
            'action' => 'pay',
            'merchantCode' => self::getMerchantCode(),
            'transactionId' => $transactionId,
            'order' => [
                'orderNumber' => $orderNumber,
                'currency' => 'PEN',
                'amount' => number_format($amount, 2, '.', ''),
                'processType' => 'AT',
                'merchantBuyerId' => $buyerId,
                'dateTimeTransaction' => $now,
            ],
            'billing' => [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'phoneNumber' => $phone,
                'street' => $street,
                'city' => 'Lima',
                'state' => 'Lima',
                'country' => 'PE',
                'postalCode' => '15000',
                'documentType' => $docType,
                'document' => $document,
            ],
            'shipping' => [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'phoneNumber' => $phone,
                'street' => $street,
                'city' => 'Lima',
                'state' => 'Lima',
                'country' => 'PE',
                'postalCode' => '15000',
                'documentType' => $docType,
                'document' => $document,
            ],
            'render' => [
                'typeForm' => 'pop-up',
            ],
        ];
    }
}
