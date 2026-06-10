<?php
/**
 * WooCommerce Payment Gateway for MoonPay.
 *
 * @package WPLunaPay
 * @copyright 2025 WPLunaPay (https://wprealwise.com)
 */

defined( 'ABSPATH' ) || exit;

class WP_LunaPay_Gateway extends WC_Payment_Gateway {

	/** @var WP_LunaPay_API|null */
	private ?WP_LunaPay_API $api = null;

	public function __construct() {
		$this->id                 = 'moonpay';
		$this->method_title       = __( 'MoonPay Crypto Payments', 'wp-lunapay' );
		$this->method_description = __( 'Accept 100+ cryptocurrencies via MoonPay.', 'wp-lunapay' );
		$this->has_fields         = false;
		$this->supports           = [ 'products', 'refunds' ];

		$this->init_form_fields();
		$this->init_settings();

		// Map settings to gateway properties.
		$this->title       = $this->get_option( 'title', __( 'Pay with Crypto (MoonPay)', 'wp-lunapay' ) );
		$this->description = $this->get_option( 'description' );

		// Order button text.
		$btn = $this->get_option( 'button_text' );
		if ( ! empty( $btn ) ) {
			$this->order_button_text = $btn;
		}

		// Gateway icon.
		if ( $this->get_option( 'show_icon' ) !== 'yes' ) {
			$this->icon = '';
		} else {
			$this->icon = WP_LUNAPAY_PLUGIN_URL . 'assets/images/moonpay-logo.svg';
		}

		// Save settings hook.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		// Enqueue checkout scripts.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

		// Thank-you page.
		add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
	}

	// ------------------------------------------------------------------
	// Form fields
	// ------------------------------------------------------------------

