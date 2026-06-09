<?php
/**
 * Contextual admin notices for WP LunaPay.
 *
 * Shows one notice at a time, per-user dismissible.
 *
 * @package WPLunaPay
 * @copyright 2025 WPLunaPay (https://wprealwise.com)
 */

defined( 'ABSPATH' ) || exit;

class WP_LunaPay_Notices {

	public static function init(): void {
		add_action( 'admin_notices', [ self::class, 'show_notice' ] );
		add_action( 'wp_ajax_wp_lunapay_dismiss_notice', [ self::class, 'ajax_dismiss' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function enqueue(): void {
		wp_enqueue_script(
			'wp-lunapay-notices',
			WP_LUNAPAY_PLUGIN_URL . 'assets/js/notices.js',
			[ 'jquery' ],
			WP_LUNAPAY_VERSION,
			true
		);
		wp_localize_script( 'wp-lunapay-notices', 'wpLunaPayNotices', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wp_lunapay_admin' ),
		] );
	}

	// ------------------------------------------------------------------
	// Determine & display one notice
	// ------------------------------------------------------------------

	public static function show_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$user_id   = get_current_user_id();
		$dismissed = (array) get_user_meta( $user_id, 'wp_lunapay_dismissed_notices', true );
		$opts      = get_option( 'woocommerce_moonpay_settings', [] );
		$setup     = get_option( 'wp_lunapay_setup', [] );

		$notice = self::resolve_notice( $opts, $setup, $dismissed );
		if ( ! $notice ) {
			return;
		}

		[ 'id' => $id, 'type' => $type, 'message' => $message ] = $notice;
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible wp-lunapay-notice" data-notice-id="<?php echo esc_attr( $id ); ?>">
			<p><?php echo wp_kses_post( $message ); ?></p>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Notice resolver
	// ------------------------------------------------------------------

	/**
	 * Returns the first applicable notice not yet dismissed.
	 *
	 * @param array $opts      Gateway settings.
	 * @param array $setup     wp_lunapay_setup option.
	 * @param array $dismissed Array of already-dismissed notice IDs.
	 * @return array{id:string,type:string,message:string}|null
	 */
	private static function resolve_notice( array $opts, array $setup, array $dismissed ): ?array {
		$pub = trim( $opts['publishable_key'] ?? '' );
		$sec = trim( $opts['secret_key'] ?? '' );

		// 1. No API keys.
		if ( ( empty( $pub ) || empty( $sec ) ) && ! in_array( 'no_keys', $dismissed, true ) ) {
			$url = admin_url( 'admin.php?page=wp-lunapay-setup' );
			return [
				'id'      => 'no_keys',
				'type'    => 'error',
				'message' => sprintf(
					/* translators: %s = Setup Wizard URL */
					__( '<strong>MoonPay:</strong> API keys are not configured. <a href="%s">Run the Setup Wizard</a> to get started.', 'wp-lunapay' ),
					esc_url( $url )
				),
			];
		}

		// 2. No webhook configured (no last webhook received).
		if ( empty( $setup['webhook_reachable'] ) && ! in_array( 'no_webhook', $dismissed, true ) ) {
			$url = admin_url( 'admin.php?page=wp-lunapay-setup&step=3' );
			return [
				'id'      => 'no_webhook',
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %s = Webhook setup URL */
					__( '<strong>MoonPay:</strong> Webhook is not set up. <a href="%s">Configure the webhook</a> to receive payment updates.', 'wp-lunapay' ),
					esc_url( $url )
				),
			];
		}

		// 3. No test order done.
		if ( empty( $setup['test_order_id'] ) && ! in_array( 'no_test', $dismissed, true ) ) {
			$url = admin_url( 'admin.php?page=wp-lunapay-setup&step=5' );
			return [
				'id'      => 'no_test',
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %s = Setup Wizard step URL */
					__( '<strong>MoonPay:</strong> You haven\'t run a test order yet. <a href="%s">Create a test order</a> to verify the integration.', 'wp-lunapay' ),
					esc_url( $url )
				),
			];
		}

		// 4. Sandbox reminder (only on settings page).
		$screen = get_current_screen();
		$on_settings = $screen && $screen->id === 'woocommerce_page_wc-settings';
		if ( $on_settings && ( $opts['sandbox'] ?? 'yes' ) === 'yes' && ( $opts['enabled'] ?? 'no' ) === 'yes' && ! in_array( 'sandbox_reminder', $dismissed, true ) ) {
			$url = admin_url( 'admin.php?page=wp-lunapay-setup&step=6' );
			return [
				'id'      => 'sandbox_reminder',
				'type'    => 'info',
				'message' => sprintf(
					/* translators: %s = Go Live URL */
					__( '<strong>MoonPay:</strong> The gateway is running in sandbox mode. <a href="%s">Go Live</a> when you are ready.', 'wp-lunapay' ),
					esc_url( $url )
				),
			];
		}

		// 5. Went live success.
		if ( ! empty( $setup['went_live_at'] ) && ( $opts['sandbox'] ?? 'yes' ) === 'no' && ! in_array( 'went_live', $dismissed, true ) ) {
			return [
				'id'      => 'went_live',
				'type'    => 'success',
				'message' => __( '<strong>MoonPay:</strong> Your gateway is now live! You are accepting real crypto payments.', 'wp-lunapay' ),
			];
		}

		return null;
	}

	// ------------------------------------------------------------------
	// AJAX dismiss
	// ------------------------------------------------------------------

	public static function ajax_dismiss(): void {
		check_ajax_referer( 'wp_lunapay_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}

		$notice_id = sanitize_key( $_POST['notice_id'] ?? '' );
		if ( ! $notice_id ) {
			wp_send_json_error( [ 'message' => 'Missing notice_id' ] );
		}

		$user_id   = get_current_user_id();
		$dismissed = (array) get_user_meta( $user_id, 'wp_lunapay_dismissed_notices', true );
		if ( ! in_array( $notice_id, $dismissed, true ) ) {
			$dismissed[] = $notice_id;
			update_user_meta( $user_id, 'wp_lunapay_dismissed_notices', $dismissed );
		}

		wp_send_json_success();
	}
}