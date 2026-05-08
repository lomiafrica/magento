<?php

namespace Lomi\Payments\Controller\Payment;

use Magento\Sales\Model\Order;
use Lomi\Payments\Gateway\Exception\ApiException;

class Callback extends AbstractLomiPayment
{
    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $incrementId = $this->request->getParam('increment_id');
        $key = $this->request->getParam('key');
        $message = '';

        if (!$incrementId || !$key) {
            return $this->redirectToFinal(false, 'Missing return parameters from lomi.');
        }

        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
        if (!$order->getId() || $order->getProtectCode() !== $key) {
            return $this->redirectToFinal(false, 'Invalid order or verification key.');
        }

        if ($order->getState() === Order::STATE_PROCESSING) {
            return $this->redirectToFinal(true);
        }

        $sessionId = $order->getPayment()->getAdditionalInformation('lomi_checkout_session_id');
        if (!$sessionId) {
            return $this->redirectToFinal(false, 'No lomi. checkout session linked to this order.');
        }

        try {
            $session = $this->lomiClient->fetchCheckoutSession((string) $sessionId);
            $ok = $this->checkoutSessionVerifier->verifyAndDispatch($order, $session);
            if (!$ok) {
                return $this->redirectToFinal(false, 'lomi. could not confirm payment for this order.');
            }
        } catch (ApiException $e) {
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        if ($message) {
            return $this->redirectToFinal(false, $message);
        }

        return $this->redirectToFinal(true);
    }
}
