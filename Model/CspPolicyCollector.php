<?php

namespace Pstk\Paystack\Model;

use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Csp\Model\Policy\FetchPolicy;

/**
 * CSP entries for lomi. hosted checkout and API.
 */
class CspPolicyCollector implements PolicyCollectorInterface
{
    /**
     * @inheritDoc
     */
    public function collect(array $defaultPolicies = []): array
    {
        $policies = $defaultPolicies;

        $hosts = [
            'api.lomi.africa',
            'sandbox.api.lomi.africa',
            'checkout.lomi.africa',
        ];

        $policies[] = new FetchPolicy('script-src', false, $hosts);
        $policies[] = new FetchPolicy('connect-src', false, $hosts);
        $policies[] = new FetchPolicy('frame-src', false, $hosts);

        return $policies;
    }
}
