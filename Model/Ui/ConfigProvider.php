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
        $publicKey = $this->method->getConfigData('live_public_key');
        if ($this->method->getConfigData('test_mode')) {
            $publicKey = $this->method->getConfigData('test_public_key');
        }

        return [
            'payment' => [
                \Lomi\Payments\Model\Payment\Lomi::CODE => [
                    'public_key' => $publicKey,
                    'integration_type' => 'standard',
                    'api_url' => $this->store->getBaseUrl() . 'rest/',
                    'integration_type_standard_url' => $this->store->getBaseUrl() . 'lomi/payment/setup',
                    'recreate_quote_url' => $this->store->getBaseUrl() . 'lomi/payment/recreate',
                ],
            ],
        ];
    }

    public function getStore()
    {
        return $this->store;
    }

    /**
     * @return array
     */
    public function getSecretKeyArray()
    {
        $data = ['live' => $this->method->getConfigData('live_secret_key')];
        if ($this->method->getConfigData('test_mode')) {
            $data = ['test' => $this->method->getConfigData('test_secret_key')];
        }

        return $data;
    }

    public function getPublicKey()
    {
        $publicKey = $this->method->getConfigData('live_public_key');
        if ($this->method->getConfigData('test_mode')) {
            $publicKey = $this->method->getConfigData('test_public_key');
        }
        return $publicKey;
    }
}
