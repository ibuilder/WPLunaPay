# WP LunaPay — MoonPay Crypto Payments for WooCommerce

[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue?logo=wordpress)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple?logo=woocommerce)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://www.php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green)](LICENSE.txt)
[![Tests](https://img.shields.io/badge/tests-131%20passing-brightgreen)](#running-tests)

Accept Bitcoin, Ethereum, USDC, and 100+ other cryptocurrencies at WooCommerce checkout — powered by [MoonPay](https://moonpay.com).

---

## Overview

WP LunaPay connects your WooCommerce store to the MoonPay crypto on-ramp. Customers pay with their preferred cryptocurrency; MoonPay handles fiat settlement. No card data ever touches your server.

The plugin ships with a **6-step Setup Wizard**, three widget display modes, four shortcodes, a sidebar widget, a full transactions admin page, and a 131-test PHPUnit suite.

---

## Features

### Payment Gateway
- Appears at checkout alongside all other WooCommerce payment methods
- **Three widget modes** — Redirect, embedded iFrame, or modal popup
- Signed widget URLs (HMAC-SHA256) prevent URL tampering
- Configurable minimum and maximum order amounts
- Configurable order statuses for pending / complete / failed events
- Custom payment title, description, button text, and icon toggle
- Custom success message on the thank-you page
- Accent colour picker passed through to the MoonPay widget
- Default and allowed cryptocurrency lists

### Webhook Listener
- Listens at `/?wc-api=wp_lunapay_webhook`
- Signature verification in production mode (skipped in sandbox)
- Maps MoonPay `completed`, `failed`, `cancelled`, and `pending` events to WooCommerce order statuses

### Shortcodes
| Shortcode | Purpose |
|-----------|---------|
| `[moonpay_buy]` | Full buy widget — iframe, popup, or redirect |
| `[moonpay_order_status]` | Payment status for a WooCommerce order |
| `[moonpay_crypto_price]` | Live price ticker (60-second transient cache) |
| `[moonpay_button]` | Styled call-to-action button |

### Sidebar Widget
Three widget types available under **Appearance → Widgets**:
- Buy Button
- Price Ticker
- Embedded iFrame

### Admin Features
| Feature | Location |
|---------|----------|
| **Setup Wizard** (6 steps) | WooCommerce → MoonPay Setup |
| **Transactions Page** | WooCommerce → MoonPay Transactions |
| **Dashboard Widget** | WordPress Dashboard |
| **Order Metabox** | Individual WooCommerce order edit screen |
| **Contextual notices** | Admin screens — dismissible |
| **Admin bar indicator** | Sandbox / Live badge |

### Developer-Friendly
- HPOS (High-Performance Order Storage) compatible
- Translation-ready — 258 translatable strings, `.pot` template included
- 131-test PHPUnit suite
- No Composer runtime dependencies — pure WordPress/WooCommerce APIs
- GPL-2.0-or-later

---

## Requirements

| Requirement | Minimum version |
|-------------|-----------------|
| WordPress | 6.4 |
| WooCommerce | 8.0 |
| PHP | 8.1 |
| MoonPay account | — |

---

## Installation

### From WordPress Admin (recommended)
1. Download the latest `.zip` from [Releases](../../releases).
2. Go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip, click **Install Now**, then **Activate**.

### Manual
1. Clone or download this repository.
2. Copy the `wp-lunapay` folder to `wp-content/plugins/`.
3. Activate the plugin from **Plugins** in your WordPress admin.

---

## Configuration

The fastest path is the **Setup Wizard** at **WooCommerce → MoonPay Setup**:

| Step | What you do |
|------|-------------|
| 1 — Get Keys | Open the [MoonPay dashboard](https://dashboard.moonpay.com/developers/api_keys) and copy your API keys |
| 2 — Enter Keys | Paste your publishable and secret keys; test the connection |
| 3 — Webhook | Copy the webhook URL into your MoonPay dashboard |
| 4 — Configure | Choose widget mode, default currency, accent colour |
| 5 — Test Order | Create a sandbox order and walk through the full payment flow |
| 6 — Go Live | Review the checklist; confirm to switch to production keys |

You can also configure everything manually at **WooCommerce → Settings → Payments → MoonPay Crypto Payments**.

### Sandbox vs Live

Enable **Sandbox Mode** and use `pk_test_` / `sk_test_` keys to test without real transactions. Switch to live keys via the wizard or by unticking Sandbox Mode in settings.

### Webhook URL

Add this URL to your MoonPay dashboard as a webhook endpoint:

```
https://yoursite.com/?wc-api=wp_lunapay_webhook
```

Use **Step 3 of the Setup Wizard → Test Reachability** to verify it is accessible before going live.

---

## Shortcode Reference

### `[moonpay_buy]`

Embeds a full MoonPay buy widget.

```
[moonpay_buy currency="eth" amount="100" mode="popup" height="600"]
```

| Attribute | Default | Options |
|-----------|---------|---------|
| `currency` | gateway default | Any MoonPay currency code |
| `amount` | — | Numeric fiat amount |
| `mode` | `iframe` | `iframe`, `popup`, `redirect` |
| `height` | `600` | Pixels (min 300) |
| `class` | — | Extra CSS class on the wrapper |

---

### `[moonpay_order_status]`

Displays the MoonPay payment status for an order. Auto-detects the order on the thank-you page.

```
[moonpay_order_status order_id="123"]
```

Only visible to shop managers and the order's customer.

---

### `[moonpay_crypto_price]`

Shows a live price for a cryptocurrency. Results are cached for 60 seconds.

```
[moonpay_crypto_price currency="btc" fiat="usd" show_currency="yes"]
```

---

### `[moonpay_button]`

A styled CTA button that opens the MoonPay widget.

```
[moonpay_button label="Buy BTC Now" currency="btc" amount="50"]
```

---

## Development

### Clone and set up

```bash
git clone https://github.com/your-org/wp-lunapay.git
cd wp-lunapay
composer install
```

### Running tests

The test suite requires a WordPress test environment. With [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/):

```bash
npm install -g @wordpress/env
wp-env start
vendor/bin/phpunit
```

Or against an existing installation:

```bash
export WP_TESTS_DIR=/path/to/wordpress-tests-lib
vendor/bin/phpunit
```

### Linting

```bash
composer run test
```

### File structure

```
wp-lunapay/
├── wp-lunapay.php              # Plugin header and bootstrap
├── uninstall.php               # Clean-up on plugin deletion
├── includes/
│   ├── class-wp-lunapay-api.php        # MoonPay HTTP client
│   ├── class-wp-lunapay-gateway.php    # WooCommerce payment gateway
│   ├── class-wp-lunapay-webhook.php    # Incoming webhook handler
│   ├── class-wp-lunapay-shortcodes.php # All four shortcodes + AJAX
│   ├── class-wp-lunapay-widget.php     # Sidebar widget
│   └── class-wp-lunapay-order-metabox.php
├── admin/
│   ├── class-wp-lunapay-admin-page.php     # Transactions page + CSV export
│   ├── class-wp-lunapay-setup-wizard.php   # 6-step guided setup
│   ├── class-wp-lunapay-dashboard-widget.php
│   └── class-wp-lunapay-notices.php
├── assets/
│   ├── css/
│   └── js/
├── languages/
│   └── wp-lunapay.pot
└── templates/
    └── payment-widget.php
```

---

## Hooks

### Actions

| Hook | When it fires |
|------|---------------|
| `wp_lunapay_webhook_processed` | After a webhook event is processed. Receives `$order_id`, `$mp_status`, `$data`. |

### Filters

No public filters yet. Open an issue or PR to request one.

---

## Security

- Webhook signatures are verified in production mode using HMAC-SHA256.
- All admin forms are protected by nonces and `manage_woocommerce` capability checks.
- All database queries use `$wpdb->prepare()`.
- All output is escaped (`esc_html`, `esc_url`, `wp_kses_post`).
- No data is sent to external servers other than MoonPay's API when a widget URL is built or a connection test is run.

To report a security vulnerability, please email **security@wprealwise.com** rather than opening a public issue.

---

## FAQ

**Does MoonPay support refunds via API?**
No. Process refunds manually in your MoonPay merchant dashboard.

**Which cryptocurrencies are supported?**
All currencies MoonPay supports (100+). Restrict them via the "Allowed Cryptocurrencies" setting (comma-separated codes, e.g. `btc,eth,usdc`).

**Is this PCI compliant?**
Yes. No card or payment data touches your server. The MoonPay widget runs in an isolated context.

**Can I test without real transactions?**
Yes. Enable Sandbox Mode in settings and use `pk_test_` / `sk_test_` keys from the MoonPay dashboard.

**Does it work with WooCommerce High-Performance Order Storage (HPOS)?**
Yes. HPOS compatibility is declared and all order queries support both storage backends.

---

## Changelog

See [readme.txt](readme.txt#changelog) for the full changelog.

### 1.0.0
- Initial release
- Full WooCommerce payment gateway with MoonPay
- 6-step Setup Wizard
- 4 shortcodes and sidebar widget
- Transactions admin page with CSV export
- Dashboard widget and contextual admin notices
- HPOS compatible
- 131-test PHPUnit suite
- 258 translatable strings

---

## Contributing

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/my-feature`.
3. Write tests for any new behaviour.
4. Open a pull request describing what you changed and why.

Please follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) and keep commits focused.

---

## License

GPL-2.0-or-later. See [LICENSE.txt](LICENSE.txt) for the full text.

---

> Built by [WPLunaPay](https://wprealwise.com) · [MoonPay](https://moonpay.com) is an independent third-party service. WP LunaPay is not affiliated with or endorsed by MoonPay.
