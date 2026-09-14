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
        var formKey = (window.FORM_KEY || (window.checkoutConfig && window.checkoutConfig.formKey) || '');
        var stored = {};

        try {
            stored = raw ? JSON.parse(raw) : {};
        } catch (error) {
            stored = {};
        }

        if (!abandonUrl || !formKey) {
            window.location.reload();
            return;
        }

        var body = 'form_key=' + encodeURIComponent(formKey);
        if (stored.incrementId) {
            body += '&increment_id=' + encodeURIComponent(stored.incrementId);
        }
        if (stored.key) {
            body += '&key=' + encodeURIComponent(stored.key);
        }

        window.fetch(abandonUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body
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
