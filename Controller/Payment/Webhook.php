<?php

namespace Lomi\Payments\Controller\Payment;

use Lomi\Payments\Gateway\Exception\ApiException;

class Webhook extends AbstractLomiPayment
{
    public function execute()
    {
        $finalMessage = 'failed';

        $resultFactory = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        try {
            $rawBody = $this->request->getContent();
            $this->logger->info('lomi. webhook: received request');

            $signature = $this->request->getHeader('X-Lomi-Signature') ?: '';
            if (!$signature || !$this->lomiClient->validateWebhookSignature($rawBody, $signature)) {
                $this->logger->warning('lomi. webhook: signature validation failed');
                $resultFactory->setHttpResponseCode(401);
                $resultFactory->setContents('auth failed');
                return $resultFactory;
            }

            $event = $this->request->getHeader('X-Lomi-Event') ?: '';
            $this->logger->info('lomi. webhook: event = ' . $event);

            if ($event !== 'PAYMENT_SUCCEEDED') {
                $resultFactory->setHttpResponseCode(200);
                $resultFactory->setContents('ignored');
                return $resultFactory;
            }

            $payload = json_decode($rawBody);
            if (!$payload || !isset($payload->data) || !is_object($payload->data)) {
                $resultFactory->setHttpResponseCode(400);
                $resultFactory->setContents('invalid payload');
                return $resultFactory;
            }

            $data = $payload->data;
            $incrementId = isset($data->metadata->magento_increment_id) ? (string) $data->metadata->magento_increment_id : '';
            $sessionId = isset($data->checkout_session_id) ? (string) $data->checkout_session_id : '';

            if ($incrementId === '') {
                $this->logger->warning('lomi. webhook: missing magento_increment_id in metadata');
                $resultFactory->setHttpResponseCode(200);
                $resultFactory->setContents('no increment id');
                return $resultFactory;
            }

            $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
            if (!$order->getId()) {
                $this->logger->warning('lomi. webhook: order not found', ['increment_id' => $incrementId]);
                $resultFactory->setHttpResponseCode(200);
                $resultFactory->setContents('order not found');
                return $resultFactory;
            }

            $storedSession = $order->getPayment()->getAdditionalInformation('lomi_checkout_session_id');
            if ($sessionId !== '' && $storedSession && (string) $storedSession !== $sessionId) {
                $this->logger->warning('lomi. webhook: session id mismatch for order', ['increment_id' => $incrementId]);
                $resultFactory->setHttpResponseCode(200);
                $resultFactory->setContents('session mismatch');
                return $resultFactory;
            }

            $useSessionId = $sessionId !== '' ? $sessionId : (string) $storedSession;
            if ($useSessionId === '') {
                $this->logger->warning('lomi. webhook: no checkout session id', ['increment_id' => $incrementId]);
                $resultFactory->setHttpResponseCode(200);
                $resultFactory->setContents('no session');
                return $resultFactory;
            }

            try {
                $session = $this->lomiClient->fetchCheckoutSession($useSessionId);
                $this->checkoutSessionVerifier->verifyAndDispatch($order, $session);
            } catch (ApiException $e) {
                $this->logger->error('lomi. webhook: API error', ['error' => $e->getMessage()]);
            }

            $resultFactory->setHttpResponseCode(200);
            $resultFactory->setContents('success');
            return $resultFactory;
        } catch (\Exception $exc) {
            $this->logger->error('lomi. webhook: exception', ['error' => $exc->getMessage()]);
            $finalMessage = $exc->getMessage();
        }

        $resultFactory->setHttpResponseCode(200);
        $resultFactory->setContents($finalMessage);
        return $resultFactory;
    }
}
