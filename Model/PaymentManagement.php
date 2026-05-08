<?php

namespace Lomi\Payments\Model;

use Exception;
use Lomi\Payments\Api\PaymentManagementInterface;
use Lomi\Payments\Gateway\Exception\ApiException;
use Lomi\Payments\Gateway\LomiApiClient;
use Lomi\Payments\Model\Payment\Lomi as LomiMethod;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\OrderFactory;

class PaymentManagement implements PaymentManagementInterface
{
    /** @var LomiApiClient */
    protected $lomiClient;

    /** @var CheckoutSessionVerifier */
    private $checkoutSessionVerifier;

    /** @var LoggerInterface */
    private $logger;

    /** @var OrderFactory */
    private $orderFactory;

    /** @var \Magento\Checkout\Model\Session */
    protected $checkoutSession;

    public function __construct(
        LomiApiClient $lomiClient,
        \Magento\Checkout\Model\Session $checkoutSession,
        CheckoutSessionVerifier $checkoutSessionVerifier,
        LoggerInterface $logger,
        OrderFactory $orderFactory
    ) {
        $this->lomiClient = $lomiClient;
        $this->checkoutSession = $checkoutSession;
        $this->checkoutSessionVerifier = $checkoutSessionVerifier;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Verify payment for the last order using a lomi. checkout session id.
     *
     * @param string $checkoutSessionId
     * @return string JSON
     */
    public function verifyPayment($checkoutSessionId)
    {
        $this->logger->info('lomi.: verifyPayment REST called', ['checkout_session_id' => $checkoutSessionId]);

        try {
            $order = $this->getOrder();
            if (!$order || !$order->getId()) {
                return json_encode([
                    'status' => false,
                    'message' => 'No order found in checkout session',
                ]);
            }

            if ($order->getPayment()->getMethod() !== LomiMethod::CODE) {
                return json_encode([
                    'status' => false,
                    'message' => 'Order does not use lomi. payment method',
                ]);
            }

            $stored = (string) $order->getPayment()->getAdditionalInformation('lomi_checkout_session_id');
            if ($stored === '' || $stored !== $checkoutSessionId) {
                return json_encode([
                    'status' => false,
                    'message' => 'Checkout session id does not match order',
                ]);
            }

            $session = $this->lomiClient->fetchCheckoutSession($checkoutSessionId);
            $ok = $this->checkoutSessionVerifier->verifyAndDispatch($order, $session);

            return json_encode([
                'status' => $ok,
                'message' => $ok ? 'Verification successful' : 'Verification failed or order on hold',
            ]);
        } catch (ApiException $e) {
            $this->logger->error('lomi.: verifyPayment API error', ['error' => $e->getMessage()]);
            return json_encode([
                'status' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('lomi.: verifyPayment exception', ['error' => $e->getMessage()]);
            return json_encode([
                'status' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return \Magento\Sales\Model\Order|false
     */
    private function getOrder()
    {
        $lastOrder = $this->checkoutSession->getLastRealOrder();
        if (!$lastOrder) {
            return false;
        }
        $lastOrderId = $lastOrder->getIncrementId();
        if (!$lastOrderId) {
            return false;
        }
        $order = $this->orderFactory->create()->loadByIncrementId($lastOrderId);
        return $order->getId() ? $order : false;
    }
}
