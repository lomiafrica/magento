<?php

namespace Lomi\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class ObserverAfterPaymentVerify implements ObserverInterface
{
    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    public function __construct(
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    ) {
        $this->orderSender = $orderSender;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getLomiOrder();

        if ($order && in_array($order->getStatus(), ['pending', 'pending_payment'], true)) {
            $order->setState(Order::STATE_PROCESSING)
                    ->addStatusToHistory(Order::STATE_PROCESSING, __('lomi. payment verified; order is being processed.'), true)
                    ->setCanSendNewEmailFlag(true)
                    ->setCustomerNoteNotify(true);
            $order->save();

            try {
                $this->orderSender->send($order, true);
            } catch (\Exception $e) {
                // Email sending failure should not affect order status
            }
        }
    }
}
