<?php
/**
 * Plugin Name:       MoonPay Crypto Payments
 * Plugin URI:        https://wprealwise.com/wp-lunapay
 * Description:       Accept 100+ cryptocurrencies at checkout via MoonPay. Includes a full payment gateway, shortcodes, sidebar widgets, setup wizard, and rich admin controls.
 * Version:           1.0.0
 * Author:            WPLunaPay
 * Author URI:        https://wprealwise.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-lunapay
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * WC requires at least: 8.0
 * WC tested up to:   9.9
 *
 * @package WPLunaPay
 * @copyright 2025 WPLunaPay (https://wprealwise.com)
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_LUNAPAY_VERSION', '1.0.0' );
define( 'WP_LUNAPAY_PLUGIN_FILE', __FILE__ );
define( 'WP_LUNAPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_LUNAPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Declare WooCommerce HPOS compatibility
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Main plugin bootstrap — loads only when WooCommerce is active.
 */
function wp_lunapay_init(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' .
			     esc_html__( 'MoonPay Crypto Payments requires WooCommerce to be active.', 'wp-lunapay' ) .
			     '</p></div>';
		} );
		return;
	}

	// Core files — loaded on every request (frontend + admin)
	require_once WP_LUNAPAY_PLUGIN_DIR . 'includes/class-wp-lunapay-api.php';
	require_once WP_LUNAPAY_PLUGIN_DIR . 'includes/class-wp-lunapay-gateway.php';
	require_once WP_LUNAPAY_PLUGIN_DIR . 'includes/class-wp-lunapay-webhook.php';
	require_once WP_LUNAPAY_PLUGIN_DIR . 'includes/class-wp-lunapay-shortcodes.php';
	require_once WP_LUNAPAY_PLUGIN_DIR . 'includes/class-wp-lunapay-widget.php';

	// Admin-only files — never loaded on the frontend
	if ( is_admin() ) {
		require_once WP_LUNAPAY_PLUGIN_DIR . 'includes/class-wp-lunapay-order-metabox.php';
		require_once WP_LUNAPAY_PLUGIN_DIR . 'admin/class-wp-lunapay-admin-page.php';
		require_once WP_LUNAPAY_PLUGIN_DIR . 'admin/class-wp-lunapay-setup-wizard.php';
		require_once WP_LUNAPAY_PLUGIN_DIR . 'admin/class-wp-lunapay-dashboard-widget.php';
		require_once WP_LUNAPAY_PLUGIN_DIR . 'admin/class-wp-lunapay-notices.php';
	}

	// Register gateway
	add_filter( 'woocommerce_payment_gateways', function ( array $gateways ): array {
		$gateways[] = 'WP_LunaPay_Gateway';
		return $gateways;
	} );

	// Boot core subsystems (frontend + admin)
	WP_LunaPay_Shortcodes::init();
	WP_LunaPay_Webhook::init();

	// Boot admin-only subsystems
	if ( is_admin() ) {
		WP_LunaPay_Order_Metabox::init();
		WP_LunaPay_Admin_Page::init();
		WP_LunaPay_Setup_Wizard::init();
		WP_LunaPay_Dashboard_Widget::init();
		WP_LunaPay_Notices::init();
	}

	// Register widget
	add_action( 'widgets_init', function () {
		register_widget( 'WP_LunaPay_Widget' );
	} );

	// Admin bar sandbox/live indicator
	add_action( 'admin_bar_menu', function ( WP_Admin_Bar $bar ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) return;
		$opts       = get_option( 'woocommerce_moonpay_settings', [] );
		$is_sandbox = ( $opts['sandbox'] ?? 'yes' ) === 'yes';
		$is_enabled = ( $opts['enabled'] ?? 'no' ) === 'yes';
		if ( ! $is_enabled ) return;

		$label = $is_sandbox
			? '🌙 MoonPay: ' . __( 'Sandbox', 'wp-lunapay' )
			: '🌙 MoonPay: ' . __( 'Live', 'wp-lunapay' );
		$color = $is_sandbox ? '#856404' : '#155724';

		$bar->add_node( [
			'id'    => 'wp-lunapay-mode',
			'title' => '<span style="color:' . esc_attr( $color ) . ';font-weight:600;">' . esc_html( $label ) . '</span>',
			'href'  => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=moonpay' ),
			'meta'  => [ 'title' => __( 'MoonPay Gateway — click to open settings', 'wp-lunapay' ) ],
		] );
	}, 100 );

	// Store last-webhook-received timestamp for the wizard checklist
	add_action( 'wp_lunapay_webhook_processed', function ( int $order_id ): void {
		$setup                         = get_option( 'wp_lunapay_setup', [] );
		$setup['last_webhook_received'] = current_time( 'mysql' );
		update_option( 'wp_lunapay_setup', $setup );
	} );
}
add_action( 'plugins_loaded', 'wp_lunapay_init' );

// Activation / deactivation hooks
register_activation_hook( __FILE__, 'wp_lunapay_activate' );
register_deactivation_hook( __FILE__, 'wp_lunapay_deactivate' );

// Translations are loaded automatically by WordPress since version 4.6
// when the plugin is hosted on WordPress.org — no manual load_plugin_textdomain() needed.

function wp_lunapay_activate(): void {
	if ( ! get_option( 'wp_lunapay_db_version' ) ) {
		// Store install timestamp
		update_option( 'wp_lunapay_db_version', WP_LUNAPAY_VERSION );
		update_option( 'wp_lunapay_installed', time() );
	}
	flush_rewrite_rules();
}

function wp_lunapay_deactivate(): void {
	flush_rewrite_rules();
}

// Plugin action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( array $links ): array {
	$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=moonpay' );
	$wizard_url   = admin_url( 'admin.php?page=wp-lunapay-setup' );

	array_unshift(
		$links,
		'<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'wp-lunapay' ) . '</a>',
		'<a href="' . esc_url( $wizard_url ) . '" style="color:#7b3fe4;font-weight:600;">' . __( 'Setup Wizard', 'wp-lunapay' ) . '</a>'
	);
	return $links;
} );
