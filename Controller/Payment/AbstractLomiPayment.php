<?php

namespace Lomi\Payments\Controller\Payment;

use Magento\Payment\Helper\Data as PaymentHelper;
use Lomi\Payments\Gateway\LomiApiClient;
use Lomi\Payments\Model\CheckoutSessionVerifier;

abstract class AbstractLomiPayment extends \Magento\Framework\App\Action\Action
{
    protected $resultPageFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;
    protected $checkoutSession;
    protected $method;
    protected $messageManager;

    /**
     * @var \Lomi\Payments\Model\Ui\ConfigProvider
     */
    protected $configProvider;

    /**
     * @var LomiApiClient
     */
    protected $lomiClient;

    /**
     * @var CheckoutSessionVerifier
     */
    protected $checkoutSessionVerifier;

    /**
     * @var \Magento\Framework\Event\Manager
     */
    protected $eventManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        PaymentHelper $paymentHelper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Lomi\Payments\Model\Ui\ConfigProvider $configProvider,
        \Magento\Framework\Event\Manager $eventManager,
        \Magento\Framework\App\Request\Http $request,
        \Psr\Log\LoggerInterface $logger,
        LomiApiClient $lomiClient,
        CheckoutSessionVerifier $checkoutSessionVerifier
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->method = $paymentHelper->getMethodInstance(\Lomi\Payments\Model\Payment\Lomi::CODE);
        $this->messageManager = $messageManager;
        $this->configProvider = $configProvider;
        $this->eventManager = $eventManager;
        $this->request = $request;
        $this->logger = $logger;
        $this->lomiClient = $lomiClient;
        $this->checkoutSessionVerifier = $checkoutSessionVerifier;

        parent::__construct($context);
    }

    protected function redirectToFinal($successFul = true, $message = '')
    {
        if ($successFul) {
            if ($message) {
                $this->messageManager->addSuccessMessage(__($message));
            }
            return $this->_redirect('checkout/onepage/success');
        }
        if ($message) {
            $this->messageManager->addErrorMessage(__($message));
        }
        return $this->_redirect('checkout/onepage/failure');
    }
}
