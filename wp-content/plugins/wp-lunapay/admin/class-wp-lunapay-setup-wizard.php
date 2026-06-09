<?php
/**
 * 6-step setup wizard for WP LunaPay.
 *
 * @package WPLunaPay
 * @copyright 2025 WPLunaPay (https://wprealwise.com)
 */

defined( 'ABSPATH' ) || exit;

class WP_LunaPay_Setup_Wizard {

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ] );

		// AJAX handlers.
		add_action( 'wp_ajax_wp_lunapay_test_connection',    [ self::class, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_wp_lunapay_test_webhook_url',   [ self::class, 'ajax_test_webhook_url' ] );
		add_action( 'wp_ajax_wp_lunapay_create_test_order',  [ self::class, 'ajax_create_test_order' ] );
		add_action( 'wp_ajax_wp_lunapay_go_live',            [ self::class, 'ajax_go_live' ] );
		add_action( 'wp_ajax_wp_lunapay_save_wizard_step',   [ self::class, 'ajax_save_wizard_step' ] );
	}

	public static function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'MoonPay Setup Wizard', 'wp-lunapay' ),
			__( 'MoonPay Setup', 'wp-lunapay' ),
			'manage_woocommerce',
			'wp-lunapay-setup',
			[ self::class, 'render' ]
		);
	}

	// ------------------------------------------------------------------
	// Render
	// ------------------------------------------------------------------

	public static function render(): void {
		$step = max( 1, min( 6, absint( $_GET['step'] ?? 1 ) ) );
		wp_enqueue_style( 'wp-lunapay-admin' );
		wp_enqueue_script( 'wp-lunapay-admin' );
		?>
		<div class="wrap wp-lunapay-wizard-wrap">
			<h1><?php esc_html_e( 'MoonPay Setup Wizard', 'wp-lunapay' ); ?></h1>

			<!-- Step nav -->
			<ol class="wp-lunapay-wizard-steps">
				<?php
				$step_labels = [
					1 => __( 'Get Keys',    'wp-lunapay' ),
					2 => __( 'Enter Keys',  'wp-lunapay' ),
					3 => __( 'Webhook',     'wp-lunapay' ),
					4 => __( 'Configure',   'wp-lunapay' ),
					5 => __( 'Test Order',  'wp-lunapay' ),
					6 => __( 'Go Live',     'wp-lunapay' ),
				];
				foreach ( $step_labels as $n => $label ) :
					$class = $n === $step ? 'active' : ( $n < $step ? 'done' : '' );
					$url   = add_query_arg( 'step', $n );
					?>
					<li class="<?php echo esc_attr( $class ); ?>">
						<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
					</li>
				<?php endforeach; ?>
			</ol>

			<div class="wp-lunapay-wizard-content">
				<?php
				switch ( $step ) {
					case 1: self::step_get_keys();    break;
					case 2: self::step_enter_keys();  break;
					case 3: self::step_webhook();     break;
					case 4: self::step_configure();   break;
					case 5: self::step_test_order();  break;
					case 6: self::step_go_live();     break;
				}
				?>
			</div>

			<!-- Credit -->
			<p class="wp-lunapay-credit" style="text-align:center;margin-top:30px;color:#999;">
				<?php
				printf(
					/* translators: %s = URL */
					esc_html__( 'Powered by %s', 'wp-lunapay' ),
					'<a href="https://wprealwise.com" target="_blank" rel="noopener">WPLunaPay</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Step 1: Get keys
	// ------------------------------------------------------------------

	private static function step_get_keys(): void {
		?>
		<h2><?php esc_html_e( 'Step 1: Get Your MoonPay API Keys', 'wp-lunapay' ); ?></h2>
		<p><?php esc_html_e( 'You need a MoonPay account to get your API keys. Click the button below to open the MoonPay dashboard.', 'wp-lunapay' ); ?></p>
		<a href="https://dashboard.moonpay.com/developers/api_keys" target="_blank" rel="noopener" class="button button-primary">
			<?php esc_html_e( 'Open MoonPay Dashboard', 'wp-lunapay' ); ?>
		</a>
		<p style="margin-top:16px;">
			<a href="<?php echo esc_url( add_query_arg( 'step', 2 ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'I have my keys — Next', 'wp-lunapay' ); ?>
			</a>
		</p>
		<?php
	}

	// ------------------------------------------------------------------
	// Step 2: Enter keys
	// ------------------------------------------------------------------

	private static function step_enter_keys(): void {
		$opts = get_option( 'woocommerce_moonpay_settings', [] );
		?>
		<h2><?php esc_html_e( 'Step 2: Enter Your API Keys', 'wp-lunapay' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wlp-pk"><?php esc_html_e( 'Publishable Key', 'wp-lunapay' ); ?></label></th>
				<td><input type="text" id="wlp-pk" class="regular-text" value="<?php echo esc_attr( $opts['publishable_key'] ?? '' ); ?>" placeholder="pk_live_ or pk_test_"></td>
			</tr>
			<tr>
				<th scope="row"><label for="wlp-sk"><?php esc_html_e( 'Secret Key', 'wp-lunapay' ); ?></label></th>
				<td><input type="password" id="wlp-sk" class="regular-text" value="<?php echo esc_attr( $opts['secret_key'] ?? '' ); ?>" placeholder="sk_live_ or sk_test_"></td>
			</tr>
		</table>
		<p>
			<button type="button" class="button button-primary" id="wlp-btn-save-keys"><?php esc_html_e( 'Save Keys', 'wp-lunapay' ); ?></button>
			<button type="button" class="button" id="wlp-btn-test-connection" style="margin-left:8px;"><?php esc_html_e( 'Test Connection', 'wp-lunapay' ); ?></button>
		</p>
		<div id="wlp-connection-result" style="margin-top:8px;"></div>
		<p>
			<a href="<?php echo esc_url( add_query_arg( 'step', 3 ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Next', 'wp-lunapay' ); ?>
			</a>
		</p>
		<?php
	}

	// ------------------------------------------------------------------
	// Step 3: Webhook
	// ------------------------------------------------------------------

	private static function step_webhook(): void {
		$webhook_url = home_url( '/?wc-api=wp_lunapay_webhook' );
		?>
		<h2><?php esc_html_e( 'Step 3: Configure Webhook', 'wp-lunapay' ); ?></h2>
		<p><?php esc_html_e( 'Add the following URL to your MoonPay dashboard as a webhook endpoint:', 'wp-lunapay' ); ?></p>
		<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
			<code id="wlp-webhook-url"><?php echo esc_html( $webhook_url ); ?></code>
			<button type="button" class="button" id="wlp-btn-copy-webhook"><?php esc_html_e( 'Copy', 'wp-lunapay' ); ?></button>
		</div>
		<p>
			<button type="button" class="button button-primary" id="wlp-btn-test-webhook"><?php esc_html_e( 'Test Reachability', 'wp-lunapay' ); ?></button>
		</p>
		<div id="wlp-webhook-result" style="margin-top:8px;"></div>
		<p>
			<a href="<?php echo esc_url( add_query_arg( 'step', 4 ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Next', 'wp-lunapay' ); ?>
			</a>
		</p>
		<?php
	}

	// ------------------------------------------------------------------
	// Step 4: Configure
	// ------------------------------------------------------------------

	private static function step_configure(): void {
		$opts = get_option( 'woocommerce_moonpay_settings', [] );
		?>
		<h2><?php esc_html_e( 'Step 4: Configure Widget', 'wp-lunapay' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wlp-widget-mode"><?php esc_html_e( 'Widget Mode', 'wp-lunapay' ); ?></label></th>
				<td>
					<select id="wlp-widget-mode">
						<?php foreach ( [ 'redirect' => __( 'Redirect', 'wp-lunapay' ), 'iframe' => __( 'iFrame', 'wp-lunapay' ), 'popup' => __( 'Popup', 'wp-lunapay' ) ] as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $opts['widget_mode'] ?? 'redirect', $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wlp-default-crypto"><?php esc_html_e( 'Default Cryptocurrency', 'wp-lunapay' ); ?></label></th>
				<td><input type="text" id="wlp-default-crypto" class="regular-text" value="<?php echo esc_attr( $opts['default_crypto'] ?? 'eth' ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="wlp-color"><?php esc_html_e( 'Accent Colour', 'wp-lunapay' ); ?></label></th>
				<td><input type="color" id="wlp-color" value="<?php echo esc_attr( $opts['color_code'] ?? '#7b3fe4' ); ?>"></td>
			</tr>
		</table>
		<p>
			<button type="button" class="button button-primary" id="wlp-btn-save-config"><?php esc_html_e( 'Save Configuration', 'wp-lunapay' ); ?></button>
		</p>
		<div id="wlp-config-result" style="margin-top:8px;"></div>
		<p>
			<a href="<?php echo esc_url( add_query_arg( 'step', 5 ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Next', 'wp-lunapay' ); ?>
			</a>
		</p>
		<?php
	}

	// ------------------------------------------------------------------
	// Step 5: Test order
	// ------------------------------------------------------------------

	private static function step_test_order(): void {
		$setup    = get_option( 'wp_lunapay_setup', [] );
		$order_id = $setup['test_order_id'] ?? 0;
		?>
		<h2><?php esc_html_e( 'Step 5: Create a Test Order', 'wp-lunapay' ); ?></h2>
		<?php if ( $order_id ) : ?>
			<div class="notice notice-success inline"><p>
				<?php
				printf(
					/* translators: %1$s = order ID, %2$s = URL */
					esc_html__( 'Test order #%1$s was created. <a href="%2$s">View in admin</a>.', 'wp-lunapay' ),
					esc_html( $order_id ),
					esc_url( get_edit_post_link( $order_id ) ?: admin_url( 'post.php?post=' . $order_id . '&action=edit' ) )
				);
				?>
			</p></div>
		<?php endif; ?>
		<p>
			<button type="button" class="button button-primary" id="wlp-btn-create-test-order">
				<?php echo $order_id ? esc_html__( 'Create Another Test Order', 'wp-lunapay' ) : esc_html__( 'Create Test Order', 'wp-lunapay' ); ?>
			</button>
		</p>
		<div id="wlp-test-order-result" style="margin-top:8px;"></div>
		<p>
			<a href="<?php echo esc_url( add_query_arg( 'step', 6 ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Next', 'wp-lunapay' ); ?>
			</a>
		</p>
		<?php
	}

	// ------------------------------------------------------------------
	// Step 6: Go live
	// ------------------------------------------------------------------

	private static function step_go_live(): void {
		$checklist = WP_LunaPay_Dashboard_Widget::build_checklist();
		$all_done  = ! in_array( false, array_column( $checklist, 'done' ), true );
		$opts      = get_option( 'woocommerce_moonpay_settings', [] );
		$is_live   = isset( $opts['sandbox'] ) && $opts['sandbox'] === 'no';
		?>
		<h2><?php esc_html_e( 'Step 6: Go Live', 'wp-lunapay' ); ?></h2>

		<?php if ( $is_live ) : ?>
			<div class="notice notice-success inline"><p>
				<strong><?php esc_html_e( 'Gateway is already in Live mode!', 'wp-lunapay' ); ?></strong>
			</p></div>
		<?php endif; ?>

		<ul style="list-style:none;padding:0;margin-bottom:16px;">
			<?php foreach ( $checklist as $item ) : ?>
				<li style="padding:4px 0;">
					<?php echo $item['done'] ? '<span style="color:green;">&#10003;</span>' : '<span style="color:#e65f00;">&#9711;</span>'; ?>
					<?php echo esc_html( $item['label'] ); ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( ! $all_done ) : ?>
			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'Some checklist items are incomplete. You can still switch to live mode, but it is recommended to complete all steps first.', 'wp-lunapay' ); ?>
			</p></div>
		<?php endif; ?>

		<?php if ( ! $is_live ) : ?>
		<p>
			<button type="button" class="button button-primary" id="wlp-btn-go-live">
				<?php esc_html_e( 'Switch to Live Mode', 'wp-lunapay' ); ?>
			</button>
		</p>
		<?php endif; ?>
		<!-- Confirm modal -->
		<div id="wlp-golive-modal" style="display:none;" class="wp-lunapay-modal-overlay">
			<div class="wp-lunapay-modal">
				<h3><?php esc_html_e( 'Confirm Go Live', 'wp-lunapay' ); ?></h3>
				<p><?php esc_html_e( 'This will switch the gateway from sandbox to live mode. Real transactions will be processed. Are you sure?', 'wp-lunapay' ); ?></p>
				<button type="button" class="button button-primary" id="wlp-btn-confirm-golive"><?php esc_html_e( 'Yes, Go Live', 'wp-lunapay' ); ?></button>
				<button type="button" class="button" id="wlp-btn-cancel-golive"><?php esc_html_e( 'Cancel', 'wp-lunapay' ); ?></button>
			</div>
		</div>
		<div id="wlp-golive-result" style="margin-top:8px;"></div>
		<?php
	}

	// ------------------------------------------------------------------
	// AJAX: test connection
	// ------------------------------------------------------------------

	public static function ajax_test_connection(): void {
		check_ajax_referer( 'wp_lunapay_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}

		$pub = sanitize_text_field( $_POST['publishable_key'] ?? '' );
		$sec = sanitize_text_field( $_POST['secret_key'] ?? '' );

		if ( empty( $pub ) || empty( $sec ) ) {
			wp_send_json_error( [ 'message' => __( 'Please enter both keys.', 'wp-lunapay' ) ] );
		}

		$sandbox = str_contains( $pub, '_test_' );
		$api     = new WP_LunaPay_API( $pub, $sec, $sandbox );
		$result  = $api->get_currencies();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => __( 'Connected successfully!', 'wp-lunapay' ) ] );
	}

	// ------------------------------------------------------------------
	// AJAX: test webhook URL
	// ------------------------------------------------------------------

	public static function ajax_test_webhook_url(): void {
		check_ajax_referer( 'wp_lunapay_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}

		$site_url    = home_url();
		$webhook_url = $site_url . '/?wc-api=wp_lunapay_webhook';

		// Fix 5: In Docker/local environments home_url() resolves to the mapped host port
		// (e.g. localhost:8080) which is not reachable from *inside* the container.
		// Try the external URL first; if that fails with a connection error (not HTTP error),
		// fall back to an internal loopback using port 80 (the actual container port).
		$response = wp_remote_get( $webhook_url, [
			'timeout'     => 8,
			'redirection' => 3,
			'sslverify'   => false,
		] );

		if ( is_wp_error( $response ) ) {
			// Try internal loopback (works inside Docker where port 80 is the real listener)
			$internal_url = 'http://localhost/?wc-api=wp_lunapay_webhook';
			$response     = wp_remote_get( $internal_url, [
				'timeout'   => 6,
				'sslverify' => false,
				'headers'   => [ 'Host' => wp_parse_url( $site_url, PHP_URL_HOST ) ],
			] );
		}

		// Any HTTP response (even 4xx/5xx) means the endpoint route exists.
		// Only a complete connection failure means it's not reachable.
		$reachable = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) > 0;

		$setup                      = get_option( 'wp_lunapay_setup', [] );
		$setup['webhook_reachable'] = $reachable;
		update_option( 'wp_lunapay_setup', $setup );

		if ( $reachable ) {
			wp_send_json_success( [ 'message' => __( 'Webhook endpoint is reachable.', 'wp-lunapay' ) ] );
		} else {
			$err = is_wp_error( $response ) ? $response->get_error_message() : __( 'No HTTP response', 'wp-lunapay' );
			wp_send_json_error( [ 'message' => sprintf(
				/* translators: %s = error detail */
				__( 'Could not reach webhook URL: %s', 'wp-lunapay' ),
				$err
			) ] );
		}
	}

	// ------------------------------------------------------------------
	// AJAX: create test order
	// ------------------------------------------------------------------

	public static function ajax_create_test_order(): void {
		check_ajax_referer( 'wp_lunapay_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}

		$product = self::get_or_create_test_product();
		if ( ! $product ) {
			wp_send_json_error( [ 'message' => __( 'Could not create test product.', 'wp-lunapay' ) ] );
		}

		$order = wc_create_order();
		$order->add_product( $product, 1 );
		$order->set_payment_method( 'moonpay' );
		$order->set_payment_method_title( 'MoonPay (Test)' );
		$order->calculate_totals();
		$order->update_status( 'pending', __( 'MoonPay test order.', 'wp-lunapay' ) );
		$order->save();

		$setup                  = get_option( 'wp_lunapay_setup', [] );
		$setup['test_order_id'] = $order->get_id();
		update_option( 'wp_lunapay_setup', $setup );

		wp_send_json_success( [
			'message'  => sprintf(
				/* translators: %d = order ID */
				__( 'Test order #%d created.', 'wp-lunapay' ),
				$order->get_id()
			),
			'order_id' => $order->get_id(),
			'order_url' => admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
			'checkout_url' => $order->get_checkout_payment_url( true ),
		] );
	}

	// ------------------------------------------------------------------
	// AJAX: go live
	// ------------------------------------------------------------------

	public static function ajax_go_live(): void {
		check_ajax_referer( 'wp_lunapay_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}

		$opts           = get_option( 'woocommerce_moonpay_settings', [] );
		$opts['sandbox'] = 'no';
		update_option( 'woocommerce_moonpay_settings', $opts );

		$setup               = get_option( 'wp_lunapay_setup', [] );
		$setup['went_live_at'] = current_time( 'mysql' );
		update_option( 'wp_lunapay_setup', $setup );

		wp_send_json_success( [ 'message' => __( 'Gateway is now live!', 'wp-lunapay' ) ] );
	}

	// ------------------------------------------------------------------
	// AJAX: save wizard step
	// ------------------------------------------------------------------

	public static function ajax_save_wizard_step(): void {
		check_ajax_referer( 'wp_lunapay_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}

		$step = absint( $_POST['step'] ?? 0 );
		$data = $_POST['data'] ?? [];

		$opts = get_option( 'woocommerce_moonpay_settings', [] );

		switch ( $step ) {
			case 2:
				$opts['publishable_key'] = sanitize_text_field( $data['publishable_key'] ?? '' );
				$opts['secret_key']      = sanitize_text_field( $data['secret_key'] ?? '' );
				$opts['sandbox']         = str_contains( $opts['publishable_key'], '_test_' ) ? 'yes' : 'no';
				// Fix 4: enable the gateway as soon as keys are saved
				$opts['enabled']         = 'yes';
				break;
			case 4:
				$opts['widget_mode']    = in_array( $data['widget_mode'] ?? '', [ 'redirect', 'iframe', 'popup' ], true ) ? $data['widget_mode'] : 'redirect';
				$opts['default_crypto'] = sanitize_key( $data['default_crypto'] ?? 'eth' );
				$opts['color_code']     = sanitize_hex_color( $data['color_code'] ?? '#7b3fe4' ) ?: '#7b3fe4';
				$opts['enabled']        = 'yes';
				break;
		}

		update_option( 'woocommerce_moonpay_settings', $opts );
		wp_send_json_success( [ 'message' => __( 'Saved.', 'wp-lunapay' ) ] );
	}

	// ------------------------------------------------------------------
	// js_data() — used by the gateway enqueue_admin_scripts
	// ------------------------------------------------------------------

	/**
	 * Returns data array to be wp_localize_script'd for the admin JS.
	 *
	 * @return array
	 */
	public static function js_data(): array {
		$setup_data = self::get_setup_data();
		return [
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'wp_lunapay_admin' ),
			'webhookUrl'  => home_url( '/?wc-api=wp_lunapay_webhook' ),
			'settingsUrl' => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=moonpay' ),
			'wizardUrl'   => admin_url( 'admin.php?page=wp-lunapay-setup' ),
			'setupData'   => $setup_data,
			'i18n'        => [
				'btnTestConnection'  => __( 'Test Connection',         'wp-lunapay' ),
				'btnSaveKeys'        => __( 'Save Keys',               'wp-lunapay' ),
				'btnTestReachability'=> __( 'Test Reachability',       'wp-lunapay' ),
				'btnSaveConfig'      => __( 'Save Configuration',      'wp-lunapay' ),
				'btnCreateTestOrder' => __( 'Create Test Order',       'wp-lunapay' ),
				'btnAnotherTestOrder'=> __( 'Create Another Test Order','wp-lunapay' ),
				'btnGoLive'          => __( 'Go Live',                 'wp-lunapay' ),
				'enterBothKeys'      => __( 'Please enter both API keys.', 'wp-lunapay' ),
				'testing'            => __( 'Testing…',                'wp-lunapay' ),
				'saving'             => __( 'Saving…',                 'wp-lunapay' ),
				'connected'          => __( 'Connected!',              'wp-lunapay' ),
				'failed'             => __( 'Failed.',                 'wp-lunapay' ),
				'copied'             => __( 'Copied!',                 'wp-lunapay' ),
				'copy'               => __( 'Copy',                    'wp-lunapay' ),
				'creating'           => __( 'Creating…',               'wp-lunapay' ),
				'goingLive'          => __( 'Going live…',             'wp-lunapay' ),
				'confirmGoLive'      => __( 'Switch to live mode?',    'wp-lunapay' ),
				'reachable'          => __( 'Reachable!',              'wp-lunapay' ),
				'notReachable'       => __( 'Not reachable.',          'wp-lunapay' ),
				'testOrderCreated'   => __( 'Test order created.',     'wp-lunapay' ),
				'openCheckout'       => __( 'Open Checkout',           'wp-lunapay' ),
				'viewInAdmin'        => __( 'View in Admin',           'wp-lunapay' ),
			],
		];
	}

	// ------------------------------------------------------------------
	// get_setup_data
	// ------------------------------------------------------------------

	/**
	 * Reads wp_lunapay_setup option and does HPOS-aware DB query for last webhook.
	 *
	 * @return array
	 */
	public static function get_setup_data(): array {
		$setup = get_option( 'wp_lunapay_setup', [] );

		// Enrich with last webhook from DB if not already set.
		if ( empty( $setup['last_webhook_received'] ) ) {
			global $wpdb;

			if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class )
				&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$table = $wpdb->prefix . 'wc_orders';
				$last  = $wpdb->get_var( $wpdb->prepare(
					"SELECT date_updated_gmt FROM {$table} WHERE payment_method = %s ORDER BY date_updated_gmt DESC LIMIT 1",
					'moonpay'
				) );
			} else {
				$last = $wpdb->get_var( $wpdb->prepare(
					"SELECT p.post_modified_gmt
					   FROM {$wpdb->posts} p
					   JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_payment_method' AND pm.meta_value = %s
					  WHERE p.post_type = 'shop_order'
					  ORDER BY p.post_modified_gmt DESC LIMIT 1",
					'moonpay'
				) );
			}

			if ( $last ) {
				$setup['last_webhook_received'] = $last;
			}
		}

		return $setup;
	}

	// ------------------------------------------------------------------
	// get_or_create_test_product
	// ------------------------------------------------------------------

	/**
	 * Returns (or creates) a hidden virtual test product for sandbox orders.
	 *
	 * @return WC_Product|false
	 */
	public static function get_or_create_test_product() {
		// Try to find an existing test product.
		$product_ids = wc_get_products( [
			'meta_key'   => '_moonpay_test_product',
			'meta_value' => '1',
			'limit'      => 1,
			'return'     => 'ids',
		] );

		if ( ! empty( $product_ids ) ) {
			$product = wc_get_product( $product_ids[0] );
			if ( $product ) {
				return $product;
			}
		}

		// Create a new test product.
		$product = new WC_Product_Simple();
		$product->set_name( __( 'MoonPay Test Product', 'wp-lunapay' ) );
		$product->set_regular_price( '10.00' );
		$product->set_virtual( true );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_status( 'private' );
		$product->update_meta_data( '_moonpay_test_product', '1' );
		$product->save();

		return $product;
	}
}