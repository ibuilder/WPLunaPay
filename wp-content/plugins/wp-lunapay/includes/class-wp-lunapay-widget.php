<?php
/**
 * WordPress sidebar widget for WP LunaPay / MoonPay.
 *
 * Provides button, price, and iframe widget types.
 *
 * @package WPLunaPay
 * @copyright 2025 WPLunaPay (https://wprealwise.com)
 */

defined( 'ABSPATH' ) || exit;

class WP_LunaPay_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'wp_lunapay_widget',
			__( 'MoonPay Crypto Widget', 'wp-lunapay' ),
			[
				'description' => __( 'Display a MoonPay buy button, live price ticker, or inline iframe.', 'wp-lunapay' ),
				'classname'   => 'wp-lunapay-widget',
			]
		);
	}

	/**
	 * Front-end display.
	 *
	 * @param array $args     Widget display arguments.
	 * @param array $instance Saved widget settings.
	 */
	public function widget( $args, $instance ): void {
		$type     = $instance['type'] ?? 'button';
		$title    = apply_filters( 'widget_title', $instance['title'] ?? '', $instance, $this->id_base );
		$currency = sanitize_key( $instance['currency'] ?? 'eth' );
		$height   = max( 300, absint( $instance['height'] ?? 400 ) );

		echo $args['before_widget'];

		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}

		wp_enqueue_style( 'wp-lunapay-shortcodes' );
		wp_enqueue_script( 'wp-lunapay-shortcodes' );

		switch ( $type ) {
			case 'price':
				echo do_shortcode( '[moonpay_crypto_price currency="' . esc_attr( $currency ) . '"]' );
				break;

			case 'iframe':
				echo do_shortcode( '[moonpay_buy currency="' . esc_attr( $currency ) . '" mode="iframe" height="' . esc_attr( $height ) . '"]' );
				break;

			case 'button':
			default:
				echo do_shortcode( '[moonpay_button currency="' . esc_attr( $currency ) . '"]' );
				break;
		}

		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Previously saved values.
	 */
	public function form( $instance ): void {
		$title    = $instance['title'] ?? '';
		$type     = $instance['type'] ?? 'button';
		$currency = $instance['currency'] ?? 'eth';
		$height   = absint( $instance['height'] ?? 400 );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'wp-lunapay' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"><?php esc_html_e( 'Widget Type:', 'wp-lunapay' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>">
				<option value="button" <?php selected( $type, 'button' ); ?>><?php esc_html_e( 'Buy Button', 'wp-lunapay' ); ?></option>
				<option value="price"  <?php selected( $type, 'price' ); ?>><?php esc_html_e( 'Price Ticker', 'wp-lunapay' ); ?></option>
				<option value="iframe" <?php selected( $type, 'iframe' ); ?>><?php esc_html_e( 'Inline iFrame', 'wp-lunapay' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'currency' ) ); ?>"><?php esc_html_e( 'Crypto Currency Code:', 'wp-lunapay' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'currency' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'currency' ) ); ?>"
				type="text" value="<?php echo esc_attr( $currency ); ?>" placeholder="eth">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'height' ) ); ?>"><?php esc_html_e( 'iFrame Height (px):', 'wp-lunapay' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'height' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'height' ) ); ?>"
				type="number" value="<?php echo esc_attr( $height ); ?>" min="300" max="1200">
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values on save.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 * @return array  Sanitized settings.
	 */
	public function update( $new_instance, $old_instance ): array {
		return [
			'title'    => sanitize_text_field( $new_instance['title'] ?? '' ),
			'type'     => in_array( $new_instance['type'] ?? '', [ 'button', 'price', 'iframe' ], true )
				? $new_instance['type']
				: 'button',
			'currency' => sanitize_key( $new_instance['currency'] ?? 'eth' ),
			'height'   => max( 300, min( 1200, absint( $new_instance['height'] ?? 400 ) ) ),
		];
	}
}