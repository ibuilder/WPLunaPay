#!/bin/sh
# WP-CLI automated setup script — runs once to install WordPress + WooCommerce
# NOTE: This file must have LF (Unix) line endings to work inside Docker.

set -e

SITE_URL="http://localhost:8080"
ADMIN_USER="admin"
ADMIN_PASS="admin123"
ADMIN_EMAIL="admin@example.com"
SITE_TITLE="WPLunaPay Store"

cd /var/www/html

# Wait for WordPress core files (volume may still be syncing)
echo "Waiting for WordPress core files..."
i=0
until [ -f wp-load.php ]; do
  i=$((i+1))
  if [ $i -gt 30 ]; then echo "Timed out waiting for wp-load.php"; exit 1; fi
  sleep 3
done

# Wait for MySQL to be ready
echo "Waiting for database connection..."
i=0
until wp db check --allow-root 2>/dev/null; do
  i=$((i+1))
  if [ $i -gt 20 ]; then echo "Timed out waiting for DB"; exit 1; fi
  sleep 3
done

# Install WordPress if not already installed
if ! wp core is-installed --allow-root 2>/dev/null; then
  echo "Installing WordPress..."
  wp core install \
    --url="$SITE_URL" \
    --title="$SITE_TITLE" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASS" \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email \
    --allow-root
else
  echo "WordPress already installed, skipping."
fi

# Install and activate WooCommerce
echo "Installing WooCommerce..."
wp plugin install woocommerce --activate --allow-root

# Install Storefront theme (WooCommerce's official theme)
echo "Installing Storefront theme..."
wp theme install storefront --activate --allow-root

# Activate our MoonPay gateway plugin (already mounted via volume)
echo "Activating MoonPay Gateway plugin..."
wp plugin activate wc-moonpay-gateway --allow-root

# Configure basic WooCommerce options
wp option update woocommerce_store_address "123 Crypto Lane" --allow-root
wp option update woocommerce_default_country "US" --allow-root
wp option update woocommerce_currency "USD" --allow-root
wp option update woocommerce_calc_taxes "no" --allow-root

# Set permalink structure (required for WooCommerce REST API / webhook endpoint)
wp rewrite structure '/%postname%/' --hard --allow-root
wp rewrite flush --allow-root

# Create a sample product for testing
echo "Creating sample product..."
wp wc product create \
  --name="Sample Crypto Product" \
  --type=simple \
  --regular_price=49.99 \
  --description="A sample product purchasable with MoonPay crypto payments." \
  --status=publish \
  --user=admin \
  --allow-root 2>/dev/null || echo "(Product may already exist, skipping)"

echo ""
echo "================================================"
echo "  Setup complete!"
echo "  WordPress:  $SITE_URL"
echo "  Admin:      $SITE_URL/wp-admin"
echo "  User:       $ADMIN_USER"
echo "  Password:   $ADMIN_PASS"
echo ""
echo "  Next steps:"
echo "  1. Go to WooCommerce > Settings > Payments > MoonPay"
echo "  2. Enter your Publishable Key and Secret Key"
echo "  3. Add webhook URL to your MoonPay dashboard:"
echo "     $SITE_URL/?wc-api=wc_moonpay_webhook"
echo "================================================"
