<?php
/**
 * Plugin uninstall — runs on plugin deletion (not deactivation).
 *
 * @package WPLunaPay
 * @copyright 2025 WPLunaPay (https://wprealwise.com)
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'woocommerce_moonpay_settings' );
delete_option( 'wp_lunapay_db_version' );
delete_option( 'wp_lunapay_installed' );
delete_option( 'wp_lunapay_setup' );

$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_wp_lunapay_price_%'
	    OR option_name LIKE '_transient_timeout_wp_lunapay_price_%'"
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->delete( $wpdb->usermeta, [ 'meta_key' => 'wp_lunapay_dismissed_notices' ] );

$wp_lunapay_test_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	"SELECT post_id FROM {$wpdb->postmeta}
	 WHERE meta_key = '_moonpay_test_product' AND meta_value = '1'"
);
foreach ( $wp_lunapay_test_ids as $wp_lunapay_pid ) {
	wp_delete_post( (int) $wp_lunapay_pid, true );
}