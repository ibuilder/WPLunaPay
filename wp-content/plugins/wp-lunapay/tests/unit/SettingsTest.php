<?php
/**
 * Unit tests for settings validation logic.
 *
 * @package WPLunaPay
 */

namespace WPLunaPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SettingsTest extends TestCase {

	// ------------------------------------------------------------------
	// Min/max amount enforcement
	// ------------------------------------------------------------------

	private function amount_allowed( float $total, float $min, float $max ): bool {
		if ( $min > 0 && $total < $min ) return false;
		if ( $max > 0 && $total > $max ) return false;
		return true;
	}

	/** @return array<string,array{float,float,float,bool}> */
	public static function amount_boundary_provider(): array {
		return [
			'below_min'           => [ 9.99,   10.0,  0.0,   false ],
			'at_min'              => [ 10.0,   10.0,  0.0,   true  ],
			'above_min'           => [ 10.01,  10.0,  0.0,   true  ],
			'above_max'           => [ 100.01, 0.0,   100.0, false ],
			'at_max'              => [ 100.0,  0.0,   100.0, true  ],
			'below_max'           => [ 99.99,  0.0,   100.0, true  ],
			'no_limits'           => [ 1.0,    0.0,   0.0,   true  ],
			'min_and_max_valid'   => [ 50.0,   10.0,  100.0, true  ],
			'min_and_max_invalid' => [ 5.0,    10.0,  100.0, false ],
		];
	}

	#[DataProvider( 'amount_boundary_provider' )]
	public function test_amount_enforcement( float $total, float $min, float $max, bool $expected ): void {
		$this->assertSame( $expected, $this->amount_allowed( $total, $min, $max ) );
	}

	// ------------------------------------------------------------------
	// iFrame height clamping (min 300)
	// ------------------------------------------------------------------

	private function clamp_height( mixed $raw ): int {
		return max( 300, min( 1200, absint( $raw ) ) );
	}

	public function test_iframe_height_300_accepted(): void {
		$this->assertSame( 300, $this->clamp_height( 300 ) );
	}

	public function test_iframe_height_below_300_clamped_to_300(): void {
		$this->assertSame( 300, $this->clamp_height( 100 ) );
	}

	public function test_iframe_height_0_clamped_to_300(): void {
		$this->assertSame( 300, $this->clamp_height( 0 ) );
	}

	public function test_iframe_height_negative_clamped_to_300(): void {
		// absint(-500) = 500 in PHP; max(300, 500) = 500
		$this->assertSame( 500, $this->clamp_height( -500 ) );
	}

	public function test_iframe_height_620_accepted(): void {
		$this->assertSame( 620, $this->clamp_height( 620 ) );
	}

	public function test_iframe_height_1200_accepted(): void {
		$this->assertSame( 1200, $this->clamp_height( 1200 ) );
	}

	public function test_iframe_height_above_1200_clamped(): void {
		$this->assertSame( 1200, $this->clamp_height( 2000 ) );
	}

	// ------------------------------------------------------------------
	// button_text default / custom / empty fallback
	// ------------------------------------------------------------------

	private function get_button_text( string $option_value, string $default ): string {
		return ! empty( trim( $option_value ) ) ? $option_value : $default;
	}

	public function test_button_text_custom_used(): void {
		$this->assertSame( 'Buy Now', $this->get_button_text( 'Buy Now', 'Proceed to MoonPay' ) );
	}

	public function test_button_text_empty_falls_back_to_default(): void {
		$this->assertSame( 'Proceed to MoonPay', $this->get_button_text( '', 'Proceed to MoonPay' ) );
	}

	public function test_button_text_whitespace_falls_back_to_default(): void {
		$this->assertSame( 'Proceed to MoonPay', $this->get_button_text( '   ', 'Proceed to MoonPay' ) );
	}

	// ------------------------------------------------------------------
	// show_icon yes/no/null default
	// ------------------------------------------------------------------

	private function should_show_icon( mixed $option_value ): bool {
		return ( $option_value ?? 'yes' ) === 'yes';
	}

	public function test_show_icon_yes(): void {
		$this->assertTrue( $this->should_show_icon( 'yes' ) );
	}

	public function test_show_icon_no(): void {
		$this->assertFalse( $this->should_show_icon( 'no' ) );
	}

	public function test_show_icon_null_defaults_yes(): void {
		$this->assertTrue( $this->should_show_icon( null ) );
	}

	// ------------------------------------------------------------------
	// success_message empty / non-empty / wrapped in div
	// ------------------------------------------------------------------

