(function () {
    'use strict';

    function getLomiConfig() {
        var checkoutConfig = window.checkoutConfig || {};

        return checkoutConfig.payment && checkoutConfig.payment.lomi
            ? checkoutConfig.payment.lomi
            : {};
    }

    function getStorageKey() {
        var config = getLomiConfig();

        return config.storage_key || 'lomi_checkout_redirect';
    }

    function isCheckoutPage() {
        return document.body.classList.contains('checkout-index-index');
    }

    function unblockCheckout() {
        if (window.require) {
            window.require(['Magento_Checkout/js/model/full-screen-loader'], function (fullScreenLoader) {
                fullScreenLoader.stopLoader();
            });
        }

        if (window.jQuery) {
            window.jQuery(document.body).trigger('processStop');
        }
    }

    function handleAbandonedCheckout() {
        var storageKey = getStorageKey();
        var raw = null;

        try {
            raw = window.sessionStorage.getItem(storageKey);
        } catch (error) {
            return;
        }

        if (!raw) {
            return;
        }

        try {
            window.sessionStorage.removeItem(storageKey);
        } catch (error) {
            // Ignore storage errors.
        }

        unblockCheckout();

        var config = getLomiConfig();
        var abandonUrl = config.abandon_url;

        if (!abandonUrl) {
            window.location.reload();
            return;
        }

        window.fetch(abandonUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json'
            }
        })
            .catch(function () {
                return null;
            })
            .finally(function () {
                window.location.reload();
            });
    }

    function onReady() {
        if (!isCheckoutPage()) {
            try {
                window.sessionStorage.removeItem(getStorageKey());
            } catch (error) {
                // Ignore storage errors.
            }
            return;
        }

        handleAbandonedCheckout();
    }

    document.addEventListener('DOMContentLoaded', onReady);

    window.addEventListener('pageshow', function (event) {
        if (!isCheckoutPage()) {
            return;
        }

        if (event.persisted) {
            handleAbandonedCheckout();
        }
    });
}());
