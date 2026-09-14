<?php

namespace Lomi\Payments\Controller\Payment;

use Lomi\Payments\Model\OrderAbandonService;

class Recreate extends AbstractLomiPayment
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
     * Hosted checkout cancel URL — cancel pending order and restore quote.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $incrementId = (string) $this->request->getParam('increment_id');
        $protectCode = (string) $this->request->getParam('key');

        if ($incrementId === '' || $protectCode === '') {
            return $this->_redirect('checkout', ['_fragment' => 'payment']);
        }

        $order = $this->orderAbandonService->resolvePendingOrder(
            $incrementId,
            $protectCode
        );

        if ($order) {
            $abandoned = $this->orderAbandonService->abandon(
                $order,
                'lomi.: customer cancelled hosted checkout.'
            );

            if ($abandoned) {
                $this->messageManager->addNoticeMessage(
                    __('Payment was cancelled. Your cart has been restored — you can place your order again.')
                );
            }
        }

        return $this->_redirect('checkout', ['_fragment' => 'payment']);
    }
}
