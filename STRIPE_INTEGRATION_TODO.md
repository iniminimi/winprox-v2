# Stripe integration — TODO

Scenario **A**: bestaande Checkout Session-aanroep bijgewerkt in
[`app/Services/Billing/StripeCheckoutService.php`](app/Services/Billing/StripeCheckoutService.php)
(HTTP form POST naar `https://api.stripe.com/v1/checkout/sessions`, geen Stripe PHP SDK).

Webhook blijft: [`app/Http/Controllers/Billing/StripeWebhookController.php`](app/Http/Controllers/Billing/StripeWebhookController.php)
→ route `POST /stripe/webhook`.

---

## Values to Replace

De `mode`, `success_url` en `cancel_url` in code zijn al echte WinProx-waarden (geen placeholders).
Wel moet je op de **server** de Price IDs en secrets invullen.

**Files:**
- [`.env.example`](.env.example)
- [`config/stripe.php`](config/stripe.php)
- [`app/Services/Billing/StripeCheckoutService.php`](app/Services/Billing/StripeCheckoutService.php)

| Field | Current Value | What to Set |
|-------|--------------|-------------|
| `mode` | `subscription` | Correct voor maandformules — niet wijzigen. |
| `success_url` / `cancel_url` | `/subscription?stripe=…` via `STRIPE_SUCCESS_PATH` / `STRIPE_CANCEL_PATH` | Al ok; optioneel paden in `.env`. |
| `line_items[].price` | `config('stripe.price_ids.{plan}')` | Zet in server-`.env`: `STRIPE_PRICE_WINPROX_5` / `_10` / `_25` / `_50` = echte `price_…` uit [Dashboard → Prices](https://dashboard.stripe.com/prices) (test, later live). |
| `STRIPE_SECRET` | (server) | `sk_test_…` of `sk_live_…` — projectnaam is **`STRIPE_SECRET`** (niet `STRIPE_SECRET_KEY`). |
| `STRIPE_WEBHOOK_SECRET` | (server) | `whsec_…` van het webhook-endpoint. |
| `ui_mode` | `hosted` | Standaard Hosted Checkout (REST). Studio’s `hosted_page` is SDK-specifiek. |

---

## Configured Parameters

Checkout Studio (`fixed_by_ui`) gezet in
[`app/Services/Billing/StripeCheckoutService.php`](app/Services/Billing/StripeCheckoutService.php):

| Parameter | Value |
|-----------|-------|
| `ui_mode` | `hosted` |
| `billing_address_collection` | `auto` |
| `phone_number_collection.enabled` | `true` |
| `automatic_tax.enabled` | `false` |
| `allow_promotion_codes` | `false` |
| `payment_method_collection` | `always` (mode = subscription) |
| `submit_type` | `auto` |
| `name_collection.individual` | enabled + optional |
| `name_collection.business` | enabled + optional |
| `integration_identifier` | `hosted_web_0001` |
| `origin_context` | `web` |

**WinProx (niet uit Studio, wel nodig voor activatie):** `client_reference_id`, `metadata[tenant_id|plan]`, `subscription_data[metadata]`, `customer` / `customer_email`.

---

## Setup and next steps

### Environment (server `.env`)

```env
BILLING_ALLOW_SELF_ACTIVATION=true
STRIPE_OFFER_CHECKOUT=true
STRIPE_SECRET=sk_test_…
STRIPE_WEBHOOK_SECRET=whsec_…
STRIPE_PRICE_WINPROX_5=price_…
STRIPE_PRICE_WINPROX_10=price_…
STRIPE_PRICE_WINPROX_25=price_…
STRIPE_PRICE_WINPROX_50=price_…
```

Daarna: `php artisan config:clear`.

### Flow

1. Admin kiest formule op `/subscription` → `StripeCheckoutService::createCheckoutSession`.
2. Redirect naar Stripe Hosted Checkout.
3. Success → fulfill via session; webhook `checkout.session.completed` activeert plan.
4. Maandelijks: `invoice.paid` verlengt; `customer.subscription.deleted` beëindigt.

### Webhook events

`checkout.session.completed`, `invoice.paid`, `customer.subscription.deleted`  
URL: `https://jouwdomein/stripe/webhook`

### Testen

- Test mode keys + test prices.
- Kaart: `4242 4242 4242 4242`, willekeurige toekomstige expiry/CVC.
- Daarna live: nieuwe live prices + `sk_live_` + live `whsec_`.

### BTW

`automatic_tax.enabled = false` (Studio). Catalogusprijzen zijn excl. BTW; 21% later apart (Stripe Tax of manuele tax rate) — niet in de price bakken.

### Resources

- https://docs.stripe.com/checkout/quickstart
- https://docs.stripe.com/mcp
- https://support.stripe.com
