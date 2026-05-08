<?php

namespace Pstk\Paystack\Model;

use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Csp\Model\Policy\FetchPolicy;

/**
 * Registers CSP whitelist entries for Paystack domains.
 *
 * Uses the PHP collector API instead of csp_whitelist.xml to stay
 * backward-compatible across Magento versions (the XML schema changed in 2.4.8).
 */
class CspPolicyCollector implements PolicyCollectorInterface
{
    /**
     * @inheritDoc
     */
    public function collect(array $defaultPolicies = []): array
    {
        $policies = $defaultPolicies;

        // script-src: allow loading Paystack Inline JS SDK
        $policies[] = new FetchPolicy(
            'script-src',
            false,
            ['js.paystack.co', 'api.paystack.co']
        );

        // connect-src: allow XHR/fetch to Paystack APIs
        $policies[] = new FetchPolicy(
            'connect-src',
            false,
            ['api.paystack.co', 'js.paystack.co', 'plugin-tracker.paystackintegrations.com']
        );

        // frame-src: allow Paystack Standard (redirect) iframe
        $policies[] = new FetchPolicy(
            'frame-src',
            false,
            ['standard.paystack.co']
        );

        return $policies;
    }
}
