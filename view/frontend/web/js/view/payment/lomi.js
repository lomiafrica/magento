/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'lomi',
                component: 'Lomi_Payments/js/view/payment/method-renderer/lomi-method'
            }
        );

        return Component.extend({});
    }
);
