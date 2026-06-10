<?php
/**
 * Handles incoming MoonPay webhook events.
 * Webhook URL: https://yoursite.com/?wc-api=wp_lunapay_webhook
 *
 * @package WPLunaPay
 * @copyright 2025 WPLunaPay (https://wprealwise.com)
 */

defined( 'ABSPATH' ) || exit;

class WP_LunaPay_Webhook {

	public static function init(): void {
		add_action( 'woocommerce_api_wp_lunapay_webhook', [ self::class, 'handle' ] );
	}

	public static function handle(): void {
		$payload   = file_get_contents( 'php://input' );
		$signature = sanitize_text_field( wp_unslash( $_SERVER['HTTP_MOONPAY_SIGNATURE_V2'] ?? '' ) );

		$gateway = self::get_gateway();
		if ( ! $gateway ) {
			wp_die( 'Gateway not configured', 'MoonPay Webhook', [ 'response' => 500 ] );
		}

		$is_sandbox = $gateway->is_sandbox();

		if ( ! $is_sandbox && empty( $signature ) ) {
			$gateway->log( 'Webhook rejected: missing signature in production mode.', 'error' );
			wp_die( 'Missing signature', 'MoonPay Webhook', [ 'response' => 401 ] );
		}

		if ( ! empty( $signature ) && ! $gateway->get_api()->verify_webhook_signature( $payload, $signature ) ) {
			$gateway->log( 'Webhook signature verification failed.', 'error' );
			wp_die( 'Invalid signature', 'MoonPay Webhook', [ 'response' => 401 ] );
		}

		$event = json_decode( $payload, true );
		if ( ! $event ) {
			wp_die( 'Invalid payload', 'MoonPay Webhook', [ 'response' => 400 ] );
		}

		$gateway->log( 'Webhook received: ' . wp_json_encode( $event ) );
		self::process_event( $event, $gateway );

		status_header( 200 );
		echo 'OK';
		exit;
	}

	private static function process_event( array $event, WP_LunaPay_Gateway $gateway ): void {
		$data        = $event['data'] ?? $event;
		$external_id = $data['externalTransactionId'] ?? '';
		$mp_status   = $data['status'] ?? '';
		$mp_tx_id    = $data['id'] ?? '';

		if ( empty( $external_id ) ) {
			$gateway->log( 'Webhook missing externalTransactionId.', 'warning' );
			return;
		}

		if ( ! preg_match( '/^order_(\d+)_/', $external_id, $matches ) ) {
			$gateway->log( 'Could not parse order ID from externalTransactionId: ' . $external_id, 'warning' );
			return;
		}
		$order_id = (int) $matches[1];

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$gateway->log( 'Order not found: ' . $order_id, 'warning' );
			return;
		}

		if ( $mp_tx_id ) {
			$order->update_meta_data( '_moonpay_transaction_id', sanitize_text_field( $mp_tx_id ) );
			$order->set_transaction_id( sanitize_text_field( $mp_tx_id ) );
		}

		$complete_status = str_replace( 'wc-', '', $gateway->get_option( 'order_status_complete', 'processing' ) );
		$failed_status   = str_replace( 'wc-', '', $gateway->get_option( 'order_status_failed', 'failed' ) );

		switch ( $mp_status ) {
			case 'completed':
				$order->payment_complete( $mp_tx_id );
				$order->update_status( $complete_status, sprintf(
					/* translators: %s = MoonPay transaction ID */
					__( 'MoonPay payment completed. Transaction ID: %s', 'wp-lunapay' ),
					$mp_tx_id
				) );
				break;

			case 'failed':
			case 'cancelled':
				if ( ! in_array( $order->get_status(), [ 'cancelled', 'failed' ], true ) ) {
					wc_increase_stock_levels( $order );
				}
				$order->update_status( $failed_status, sprintf(
					/* translators: %s = MoonPay status */
					__( 'MoonPay payment %s.', 'wp-lunapay' ),
					$mp_status
				) );
				break;

			case 'pending':
			case 'waitingPayment':
			case 'waitingAuthorization':
				$order->add_order_note( sprintf(
					/* translators: %s = MoonPay status */
					__( 'MoonPay payment status: %s', 'wp-lunapay' ),
					$mp_status
				) );
				break;

			default:
				$gateway->log( 'Unhandled MoonPay status: ' . $mp_status );
		}

		$order->update_meta_data( '_moonpay_status', sanitize_text_field( $mp_status ) );
		$order->update_meta_data( '_moonpay_last_event', wp_json_encode( $data ) );
		$order->save();

		do_action( 'wp_lunapay_webhook_processed', $order->get_id(), $mp_status, $data );
	}

	private static function get_gateway(): ?WP_LunaPay_Gateway {
		$gateways = WC()->payment_gateways()->payment_gateways();
		return $gateways['moonpay'] ?? null;
	}
}