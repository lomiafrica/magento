<?php

namespace Lomi\Payments\Plugin;

use Magento\Framework\App\RequestInterface;

class CsrfValidatorSkip
{
    /**
     * Skip CSRF check for lomi. webhook endpoint (no session cookie).
     *
     * @param \Magento\Framework\App\Request\CsrfValidator $subject
     * @param \Closure $proceed
     * @param RequestInterface $request
     * @param \Magento\Framework\App\ActionInterface $action
     * @return mixed
     */
    public function aroundValidate(
        $subject,
        \Closure $proceed,
        RequestInterface $request,
        $action
    ) {
        if ($request->getFullActionName() === 'lomi_payment_webhook') {
            return null;
        }

        return $proceed($request, $action);
    }
}
