#!/bin/bash
#
# Paystack Magento 2 — Development Environment Setup
#
# Prerequisites:
#   1. cp .env.example .env   (then fill in your Paystack test keys)
#   2. docker compose up -d   (wait ~3 minutes for Magento to install)
#   3. bash setup.sh
#
set -e

# Prevent Git Bash (MSYS/MinGW) from converting Unix paths to Windows paths
export MSYS_NO_PATHCONV=1

source .env

# Helper: run magento CLI as www-data to avoid root-owned file permission issues
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
echo "==> Enabling Pstk_Paystack module..."
mage module:enable Pstk_Paystack --clear-static-content

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
echo "==> Configuring Paystack payment method..."
mage config:set payment/pstk_paystack/active 1
mage config:set payment/pstk_paystack/test_mode 1
mage config:set payment/pstk_paystack/test_public_key "$PAYSTACK_TEST_PK"
mage config:set payment/pstk_paystack/test_secret_key "$PAYSTACK_TEST_SK"
mage config:set payment/pstk_paystack/integration_type inline

echo ""
echo "==> Setting store currency to NGN..."
mage config:set currency/options/allow NGN,USD,ZAR
mage config:set currency/options/base NGN
mage config:set currency/options/default NGN
mage config:set currency/options/allow NGN

echo ""
echo "==> Creating test products with images..."
docker compose exec --user www-data magento php app/code/Pstk/Paystack/dev/seed-products.php

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
echo ""
echo "  Test card:   4084 0840 8408 4081"
echo "  Expiry:      12/30"
echo "  CVV:         408"
echo "  PIN:         0000"
echo "  OTP:         123456"
echo "============================================"
