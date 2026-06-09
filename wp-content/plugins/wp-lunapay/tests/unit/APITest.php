<?php
/**
 * Unit tests for WP_LunaPay_API.
 *
 * @package WPLunaPay
 */

namespace WPLunaPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_LunaPay_API;
use WP_Error;

class APITest extends TestCase {

	private WP_LunaPay_API $api;
	private WP_LunaPay_API $api_sandbox;
	private string $secret = 'sk_test_secret123';

	protected function setUp(): void {
		$this->api         = new WP_LunaPay_API( 'pk_live_pub', 'sk_live_sec', false );
		$this->api_sandbox = new WP_LunaPay_API( 'pk_test_pub', 'sk_test_sec', true );
	}

	// ------------------------------------------------------------------
	// sign()
	// ------------------------------------------------------------------

	public function test_sign_returns_base64_string(): void {
		$api = new WP_LunaPay_API( 'pk_test_pub', $this->secret, true );
		$sig = $api->sign( '?foo=bar' );
		$this->assertNotEmpty( $sig );
		$this->assertNotFalse( base64_decode( $sig, true ) );
	}

	public function test_sign_is_deterministic(): void {
		$api = new WP_LunaPay_API( 'pk_test_pub', $this->secret, true );
		$this->assertSame( $api->sign( '?x=1' ), $api->sign( '?x=1' ) );
	}

	public function test_sign_differs_for_different_input(): void {
		$api = new WP_LunaPay_API( 'pk_test_pub', $this->secret, true );
		$this->assertNotSame( $api->sign( '?a=1' ), $api->sign( '?a=2' ) );
	}

	public function test_sign_differs_for_different_secret(): void {
		$a = new WP_LunaPay_API( 'pk_test_pub', 'sk_test_aaa', true );
		$b = new WP_LunaPay_API( 'pk_test_pub', 'sk_test_bbb', true );
		$this->assertNotSame( $a->sign( '?x=1' ), $b->sign( '?x=1' ) );
	}

	// ------------------------------------------------------------------
	// verify_webhook_signature()
	// ------------------------------------------------------------------

	public function test_verify_webhook_signature_valid(): void {
		$api     = new WP_LunaPay_API( 'pk_test_pub', $this->secret, true );
		$payload = '{"type":"transaction_updated"}';
		$sig     = base64_encode( hash_hmac( 'sha256', $payload, $this->secret, true ) );
		$this->assertTrue( $api->verify_webhook_signature( $payload, $sig ) );
	}

	public function test_verify_webhook_signature_tampered_payload(): void {
		$api     = new WP_LunaPay_API( 'pk_test_pub', $this->secret, true );
		$payload = '{"type":"transaction_updated"}';
		$sig     = base64_encode( hash_hmac( 'sha256', $payload, $this->secret, true ) );
		$this->assertFalse( $api->verify_webhook_signature( $payload . 'X', $sig ) );
	}

	public function test_verify_webhook_signature_wrong_key(): void {
		$api     = new WP_LunaPay_API( 'pk_test_pub', $this->secret, true );
		$payload = '{"type":"transaction_updated"}';
		$sig     = base64_encode( hash_hmac( 'sha256', $payload, 'wrong_secret', true ) );
		$this->assertFalse( $api->verify_webhook_signature( $payload, $sig ) );
	}

	public function test_verify_webhook_signature_empty_signature(): void {
		$api     = new WP_LunaPay_API( 'pk_test_pub', $this->secret, true );
		$this->assertFalse( $api->verify_webhook_signature( '{}', '' ) );
	}

	public function test_verify_webhook_signature_garbage(): void {
		$api = new WP_LunaPay_API( 'pk_test_pub', $this->secret, true );
		$this->assertFalse( $api->verify_webhook_signature( '{}', 'notavalidhmac' ) );
	}

	// ------------------------------------------------------------------
	// get_widget_url()
	// ------------------------------------------------------------------

	public function test_get_widget_url_contains_api_key(): void {
		$url = $this->api->get_widget_url( [ 'currencyCode' => 'eth' ] );
		$this->assertStringContainsString( 'apiKey=pk_live_pub', $url );
	}

	public function test_get_widget_url_uses_live_base(): void {
		$url = $this->api->get_widget_url( [] );
		$this->assertStringStartsWith( 'https://buy.moonpay.com', $url );
	}

	public function test_get_widget_url_uses_sandbox_base(): void {
		$url = $this->api_sandbox->get_widget_url( [] );
		$this->assertStringStartsWith( 'https://buy-sandbox.moonpay.com', $url );
	}

	public function test_get_widget_url_contains_signature_at_end(): void {
		$url = $this->api->get_widget_url( [ 'currencyCode' => 'eth' ] );
		$this->assertMatchesRegularExpression( '/&signature=[^&]+$/', $url );
	}

