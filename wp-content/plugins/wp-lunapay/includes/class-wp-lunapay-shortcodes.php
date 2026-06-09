<?php
/**
 * Shortcodes for WP LunaPay / MoonPay.
 *
 * Registers [moonpay_buy], [moonpay_order_status], [moonpay_crypto_price],
 * and [moonpay_button] shortcodes plus the price AJAX handler.
 *
 * @package WPLunaPay
 * @copyright 2025 WPLunaPay (https://wprealwise.com)
 */

defined( 'ABSPATH' ) || exit;

class WP_LunaPay_Shortcodes {

	public static function init(): void {
		add_shortcode( 'moonpay_buy',          [ self::class, 'sc_buy' ] );
		add_shortcode( 'moonpay_order_status', [ self::class, 'sc_order_status' ] );
		add_shortcode( 'moonpay_crypto_price', [ self::class, 'sc_crypto_price' ] );
		add_shortcode( 'moonpay_button',       [ self::class, 'sc_button' ] );

		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_action( 'wp_ajax_wp_lunapay_get_price',        [ self::class, 'ajax_get_price' ] );
		add_action( 'wp_ajax_nopriv_wp_lunapay_get_price', [ self::class, 'ajax_get_price' ] );
	}

	public static function enqueue(): void {
		wp_register_style(
			'wp-lunapay-shortcodes',
			WP_LUNAPAY_PLUGIN_URL . 'assets/css/shortcodes.css',
			[],
			WP_LUNAPAY_VERSION
		);
		wp_register_script(
			'wp-lunapay-shortcodes',
			WP_LUNAPAY_PLUGIN_URL . 'assets/js/shortcodes.js',
			[ 'jquery' ],
			WP_LUNAPAY_VERSION,
			true
		);
		wp_localize_script( 'wp-lunapay-shortcodes', 'wpLunaPayShortcodes', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wp_lunapay_sc_nonce' ),
		] );
	}

	// ------------------------------------------------------------------
	// [moonpay_buy]
	// ------------------------------------------------------------------

	/**
	 * @param array $atts  Shortcode attributes.
	 * @return string  HTML output.
	 */
	public static function sc_buy( array $atts ): string {
		$atts = shortcode_atts( [
			'currency' => '',
			'amount'   => '',
			'mode'     => 'iframe',   // iframe | popup | redirect
			'height'   => '600',
			'class'    => '',
		], $atts, 'moonpay_buy' );

		$opts     = get_option( 'woocommerce_moonpay_settings', [] );
		$currency = sanitize_key( $atts['currency'] ?: ( $opts['default_crypto'] ?? 'eth' ) );
		$mode     = in_array( $atts['mode'], [ 'iframe', 'popup', 'redirect' ], true ) ? $atts['mode'] : 'iframe';
		$height   = max( 300, absint( $atts['height'] ) );
		$class    = sanitize_html_class( $atts['class'] );

		$api = self::make_api();
		if ( ! $api ) {
			return '<p class="wp-lunapay-error">' . esc_html__( 'MoonPay gateway is not configured.', 'wp-lunapay' ) . '</p>';
		}

		$params = [ 'currencyCode' => $currency ];
		if ( ! empty( $atts['amount'] ) ) {
			$params['baseCurrencyAmount'] = floatval( $atts['amount'] );
		}
		$url = $api->get_widget_url( $params );

		wp_enqueue_style( 'wp-lunapay-shortcodes' );
		wp_enqueue_script( 'wp-lunapay-shortcodes' );

		if ( $mode === 'redirect' ) {
			return '<a href="' . esc_url( $url ) . '" class="wp-lunapay-redirect-btn ' . esc_attr( $class ) . '">'
				. esc_html__( 'Buy with MoonPay', 'wp-lunapay' )
				. '</a>';
		}

		if ( $mode === 'popup' ) {
			$id = 'wlp-popup-' . wp_unique_id();
			return '<button class="wp-lunapay-popup-btn ' . esc_attr( $class ) . '" data-url="' . esc_url( $url ) . '" data-target="' . esc_attr( $id ) . '">'
				. esc_html__( 'Buy with MoonPay', 'wp-lunapay' )
				. '</button>'
				. '<div id="' . esc_attr( $id ) . '" class="wp-lunapay-popup-wrap" style="display:none;">'
				. '<div class="wp-lunapay-popup-overlay"></div>'
				. '<div class="wp-lunapay-popup-inner">'
				. '<button class="wp-lunapay-popup-close">&times;</button>'
				. '<iframe src="' . esc_url( $url ) . '" frameborder="0" allow="accelerometer; autoplay; camera; gyroscope; payment" style="width:100%;height:' . esc_attr( $height ) . 'px;"></iframe>'
				. '</div></div>';
		}

		// iframe (default)
		return '<div class="wp-lunapay-iframe-wrap ' . esc_attr( $class ) . '">'
			. '<iframe src="' . esc_url( $url ) . '" frameborder="0" allow="accelerometer; autoplay; camera; gyroscope; payment" style="width:100%;height:' . esc_attr( $height ) . 'px;border:none;"></iframe>'
			. '</div>';
	}

	// ------------------------------------------------------------------
	// [moonpay_order_status]
	// ------------------------------------------------------------------

	/**
	 * @param array $atts  Shortcode attributes.
	 * @return string  HTML output.
	 */
	public static function sc_order_status( array $atts ): string {
		$atts = shortcode_atts( [ 'order_id' => '0' ], $atts, 'moonpay_order_status' );

		$order_id = absint( $atts['order_id'] );
		if ( ! $order_id ) {
			// Try to use the current order from the URL (order-received page).
			global $wp;
			$order_id = absint( $wp->query_vars['order-received'] ?? 0 );
		}

		if ( ! $order_id ) {
			return '';
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return '';
		}

		// Visibility: admin or the order owner only.
		if ( ! current_user_can( 'manage_woocommerce' ) && get_current_user_id() !== (int) $order->get_customer_id() ) {
			return '';
		}

		$mp_status = $order->get_meta( '_moonpay_status' );
		$mp_tx_id  = $order->get_meta( '_moonpay_transaction_id' );

		ob_start();
		?>
		<div class="wp-lunapay-order-status">
			<strong><?php esc_html_e( 'MoonPay Payment Status', 'wp-lunapay' ); ?>:</strong>
			<?php if ( $mp_status ) : ?>
				<span class="wp-lunapay-status wp-lunapay-status--<?php echo esc_attr( $mp_status ); ?>"><?php echo esc_html( ucfirst( $mp_status ) ); ?></span>
			<?php else : ?>
				<span class="wp-lunapay-status"><?php esc_html_e( 'Pending', 'wp-lunapay' ); ?></span>
			<?php endif; ?>
			<?php if ( $mp_tx_id ) : ?>
				<br><small><?php esc_html_e( 'Transaction ID', 'wp-lunapay' ); ?>: <?php echo esc_html( $mp_tx_id ); ?></small>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	// ------------------------------------------------------------------
	// [moonpay_crypto_price]
	// ------------------------------------------------------------------

	/**
	 * @param array $atts  Shortcode attributes.
	 * @return string  HTML output.
	 */
	public static function sc_crypto_price( array $atts ): string {
		$atts = shortcode_atts( [
			'currency'      => 'eth',
			'fiat'          => 'usd',
			'amount'        => '1',
			'show_currency' => 'yes',
		], $atts, 'moonpay_crypto_price' );

		$currency = strtolower( sanitize_key( $atts['currency'] ) );
		$fiat     = strtolower( sanitize_key( $atts['fiat'] ) );
		$amount   = floatval( $atts['amount'] );

		$cache_key = 'wp_lunapay_price_' . $currency . '_' . $fiat . '_' . (int) ( $amount * 100 );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (string) $cached;
		}

		$api = self::make_api();
		if ( ! $api ) {
			return '';
		}

		$quote = $api->get_buy_quote( $currency, $fiat, $amount );
		if ( is_wp_error( $quote ) ) {
			return '<span class="wp-lunapay-price-error">' . esc_html__( 'Price unavailable', 'wp-lunapay' ) . '</span>';
		}

		$price = $quote['quoteCurrencyPrice'] ?? ( $quote['baseCurrencyAmount'] ?? '' );

		if ( $atts['show_currency'] === 'yes' ) {
			$output = '<span class="wp-lunapay-crypto-price">' . esc_html( strtoupper( $currency ) ) . ': ' . esc_html( number_format( (float) $price, 2 ) ) . ' ' . esc_html( strtoupper( $fiat ) ) . '</span>';
		} else {
			$output = '<span class="wp-lunapay-crypto-price">' . esc_html( number_format( (float) $price, 2 ) ) . '</span>';
		}

		set_transient( $cache_key, $output, 60 );

		wp_enqueue_style( 'wp-lunapay-shortcodes' );

		return $output;
	}

	// ------------------------------------------------------------------
	// [moonpay_button]
	// ------------------------------------------------------------------

	/**
	 * @param array $atts  Shortcode attributes.
	 * @return string  HTML output.
	 */
	public static function sc_button( array $atts ): string {
		$opts = get_option( 'woocommerce_moonpay_settings', [] );

		$atts = shortcode_atts( [
			'currency' => $opts['default_crypto'] ?? 'eth',
			'amount'   => '',
			'label'    => $opts['button_text'] ?? __( 'Buy with MoonPay', 'wp-lunapay' ),
			'class'    => '',
		], $atts, 'moonpay_button' );

		$api = self::make_api();
		if ( ! $api ) {
			return '';
		}

		$params = [ 'currencyCode' => sanitize_key( $atts['currency'] ) ];
		if ( ! empty( $atts['amount'] ) ) {
			$params['baseCurrencyAmount'] = floatval( $atts['amount'] );
		}
		$url = $api->get_widget_url( $params );

		wp_enqueue_style( 'wp-lunapay-shortcodes' );

		return '<a href="' . esc_url( $url ) . '" class="wp-lunapay-btn ' . sanitize_html_class( $atts['class'] ) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html( $atts['label'] )
			. '</a>';
	}

	// ------------------------------------------------------------------
	// AJAX: get price
	// ------------------------------------------------------------------

	public static function ajax_get_price(): void {
		check_ajax_referer( 'wp_lunapay_sc_nonce', 'nonce' );

		$currency = sanitize_key( $_POST['currency'] ?? 'eth' );
		$fiat     = sanitize_key( $_POST['fiat'] ?? 'usd' );
		$amount   = floatval( $_POST['amount'] ?? 1 );

		$cache_key = 'wp_lunapay_price_' . $currency . '_' . $fiat . '_' . (int) ( $amount * 100 );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			wp_send_json_success( [ 'price' => $cached ] );
		}

		$api = self::make_api();
		if ( ! $api ) {
			wp_send_json_error( [ 'message' => __( 'Gateway not configured.', 'wp-lunapay' ) ] );
		}

		$quote = $api->get_buy_quote( $currency, $fiat, $amount );
		if ( is_wp_error( $quote ) ) {
			wp_send_json_error( [ 'message' => $quote->get_error_message() ] );
		}

		$price = $quote['quoteCurrencyPrice'] ?? ( $quote['baseCurrencyAmount'] ?? 0 );
		set_transient( $cache_key, $price, 60 );

		wp_send_json_success( [ 'price' => $price ] );
	}

	// ------------------------------------------------------------------
	// Helper
	// ------------------------------------------------------------------

	private static function make_api(): ?WP_LunaPay_API {
		$opts = get_option( 'woocommerce_moonpay_settings', [] );
		$pub  = trim( $opts['publishable_key'] ?? '' );
		$sec  = trim( $opts['secret_key'] ?? '' );
		if ( empty( $pub ) || empty( $sec ) ) {
			return null;
		}
		$sandbox = ( $opts['sandbox'] ?? 'yes' ) === 'yes';
		return new WP_LunaPay_API( $pub, $sec, $sandbox );
	}
}