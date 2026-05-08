<?php

namespace Pstk\Paystack\Controller\Payment;

use Magento\Sales\Model\Order;
use Pstk\Paystack\Gateway\Exception\ApiException;

class Setup extends AbstractPaystackStandard
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

        $successUrl = $baseUrl . 'paystack/payment/callback?increment_id=' . rawurlencode($order->getIncrementId())
            . '&key=' . rawurlencode($order->getProtectCode());

        $cancelUrl = $store->getUrl('checkout/cart');

        $payload = [
            'currency_code' => strtoupper((string) $order->getOrderCurrencyCode()),
            'amount' => $this->paystackClient->getOrderAmountMinorUnits($order),
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

        $data = $this->paystackClient->createCheckoutSession($payload);

        if (empty($data->checkout_url) || empty($data->checkout_session_id)) {
            throw new ApiException('Lomi did not return checkout_url or checkout_session_id.');
        }

        $payment = $order->getPayment();
        $payment->setAdditionalInformation('lomi_checkout_session_id', (string) $data->checkout_session_id);
        $this->orderRepository->save($order);

        $redirectFactory = $this->resultRedirectFactory->create();
        $redirectFactory->setUrl((string) $data->checkout_url);

        return $redirectFactory;
    }
}
