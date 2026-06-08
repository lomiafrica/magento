<?php

namespace Lomi\Payments\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\Store as Store;

class ConfigProvider implements ConfigProviderInterface
{
    /** @var \Magento\Payment\Model\MethodInterface */
    protected $method;

    /** @var Store */
    protected $store;

    public function __construct(PaymentHelper $paymentHelper, Store $store)
    {
        $this->method = $paymentHelper->getMethodInstance(\Lomi\Payments\Model\Payment\Lomi::CODE);
        $this->store = $store;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                \Lomi\Payments\Model\Payment\Lomi::CODE => [
                    'setup_url' => $this->store->getBaseUrl() . 'lomi/payment/setup',
                    'recreate_quote_url' => $this->store->getBaseUrl() . 'lomi/payment/recreate',
                ],
            ],
        ];
    }

    public function getStore()
    {
        return $this->store;
    }
}
