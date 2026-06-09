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

$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_wp_lunapay_price_%'
	    OR option_name LIKE '_transient_timeout_wp_lunapay_price_%'"
);

$wpdb->delete( $wpdb->usermeta, [ 'meta_key' => 'wp_lunapay_dismissed_notices' ] );

$test_ids = $wpdb->get_col(
	"SELECT post_id FROM {$wpdb->postmeta}
	 WHERE meta_key = '_moonpay_test_product' AND meta_value = '1'"
);
foreach ( $test_ids as $pid ) {
	wp_delete_post( (int) $pid, true );
}