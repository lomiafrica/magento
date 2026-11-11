<?php

namespace Lomi\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;

class ObserverAfterPaymentVerify implements ObserverInterface
{
    /** @var \Magento\Sales\Model\Order\Email\Sender\OrderSender */
    protected $orderSender;

    /** @var \Magento\Sales\Api\OrderRepositoryInterface */
    private $orderRepository;

    /** @var \Magento\Sales\Model\Service\InvoiceService */
    private $invoiceService;

    /** @var \Magento\Framework\DB\TransactionFactory */
    private $transactionFactory;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->orderSender = $orderSender;
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getLomiOrder();

        if ($order && in_array($order->getStatus(), ['pending', 'pending_payment'], true)) {
            $this->invoiceOrder($order);

            $order->setState(Order::STATE_PROCESSING)
                    ->addStatusToHistory(Order::STATE_PROCESSING, __('lomi. payment verified; order is being processed.'), true)
                    ->setCanSendNewEmailFlag(true)
                    ->setCustomerNoteNotify(true);
            $this->orderRepository->save($order);

            try {
                $this->orderSender->send($order, true);
            } catch (\Exception $e) {
                // Email sending failure should not affect order status
            }
        }
    }

    /**
     * Record the lomi. payment on the order so Total Paid, reports and credit memos work.
     * lomi. already captured the funds, so the invoice is captured offline.
     */
    private function invoiceOrder(Order $order): void
    {
        if (!$order->canInvoice()) {
            return;
        }

        try {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
            $sessionId = (string) $order->getPayment()->getAdditionalInformation('lomi_checkout_session_id');
            if ($sessionId !== '') {
                $invoice->setTransactionId($sessionId);
            }
            $invoice->register();

            $this->transactionFactory->create()
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
        } catch (\Throwable $e) {
            $this->logger->error('lomi.: failed to invoice paid order', [
                'increment_id' => $order->getIncrementId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