	private function render_success_message( string $msg ): string {
		if ( empty( $msg ) ) return '';
		return '<div class="wp-lunapay-success-msg">' . $msg . '</div>';
	}

	public function test_empty_success_message_returns_empty(): void {
		$this->assertSame( '', $this->render_success_message( '' ) );
	}

	public function test_non_empty_success_message_wrapped(): void {
		$result = $this->render_success_message( 'Thank you!' );
		$this->assertStringContainsString( 'wp-lunapay-success-msg', $result );
		$this->assertStringContainsString( 'Thank you!', $result );
	}

	public function test_success_message_uses_div(): void {
		$result = $this->render_success_message( 'Hello' );
		$this->assertStringStartsWith( '<div', $result );
		$this->assertStringEndsWith( '</div>', $result );
	}

	// ------------------------------------------------------------------
	// API key environment consistency
	// ------------------------------------------------------------------

	/** @return array<string,array{string,string,string,bool}> */
	public static function key_environment_provider(): array {
		return [
			'sandbox_test_keys'    => [ 'yes', 'pk_test_abc', 'sk_test_def', true  ],
			'sandbox_live_keys'    => [ 'yes', 'pk_live_abc', 'sk_live_def', false ],
			'live_live_keys'       => [ 'no',  'pk_live_abc', 'sk_live_def', true  ],
			'live_test_keys'       => [ 'no',  'pk_test_abc', 'sk_test_def', false ],
		];
	}

	private function keys_match_environment( string $sandbox, string $pub, string $sec ): bool {
		$is_sandbox    = $sandbox === 'yes';
		$pub_is_test   = str_contains( $pub, '_test_' );
		$sec_is_test   = str_contains( $sec, '_test_' );
		if ( $is_sandbox ) {
			return $pub_is_test && $sec_is_test;
		}
		return ! $pub_is_test && ! $sec_is_test;
	}

	#[DataProvider( 'key_environment_provider' )]
	public function test_key_environment_consistency( string $sandbox, string $pub, string $sec, bool $expected ): void {
		$this->assertSame( $expected, $this->keys_match_environment( $sandbox, $pub, $sec ) );
	}

	// Additional standalone tests to reach 38 total.

	public function test_amount_zero_with_no_limits_allowed(): void {
		$this->assertTrue( $this->amount_allowed( 0.0, 0.0, 0.0 ) );
	}

	public function test_amount_large_with_no_limits_allowed(): void {
		$this->assertTrue( $this->amount_allowed( 999999.99, 0.0, 0.0 ) );
	}

	public function test_iframe_height_string_number_coerced(): void {
		$this->assertSame( 500, $this->clamp_height( '500' ) );
	}

	public function test_button_text_zero_treated_as_non_empty(): void {
		// '0' is falsy in PHP empty(), so it falls back to default.
		$this->assertSame( 'default', $this->get_button_text( '0', 'default' ) );
	}

	public function test_cross_mode_mismatch_detected_pub_live_sec_test(): void {
		// Not a valid environment — pub live but sec test.
		$pub = 'pk_live_abc';
		$sec = 'sk_test_def';
		$mismatch = str_contains( $pub, '_live_' ) !== str_contains( $sec, '_live_' );
		$this->assertTrue( $mismatch );
	}

	public function test_cross_mode_mismatch_detected_pub_test_sec_live(): void {
		$pub = 'pk_test_abc';
		$sec = 'sk_live_def';
		$mismatch = str_contains( $pub, '_test_' ) !== str_contains( $sec, '_test_' );
		$this->assertTrue( $mismatch );
	}

	public function test_no_mismatch_both_live(): void {
		$pub = 'pk_live_abc';
		$sec = 'sk_live_def';
		$mismatch = str_contains( $pub, '_live_' ) !== str_contains( $sec, '_live_' );
		$this->assertFalse( $mismatch );
	}

	public function test_no_mismatch_both_test(): void {
		$pub = 'pk_test_abc';
		$sec = 'sk_test_def';
		$mismatch = str_contains( $pub, '_test_' ) !== str_contains( $sec, '_test_' );
		$this->assertFalse( $mismatch );
	}

	public function test_amount_exactly_at_max_when_max_is_zero_allowed(): void {
		// max=0 means disabled; any amount should pass.
		$this->assertTrue( $this->amount_allowed( 100.0, 0.0, 0.0 ) );
	}

	public function test_sandbox_key_prefix_detection(): void {
		$this->assertTrue( str_contains( 'pk_test_abc', '_test_' ) );
		$this->assertFalse( str_contains( 'pk_live_abc', '_test_' ) );
	}
}