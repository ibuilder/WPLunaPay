# WP LunaPay — MoonPay Crypto Payments for WooCommerce

> Accept Bitcoin, Ethereum, USDC, and 100+ cryptocurrencies at checkout via [MoonPay](https://moonpay.com). Built by [WPLunaPay](https://wprealwise.com).

[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue?logo=wordpress)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple?logo=woocommerce)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL--2.0%2B-green)](LICENSE.txt)
[![Tests](https://img.shields.io/badge/Tests-150%20passing-brightgreen)](#testing)

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Quick Start (Docker)](#quick-start-docker)
- [Plugin Installation](#plugin-installation)
- [Configuration](#configuration)
- [Shortcodes](#shortcodes)
- [Sidebar Widget](#sidebar-widget)
- [Admin Features](#admin-features)
- [Developer Notes](#developer-notes)
- [Testing](#testing)
- [Project Structure](#project-structure)
- [Changelog](#changelog)
- [License](#license)

---

## Overview

**WP LunaPay** is a full-featured WooCommerce payment gateway that integrates [MoonPay's](https://moonpay.com) on-ramp widget directly into your store. Customers pay with crypto; MoonPay handles conversion and fiat settlement to your account. No crypto custody required on your end.

The repository includes:
- **WordPress + WooCommerce site** — Docker Compose setup for instant local development
- **`wp-lunapay` plugin** — the payment gateway plugin, ready for WordPress.org submission

---

## Features

### Payment Gateway
- ✅ Supports 100+ cryptocurrencies (ETH, BTC, USDC, USDC Polygon, and more)
- ✅ Three widget display modes: **Redirect**, **embedded iFrame**, **Modal Popup**
- ✅ HMAC-SHA256 signed widget URLs (tamper-proof)
- ✅ Automatic order status updates via signed webhooks
- ✅ Minimum and maximum order amount limits
- ✅ Configurable order statuses for pending / completed / failed
- ✅ HPOS (High-Performance Order Storage) compatible

### Checkout Customisation
- ✅ Custom payment title, description, and order button text
- ✅ Configurable iFrame height
- ✅ Accent colour picker for the MoonPay widget
- ✅ Show / hide the payment icon
- ✅ Custom thank-you page success message
- ✅ Default crypto pre-selection and allowed currencies list

### Shortcodes
| Shortcode | Description |
|-----------|-------------|
| `[moonpay_buy]` | Full MoonPay buy widget (iframe / popup / redirect) |
| `[moonpay_order_status]` | Shows payment status for an order |
| `[moonpay_crypto_price]` | Live price ticker (auto-refreshes every 60 s) |
| `[moonpay_button]` | Styled CTA button linking to MoonPay |

### Admin
- ✅ 6-step **Setup Wizard** (get keys → enter & test → webhook → configure → test order → go live)
- ✅ **API connection test** — verifies keys against MoonPay live API
- ✅ **Webhook reachability test** — works in Docker and on live servers
- ✅ **Test order creator** — one-click sandbox order with checkout link
- ✅ **Go-Live confirmation modal** — checklist review before switching to production
- ✅ **Transactions page** — paginated order list, stats cards, CSV export
- ✅ **WP Dashboard widget** — setup progress + 30-day revenue
- ✅ **Admin bar indicator** — always-visible Sandbox / Live badge
- ✅ **Smart admin notices** — contextual, per-user dismissible
- ✅ **Per-order metabox** — transaction ID, MoonPay status, last webhook event, refresh button

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | 8.1 |
| WordPress | 6.4 |
| WooCommerce | 8.0 |
| MoonPay account | [Sign up free](https://dashboard.moonpay.com) |

---

## Quick Start (Docker)

```bash
# 1. Clone the repo
git clone https://github.com/wplunapay/wp-lunapay.git
cd wp-lunapay

# 2. Start WordPress + MySQL
docker compose up -d

# 3. Install WordPress, WooCommerce & activate the plugin
docker compose run --rm wpcli

# 4. Open your site
open http://localhost:8080

# Admin panel
open http://localhost:8080/wp-admin
# User: admin  Password: admin123
```

The `wpcli` container runs `setup.sh` which:
- Installs WordPress
- Installs and activates WooCommerce
- Installs the Storefront theme
- Activates the `wp-lunapay` plugin
- Creates a sample product

---

## Plugin Installation

### From this repository
```bash
# Copy the plugin folder to your WordPress install
cp -r wp-content/plugins/wp-lunapay /path/to/wordpress/wp-content/plugins/

# Then activate in WP Admin → Plugins
```

### From a zip file
1. Download `wp-lunapay-1.0.0.zip` from [Releases](../../releases)
2. WP Admin → Plugins → Add New → Upload Plugin
3. Activate

---

## Configuration

### Step-by-step (recommended)
1. WP Admin → **WooCommerce → MoonPay Setup** (or click "Setup Wizard" in the Plugins list)
2. Follow the 6 steps:

| Step | Action |
|------|--------|
| 1 | Open [MoonPay Dashboard](https://dashboard.moonpay.com/api_keys) and copy your keys |
| 2 | Paste your Publishable Key and Secret Key — click **Test Connection** then **Save Keys** |
| 3 | Copy the webhook URL and add it to your [MoonPay Webhooks](https://dashboard.moonpay.com/webhooks) — click **Test Reachability** |
| 4 | Choose widget mode, accent colour, and default currency — click **Save Configuration** |
| 5 | Click **Create Test Order**, open the checkout link, complete a sandbox payment |
| 6 | Review the checklist, click **Switch to Live Mode** |

### Manual (WC Settings)
**WooCommerce → Settings → Payments → MoonPay Crypto Payments**

### Webhook URL
Register this URL in your MoonPay dashboard:
```
https://yoursite.com/?wc-api=wp_lunapay_webhook
```

---

## Shortcodes

```
[moonpay_buy currency="eth" fiat="usd" amount="100" mode="iframe" height="600"]
[moonpay_buy mode="popup"]
[moonpay_buy mode="redirect"]

[moonpay_order_status]
[moonpay_order_status order_id="123"]

[moonpay_crypto_price currency="eth" fiat="usd" show_change="true"]

[moonpay_button label="Buy Bitcoin" currency="btc" fiat="usd" amount="50"]
```

---

## Sidebar Widget

**Appearance → Widgets → MoonPay Crypto Widget**

Three types:
- **Buy Button** — styled CTA linking to the MoonPay widget
- **Price Ticker** — live crypto price, refreshes every 60 s
- **Embedded iFrame** — full MoonPay widget in the sidebar

---

## Admin Features

### Setup Wizard
`WooCommerce → MoonPay Setup`

Guides you through every step from API keys to go-live with live feedback at each stage.

### Transactions Page
`WooCommerce → MoonPay`

- Stats cards: Total Orders, Completed, Pending, Failed/Cancelled, Total Revenue
- Paginated orders table with WC status + MoonPay status columns
- **⬇ Export CSV** — downloads all MoonPay orders as a UTF-8 CSV (Excel compatible)

### WP Dashboard Widget
Always-visible on `/wp-admin`:
- Setup progress bar + checklist
- Last 30 days: Orders, Revenue, Pending count
- Quick links to all admin pages

### Admin Bar
Shows **🌙 MoonPay: Sandbox** (amber) or **🌙 MoonPay: Live** (green) for shop managers.

---

## Developer Notes

### Webhook signature verification
Signatures use HMAC-SHA256 over the raw request body, base64-encoded:
```php
$expected = base64_encode( hash_hmac( 'sha256', $payload, $secret_key, true ) );
```
- **Sandbox mode**: signature optional (simplifies local testing)
- **Live mode**: missing/invalid signature returns HTTP 401

### Price caching
The price ticker AJAX endpoint (`wp_lunapay_get_price`) caches results in WordPress transients for 60 seconds per currency pair to prevent API rate limiting.

### HPOS compatibility
The plugin declares HPOS compatibility and uses `wc_get_orders()` + `WC_Order` methods throughout. Direct DB queries fall back between classic `{$wpdb->postmeta}` and `{$wpdb->prefix}wc_orders_meta` based on runtime detection.

### Hooks

| Hook | Type | Description |
|------|------|-------------|
| `wp_lunapay_webhook_processed` | Action | Fired after a webhook updates an order. Args: `$order_id`, `$mp_status`, `$data` |
| `woocommerce_payment_gateways` | Filter | Used to register `WP_LunaPay_Gateway` |

---

## Testing

```bash
cd wp-content/plugins/wp-lunapay

# Install dev dependencies (PHPUnit 11)
composer install

# Run all tests
composer test

# Run with verbose output
./vendor/bin/phpunit --testdox
```

### Test coverage

| Suite | Tests | Covers |
|-------|-------|--------|
| `APITest` | 27 | HMAC signing, widget URL building, colorCode encoding, sandbox/live URLs, signature verification |
| `WebhookTest` | 22 | External ID regex, status mapping, stock restoration guard, sig round-trip |
| `GatewayTest` | 26 | Color pipeline, externalTransactionId format, API key guards, allowed currencies |
| `ShortcodeTest` | 17 | Defaults, sanitisation, price cache keys, order status visibility |
| `SettingsTest` | 38 | Min/max order amounts, iframe height clamping, button text, show_icon, success message |
| **Total** | **150** | **166 assertions** |

Tests run without a WordPress installation — only a minimal stub layer is required (`tests/bootstrap.php`).

---

## Project Structure

```
wp-content/plugins/wp-lunapay/
├── wp-lunapay.php                        # Plugin bootstrap, constants, hooks
├── uninstall.php                         # Cleanup on deletion
├── readme.txt                            # WordPress.org readme
├── LICENSE.txt                           # GPL-2.0
├── includes/
│   ├── class-wp-lunapay-api.php          # MoonPay API client (HMAC, REST)
│   ├── class-wp-lunapay-gateway.php      # WooCommerce payment gateway
│   ├── class-wp-lunapay-webhook.php      # Webhook receiver & order updater
│   ├── class-wp-lunapay-shortcodes.php   # 4 shortcodes + price AJAX
│   ├── class-wp-lunapay-widget.php       # Sidebar widget
│   └── class-wp-lunapay-order-metabox.php# Order detail panel
├── admin/
│   ├── class-wp-lunapay-setup-wizard.php # 6-step guided setup
│   ├── class-wp-lunapay-admin-page.php   # Transactions page + CSV export
│   ├── class-wp-lunapay-dashboard-widget.php # WP dashboard widget
│   └── class-wp-lunapay-notices.php      # Contextual admin notices
├── assets/
│   ├── css/
│   │   ├── moonpay-admin.css             # Admin, wizard, dashboard styles
│   │   ├── moonpay-gateway.css           # Checkout / receipt page
│   │   └── moonpay-shortcodes.css        # Shortcode & widget styles
│   ├── js/
│   │   ├── moonpay-admin.js              # Wizard AJAX, go-live, notices
│   │   ├── moonpay-gateway.js            # Checkout popup, postMessage
│   │   └── moonpay-shortcodes.js         # Price ticker, shortcode popups
│   └── images/
│       └── moonpay-logo.svg
├── languages/
│   └── wp-lunapay.pot                    # 258-string translation template
├── templates/
│   └── payment-widget.php               # Receipt page (iframe/popup/redirect)
├── tests/
│   ├── bootstrap.php                     # WP/WC stubs, no full install needed
│   └── unit/
│       ├── APITest.php
│       ├── GatewayTest.php
│       ├── SettingsTest.php
│       ├── ShortcodeTest.php
│       └── WebhookTest.php
├── composer.json                         # Dev: phpunit/phpunit ^10.5||^11
└── phpunit.xml.dist
```

---

## Changelog

### 1.0.0 — 2025-06-09
- Initial release
- Full WooCommerce payment gateway (redirect, iFrame, popup modes)
- 6-step setup wizard with live API test, webhook reachability check, test order creator
- Sandbox → live mode with go-live confirmation
- 4 shortcodes + sidebar widget
- Transactions admin page with CSV export
- WP dashboard widget, admin bar indicator, smart notices
- Min/max order amount, custom button text, iFrame height, icon toggle, success message
- HPOS compatible
- 150 PHPUnit tests

---

## License

GPL-2.0-or-later — see [LICENSE.txt](LICENSE.txt)

---

*Built with ❤️ by [WPLunaPay](https://wprealwise.com)*