	public function init_form_fields(): void {
		$webhook_url = home_url( '/?wc-api=wp_lunapay_webhook' );

		$this->form_fields = [
			'enabled' => [
				'title'   => __( 'Enable/Disable', 'wp-lunapay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable MoonPay Crypto Payments', 'wp-lunapay' ),
				'default' => 'no',
			],
			'title' => [
				'title'       => __( 'Title', 'wp-lunapay' ),
				'type'        => 'text',
				'description' => __( 'Title shown to the customer during checkout.', 'wp-lunapay' ),
				'default'     => __( 'Pay with Crypto (MoonPay)', 'wp-lunapay' ),
				'desc_tip'    => true,
			],
			'description' => [
				'title'   => __( 'Description', 'wp-lunapay' ),
				'type'    => 'textarea',
				'default' => __( 'Complete your purchase using Bitcoin, Ethereum, and 100+ other cryptocurrencies.', 'wp-lunapay' ),
			],
			'sandbox' => [
				'title'   => __( 'Sandbox Mode', 'wp-lunapay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable sandbox (test) mode', 'wp-lunapay' ),
				'default' => 'yes',
			],
			'publishable_key' => [
				'title'       => __( 'Publishable Key', 'wp-lunapay' ),
				'type'        => 'text',
				'description' => __( 'Your MoonPay publishable API key (starts with pk_live_ or pk_test_).', 'wp-lunapay' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'secret_key' => [
				'title'       => __( 'Secret Key', 'wp-lunapay' ),
				'type'        => 'password',
				'description' => __( 'Your MoonPay secret API key (starts with sk_live_ or sk_test_).', 'wp-lunapay' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'default_crypto' => [
				'title'       => __( 'Default Cryptocurrency', 'wp-lunapay' ),
				'type'        => 'text',
				'description' => __( 'Default currency code shown in the widget (e.g. eth, btc).', 'wp-lunapay' ),
				'default'     => 'eth',
				'desc_tip'    => true,
			],
			'allowed_cryptos' => [
				'title'       => __( 'Allowed Cryptocurrencies', 'wp-lunapay' ),
				'type'        => 'text',
				'description' => __( 'Comma-separated list of allowed currency codes. Leave empty to allow all.', 'wp-lunapay' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'widget_mode' => [
				'title'       => __( 'Widget Mode', 'wp-lunapay' ),
				'type'        => 'select',
				'description' => __( 'How to display the MoonPay widget on the order-pay page.', 'wp-lunapay' ),
				'default'     => 'redirect',
				'options'     => [
					'redirect' => __( 'Redirect', 'wp-lunapay' ),
					'iframe'   => __( 'Inline iFrame', 'wp-lunapay' ),
					'popup'    => __( 'Popup Modal', 'wp-lunapay' ),
				],
				'desc_tip'    => true,
			],
			'iframe_height' => [
				'title'             => __( 'iFrame Height (px)', 'wp-lunapay' ),
				'type'              => 'number',
				'description'       => __( 'Height of the embedded MoonPay iFrame in pixels (300–1200).', 'wp-lunapay' ),
				'default'           => '620',
				'desc_tip'          => true,
				'custom_attributes' => [ 'min' => 300, 'max' => 1200 ],
			],
			'color_code' => [
				'title'       => __( 'Accent Colour', 'wp-lunapay' ),
				'type'        => 'color',
				'description' => __( 'Brand accent colour passed to the MoonPay widget.', 'wp-lunapay' ),
				'default'     => '#7b3fe4',
				'desc_tip'    => true,
			],
			'show_icon' => [
				'title'   => __( 'Show Gateway Icon', 'wp-lunapay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Display the MoonPay logo next to the payment method title', 'wp-lunapay' ),
				'default' => 'yes',
			],
			'button_text' => [
				'title'       => __( 'Order Button Text', 'wp-lunapay' ),
				'type'        => 'text',
				'description' => __( 'Text on the Place Order button when MoonPay is selected.', 'wp-lunapay' ),
				'default'     => __( 'Proceed to MoonPay', 'wp-lunapay' ),
				'desc_tip'    => true,
			],
			'amount_section' => [
				'title' => __( 'Order Amount Limits', 'wp-lunapay' ),
				'type'  => 'title',
			],
			'min_order_amount' => [
				'title'             => __( 'Minimum Order Amount', 'wp-lunapay' ),
				'type'              => 'number',
				'description'       => __( 'Minimum order total (in store currency) to show MoonPay. 0 = no limit.', 'wp-lunapay' ),
				'default'           => '0',
				'desc_tip'          => true,
				'custom_attributes' => [ 'min' => 0, 'step' => '0.01' ],
			],
			'max_order_amount' => [
				'title'             => __( 'Maximum Order Amount', 'wp-lunapay' ),
				'type'              => 'number',
				'description'       => __( 'Maximum order total (in store currency) to show MoonPay. 0 = no limit.', 'wp-lunapay' ),
				'default'           => '0',
				'desc_tip'          => true,
				'custom_attributes' => [ 'min' => 0, 'step' => '0.01' ],
			],
			'success_message' => [
				'title'       => __( 'Success Message', 'wp-lunapay' ),
				'type'        => 'textarea',
				'description' => __( 'Message shown on the thank-you page after MoonPay checkout.', 'wp-lunapay' ),
				'default'     => __( 'Thank you! Your crypto payment is being processed by MoonPay.', 'wp-lunapay' ),
				'desc_tip'    => true,
			],
			'order_statuses_section' => [
				'title' => __( 'Order Status Mapping', 'wp-lunapay' ),
				'type'  => 'title',
			],
			'order_status_pending' => [
				'title'   => __( 'Pending Payment Status', 'wp-lunapay' ),
				'type'    => 'select',
				'default' => 'pending',
				'options' => wc_get_order_statuses(),
			],
			'order_status_complete' => [
				'title'   => __( 'Payment Complete Status', 'wp-lunapay' ),
				'type'    => 'select',
				'default' => 'processing',
				'options' => wc_get_order_statuses(),
			],
			'order_status_failed' => [
				'title'   => __( 'Payment Failed Status', 'wp-lunapay' ),
				'type'    => 'select',
				'default' => 'failed',
				'options' => wc_get_order_statuses(),
			],
			'debug' => [
				'title'   => __( 'Debug Logging', 'wp-lunapay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable debug logging (WooCommerce &rarr; Status &rarr; Logs)', 'wp-lunapay' ),
				'default' => 'no',
			],
			'webhook_secret_note' => [
				'title'       => __( 'Webhook URL', 'wp-lunapay' ),
				'type'        => 'title',
				'description' => sprintf(
					/* translators: %s = Webhook URL */
					__( 'Add this URL to your MoonPay dashboard: <code>%s</code>', 'wp-lunapay' ),
					esc_url( $webhook_url )
				),
			],
			'wprealwise_credit' => [
				'title'       => '',
				'type'        => 'title',
				'description' => '<p style="text-align:center;">' . sprintf(
					/* translators: %s = URL */
					__( 'Powered by <a href="%s" target="_blank" rel="noopener">WPLunaPay</a>', 'wp-lunapay' ),
					'https://wprealwise.com'
				) . '</p>',
			],
		];
	}

	// ------------------------------------------------------------------
	// Availability
	// ------------------------------------------------------------------

	public function is_available(): bool {
		if ( ! parent::is_available() ) {
			return false;
		}

		if ( ! $this->validate_api_keys() ) {
			return false;
		}

		// Min / max order amount check (only on checkout when cart is available).
		if ( WC()->cart ) {
			$total = (float) WC()->cart->get_total( 'raw' );
			$min   = (float) $this->get_option( 'min_order_amount', '0' );
			$max   = (float) $this->get_option( 'max_order_amount', '0' );

			if ( $min > 0 && $total < $min ) {
				return false;
			}
			if ( $max > 0 && $total > $max ) {
				return false;
			}
		}

		return true;
	}

	// ------------------------------------------------------------------
	// Process payment
	// ------------------------------------------------------------------

	/**
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ): array {
		// Guard: API keys configured?
		if ( ! $this->validate_api_keys() ) {
			wc_add_notice( __( 'MoonPay is not configured. Please contact the store owner.', 'wp-lunapay' ), 'error' );
			return [ 'result' => 'failure' ];
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( __( 'Order not found.', 'wp-lunapay' ), 'error' );
			return [ 'result' => 'failure' ];
		}

		$total = (float) $order->get_total();
		$min   = (float) $this->get_option( 'min_order_amount', '0' );
		$max   = (float) $this->get_option( 'max_order_amount', '0' );

		if ( $min > 0 && $total < $min ) {
			/* translators: %s = formatted amount */
			wc_add_notice( sprintf( __( 'Minimum order amount for MoonPay is %s.', 'wp-lunapay' ), wc_price( $min ) ), 'error' );
			return [ 'result' => 'failure' ];
		}

		if ( $max > 0 && $total > $max ) {
			/* translators: %s = formatted amount */
			wc_add_notice( sprintf( __( 'Maximum order amount for MoonPay is %s.', 'wp-lunapay' ), wc_price( $max ) ), 'error' );
			return [ 'result' => 'failure' ];
		}

		$pending_status = str_replace( 'wc-', '', $this->get_option( 'order_status_pending', 'pending' ) );
		$order->update_status( $pending_status, __( 'Awaiting MoonPay crypto payment.', 'wp-lunapay' ) );
		$order->save();

		$widget_mode = $this->get_option( 'widget_mode', 'redirect' );

		if ( $widget_mode === 'redirect' ) {
			$url = $this->get_widget_url_for_order( $order );
			return [
				'result'   => 'success',
				'redirect' => $url,
			];
		}

		// iframe / popup: redirect to order-pay page; JS will embed/popup widget.
		return [
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		];
	}

	// ------------------------------------------------------------------
	// Thank-you page
	// ------------------------------------------------------------------

	/**
	 * @param int $order_id
	 */
	public function thankyou_page( $order_id ): void {
		$msg = $this->get_option( 'success_message' );
		if ( $msg ) {
			echo '<div class="wp-lunapay-success-msg">' . wp_kses_post( $msg ) . '</div>';
		}
	}

	// ------------------------------------------------------------------
	// Widget output (order-pay page)
	// ------------------------------------------------------------------

	/**
	 * @param WC_Order $order
	 * @return string
	 */
	public function build_widget_output( WC_Order $order ): string {
		$url          = $this->get_widget_url_for_order( $order );
		$iframe_height = max( 300, absint( $this->get_option( 'iframe_height', '620' ) ) );
		$widget_mode  = $this->get_option( 'widget_mode', 'redirect' );

		if ( $widget_mode === 'iframe' ) {
			return '<div class="wp-lunapay-iframe-wrap">'
				. '<iframe src="' . esc_url( $url ) . '" frameborder="0" allow="accelerometer; autoplay; camera; gyroscope; payment" style="width:100%;height:' . esc_attr( $iframe_height ) . 'px;border:none;"></iframe>'
				. '</div>';
		}

		// popup
		return '<div class="wp-lunapay-popup-wrap">'
			. '<button class="wp-lunapay-popup-btn button alt" data-url="' . esc_url( $url ) . '">' . esc_html__( 'Open MoonPay', 'wp-lunapay' ) . '</button>'
			. '</div>';
	}

	// ------------------------------------------------------------------
	// URL builder
	// ------------------------------------------------------------------

	/**
	 * @param WC_Order $order
	 * @return string  Signed MoonPay widget URL.
	 */
	public function get_widget_url_for_order( WC_Order $order ): string {
		$color      = $this->get_option( 'color_code', '#7b3fe4' );
		$color_code = '#' . ltrim( sanitize_hex_color_no_hash( $color ) ?: '7b3fe4', '#' );

		$external_id = 'order_' . $order->get_id() . '_' . wp_generate_password( 8, false );

		$params = [
			'currencyCode'          => sanitize_key( $this->get_option( 'default_crypto', 'eth' ) ),
			'baseCurrencyAmount'    => $order->get_total(),
			'baseCurrencyCode'      => strtolower( get_woocommerce_currency() ),
			'externalTransactionId' => $external_id,
			'email'                 => $order->get_billing_email(),
			'colorCode'             => $color_code,
			'redirectURL'           => $this->get_return_url( $order ),
		];

		$allowed = $this->get_option( 'allowed_cryptos', '' );
		if ( ! empty( $allowed ) ) {
			$codes = array_filter( array_map( 'sanitize_key', explode( ',', $allowed ) ) );
			if ( $codes ) {
				$params['enabledPaymentMethods'] = implode( ',', $codes );
			}
		}

		$order->update_meta_data( '_moonpay_external_transaction_id', sanitize_text_field( $external_id ) );
		$order->save();

		return $this->get_api()->get_widget_url( $params );
	}

	// ------------------------------------------------------------------
	// Scripts
	// ------------------------------------------------------------------

	public function enqueue_scripts(): void {
		if ( ! is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		wp_enqueue_style(
			'wp-lunapay',
			WP_LUNAPAY_PLUGIN_URL . 'assets/css/checkout.css',
			[],
			WP_LUNAPAY_VERSION
		);
		wp_enqueue_script(
			'wp-lunapay',
			WP_LUNAPAY_PLUGIN_URL . 'assets/js/checkout.js',
			[ 'jquery' ],
			WP_LUNAPAY_VERSION,
			true
		);

		$order_id = absint( get_query_var( 'order-pay' ) );
		wp_localize_script( 'wp-lunapay', 'wpLunaPay', [
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'wp_lunapay_nonce' ),
			'widgetMode' => $this->get_option( 'widget_mode', 'redirect' ),
			'orderId'    => $order_id,
		] );
	}

	/**
	 * @param string $hook  Current admin page hook.
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		$screen = get_current_screen();
		$is_wc_settings = ( $hook === 'woocommerce_page_wc-settings' );
		$is_order_page  = $screen && in_array( $screen->id, [ 'shop_order', 'woocommerce_page_wc-orders' ], true );

		if ( ! $is_wc_settings && ! $is_order_page ) {
			return;
		}

		wp_enqueue_style(
			'wp-lunapay-admin',
			WP_LUNAPAY_PLUGIN_URL . 'assets/css/admin.css',
			[],
			WP_LUNAPAY_VERSION
		);
		wp_enqueue_script(
			'wp-lunapay-admin',
			WP_LUNAPAY_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			WP_LUNAPAY_VERSION,
			true
		);

		$js_data = WP_LunaPay_Setup_Wizard::js_data();
		wp_localize_script( 'wp-lunapay-admin', 'wpLunaPayAdmin', $js_data );
	}

	// ------------------------------------------------------------------
	// Refund
	// ------------------------------------------------------------------

	/**
	 * @param int    $order_id
	 * @param float  $amount
	 * @param string $reason
	 * @return WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ): WP_Error {
		return new WP_Error(
			'wp_lunapay_no_refund',
			__( 'Refunds via MoonPay API are not supported. Please process manually in the MoonPay dashboard.', 'wp-lunapay' )
		);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	public function is_sandbox(): bool {
		return $this->get_option( 'sandbox' ) === 'yes';
	}

	public function get_api(): WP_LunaPay_API {
		if ( $this->api === null ) {
			$this->api = $this->make_api();
		}
		return $this->api;
	}

	public function make_api(): WP_LunaPay_API {
		return new WP_LunaPay_API(
			$this->get_option( 'publishable_key', '' ),
			$this->get_option( 'secret_key', '' ),
			$this->is_sandbox()
		);
	}

	/**
	 * @return bool  True when both keys are present and correctly prefixed.
	 */
	public function validate_api_keys(): bool {
		$pub = trim( $this->get_option( 'publishable_key', '' ) );
		$sec = trim( $this->get_option( 'secret_key', '' ) );

		if ( empty( $pub ) || empty( $sec ) ) {
			return false;
		}

		if ( ! str_starts_with( $pub, 'pk_' ) || ! str_starts_with( $sec, 'sk_' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $message
	 * @param string $level   WC log level.
	 */
	public function log( string $message, string $level = 'debug' ): void {
		if ( $this->get_option( 'debug' ) !== 'yes' ) {
			return;
		}
		$logger = wc_get_logger();
		$logger->log( $level, $message, [ 'source' => 'wp-lunapay' ] );
	}
}