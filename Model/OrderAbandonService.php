<?php

namespace Lomi\Payments\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;

/**
 * Cancel pending hosted-checkout orders and restore the shopper quote.
 */
class OrderAbandonService
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var CheckoutSession */
    private $checkoutSession;

    /** @var OrderFactory */
    private $orderFactory;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CheckoutSession $checkoutSession,
        OrderFactory $orderFactory
    ) {
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
    }

    public function isAbandonable(Order $order): bool
    {
        if (!$order->getId()) {
            return false;
        }

        if ($order->getState() === Order::STATE_CANCELED) {
            return false;
        }

        $abandonableStatuses = ['pending', 'pending_payment', 'new'];

        return in_array((string) $order->getStatus(), $abandonableStatuses, true)
            || in_array((string) $order->getState(), [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT], true);
    }

    public function abandon(Order $order, string $comment = ''): bool
    {
        if (!$this->isAbandonable($order)) {
            return false;
        }

        if ($comment === '') {
            $comment = 'lomi.: customer left hosted checkout before completing payment.';
        }

        if ($order->canCancel()) {
            $order->registerCancellation($comment);
            $this->orderRepository->save($order);
        } elseif ($order->getState() !== Order::STATE_CANCELED) {
            $order->setState(Order::STATE_CANCELED);
            $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CANCELED));
            $order->addStatusHistoryComment($comment);
            $this->orderRepository->save($order);
        }

        $this->checkoutSession->restoreQuote();

        return true;
    }

    /**
     * Resolve the pending order from request params or checkout session.
     */
    public function resolvePendingOrder(?string $incrementId, ?string $protectCode): ?Order
    {
        if ($incrementId && $protectCode) {
            $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
            if ($order->getId() && $order->getProtectCode() === $protectCode && $this->isAbandonable($order)) {
                return $order;
            }

            return null;
        }

        $order = $this->checkoutSession->getLastRealOrder();
        if ($order && $order->getId() && $this->isAbandonable($order)) {
            return $order;
        }

        return null;
    }
}
