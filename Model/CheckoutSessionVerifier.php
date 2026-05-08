<?php

namespace Lomi\Payments\Model;

use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Lomi\Payments\Gateway\LomiApiClient;
use Psr\Log\LoggerInterface;

/**
 * Validates Lomi checkout session against a Magento order and dispatches pay event.
 */
class CheckoutSessionVerifier
{
    /** @var LomiApiClient */
    private $apiClient;

    /** @var EventManager */
    private $eventManager;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        LomiApiClient $apiClient,
        EventManager $eventManager,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->apiClient = $apiClient;
        $this->eventManager = $eventManager;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Compare API session to order; dispatch verify event only when valid and order still pending.
     *
     * @param \Magento\Sales\Model\Order $order
     * @param object                     $session Normalized session object from API
     */
    public function verifyAndDispatch(Order $order, object $session): bool
    {
        if (!$this->amountAndCurrencyMatch($order, $session)) {
            $this->holdOrderWithComment(
                $order,
                'lomi.: session amount or currency does not match order. Check the lomi. dashboard.'
            );

            return false;
        }

        $status = strtolower((string) ($session->status ?? ''));
        if ($status !== 'completed') {
            $this->holdOrderWithComment(
                $order,
                'lomi.: payment not completed (status: ' . ($status ?: 'unknown') . ').'
            );

            return false;
        }

        if (!in_array($order->getStatus(), ['pending', 'pending_payment'], true)) {
            $this->logger->info('lomi.: order not awaiting payment, skipping verify dispatch', [
                'increment_id' => $order->getIncrementId(),
                'status' => $order->getStatus(),
            ]);

            return true;
        }

        $this->eventManager->dispatch('lomi_payment_verify_after', [
            'lomi_order' => $order,
        ]);

        return true;
    }

    public function amountAndCurrencyMatch(Order $order, object $session): bool
    {
        $expected = $this->apiClient->getOrderAmountMinorUnits($order);
        $paid = isset($session->amount) ? (int) $session->amount : 0;
        if ($paid !== $expected) {
            return false;
        }
        $orderCur = strtoupper((string) $order->getOrderCurrencyCode());
        $sessCur = isset($session->currency_code) ? strtoupper((string) $session->currency_code) : '';
        if ($sessCur !== '' && $sessCur !== $orderCur) {
            return false;
        }

        return true;
    }

    private function holdOrderWithComment(Order $order, $comment): void
    {
        try {
            $order->setState(Order::STATE_HOLDED);
            $order->setStatus('holded');
            $order->addStatusHistoryComment($comment);
            $this->orderRepository->save($order);
        } catch (\Throwable $e) {
            $this->logger->error('lomi.: failed to hold order', ['error' => $e->getMessage()]);
        }
    }
}
