<?php

namespace Lomi\Payments\Model\Payment;

/**
 * lomi. payment method (hosted checkout).
 */
class Lomi extends \Magento\Payment\Model\Method\AbstractMethod
{
    public const CODE = 'lomi';

    protected $_code = self::CODE;
    protected $_isOffline = true;

    public function isAvailable(
        ?\Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        if (!parent::isAvailable($quote)) {
            return false;
        }
        if ($quote && $quote->getCurrency()) {
            $code = strtoupper((string) $quote->getCurrency()->getQuoteCurrencyCode());
            $allowed = ['XOF', 'USD', 'EUR'];
            if (!in_array($code, $allowed, true)) {
                return false;
            }
        }
        return true;
    }
}
