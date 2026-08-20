<?php
/**
 * Transactions admin page for WP LunaPay.
 *
 * @package WPLunaPay
 * @copyright 2025 WPLunaPay (https://wprealwise.com)
 */

defined( 'ABSPATH' ) || exit;

class WP_LunaPay_Admin_Page {

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ] );
		add_action( 'admin_init', [ self::class, 'handle_csv_export' ] );
	}

	public static function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'MoonPay Transactions', 'wp-lunapay' ),
			__( 'MoonPay Transactions', 'wp-lunapay' ),
			'manage_woocommerce',
			'wp-lunapay-transactions',
			[ self::class, 'render' ]
		);
	}

	// ------------------------------------------------------------------
	// Render
	// ------------------------------------------------------------------

	public static function render(): void {
		$stats    = self::get_stats();
		$per_page = 20;
		$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin-only display, no state change.
		$offset   = ( $paged - 1 ) * $per_page;
		$orders   = self::get_orders( $per_page, $offset );
		$total    = (int) ( $stats['total'] ?? 0 );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		?>
		<div class="wrap wp-lunapay-transactions-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'MoonPay Transactions', 'wp-lunapay' ); ?></h1>
			<a href="<?php echo esc_url( self::csv_export_url() ); ?>" class="page-title-action">
				<?php esc_html_e( 'Export CSV', 'wp-lunapay' ); ?>
			</a>
			<hr class="wp-header-end">

			<!-- Stats cards -->
			<div class="wp-lunapay-stats-row">
				<?php self::stat_card( __( 'Total Orders', 'wp-lunapay' ),     $stats['total'] ); ?>
				<?php self::stat_card( __( 'Completed',    'wp-lunapay' ),     $stats['completed'] ); ?>
				<?php self::stat_card( __( 'Pending',      'wp-lunapay' ),     $stats['pending'] ); ?>
				<?php self::stat_card( __( 'Failed',       'wp-lunapay' ),     $stats['failed'] ); ?>
				<?php self::stat_card( __( 'Revenue',      'wp-lunapay' ),     wc_price( $stats['revenue'] ), false ); ?>
			</div>

			<!-- Orders table -->
			<table class="widefat striped wp-lunapay-orders-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order', 'wp-lunapay' ); ?></th>
						<th><?php esc_html_e( 'Date', 'wp-lunapay' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'wp-lunapay' ); ?></th>
						<th><?php esc_html_e( 'Total', 'wp-lunapay' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-lunapay' ); ?></th>
						<th><?php esc_html_e( 'MoonPay Status', 'wp-lunapay' ); ?></th>
						<th><?php esc_html_e( 'Transaction ID', 'wp-lunapay' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $orders ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No orders found.', 'wp-lunapay' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $orders as $order ) : ?>
							<?php
							$mp_status = $order->get_meta( '_moonpay_status' );
							$mp_tx_id  = $order->get_meta( '_moonpay_transaction_id' );
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
										#<?php echo esc_html( $order->get_id() ); ?>
									</a>
								</td>
								<td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
								<td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
								<td><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></td>
								<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
								<td><?php echo $mp_status ? '<span class="wp-lunapay-status wp-lunapay-status--' . esc_attr( $mp_status ) . '">' . esc_html( ucfirst( $mp_status ) ) . '</span>' : '—'; ?></td>
								<td><?php echo $mp_tx_id ? '<code>' . esc_html( $mp_tx_id ) . '</code>' : '—'; ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post( paginate_links( [
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'total'   => $pages,
							'current' => $paged,
						] ) );
						?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( apply_filters( 'wplunapay_show_credit', true ) ) : ?>
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
			<?php endif; ?>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Stats
	// ------------------------------------------------------------------

	/**
	 * Returns aggregated stats using direct DB queries (no full order loads).
	 *
	 * @return array{total:int,completed:int,pending:int,failed:int,revenue:float}
	 */
	public static function get_stats(): array {
		global $wpdb;

		$defaults = [ 'total' => 0, 'completed' => 0, 'pending' => 0, 'failed' => 0, 'revenue' => 0.0 ];

		// HPOS detection.
		$hpos = self::is_hpos_enabled();

		$cache_key = 'wp_lunapay_stats';
		$cached    = wp_cache_get( $cache_key, 'wp_lunapay' );
		if ( false !== $cached ) {
			return $cached;
		}

		if ( $hpos ) {
			$table = $wpdb->prefix . 'wc_orders'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is always a WP core table name, never user input.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT status, COUNT(*) as cnt, SUM(total_amount) as rev FROM {$table} WHERE payment_method = %s GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'moonpay'
			) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.post_status AS status, COUNT(*) AS cnt, SUM( pm.meta_value + 0 ) AS rev
				   FROM {$wpdb->posts} p
				   JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_order_total'
				   JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = p.ID AND pm2.meta_key = '_payment_method' AND pm2.meta_value = %s
				  WHERE p.post_type = 'shop_order'
				  GROUP BY p.post_status",
				'moonpay'
			) );
		}

		if ( ! $rows ) {
			return $defaults;
		}

		$stats = $defaults;
		foreach ( $rows as $row ) {
			$status = str_replace( 'wc-', '', $row->status );
			$stats['total'] += (int) $row->cnt;
			$stats['revenue'] += (float) $row->rev;
			if ( in_array( $status, [ 'completed', 'processing' ], true ) ) {
				$stats['completed'] += (int) $row->cnt;
			} elseif ( $status === 'pending' ) {
				$stats['pending'] += (int) $row->cnt;
			} elseif ( in_array( $status, [ 'failed', 'cancelled' ], true ) ) {
				$stats['failed'] += (int) $row->cnt;
			}
		}

		wp_cache_set( $cache_key, $stats, 'wp_lunapay', 5 * MINUTE_IN_SECONDS );
		return $stats;
	}

	// ------------------------------------------------------------------
	// Order query
	// ------------------------------------------------------------------

	/**
	 * @param int $limit
	 * @param int $offset
	 * @return WC_Order[]
	 */
	private static function get_orders( int $limit, int $offset ): array {
		$result = wc_get_orders( [
			'payment_method' => 'moonpay',
			'limit'          => $limit,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );
		return $result ?: [];
	}

	// ------------------------------------------------------------------
	// CSV Export
	// ------------------------------------------------------------------

	public static function handle_csv_export(): void {
		if ( ! isset( $_GET['wp_lunapay_export_csv'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-lunapay' ) );
		}
		check_admin_referer( 'wp_lunapay_export_csv' );

		$orders = self::get_orders( -1, 0 );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="moonpay-transactions-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'Order ID', 'Date', 'Customer', 'Email', 'Total', 'Status', 'MoonPay Status', 'Transaction ID' ] );

		foreach ( $orders as $order ) {
			fputcsv( $out, [
				$order->get_id(),
				$order->get_date_created()?->format( 'Y-m-d H:i:s' ) ?? '',
				$order->get_formatted_billing_full_name(),
				$order->get_billing_email(),
				$order->get_total(),
				$order->get_status(),
				$order->get_meta( '_moonpay_status' ),
				$order->get_meta( '_moonpay_transaction_id' ),
			] );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming to php://output for CSV; WP_Filesystem not applicable here.
		exit;
	}

	private static function csv_export_url(): string {
		return wp_nonce_url(
			add_query_arg( [ 'wp_lunapay_export_csv' => '1' ] ),
			'wp_lunapay_export_csv'
		);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private static function stat_card( string $label, $value, bool $escape = true ): void {
		echo '<div class="wp-lunapay-stat-card"><span class="wp-lunapay-stat-label">' . esc_html( $label ) . '</span>'
			. '<span class="wp-lunapay-stat-value">' . ( $escape ? esc_html( (string) $value ) : wp_kses_post( (string) $value ) ) . '</span></div>';
	}

	public static function is_hpos_enabled(): bool {
		if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) ) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		return false;
	}
}
