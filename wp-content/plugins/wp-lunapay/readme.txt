=== MoonPay Crypto Payments ===
Contributors: wplunapay
Tags: woocommerce, moonpay, cryptocurrency, bitcoin, payments
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept 100+ cryptocurrencies at checkout via MoonPay. Full payment gateway, shortcodes, widgets, setup wizard, and rich admin controls — by WPLunaPay.

== Description ==

**MoonPay Crypto Payments** by [WPLunaPay](https://wprealwise.com) integrates the MoonPay on-ramp directly into your WooCommerce store. Customers can pay with Bitcoin, Ethereum, USDC, Polygon, and 100+ other cryptocurrencies. Fiat settlement is handled by MoonPay.

= Core Features =

* **WooCommerce Payment Gateway** — appears at checkout alongside other payment methods
* **Three widget display modes** — Redirect, embedded iFrame, or modal popup
* **Signed widget URLs** — HMAC-SHA256 signatures prevent tampering
* **Webhook listener** — automatically updates order status on MoonPay events
* **Minimum / maximum order amount** — restrict which orders can use crypto payment
* **Configurable order statuses** — set pending/complete/failed statuses independently
* **Sandbox support** — test with MoonPay sandbox keys before going live
* **Debug logging** — writes to WooCommerce native log viewer
* **HPOS compatible** — supports WooCommerce High-Performance Order Storage

= Checkout Customisation =

* Custom payment title and description
* Custom order button text
* Configurable iFrame height
* Accent colour picker for the MoonPay widget
* Show or hide the payment icon
* Custom success message on the thank-you page
* Default crypto pre-selection
* Allowed currencies list (comma-separated codes)

= Shortcodes =

**[moonpay_buy]** — Embed a full MoonPay buy widget on any page or post.
Attributes: `currency`, `fiat`, `amount`, `mode` (iframe|redirect|popup), `height`, `width`

Example: `[moonpay_buy currency="btc" fiat="usd" amount="100" mode="popup"]`

**[moonpay_order_status order_id="123"]** — Display payment status for an order.
Auto-detected on the thank-you page if `order_id` is omitted.

**[moonpay_crypto_price currency="eth" fiat="usd" show_change="true"]** — Live price ticker (refreshes every 60 s).

**[moonpay_button label="Buy BTC" currency="btc" amount="50"]** — Styled CTA button.
Attributes: `label`, `currency`, `fiat`, `amount`, `target`

= Sidebar Widget =

Go to **Appearance → Widgets** → drag "MoonPay Crypto Widget" into any sidebar.
Types: Buy Button, Price Ticker, Embedded iFrame.

= Admin Features =

* **Setup Wizard** (6 steps) — guided walkthrough from API keys to go-live
* **WP Dashboard Widget** — setup progress checklist + 30-day revenue stats
* **Transactions Page** — WooCommerce → MoonPay — paginated order list, stats cards, CSV export
* **Order Metabox** — per-order MoonPay status, transaction ID, last webhook event, "Refresh from API" button
* **Smart Admin Notices** — contextual, dismissible notices for each incomplete step
* **Admin Bar Indicator** — sandbox/live mode badge always visible
* **API Connection Test** — verify keys work directly from the wizard
* **Webhook Reachability Test** — confirm your URL is accessible before going live
* **Go-Live Confirmation Modal** — checklist gate before switching to production keys

= Configuration =

1. Activate the plugin
2. Open **WooCommerce → MoonPay Setup** (Setup Wizard)
3. Follow the 6 steps: get keys → enter & test → webhook → configure → test order → go live

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate through **Plugins** in WordPress admin
3. Open WooCommerce → MoonPay Setup to run the Setup Wizard

== Frequently Asked Questions ==

= Does MoonPay support refunds via API? =
MoonPay does not expose a merchant refund API. Process refunds manually in your MoonPay merchant dashboard.

= Which cryptocurrencies are supported? =
All currencies supported by MoonPay (100+). Restrict them via the "Allowed Cryptocurrencies" setting.

= Is this PCI compliant? =
Yes. No card data ever touches your server. The MoonPay widget handles all payment processing in an isolated context.

= Can I test before going live? =
Yes. Enable Sandbox mode and use MoonPay test keys (pk_test_ / sk_test_). The Setup Wizard creates a test order and verifies the full payment flow automatically.

= Can I set a minimum order amount? =
Yes. Set "Minimum Order Amount" in WooCommerce → Settings → Payments → MoonPay. Orders below this value will not show the crypto payment option.


== Third Party Services ==

This plugin connects to **MoonPay** (https://www.moonpay.com) to process cryptocurrency payments on behalf of your customers. By activating and using this plugin, your site will send data to MoonPay's servers.

**What data is sent:**
* Order amount, currency, and the customer's wallet address (if pre-filled) to construct a signed widget URL.
* Webhook payloads are received *from* MoonPay to update order statuses.
* API keys are sent to MoonPay's REST API (`https://api.moonpay.com`) to verify credentials.

**When it is sent:**
* At checkout when the customer selects the MoonPay payment option.
* When the Setup Wizard tests your API connection.
* When MoonPay sends a webhook notification after a transaction.

**MoonPay Terms of Service:** https://www.moonpay.com/legal/terms_of_use
**MoonPay Privacy Policy:** https://www.moonpay.com/legal/privacy_policy

No payment card data or personal customer data is processed by this plugin directly. All payment processing occurs within MoonPay's PCI-compliant environment.

== Screenshots ==

1. Checkout — MoonPay Crypto Payments option alongside other gateways.
2. MoonPay widget in embedded iFrame mode at the order-pay screen.
3. Setup Wizard — Step 2: enter and test your API keys.
4. Setup Wizard — Step 6: go-live checklist.
5. WooCommerce → MoonPay Transactions admin page with stats and CSV export.
6. Per-order MoonPay Payment Details metabox showing transaction ID and status.
7. WordPress dashboard widget with setup progress and 30-day revenue stats.
8. Admin bar sandbox/live mode indicator.
== Changelog ==

= 1.0.0 — 2025-06-06 =
* Initial release by WPLunaPay (https://wprealwise.com)
* Full WooCommerce payment gateway integration with MoonPay
* 6-step setup wizard with connection test, webhook verification, and test order
* Sandbox and live mode with go-live confirmation modal
* 4 shortcodes: moonpay_buy, moonpay_order_status, moonpay_crypto_price, moonpay_button
* Sidebar widget with buy button, price ticker, and embedded iFrame modes
* Admin transactions page with stats cards and CSV export
* WP dashboard widget with setup progress and 30-day stats
* Smart contextual admin notices
* Admin bar sandbox/live indicator
* Min/max order amount, custom button text, iFrame height, icon toggle, success message
* HPOS (High-Performance Order Storage) compatible
* Translation-ready with full .pot template (258 strings)
* 150-test PHPUnit suite (166 assertions)

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade needed.
