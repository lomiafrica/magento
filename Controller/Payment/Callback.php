<?php

namespace Lomi\Payments\Controller\Payment;

use Lomi\Payments\Gateway\Exception\ApiException;
use Lomi\Payments\Model\OrderAbandonService;
use Magento\Sales\Model\Order;

class Callback extends AbstractLomiPayment
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
            $status = strtolower((string) ($session->status ?? ''));

            if ($status !== 'completed') {
                $this->orderAbandonService->abandon(
                    $order,
                    'lomi.: hosted checkout returned without a completed payment.'
                );
                $this->messageManager->addNoticeMessage(
                    __('Payment was cancelled. Your cart has been restored — you can place your order again.')
                );

                return $this->_redirect('checkout', ['_fragment' => 'payment']);
            }

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
