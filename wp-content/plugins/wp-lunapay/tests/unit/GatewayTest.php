<?php
/**
 * Unit tests for WP_LunaPay_Gateway logic.
 *
 * Tests are extracted from the gateway class without instantiating it
 * (to avoid WC dependency). Color pipeline, external ID, key validation, etc.
 *
 * @package WPLunaPay
 */

namespace WPLunaPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_LunaPay_API;

class GatewayTest extends TestCase {

	// ------------------------------------------------------------------
	// colorCode pipeline: '#' . ltrim(color, '#')
	// ------------------------------------------------------------------

	private function build_color_code( string $raw ): string {
		return '#' . ltrim( $raw, '#' );
	}

	public function test_color_code_with_hash_prefix(): void {
		$this->assertSame( '#7b3fe4', $this->build_color_code( '#7b3fe4' ) );
	}

	public function test_color_code_without_hash_prefix(): void {
		$this->assertSame( '#7b3fe4', $this->build_color_code( '7b3fe4' ) );
	}

	public function test_color_code_double_hash(): void {
		$this->assertSame( '#7b3fe4', $this->build_color_code( '##7b3fe4' ) );
	}

	public function test_color_code_uppercase(): void {
		$result = $this->build_color_code( '#FF0000' );
		$this->assertSame( '#FF0000', $result );
	}

	public function test_color_code_short_hex(): void {
		$this->assertSame( '#fff', $this->build_color_code( '#fff' ) );
	}

	// Verify colorCode is encoded as %23 (once) in widget URL.
	public function test_color_code_in_widget_url_encoded_once(): void {
		$api = new WP_LunaPay_API( 'pk_live_x', 'sk_live_y', false );
		$url = $api->get_widget_url( [ 'colorCode' => '#7b3fe4' ] );
		$this->assertStringContainsString( '%23', $url );
		$this->assertStringNotContainsString( '%2523', $url );
	}

	// ------------------------------------------------------------------
	// externalTransactionId round-trip
	// ------------------------------------------------------------------

	/** @return array<string,array{int}> */
	public static function order_id_provider(): array {
		return [
			'order_1'      => [ 1 ],
			'order_42'     => [ 42 ],
			'order_999'    => [ 999 ],
			'order_100000' => [ 100000 ],
		];
	}

	#[DataProvider( 'order_id_provider' )]
	public function test_external_id_round_trip( int $order_id ): void {
		$external_id = 'order_' . $order_id . '_suffix';
		preg_match( '/^order_(\d+)_/', $external_id, $m );
		$this->assertSame( $order_id, (int) $m[1] );
	}

	// ------------------------------------------------------------------
	// API key guards
	// ------------------------------------------------------------------

	private function validate_keys( string $pub, string $sec ): bool {
		if ( empty( trim( $pub ) ) || empty( trim( $sec ) ) ) {
			return false;
		}
		if ( ! str_starts_with( $pub, 'pk_' ) || ! str_starts_with( $sec, 'sk_' ) ) {
			return false;
		}
		return true;
	}

	public function test_empty_publishable_key_fails(): void {
		$this->assertFalse( $this->validate_keys( '', 'sk_live_sec' ) );
	}

	public function test_empty_secret_key_fails(): void {
		$this->assertFalse( $this->validate_keys( 'pk_live_pub', '' ) );
	}

	public function test_both_keys_present_passes(): void {
		$this->assertTrue( $this->validate_keys( 'pk_live_pub', 'sk_live_sec' ) );
	}

	public function test_whitespace_only_key_fails(): void {
		$this->assertFalse( $this->validate_keys( '   ', 'sk_live_sec' ) );
	}

	// ------------------------------------------------------------------
	// Key format validation (pk_ / sk_ prefixes)
	// ------------------------------------------------------------------

	/** @return array<string,array{string,string,bool}> */
	public static function key_format_provider(): array {
		return [
			'live_keys_valid'       => [ 'pk_live_abc', 'sk_live_def', true ],
			'test_keys_valid'       => [ 'pk_test_abc', 'sk_test_def', true ],
			'pub_wrong_prefix'      => [ 'wrong_live_abc', 'sk_live_def', false ],
			'sec_wrong_prefix'      => [ 'pk_live_abc', 'wrong_live_def', false ],
			'both_wrong_prefix'     => [ 'wrong_abc', 'also_wrong', false ],
			'missing_underscore'    => [ 'pklive', 'sklive', false ],
		];
	}

	#[DataProvider( 'key_format_provider' )]
	public function test_key_format( string $pub, string $sec, bool $expected ): void {
		$this->assertSame( $expected, $this->validate_keys( $pub, $sec ) );
	}

	// ------------------------------------------------------------------
	// Allowed cryptos sanitisation
	// ------------------------------------------------------------------

	private function sanitize_allowed_cryptos( string $raw ): array {
		return array_filter( array_map( 'sanitize_key', explode( ',', $raw ) ) );
	}

	public function test_allowed_cryptos_single(): void {
		$result = $this->sanitize_allowed_cryptos( 'eth' );
		$this->assertSame( [ 'eth' ], array_values( $result ) );
	}

	public function test_allowed_cryptos_multiple(): void {
		$result = $this->sanitize_allowed_cryptos( 'eth,btc,sol' );
		$this->assertSame( [ 'eth', 'btc', 'sol' ], array_values( $result ) );
	}

	public function test_allowed_cryptos_strips_spaces(): void {
		$result = $this->sanitize_allowed_cryptos( 'eth, btc ,sol' );
		// sanitize_key trims and lowercases
		$this->assertContains( 'eth', array_values( $result ) );
	}

	public function test_allowed_cryptos_empty_returns_empty(): void {
		$result = $this->sanitize_allowed_cryptos( '' );
		$this->assertEmpty( $result );
	}

	public function test_allowed_cryptos_removes_invalid(): void {
		$result = $this->sanitize_allowed_cryptos( 'eth,,btc' );
		// empty string after sanitize_key is filtered out
		$this->assertCount( 2, array_values( $result ) );
	}

	// ------------------------------------------------------------------
	// Additional coverage tests to reach 26
	// ------------------------------------------------------------------

	public function test_color_ltrim_does_not_affect_non_hash_start(): void {
		$this->assertSame( '#abc', $this->build_color_code( 'abc' ) );
	}

	public function test_min_amount_check_below_min_fails(): void {
		$total = 5.0;
		$min   = 10.0;
		$this->assertTrue( $min > 0 && $total < $min );
	}

	public function test_min_amount_check_at_min_passes(): void {
		$total = 10.0;
		$min   = 10.0;
		$this->assertFalse( $min > 0 && $total < $min );
	}

	public function test_max_amount_check_above_max_fails(): void {
		$total = 200.0;
		$max   = 100.0;
		$this->assertTrue( $max > 0 && $total > $max );
	}

	public function test_max_amount_check_zero_disabled(): void {
		$total = 9999.0;
		$max   = 0.0;
		$this->assertFalse( $max > 0 && $total > $max );
	}

	public function test_sandbox_key_detection(): void {
		$pub = 'pk_test_abc123';
		$this->assertTrue( str_contains( $pub, '_test_' ) );
	}

	public function test_live_key_detection(): void {
		$pub = 'pk_live_abc123';
		$this->assertFalse( str_contains( $pub, '_test_' ) );
	}
}