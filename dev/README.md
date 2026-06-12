# Local development environment

Docker-based Magento 2 store for testing the **lomi.** payment module.

## Prerequisites

- Docker Desktop
- Bash (Git Bash on Windows)
- lomi. **test** API keys from [dashboard.lomi.africa](https://dashboard.lomi.africa)

## Quick start

```bash
cd dev
cp .env.example .env
# Edit .env: LOMI_TEST_SK and LOMI_TEST_WEBHOOK_SECRET

docker compose up -d --build
# Wait ~3 minutes on first run (Magento installs automatically)

bash setup.sh
```

| Service    | URL |
|------------|-----|
| Storefront | http://localhost:8080 |
| Admin      | http://localhost:8080/admin |
| Admin user | `admin` / `Admin12345!` |

## Webhooks on localhost

lomi. must reach your webhook over HTTPS. Use [ngrok](https://ngrok.com/):

```bash
ngrok http 8080
```

Then update Magento base URLs:

```bash
docker compose exec --user www-data magento php bin/magento config:set web/unsecure/base_url "https://YOUR-NGROK-URL/"
docker compose exec --user www-data magento php bin/magento config:set web/secure/base_url "https://YOUR-NGROK-URL/"
docker compose exec --user www-data magento php bin/magento cache:flush
```

Register the webhook in the lomi. dashboard (test mode):

- URL: `https://YOUR-NGROK-URL/lomi/payment/webhook`
- Events: `PAYMENT_SUCCEEDED` (minimum)
- Copy the `whsec_…` signing secret into admin or `.env` as `LOMI_TEST_WEBHOOK_SECRET`, then re-run the webhook secret part of setup or:

```bash
docker compose exec --user www-data magento php bin/magento config:set payment/lomi/test_webhook_secret "whsec_..."
docker compose exec --user www-data magento php bin/magento cache:flush
```

## Test card (sandbox)

| Field | Value |
|-------|-------|
| Card number | `4242 4242 4242 4242` |
| Expiry | Any future date |
| CVC | Any 3 digits |

See [Sandbox payments](https://docs.lomi.africa/start/sandbox-payments) for decline and 3DS test cards.

## Verify webhook config (no secrets printed)

```bash
docker compose exec --user www-data magento php app/code/Lomi/Payments/dev/check-webhook-config.php
```

Expected: `plugin_whsec=yes`, `plugin_secret_length=70` (approx.).

## Troubleshooting

| Symptom | Check |
|---------|--------|
| Webhook 401 `auth failed` | `whsec_…` in Magento must match the **test** webhook in the dashboard (same environment as Test Mode). |
| Webhook 200 `ignored` on test button | Normal for `TEST_WEBHOOK`. Real payments send `PAYMENT_SUCCEEDED`. |
| Order stays Pending | Webhook `PAYMENT_SUCCEEDED` must return 200; callback URL must be reachable; session must be `completed` in lomi. API. |
| Branding images missing | `php bin/magento setup:static-content:deploy -f en_US` then flush cache. |
| Obscure admin fields | Re-paste the **full** secret when saving, or use `config:set` via CLI. |

## Files

| File | Purpose |
|------|---------|
| `docker-compose.yml` | MariaDB, OpenSearch, Magento |
| `setup.sh` | Enables module, sets test keys, deploys static assets |
| `.env.example` | Template for test credentials |
| `check-webhook-config.php` | Validates webhook secret is loaded |
| `inspect-order.php` | Order status + lomi session id |
| `verify-order-session.php` | Fetches session from lomi. API; `--apply` marks order paid if completed |

## Production

Do **not** deploy this Docker stack to production. For live stores, see the main [README](../README.md) and [docs/GUIDE.fr.md](../docs/GUIDE.fr.md).
