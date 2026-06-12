# lomi. for Magento 2

Accept Magento 2 payments with **lomi.** hosted checkout in **XOF**, **USD**, and **EUR**.

**Package:** `lomi/magento2-payments`  
**Module:** `Lomi_Payments`  
**Payment method code:** `lomi`

## Overview

The extension connects your Magento store to lomi. hosted checkout:

1. Customer selects **lomi.** at checkout and places the order.
2. Magento creates a [checkout session](https://docs.lomi.africa/build/checkout) via the lomi. API.
3. Customer pays on `checkout.lomi.africa`.
4. Magento confirms payment via **webhook** (`PAYMENT_SUCCEEDED`) and/or **return URL** (`/lomi/payment/callback`).
5. Order moves to **Processing**.

## Requirements

- Magento **2.4.x** (PHP 7.4+ / 8.x as supported by your Magento version)
- Store currency **XOF**, **USD**, or **EUR**
- HTTPS on production (required for webhooks and secure checkout return URLs)
- lomi. account — [dashboard.lomi.africa](https://dashboard.lomi.africa)

## Installation

### Composer (recommended)

```bash
composer require lomi/magento2-payments
php bin/magento module:enable Lomi_Payments
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

### Manual

Copy this package to `app/code/Lomi/Payments/`, then run the same Magento commands above.

## Configuration

**Stores → Configuration → Sales → Payment Methods → lomi.**

| Setting | Description |
|---------|-------------|
| **Enabled** | Show lomi. at checkout |
| **Title** | Label on checkout (empty = default branding card) |
| **Test Mode** | Yes = sandbox API; No = live API |
| **Test API secret key** | `lomi_sk_test_…` from dashboard |
| **Test webhook signing secret** | `whsec_…` from your **test** webhook endpoint |
| **Live API secret key** | `lomi_sk_live_…` (when Test Mode is No) |
| **Live webhook signing secret** | `whsec_…` from your **live** webhook endpoint |
| **Webhook URL** | Read-only hint — register this URL in the dashboard |

### Webhooks (required for reliable order confirmation)

1. In [dashboard.lomi.africa](https://dashboard.lomi.africa) → **Developers → Webhooks**, create an endpoint:
   - **URL:** `https://your-store.example/lomi/payment/webhook`
   - **Events:** at least `PAYMENT_SUCCEEDED`
2. Copy the **signing secret** (`whsec_…`) into Magento (test or live field matching **Test Mode**).
3. Send a test webhook from the dashboard — expect **200** with body `ignored` for test events, or `success` for real `PAYMENT_SUCCEEDED`.

**Important:** Use the signing secret for **that specific endpoint**, not your API secret key. Test and live webhooks have different secrets.

When saving obscure (password) fields in admin, **paste the full secret** each time. If in doubt, set via CLI:

```bash
php bin/magento config:set payment/lomi/test_webhook_secret "whsec_..."
php bin/magento cache:flush
```

### Supported currencies

| Currency | Amount sent to lomi. API |
|----------|--------------------------|
| XOF | Whole francs (e.g. 505 → `505`) |
| USD / EUR | Minor units / cents (e.g. 10.50 → `1050`) |

Stores using other currencies will not see this method at checkout.

## Customer flow

```
Checkout → Place order → Redirect to lomi. hosted checkout
    → Payment success
        → Webhook PAYMENT_SUCCEEDED → Order Processing
        → (and/or) Redirect to /lomi/payment/callback → Order Processing
```

Return URL pattern:

`https://your-store.example/lomi/payment/callback?increment_id=…&key=…`

Webhook headers: `X-Lomi-Signature`, `X-Lomi-Event` — see [webhooks documentation](https://docs.lomi.africa/build/fundamentals/webhooks).

## Testing (sandbox)

1. Enable **Test Mode** and enter test API key + test webhook secret.
2. Create a webhook in dashboard **test mode** pointing to your store (use [ngrok](https://ngrok.com/) for local dev — see [dev/README.md](dev/README.md)).
3. Place a test order; pay with card **`4242 4242 4242 4242`** (any future expiry, any CVC).
4. Confirm:
   - Webhook log: `PAYMENT_SUCCEEDED` → **200**
   - Magento order: **Processing**

More test cards: [Sandbox payments](https://docs.lomi.africa/start/sandbox-payments).

## Go live checklist

- [ ] Production store served over **HTTPS**
- [ ] **Test Mode** = **No**
- [ ] **Live** API secret key and **live** webhook signing secret configured
- [ ] Live webhook in dashboard with production URL and `PAYMENT_SUCCEEDED`
- [ ] Base URLs in **Stores → Configuration → General → Web** match your public domain
- [ ] `setup:di:compile` and static content deploy run in **production** mode
- [ ] End-to-end test with a small real payment (or live test card if available)

## FAQ

### Which API base URL is used?

| Test Mode | API base |
|-----------|----------|
| Yes | `https://sandbox.api.lomi.africa` |
| No | `https://api.lomi.africa` |

### Why is my order still Pending?

- Webhook secret mismatch (401 `auth failed`)
- Webhook not subscribed to `PAYMENT_SUCCEEDED`
- Checkout session not `completed` yet on lomi. API
- Customer did not return to your store (webhook is the reliable path)

### Are refunds supported from Magento admin?

Process refunds from the lomi. dashboard. Automatic Magento refunds are not included in this release.

### REST endpoint (optional)

`GET /rest/V1/lomi/verify/:checkoutSessionId` — verifies payment for the checkout session’s last order (browser session context). Defined in `etc/webapi.xml`.

## Upgrade from legacy module identifiers

Run `setup:upgrade` after upgrading; a data patch migrates legacy config and payment method codes where applicable.

## Development

Local Docker environment: **[dev/README.md](dev/README.md)**.

French merchant guide: **[docs/GUIDE.fr.md](docs/GUIDE.fr.md)**.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT — see [LICENSE](LICENSE).
