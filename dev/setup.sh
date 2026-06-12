#!/bin/bash
#
# lomi. — Magento 2 local development setup
#
# Prerequisites:
#   1. cp .env.example .env   (then fill in your lomi. test keys)
#   2. docker compose up -d   (wait ~3 minutes for Magento to install)
#   3. bash setup.sh
#
set -e

export MSYS_NO_PATHCONV=1

source .env

mage() {
    docker compose exec --user www-data magento php bin/magento "$@"
}

echo "==> Waiting for Magento to be ready..."
until mage --version 2>/dev/null; do
    echo "    Still initializing... (this can take a few minutes on first run)"
    sleep 10
done

echo ""
echo "==> Fixing file ownership..."
docker compose exec magento chown -R www-data:www-data \
    /var/www/html/var \
    /var/www/html/generated \
    /var/www/html/pub/static \
    /var/www/html/app/etc

echo ""
echo "==> Configuring admin security for local development..."
mage config:set admin/security/lockout_failures 0
mage config:set admin/security/lockout_threshold 0
mage config:set admin/security/password_is_forced 0
mage config:set admin/security/password_lifetime 0

echo ""
echo "==> Disabling 2FA for local development..."
mage module:disable Magento_AdminAdobeImsTwoFactorAuth Magento_TwoFactorAuth --clear-static-content

echo ""
echo "==> Enabling Lomi_Payments module..."
mage module:enable Lomi_Payments --clear-static-content

echo ""
echo "==> Running setup:upgrade..."
mage setup:upgrade

echo ""
echo "==> Switching to developer mode..."
mage deploy:mode:set developer

echo ""
echo "==> Compiling DI..."
mage setup:di:compile

echo ""
echo "==> Configuring lomi. payment method..."
mage config:set payment/lomi/active 1
mage config:set payment/lomi/test_mode 1
if [ -z "${LOMI_TEST_SK:-}" ]; then
    echo "    WARNING: LOMI_TEST_SK is empty in dev/.env — set your test secret key (sk_test_...)."
else
    mage config:set payment/lomi/test_secret_key "${LOMI_TEST_SK}"
fi
if [ -z "${LOMI_TEST_WEBHOOK_SECRET:-}" ]; then
    echo "    WARNING: LOMI_TEST_WEBHOOK_SECRET is empty in dev/.env — paste whsec_... from dashboard webhooks."
else
    mage config:set payment/lomi/test_webhook_secret "${LOMI_TEST_WEBHOOK_SECRET}"
fi

echo ""
echo "==> Setting store currency (XOF, USD, EUR supported by gateway)..."
mage config:set currency/options/allow XOF,USD,EUR
mage config:set currency/options/base XOF
mage config:set currency/options/default XOF

echo ""
echo "==> Creating test products with images..."
docker compose exec --user www-data magento php app/code/Lomi/Payments/dev/seed-products.php

echo ""
echo "==> Deploying storefront static assets (branding images + checkout template)..."
mage setup:static-content:deploy -f en_US

echo ""
echo "==> Reindexing..."
mage indexer:reindex

echo ""
echo "==> Flushing cache..."
mage cache:flush

echo ""
echo "============================================"
echo "  Setup complete!"
echo ""
echo "  Storefront:  http://localhost:8080"
echo "  Admin panel: http://localhost:8080/admin"
echo "  Admin login: admin / Admin12345!"
echo "============================================"
