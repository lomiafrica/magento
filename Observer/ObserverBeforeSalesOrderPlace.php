<?php

namespace Pstk\Paystack\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class ObserverBeforeSalesOrderPlace implements ObserverInterface
{

    
    public function __construct() {

    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order **/
        $order = $observer->getEvent()->getOrder();

        if ($order && $order->getPayment()
            && $order->getPayment()->getMethod() === \Pstk\Paystack\Model\Payment\Paystack::CODE
        ) {
            $order->setCanSendNewEmailFlag(false)
                    ->setCustomerNoteNotify(false);
        }
    }
}
