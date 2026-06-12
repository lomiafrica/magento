<?php

namespace Lomi\Payments\Controller\Payment;

use Lomi\Payments\Model\OrderAbandonService;
use Magento\Framework\Controller\ResultFactory;

class Abandon extends AbstractLomiPayment
{
    /** @var OrderAbandonService */
    private $orderAbandonService;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Lomi\Payments\Model\Ui\ConfigProvider $configProvider,
        \Magento\Framework\Event\Manager $eventManager,
        \Magento\Framework\App\Request\Http $request,
        \Psr\Log\LoggerInterface $logger,
        \Lomi\Payments\Gateway\LomiApiClient $lomiClient,
        \Lomi\Payments\Model\CheckoutSessionVerifier $checkoutSessionVerifier,
        OrderAbandonService $orderAbandonService
    ) {
        $this->orderAbandonService = $orderAbandonService;
        parent::__construct(
            $context,
            $resultPageFactory,
            $orderRepository,
            $orderFactory,
            $checkoutSession,
            $paymentHelper,
            $messageManager,
            $configProvider,
            $eventManager,
            $request,
            $logger,
            $lomiClient,
            $checkoutSessionVerifier
        );
    }

    /**
     * Browser returned to checkout without completing hosted payment.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $order = $this->orderAbandonService->resolvePendingOrder(null, null);
        $abandoned = false;

        if ($order) {
            $abandoned = $this->orderAbandonService->abandon(
                $order,
                'lomi.: customer left hosted checkout before completing payment.'
            );

            if ($abandoned) {
                $this->messageManager->addNoticeMessage(
                    __('Payment was cancelled. Your cart has been restored — you can place your order again.')
                );
            }
        }

        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData([
            'success' => true,
            'abandoned' => $abandoned,
        ]);

        return $result;
    }
}
