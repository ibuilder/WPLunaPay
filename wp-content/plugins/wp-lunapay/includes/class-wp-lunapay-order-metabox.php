<?php
/**
 * Adds a MoonPay info metabox to the WooCommerce order detail screen.
 *
 * @package WPLunaPay
 * @copyright 2025 WPLunaPay (https://wprealwise.com)
 */

defined( 'ABSPATH' ) || exit;

class WP_LunaPay_Order_Metabox {

	public static function init(): void {
		add_action( 'add_meta_boxes', [ self::class, 'register' ] );
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ self::class, 'inline_order_note' ], 10, 1 );
		add_action( 'wp_ajax_wp_lunapay_refresh_tx', [ self::class, 'ajax_refresh_transaction' ] );
	}

	public static function register(): void {
		$screen = 'shop_order';
		try {
			if (
				class_exists( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class ) &&
				wc_get_container()
					->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )
					->custom_orders_table_usage_is_enabled()
			) {
				$screen = wc_get_page_screen_id( 'shop-order' );
			}
		} catch ( \Throwable $e ) {
			// Fall back to classic screen ID
		}

		add_meta_box(
			'wp_lunapay_order_info',
			__( 'MoonPay Payment Details', 'wp-lunapay' ),
			[ self::class, 'render' ],
			$screen,
			'side',
			'high'
		);
	}

	public static function render( $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order
			? $post_or_order
			: wc_get_order( $post_or_order->ID );

		if ( ! $order || $order->get_payment_method() !== 'moonpay' ) {
			echo '<p>' . esc_html__( 'This order was not paid via MoonPay.', 'wp-lunapay' ) . '</p>';
			return;
		}

		$mp_tx_id   = $order->get_meta( '_moonpay_transaction_id' );
		$mp_status  = $order->get_meta( '_moonpay_status' );
		$mp_widget  = $order->get_meta( '_moonpay_widget_url' );
		$last_event = $order->get_meta( '_moonpay_last_event' );
		$gateway    = WC()->payment_gateways()->payment_gateways()['moonpay'] ?? null;
		$sandbox    = $gateway ? $gateway->is_sandbox() : false;
		?>
		<div class="moonpay-metabox">
			<table class="moonpay-metabox-table widefat">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Environment', 'wp-lunapay' ); ?></th>
						<td>
							<?php if ( $sandbox ) : ?>
								<span class="moonpay-badge moonpay-badge--warning"><?php esc_html_e( 'Sandbox', 'wp-lunapay' ); ?></span>
							<?php else : ?>
								<span class="moonpay-badge moonpay-badge--success"><?php esc_html_e( 'Live', 'wp-lunapay' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'MoonPay Status', 'wp-lunapay' ); ?></th>
						<td>
							<?php if ( $mp_status ) : ?>
								<span class="moonpay-badge moonpay-status-<?php echo esc_attr( $mp_status ); ?>">
									<?php echo esc_html( ucfirst( $mp_status ) ); ?>
								</span>
							<?php else : ?>
								<em><?php esc_html_e( 'No webhook received yet', 'wp-lunapay' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( $mp_tx_id ) : ?>
					<tr>
						<th><?php esc_html_e( 'Transaction ID', 'wp-lunapay' ); ?></th>
						<td><code><?php echo esc_html( $mp_tx_id ); ?></code></td>
					</tr>
					<?php endif; ?>
					<?php if ( $mp_widget ) : ?>
					<tr>
						<th><?php esc_html_e( 'Widget URL', 'wp-lunapay' ); ?></th>
						<td><a href="<?php echo esc_url( $mp_widget ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Widget', 'wp-lunapay' ); ?></a></td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $mp_tx_id && $gateway ) : ?>
			<p style="margin-top:10px;">
				<button type="button" class="button button-secondary" id="moonpay-refresh-btn"
					data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_lunapay_refresh_' . $order->get_id() ) ); ?>">
					<?php esc_html_e( 'Refresh from MoonPay API', 'wp-lunapay' ); ?>
				</button>
				<span id="moonpay-refresh-result" style="margin-left:8px;"></span>
			</p>
			<script>
			jQuery(function($){
				$('#moonpay-refresh-btn').on('click', function(){
					var btn=$(this);
					btn.prop('disabled',true).text('<?php esc_html_e( 'Refreshing…', 'wp-lunapay' ); ?>');
					$.post(ajaxurl,{action:'wp_lunapay_refresh_tx',order_id:btn.data('order-id'),nonce:btn.data('nonce')},function(res){
						btn.prop('disabled',false).text('<?php esc_html_e( 'Refresh from MoonPay API', 'wp-lunapay' ); ?>');
						if(res.success){$('#moonpay-refresh-result').text('<?php esc_html_e( 'Updated!', 'wp-lunapay' ); ?>').css('color','green');setTimeout(function(){location.reload();},1200);}
						else{$('#moonpay-refresh-result').text(res.data||'<?php esc_html_e( 'Error', 'wp-lunapay' ); ?>').css('color','red');}
					});
				});
			});
			</script>
			<?php endif; ?>

			<?php if ( $last_event ) : ?>
			<details style="margin-top:10px;">
				<summary><?php esc_html_e( 'Last Webhook Event', 'wp-lunapay' ); ?></summary>
				<pre style="font-size:10px;overflow:auto;max-height:200px;"><?php echo esc_html( json_encode( json_decode( $last_event ), JSON_PRETTY_PRINT ) ); ?></pre>
			</details>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function inline_order_note( WC_Order $order ): void {
		if ( $order->get_payment_method() !== 'moonpay' ) return;
		$mp_tx_id = $order->get_meta( '_moonpay_transaction_id' );
		if ( $mp_tx_id ) {
			echo '<p><strong>' . esc_html__( 'MoonPay TX:', 'wp-lunapay' ) . '</strong> <code>' . esc_html( $mp_tx_id ) . '</code></p>';
		}
	}

	public static function ajax_refresh_transaction(): void {
		$order_id = absint( $_POST['order_id'] ?? 0 );
		check_ajax_referer( 'wp_lunapay_refresh_' . $order_id, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-lunapay' ) );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) { wp_send_json_error( __( 'Order not found.', 'wp-lunapay' ) ); }

		$gateway = WC()->payment_gateways()->payment_gateways()['moonpay'] ?? null;
		if ( ! $gateway ) { wp_send_json_error( __( 'Gateway not available.', 'wp-lunapay' ) ); }

		$result = $gateway->get_api()->get_transactions_by_external_id(
			'order_' . $order_id . '_' . $order->get_order_key()
		);
		if ( is_wp_error( $result ) ) { wp_send_json_error( $result->get_error_message() ); }

		$transactions = $result['transactions'] ?? ( isset( $result[0] ) ? $result : [] );
		if ( ! empty( $transactions ) ) {
			$tx = $transactions[0];
			$order->update_meta_data( '_moonpay_transaction_id', sanitize_text_field( $tx['id'] ?? '' ) );
			$order->update_meta_data( '_moonpay_status', sanitize_text_field( $tx['status'] ?? '' ) );
			$order->update_meta_data( '_moonpay_last_event', wp_json_encode( $tx ) );
			$order->save();
		}
		wp_send_json_success();
	}
}