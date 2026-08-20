<?php
/**
 * WordPress dashboard widget for WP LunaPay.
 *
 * @package WPLunaPay
 * @copyright 2025 WPLunaPay (https://wprealwise.com)
 */

defined( 'ABSPATH' ) || exit;

class WP_LunaPay_Dashboard_Widget {

	public static function init(): void {
		add_action( 'wp_dashboard_setup', [ self::class, 'register' ] );
	}

	public static function register(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'wp_lunapay_dashboard_widget',
			__( 'MoonPay Overview', 'wp-lunapay' ),
			[ self::class, 'render' ]
		);
	}

	public static function render(): void {
		$opts       = get_option( 'woocommerce_moonpay_settings', [] );
		$enabled    = ( $opts['enabled'] ?? 'no' ) === 'yes';
		$sandbox    = ( $opts['sandbox'] ?? 'yes' ) === 'yes';
		$checklist  = self::build_checklist();
		$done_count = count( array_filter( array_column( $checklist, 'done' ) ) );
		$total      = count( $checklist );
		$pct        = $total > 0 ? round( $done_count / $total * 100 ) : 0;
		$stats      = self::get_quick_stats();

		// Environment pill.
		if ( ! $enabled ) {
			$pill_class = 'wp-lunapay-pill--disabled';
			$pill_label = __( 'Disabled', 'wp-lunapay' );
		} elseif ( $sandbox ) {
			$pill_class = 'wp-lunapay-pill--sandbox';
			$pill_label = __( 'Sandbox', 'wp-lunapay' );
		} else {
			$pill_class = 'wp-lunapay-pill--live';
			$pill_label = __( 'Live', 'wp-lunapay' );
		}
		?>
		<div class="wp-lunapay-dash-widget">

			<!-- Env pill -->
			<p><span class="wp-lunapay-pill <?php echo esc_attr( $pill_class ); ?>"><?php echo esc_html( $pill_label ); ?></span></p>

			<!-- Setup checklist -->
			<h4 style="margin-bottom:4px;"><?php esc_html_e( 'Setup Progress', 'wp-lunapay' ); ?></h4>
			<div class="wp-lunapay-progress-bar" style="background:#eee;border-radius:4px;height:8px;margin-bottom:8px;">
				<div style="width:<?php echo esc_attr( $pct ); ?>%;background:#7b3fe4;height:8px;border-radius:4px;"></div>
			</div>
			<ul class="wp-lunapay-checklist" style="margin:0 0 12px 0;padding:0;list-style:none;">
				<?php foreach ( $checklist as $item ) : ?>
					<li style="padding:2px 0;">
						<?php echo $item['done'] ? esc_html( '✓' ) : esc_html( '○' ); ?>
						<?php echo esc_html( $item['label'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<!-- 30-day stats -->
			<h4 style="margin-bottom:4px;"><?php esc_html_e( 'Last 30 Days', 'wp-lunapay' ); ?></h4>
			<table class="wp-lunapay-quick-stats" style="width:100%;border-collapse:collapse;">
				<tr>
					<td><?php esc_html_e( 'Orders', 'wp-lunapay' ); ?></td>
					<td style="text-align:right;"><?php echo esc_html( $stats['orders'] ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Revenue', 'wp-lunapay' ); ?></td>
					<td style="text-align:right;"><?php echo wp_kses_post( wc_price( $stats['revenue'] ) ); ?></td>
				</tr>
			</table>

			<!-- Quick links -->
			<p style="margin-top:12px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=moonpay' ) ); ?>"><?php esc_html_e( 'Settings', 'wp-lunapay' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-lunapay-setup' ) ); ?>"><?php esc_html_e( 'Setup Wizard', 'wp-lunapay' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-lunapay-transactions' ) ); ?>"><?php esc_html_e( 'Transactions', 'wp-lunapay' ); ?></a>
			</p>

			<?php if ( apply_filters( 'wplunapay_show_credit', true ) ) : ?>
			<!-- Credit -->
			<p style="text-align:center;color:#999;font-size:11px;margin-top:8px;">
				<?php
				printf(
					/* translators: %s = URL */
					esc_html__( 'Powered by %s', 'wp-lunapay' ),
					'<a href="https://wprealwise.com" target="_blank" rel="noopener">WPLunaPay</a>'
				);
				?>
			</p>
			<?php endif; ?>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Checklist
	// ------------------------------------------------------------------

	/**
	 * Returns 5-item setup checklist.
	 *
	 * @return array<int, array{label:string, done:bool}>
	 */
	public static function build_checklist(): array {
		$opts  = get_option( 'woocommerce_moonpay_settings', [] );
		$setup = get_option( 'wp_lunapay_setup', [] );

		$pub = trim( $opts['publishable_key'] ?? '' );
		$sec = trim( $opts['secret_key'] ?? '' );
		$keys_configured = ! empty( $pub ) && ! empty( $sec )
			&& str_starts_with( $pub, 'pk_' ) && str_starts_with( $sec, 'sk_' );

		$gateway_enabled = ( $opts['enabled'] ?? 'no' ) === 'yes';

		$webhook_reachable = ! empty( $setup['webhook_reachable'] ) && $setup['webhook_reachable'] === true;

		$test_order_done = ! empty( $setup['test_order_id'] );

		$went_live = ( $opts['sandbox'] ?? 'yes' ) === 'no';

		return [
			[ 'label' => __( 'API keys configured', 'wp-lunapay' ),    'done' => $keys_configured ],
			[ 'label' => __( 'Gateway enabled',      'wp-lunapay' ),    'done' => $gateway_enabled ],
			[ 'label' => __( 'Webhook reachable',    'wp-lunapay' ),    'done' => $webhook_reachable ],
			[ 'label' => __( 'Test order completed', 'wp-lunapay' ),    'done' => $test_order_done ],
			[ 'label' => __( 'Went live',            'wp-lunapay' ),    'done' => $went_live ],
		];
	}

	// ------------------------------------------------------------------
	// 30-day stats
	// ------------------------------------------------------------------

	/**
	 * Returns quick stats via direct wpdb SUM queries.
	 *
	 * @return array{orders:int,revenue:float}
	 */
	public static function get_quick_stats(): array {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		$cache_key = 'wp_lunapay_quick_stats';
		$cached    = wp_cache_get( $cache_key, 'wp_lunapay' );
		if ( false !== $cached ) {
			return $cached;
		}

		if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row( $wpdb->prepare(
				'SELECT COUNT(*) AS orders, SUM(total_amount) AS revenue FROM ' . $wpdb->prefix . 'wc_orders WHERE payment_method = %s AND date_created_gmt >= %s',
				'moonpay',
				$since
			) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT COUNT(*) AS orders, SUM( pm.meta_value + 0 ) AS revenue
				   FROM {$wpdb->posts} p
				   JOIN {$wpdb->postmeta} pm  ON pm.post_id  = p.ID AND pm.meta_key  = '_order_total'
				   JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = p.ID AND pm2.meta_key = '_payment_method' AND pm2.meta_value = %s
				  WHERE p.post_type = 'shop_order'
				    AND p.post_date_gmt >= %s",
				'moonpay',
				$since
			) );
		}

		$result = [
			'orders'  => (int) ( $row->orders ?? 0 ),
			'revenue' => (float) ( $row->revenue ?? 0 ),
		];
		wp_cache_set( $cache_key, $result, 'wp_lunapay', 5 * MINUTE_IN_SECONDS );
		return $result;
	}
}
