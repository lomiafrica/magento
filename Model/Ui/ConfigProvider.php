<?php

namespace Lomi\Payments\Model\Ui;

use Lomi\Payments\Model\CheckoutBranding;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\Store as Store;

class ConfigProvider implements ConfigProviderInterface
{
    /** @var \Magento\Payment\Model\MethodInterface */
    protected $method;

    /** @var Store */
    protected $store;

    /** @var CheckoutBranding */
    private $checkoutBranding;

    public function __construct(
        PaymentHelper $paymentHelper,
        Store $store,
        CheckoutBranding $checkoutBranding
    ) {
        $this->method = $paymentHelper->getMethodInstance(\Lomi\Payments\Model\Payment\Lomi::CODE);
        $this->store = $store;
        $this->checkoutBranding = $checkoutBranding;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $baseUrl = $this->store->getBaseUrl();

        return [
            'payment' => [
                \Lomi\Payments\Model\Payment\Lomi::CODE => [
                    'setup_url' => $baseUrl . 'lomi/payment/setup',
                    'recreate_quote_url' => $baseUrl . 'lomi/payment/recreate',
                    'abandon_url' => $baseUrl . 'lomi/payment/abandon',
                    'storage_key' => 'lomi_checkout_redirect',
                    'uses_branding_card' => $this->checkoutBranding->usesCheckoutBrandingCard(),
                    'pay_with_image_url' => $this->checkoutBranding->getPayWithImageUrl(),
                    'payment_icon_urls' => $this->checkoutBranding->getPaymentIconUrls(),
                ],
            ],
        ];
    }

    public function getStore()
    {
        return $this->store;
    }
}
