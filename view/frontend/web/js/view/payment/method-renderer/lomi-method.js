define(
    [
        "jquery",
        'mage/url',
        "Magento_Checkout/js/view/payment/default",
        "Magento_Checkout/js/action/place-order",
        "Magento_Checkout/js/model/payment/additional-validators",
        "Magento_Checkout/js/model/quote",
        "Magento_Checkout/js/model/full-screen-loader",
        "Magento_Checkout/js/action/redirect-on-success"
    ],
    function (
        $,
        mageUrl,
        Component,
        placeOrderAction,
        additionalValidators,
        quote,
        fullScreenLoader,
        redirectOnSuccessAction
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Lomi_Payments/payment/lomi'
            },
            redirectAfterPlaceOrder: false,

            isActive: function () {
                return true;
            },

            redirectToCustomAction: function (url) {
                fullScreenLoader.startLoader();
                window.location.replace(mageUrl.build(url));
            },

            /**
             * Hosted checkout: redirect to server route that creates lomi. session.
             */
            afterPlaceOrder: function () {
                var checkoutConfig = window.checkoutConfig;
                var lomiConfiguration = checkoutConfig.payment.lomi;
                this.redirectToCustomAction(lomiConfiguration.setup_url);
            }
        });
    }
);
