<?php

namespace Lomi\Payments\Controller\Payment;

use Magento\Sales\Model\Order;
use Lomi\Payments\Gateway\Exception\ApiException;

class Setup extends AbstractLomiPayment
{
    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $message = '';
        $order = $this->orderFactory->create()->loadByIncrementId($this->checkoutSession->getLastRealOrder()->getIncrementId());
        if ($order && $this->method->getCode() == $order->getPayment()->getMethod()) {
            try {
                return $this->processAuthorization($order);
            } catch (ApiException $e) {
                $message = $e->getMessage();
                $order->addStatusToHistory($order->getStatus(), $message);
                $this->orderRepository->save($order);
            }
        }

        return $this->redirectToFinal(false, $message);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Redirect
     * @throws ApiException
     */
    protected function processAuthorization(Order $order)
    {
        $store = $this->configProvider->getStore();
        $baseUrl = $store->getBaseUrl();

        $email = $order->getCustomerEmail();
        if (!$email && $order->getBillingAddress()) {
            $email = $order->getBillingAddress()->getEmail();
        }

        $successUrl = $baseUrl . 'lomi/payment/callback?increment_id=' . rawurlencode($order->getIncrementId())
            . '&key=' . rawurlencode($order->getProtectCode());

        $cancelUrl = $baseUrl . 'lomi/payment/recreate?increment_id=' . rawurlencode($order->getIncrementId())
            . '&key=' . rawurlencode($order->getProtectCode());

        $payload = [
            'currency_code' => strtoupper((string) $order->getOrderCurrencyCode()),
            'amount' => $this->lomiClient->getOrderAmountMinorUnits($order),
            'integration_source' => 'magento',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $email,
            'customer_name' => trim((string) $order->getCustomerFirstname() . ' ' . (string) $order->getCustomerLastname()),
            'require_billing_address' => false,
            'title' => 'Order ' . $order->getIncrementId(),
            'metadata' => [
                'magento_increment_id' => $order->getIncrementId(),
                'quote_id' => (string) $order->getQuoteId(),
                'plugin' => 'magento-lomi',
            ],
        ];

        $data = $this->lomiClient->createCheckoutSession($payload);

        if (empty($data->checkout_url) || empty($data->checkout_session_id)) {
            throw new ApiException('lomi. did not return checkout_url or checkout_session_id.');
        }

        $payment = $order->getPayment();
        $payment->setAdditionalInformation('lomi_checkout_session_id', (string) $data->checkout_session_id);
        $this->orderRepository->save($order);

        $redirectFactory = $this->resultRedirectFactory->create();
        $redirectFactory->setUrl((string) $data->checkout_url);

        return $redirectFactory;
    }
}
