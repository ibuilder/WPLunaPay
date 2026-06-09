<?php
/**
 * Receipt page / order-pay template.
 *
 * Variables: $order, $widget_url, $widget_mode, $iframe_height, $order_id
 *
 * @package WPLunaPay
 * @copyright 2025 WPLunaPay (https://wprealwise.com)
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="moonpay-payment-container" id="moonpay-payment-container" data-mode="<?php echo esc_attr( $widget_mode ); ?>">

	<div class="moonpay-payment-header">
		<h2><?php esc_html_e( 'Complete Your Payment', 'wp-lunapay' ); ?></h2>
		<p class="moonpay-payment-subheading">
			<?php printf(
				/* translators: %s = order total */
				esc_html__( 'Order total: %s — Pay securely with crypto via MoonPay.', 'wp-lunapay' ),
				'<strong>' . wp_kses_post( $order->get_formatted_order_total() ) . '</strong>'
			); ?>
		</p>
	</div>

	<?php if ( $widget_mode === 'iframe' ) : ?>
		<div class="moonpay-iframe-container">
			<iframe id="moonpay-widget-iframe"
				src="<?php echo esc_url( $widget_url ); ?>"
				width="100%" height="<?php echo esc_attr( $iframe_height ); ?>px"
				frameborder="0"
				allow="accelerometer; autoplay; camera; gyroscope; payment"
				title="<?php esc_attr_e( 'MoonPay Payment Widget', 'wp-lunapay' ); ?>"></iframe>
		</div>

	<?php elseif ( $widget_mode === 'popup' ) : ?>
		<div class="moonpay-popup-container">
			<p><?php esc_html_e( 'Click the button below to open the MoonPay payment window.', 'wp-lunapay' ); ?></p>
			<button id="moonpay-open-popup" class="button alt moonpay-popup-trigger"
				data-url="<?php echo esc_url( $widget_url ); ?>">
				<?php esc_html_e( 'Open MoonPay to Pay', 'wp-lunapay' ); ?>
			</button>
		</div>
		<div class="moonpay-popup-overlay" id="moonpay-popup-overlay" style="display:none;">
			<div class="moonpay-popup-modal">
				<button class="moonpay-popup-close" id="moonpay-popup-close"
					aria-label="<?php esc_attr_e( 'Close', 'wp-lunapay' ); ?>">&times;</button>
				<iframe id="moonpay-popup-iframe" src="" frameborder="0"
					allow="accelerometer; autoplay; camera; gyroscope; payment"
					title="<?php esc_attr_e( 'MoonPay Payment Widget', 'wp-lunapay' ); ?>"
					height="<?php echo esc_attr( $iframe_height ); ?>px"></iframe>
			</div>
		</div>

	<?php else : ?>
		<div class="moonpay-redirect-container">
			<p><?php esc_html_e( 'You will be redirected to MoonPay to complete your payment…', 'wp-lunapay' ); ?></p>
			<a id="moonpay-redirect-link" href="<?php echo esc_url( $widget_url ); ?>" class="button alt">
				<?php esc_html_e( 'Continue to MoonPay', 'wp-lunapay' ); ?>
			</a>
		</div>
		<script>
		(function(){ window.location.href = <?php echo wp_json_encode( $widget_url ); ?>; })();
		</script>
	<?php endif; ?>

	<p class="moonpay-payment-footer">
		<?php esc_html_e( 'Your payment is processed securely by MoonPay. Funds settle to the merchant after confirmation.', 'wp-lunapay' ); ?>
	</p>

</div>