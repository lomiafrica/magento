<?php

namespace Lomi\Payments\Api;

/**
 * @api
 */
interface PaymentManagementInterface
{
    /**
     * @param string $reference Checkout session id
     * @return string JSON
     */
    public function verifyPayment($reference);
}
