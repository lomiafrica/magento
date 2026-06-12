# Changelog

All notable changes to **lomi/magento2-payments** are documented here.

## 5.0.0

### Added

- Hosted checkout via lomi. API checkout sessions (`POST /checkout-sessions`).
- Test and live API secret keys with encrypted admin storage.
- Test and live webhook signing secrets with HMAC-SHA256 verification.
- Webhook endpoint `/lomi/payment/webhook` for `PAYMENT_SUCCEEDED`.
- Return URL handler `/lomi/payment/callback` with order protect code verification.
- Checkout abandon recovery (`checkout-abandon.js`, recreate flow).
- Checkout branding card (payment icons, CSS, lomi. assets).
- XOF amounts as whole francs; USD/EUR as minor units.
- Docker-based local development environment under `dev/`.
- Merchant documentation (README, French guide, dev README).

### Changed

- Module identifier `Lomi_Payments` with data patch for legacy config migration.
- Webhook URL admin hint uses secure base URL when configured.
- API client decrypts Magento-encrypted obscure config values.

### Security

- Webhook CSRF validation skipped only for the webhook action.
- Outbound webhook URL safety enforced by lomi. API; Magento verifies `X-Lomi-Signature`.

## Earlier versions

See git history for releases under previous vendor namespaces.
