<?php

namespace Lomi\Payments\Gateway;

use Magento\Directory\Model\CurrencyFactory;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order;
use Lomi\Payments\Gateway\Exception\ApiException;
use Lomi\Payments\Model\Payment\Lomi as LomiMethod;

/**
 * Lomi API HTTP client (checkout sessions).
 */
class LomiApiClient
{
    /** @var string */
    private $secretKey;

    /** @var string */
    private $webhookSecret;

    /** @var bool */
    private $testMode;

    /** @var CurrencyFactory */
    private $currencyFactory;

    public function __construct(
        PaymentHelper $paymentHelper,
        CurrencyFactory $currencyFactory
    ) {
        $this->currencyFactory = $currencyFactory;
        $method = $paymentHelper->getMethodInstance(LomiMethod::CODE);
        $this->testMode = (bool) $method->getConfigData('test_mode');
        $this->secretKey = (string) ($this->testMode
            ? $method->getConfigData('test_secret_key')
            : $method->getConfigData('live_secret_key'));
        $this->webhookSecret = (string) ($this->testMode
            ? $method->getConfigData('test_webhook_secret')
            : $method->getConfigData('live_webhook_secret'));
    }

    /**
     * Minor units for order grand total (API amount).
     */
    public function getOrderAmountMinorUnits(Order $order): int
    {
        $code = $order->getOrderCurrencyCode();
        $currency = $this->currencyFactory->create()->load($code);
        $precision = 2;
        if (method_exists($currency, 'getPrecision')) {
            $precision = (int) $currency->getPrecision();
        }

        return (int) round((float) $order->getGrandTotal() * (10 ** $precision));
    }

    private function getBaseUrl(): string
    {
        return $this->testMode
            ? 'https://sandbox.api.lomi.africa/v1'
            : 'https://api.lomi.africa/v1';
    }

    /**
     * @param array<string,mixed> $params Request body
     * @return object Normalized checkout session object
     * @throws ApiException
     */
    public function createCheckoutSession(array $params): object
    {
        return $this->request('POST', '/checkout-sessions', $params);
    }

    /**
     * @throws ApiException
     */
    public function fetchCheckoutSession(string $sessionId): object
    {
        return $this->request('GET', '/checkout-sessions/' . rawurlencode($sessionId), null);
    }

    /**
     * Lomi webhook: HMAC-SHA256 over raw body.
     */
    public function validateWebhookSignature(string $rawBody, string $signature): bool
    {
        if ($signature === '' || $this->webhookSecret === '') {
            return false;
        }
        $computed = hash_hmac('sha256', $rawBody, $this->webhookSecret);

        return hash_equals($computed, $signature);
    }

    /**
     * @deprecated Removed — use Lomi checkout sessions only.
     * @throws ApiException
     */
    public function initializeTransaction(array $params): object
    {
        throw new ApiException('Direct transaction initialize is not supported. Use Lomi checkout sessions.');
    }

    /**
     * @deprecated Removed — use fetchCheckoutSession.
     * @throws ApiException
     */
    public function verifyTransaction(string $reference): object
    {
        throw new ApiException('Direct transaction verify is not supported. Use fetchCheckoutSession.');
    }

    public function logTransactionSuccess(string $transactionReference, string $publicKey): void
    {
        // Intentionally empty — hosted Lomi checkout; no third-party tracker.
    }

    /**
     * @return object Single session DTO (unwraps data if present)
     * @throws ApiException
     */
    private function request(string $method, string $endpoint, ?array $data): object
    {
        if ($this->secretKey === '') {
            throw new ApiException('lomi. API secret key is not configured.');
        }

        $url = $this->getBaseUrl() . $endpoint;

        $ch = curl_init();
        $headers = [
            'X-API-Key: ' . $this->secretKey,
            'Accept: application/json',
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($method === 'POST' && $data !== null) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new ApiException('lomi. API request failed: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $body = json_decode($response);

        if (!$body || !is_object($body)) {
            throw new ApiException('Invalid JSON response from lomi. API', $statusCode);
        }

        if ($statusCode >= 400) {
            $message = isset($body->message) ? (string) $body->message : 'lomi. API request failed';
            throw new ApiException($message, $statusCode);
        }

        return $this->normalizePayload($body);
    }

    private function normalizePayload(object $json): object
    {
        if (isset($json->data) && is_object($json->data)) {
            return $json->data;
        }

        return $json;
    }
}
