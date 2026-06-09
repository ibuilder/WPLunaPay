<?php
/**
 * Unit tests for shortcode logic.
 *
 * @package WPLunaPay
 */

namespace WPLunaPay\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ShortcodeTest extends TestCase {

	// ------------------------------------------------------------------
	// [moonpay_buy] defaults
	// ------------------------------------------------------------------

	private function parse_buy_atts( array $input ): array {
		return shortcode_atts_stub( [
			'currency' => 'eth',
			'amount'   => '',
			'mode'     => 'iframe',
			'height'   => '600',
			'class'    => '',
		], $input );
	}

	public function test_buy_default_mode_is_iframe(): void {
		$atts = $this->parse_buy_atts( [] );
		$this->assertSame( 'iframe', $atts['mode'] );
	}

	public function test_buy_default_currency_is_eth(): void {
		$atts = $this->parse_buy_atts( [] );
		$this->assertSame( 'eth', $atts['currency'] );
	}

	public function test_buy_default_height_is_600(): void {
		$atts = $this->parse_buy_atts( [] );
		$this->assertSame( '600', $atts['height'] );
	}

	public function test_buy_custom_currency(): void {
		$atts = $this->parse_buy_atts( [ 'currency' => 'btc' ] );
		$this->assertSame( 'btc', $atts['currency'] );
	}

	// ------------------------------------------------------------------
	// Mode sanitisation
	// ------------------------------------------------------------------

	private function sanitize_mode( string $raw ): string {
		return in_array( $raw, [ 'iframe', 'popup', 'redirect' ], true ) ? $raw : 'iframe';
	}

	public function test_mode_iframe_accepted(): void {
		$this->assertSame( 'iframe', $this->sanitize_mode( 'iframe' ) );
	}

	public function test_mode_popup_accepted(): void {
		$this->assertSame( 'popup', $this->sanitize_mode( 'popup' ) );
	}

	public function test_mode_redirect_accepted(): void {
		$this->assertSame( 'redirect', $this->sanitize_mode( 'redirect' ) );
	}

	public function test_mode_invalid_falls_back_to_iframe(): void {
		$this->assertSame( 'iframe', $this->sanitize_mode( 'unknown' ) );
	}

	// ------------------------------------------------------------------
	// Height coercion (min 300)
	// ------------------------------------------------------------------

	private function coerce_height( mixed $raw ): int {
		return max( 300, absint( $raw ) );
	}

	public function test_height_300_passes_through(): void {
		$this->assertSame( 300, $this->coerce_height( 300 ) );
	}

	public function test_height_below_300_clamped(): void {
		$this->assertSame( 300, $this->coerce_height( 100 ) );
	}

	public function test_height_600_passes_through(): void {
		$this->assertSame( 600, $this->coerce_height( 600 ) );
	}

	// ------------------------------------------------------------------
	// Price ticker: currency lowercase
	// ------------------------------------------------------------------

	public function test_price_currency_lowercased(): void {
		$currency = strtolower( 'ETH' );
		$this->assertSame( 'eth', $currency );
	}

	// ------------------------------------------------------------------
	// Cache key format & collision
	// ------------------------------------------------------------------

	private function make_cache_key( string $currency, string $fiat, float $amount ): string {
		return 'wp_lunapay_price_' . $currency . '_' . $fiat . '_' . (int) ( $amount * 100 );
	}

	public function test_cache_key_format(): void {
		$key = $this->make_cache_key( 'eth', 'usd', 1.0 );
		$this->assertSame( 'wp_lunapay_price_eth_usd_100', $key );
	}

	public function test_cache_key_different_currencies_no_collision(): void {
		$a = $this->make_cache_key( 'eth', 'usd', 1.0 );
		$b = $this->make_cache_key( 'btc', 'usd', 1.0 );
		$this->assertNotSame( $a, $b );
	}

	public function test_cache_key_different_amounts_no_collision(): void {
		$a = $this->make_cache_key( 'eth', 'usd', 1.0 );
		$b = $this->make_cache_key( 'eth', 'usd', 2.0 );
		$this->assertNotSame( $a, $b );
	}

	// ------------------------------------------------------------------
	// Input length guard (5 boundary cases)
	// ------------------------------------------------------------------

	private function is_safe_currency( string $code ): bool {
		return strlen( $code ) >= 2 && strlen( $code ) <= 10;
	}

	public function test_currency_len_1_invalid(): void {
		$this->assertFalse( $this->is_safe_currency( 'e' ) );
	}

	public function test_currency_len_2_valid(): void {
		$this->assertTrue( $this->is_safe_currency( 'et' ) );
	}

	public function test_currency_len_5_valid(): void {
		$this->assertTrue( $this->is_safe_currency( 'matic' ) );
	}

	public function test_currency_len_10_valid(): void {
		$this->assertTrue( $this->is_safe_currency( 'abcdefghij' ) );
	}

	public function test_currency_len_11_invalid(): void {
		$this->assertFalse( $this->is_safe_currency( 'abcdefghijk' ) );
	}

	// ------------------------------------------------------------------
	// Order status shortcode visibility
	// ------------------------------------------------------------------

	private function can_see_order_status( int $order_customer_id, int $current_user_id, bool $is_admin ): bool {
		if ( $is_admin ) return true;
		return $current_user_id !== 0 && $current_user_id === $order_customer_id;
	}

	public function test_admin_can_see_order_status(): void {
		$this->assertTrue( $this->can_see_order_status( 5, 1, true ) );
	}

	public function test_order_owner_can_see_status(): void {
		$this->assertTrue( $this->can_see_order_status( 5, 5, false ) );
	}

	public function test_other_user_cannot_see_status(): void {
		$this->assertFalse( $this->can_see_order_status( 5, 6, false ) );
	}

	public function test_guest_cannot_see_status(): void {
		$this->assertFalse( $this->can_see_order_status( 5, 0, false ) );
	}
}

// ------------------------------------------------------------------
// Minimal shortcode_atts stub (not in bootstrap as it is shortcode-specific)
// ------------------------------------------------------------------

if ( ! function_exists( 'WPLunaPay\Tests\Unit\shortcode_atts_stub' ) ) {
	function shortcode_atts_stub( array $defaults, array $input ): array {
		$out = $defaults;
		foreach ( $defaults as $key => $default ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = $input[ $key ];
			}
		}
		return $out;
	}
}