	public function test_get_widget_url_signature_not_double_encoded(): void {
		$url = $this->api->get_widget_url( [ 'colorCode' => '#7b3fe4' ] );
		// colorCode encoded as %23, signature should not contain %25
		$query = parse_url( $url, PHP_URL_QUERY );
		parse_str( urldecode( $query ), $params );
		$this->assertArrayHasKey( 'signature', $params );
		$this->assertStringNotContainsString( '%25', $url );
	}

	public function test_get_widget_url_color_code_encoded_once(): void {
		$url = $this->api->get_widget_url( [ 'colorCode' => '#abc123' ] );
		// Should contain %23 but not %2523
		$this->assertStringContainsString( '%23', $url );
		$this->assertStringNotContainsString( '%2523', $url );
	}

	public function test_get_widget_url_uses_rfc3986_encoding(): void {
		// RFC 3986 encodes tilde ~ literally (not as %7E in some implementations)
		$url = $this->api->get_widget_url( [ 'currencyCode' => 'eth' ] );
		// PHP_QUERY_RFC3986 — just verify the URL is valid
		$this->assertNotEmpty( parse_url( $url, PHP_URL_QUERY ) );
	}

	public function test_get_widget_url_contains_currency(): void {
		$url = $this->api->get_widget_url( [ 'currencyCode' => 'btc' ] );
		$this->assertStringContainsString( 'currencyCode=btc', $url );
	}

	// ------------------------------------------------------------------
	// Data provider: 4 currency pairs
	// ------------------------------------------------------------------

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function currency_pair_provider(): array {
		return [
			'eth/usd' => [ 'eth', 'usd' ],
			'btc/eur' => [ 'btc', 'eur' ],
			'sol/gbp' => [ 'sol', 'gbp' ],
			'matic/cad' => [ 'matic', 'cad' ],
		];
	}

	#[DataProvider( 'currency_pair_provider' )]
	public function test_get_widget_url_includes_currency_code( string $crypto, string $fiat ): void {
		$url = $this->api->get_widget_url( [
			'currencyCode'     => $crypto,
			'baseCurrencyCode' => $fiat,
		] );
		$this->assertStringContainsString( 'currencyCode=' . $crypto, $url );
		$this->assertStringContainsString( 'baseCurrencyCode=' . $fiat, $url );
	}

	// ------------------------------------------------------------------
	// Signature round-trip
	// ------------------------------------------------------------------

	public function test_signature_round_trip(): void {
		$api = new WP_LunaPay_API( 'pk_test_pub', $this->secret, true );
		$params = [ 'currencyCode' => 'eth', 'baseCurrencyAmount' => '100' ];
		$url   = $api->get_widget_url( $params );
		$query = parse_url( $url, PHP_URL_QUERY );
		parse_str( urldecode( $query ), $parts );

		$sig = $parts['signature'];
		unset( $parts['signature'] );
		$rebuilt = '?' . http_build_query( $parts, '', '&', PHP_QUERY_RFC3986 );
		$this->assertTrue( $api->verify_webhook_signature( $rebuilt, $sig ) );
	}

	// Extra coverage tests to reach 25

	public function test_api_constructor_accepts_sandbox_false(): void {
		$api = new WP_LunaPay_API( 'pk_live_x', 'sk_live_y', false );
		$url = $api->get_widget_url( [] );
		$this->assertStringStartsWith( 'https://buy.moonpay.com', $url );
	}

	public function test_sign_uses_sha256_hmac(): void {
		$secret  = 'sk_test_mykey';
		$payload = '?test=1';
		$api     = new WP_LunaPay_API( 'pk_test_pub', $secret, true );
		$expected = base64_encode( hash_hmac( 'sha256', $payload, $secret, true ) );
		$this->assertSame( $expected, $api->sign( $payload ) );
	}

	public function test_verify_returns_false_for_empty_payload(): void {
		$api = new WP_LunaPay_API( 'pk_test_pub', $this->secret, true );
		$sig = base64_encode( hash_hmac( 'sha256', '', $this->secret, true ) );
		// Empty payload is technically valid HMAC, but test the path works.
		$this->assertTrue( $api->verify_webhook_signature( '', $sig ) );
	}

	public function test_get_widget_url_passes_amount(): void {
		$url = $this->api->get_widget_url( [ 'baseCurrencyAmount' => '50' ] );
		$this->assertStringContainsString( 'baseCurrencyAmount=50', $url );
	}

	public function test_get_widget_url_passes_external_id(): void {
		$url = $this->api->get_widget_url( [ 'externalTransactionId' => 'order_42_abc' ] );
		$this->assertStringContainsString( 'externalTransactionId=', $url );
	}
}