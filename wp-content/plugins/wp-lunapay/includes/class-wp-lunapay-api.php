<?php
/**
 * MoonPay REST API client.
 *
 * Handles HMAC-SHA256 signature generation and all HTTP calls to the MoonPay v3 API.
 *
 * @package WPLunaPay
 * @copyright 2025 WPLunaPay (https://wprealwise.com)
 */

defined( 'ABSPATH' ) || exit;

class WP_LunaPay_API {

	private string $publishable_key;
	private string $secret_key;
	private bool   $sandbox;

	const API_BASE_LIVE    = 'https://api.moonpay.com/v3/';
	const API_BASE_SANDBOX = 'https://api.moonpay.com/v3/';
	const WIDGET_LIVE      = 'https://buy.moonpay.com';
	const WIDGET_SANDBOX   = 'https://buy-sandbox.moonpay.com';

	public function __construct( string $publishable_key, string $secret_key, bool $sandbox = false ) {
		$this->publishable_key = $publishable_key;
		$this->secret_key      = $secret_key;
		$this->sandbox         = $sandbox;
	}

	public function get_widget_url( array $params ): string {
		$base   = $this->sandbox ? self::WIDGET_SANDBOX : self::WIDGET_LIVE;
		$merged = array_merge( [ 'apiKey' => $this->publishable_key ], $params );
		$query  = http_build_query( $merged, '', '&', PHP_QUERY_RFC3986 );
		$sig    = $this->sign( '?' . $query );
		return $base . '?' . $query . '&signature=' . rawurlencode( $sig );
	}

	public function sign( string $query_string ): string {
		// MoonPay requires HMAC-SHA256 raw output encoded as base64 — this is their documented signature format.
		return base64_encode( hash_hmac( 'sha256', $query_string, $this->secret_key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public function verify_webhook_signature( string $payload, string $received_signature ): bool {
		if ( empty( $received_signature ) ) {
			return false;
		}
		// MoonPay webhook signatures are HMAC-SHA256 raw bytes encoded as base64.
		$expected = base64_encode( hash_hmac( 'sha256', $payload, $this->secret_key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return hash_equals( $expected, $received_signature );
	}

	public function get_transaction( string $transaction_id ): array|WP_Error {
		return $this->request( 'GET', 'transactions/' . urlencode( $transaction_id ) );
	}

	public function get_transactions_by_external_id( string $external_id ): array|WP_Error {
		return $this->request( 'GET', 'transactions', [ 'externalTransactionId' => $external_id ] );
	}

	public function get_currencies(): array|WP_Error {
		return $this->request( 'GET', 'currencies' );
	}

	public function get_buy_quote( string $crypto_code, string $fiat_code, float $fiat_amount ): array|WP_Error {
		return $this->request( 'GET', 'currencies/' . urlencode( $crypto_code ) . '/buy_quote', [
			'baseCurrencyAmount' => $fiat_amount,
			'baseCurrencyCode'   => $fiat_code,
		] );
	}

	private function request( string $method, string $endpoint, array $params = [] ): array|WP_Error {
		$base_url = $this->sandbox ? self::API_BASE_SANDBOX : self::API_BASE_LIVE;
		$url      = $base_url . ltrim( $endpoint, '/' );

		$args = [
			'method'  => $method,
			'timeout' => 30,
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Api-Key ' . $this->secret_key,
			],
		];

		if ( $method === 'GET' && ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		} elseif ( ! empty( $params ) ) {
			$args['body'] = wp_json_encode( $params );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$message = $body['message'] ?? 'MoonPay API error';
			return new WP_Error( 'wp_lunapay_api_error', $message, [ 'status' => $code, 'body' => $body ] );
		}

		return $body ?? [];
	}
}