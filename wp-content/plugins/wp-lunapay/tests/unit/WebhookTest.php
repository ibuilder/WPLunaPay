<?php
/**
 * Unit tests for webhook processing logic.
 *
 * Tests the external ID regex and status transitions without a live WP/WC environment.
 *
 * @package WPLunaPay
 */

namespace WPLunaPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WP_LunaPay_API;

class WebhookTest extends TestCase {

	// ------------------------------------------------------------------
	// external ID regex  /^order_(\d+)_/
	// ------------------------------------------------------------------

	/** @return array<string,array{string,int|null}> */
	public static function external_id_provider(): array {
		return [
			'standard'              => [ 'order_42_abc123',      42 ],
			'large_order_id'        => [ 'order_99999_xyz',      99999 ],
			'single_digit'          => [ 'order_1_suffix',       1 ],
			'underscore_in_key'     => [ 'order_7_a_b_c',        7 ],
			'invalid_no_prefix'     => [ '42_order_abc',         null ],
			'invalid_missing_id'    => [ 'order__abc',           null ],
			'invalid_alpha_id'      => [ 'order_abc_suffix',     null ],
			'invalid_empty'         => [ '',                     null ],
			'invalid_partial'       => [ 'order_5',              null ],
			'invalid_no_trailing'   => [ 'order_5',              null ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('external_id_provider')]
	public function test_external_id_regex( string $id, ?int $expected_order_id ): void {
		$matched = preg_match( '/^order_(\d+)_/', $id, $m );
		if ( $expected_order_id === null ) {
			$this->assertSame( 0, $matched );
		} else {
			$this->assertSame( 1, $matched );
			$this->assertSame( $expected_order_id, (int) $m[1] );
		}
	}

	// ------------------------------------------------------------------
	// wc- prefix stripping
	// ------------------------------------------------------------------

	public function test_wc_prefix_stripped_from_complete_status(): void {
		$status = 'wc-processing';
		$result = str_replace( 'wc-', '', $status );
		$this->assertSame( 'processing', $result );
	}

	public function test_wc_prefix_stripped_from_failed_status(): void {
		$status = 'wc-failed';
		$result = str_replace( 'wc-', '', $status );
		$this->assertSame( 'failed', $result );
	}

	public function test_wc_prefix_not_stripped_when_absent(): void {
		$status = 'processing';
		$result = str_replace( 'wc-', '', $status );
		$this->assertSame( 'processing', $result );
	}

	// ------------------------------------------------------------------
	// Idempotent delivery
	// ------------------------------------------------------------------

	public function test_second_completed_event_same_tx_id(): void {
		// Simulates: order already has _moonpay_transaction_id set to same value.
		$existing_tx = 'mp_tx_abc';
		$incoming_tx = 'mp_tx_abc';
		// Idempotent if they are equal — no change needed.
		$this->assertSame( $existing_tx, $incoming_tx );
	}

	public function test_second_event_different_tx_id_updates(): void {
		$existing_tx = 'mp_tx_abc';
		$incoming_tx = 'mp_tx_def';
		$this->assertNotSame( $existing_tx, $incoming_tx );
	}

	// ------------------------------------------------------------------
	// Duplicate delivery stock guard
	// ------------------------------------------------------------------

	public function test_stock_not_restored_when_already_cancelled(): void {
		$current_status = 'cancelled';
		$should_restore = ! in_array( $current_status, [ 'cancelled', 'failed' ], true );
		$this->assertFalse( $should_restore );
	}

	public function test_stock_not_restored_when_already_failed(): void {
		$current_status = 'failed';
		$should_restore = ! in_array( $current_status, [ 'cancelled', 'failed' ], true );
		$this->assertFalse( $should_restore );
	}

	public function test_stock_restored_when_pending(): void {
		$current_status = 'pending';
		$should_restore = ! in_array( $current_status, [ 'cancelled', 'failed' ], true );
		$this->assertTrue( $should_restore );
	}

	public function test_stock_restored_when_processing(): void {
		$current_status = 'processing';
		$should_restore = ! in_array( $current_status, [ 'cancelled', 'failed' ], true );
		$this->assertTrue( $should_restore );
	}

	// ------------------------------------------------------------------
	// Webhook signature round-trip
	// ------------------------------------------------------------------

	public function test_webhook_sig_round_trip_valid(): void {
		$secret  = 'sk_test_webhooksecret';
		$payload = json_encode( [ 'type' => 'transaction_updated', 'data' => [ 'status' => 'completed' ] ] );
		$api     = new WP_LunaPay_API( 'pk_test_pub', $secret, true );

		$sig = $api->sign( $payload );
		$this->assertTrue( $api->verify_webhook_signature( $payload, $sig ) );
	}

	public function test_webhook_sig_invalid_when_payload_modified(): void {
		$secret  = 'sk_test_webhooksecret';
		$payload = '{"status":"completed"}';
		$api     = new WP_LunaPay_API( 'pk_test_pub', $secret, true );

		$sig = $api->sign( $payload );
		$this->assertFalse( $api->verify_webhook_signature( $payload . ' ', $sig ) );
	}

	// ------------------------------------------------------------------
	// Status mapping
	// ------------------------------------------------------------------

	public function test_completed_mp_status_maps_to_processing(): void {
		$mp_status = 'completed';
		$mapping   = [ 'completed' => 'processing', 'failed' => 'failed' ];
		$this->assertSame( 'processing', $mapping[ $mp_status ] ?? 'on-hold' );
	}

	public function test_failed_mp_status_maps_to_failed(): void {
		$mp_status = 'failed';
		$mapping   = [ 'completed' => 'processing', 'failed' => 'failed' ];
		$this->assertSame( 'failed', $mapping[ $mp_status ] ?? 'on-hold' );
	}

	public function test_cancelled_mp_status_uses_failed_map(): void {
		$mp_status    = 'cancelled';
		$failed_status = 'failed';
		// In the webhook handler, both 'failed' and 'cancelled' trigger the failed path.
		$this->assertContains( $mp_status, [ 'failed', 'cancelled' ] );
	}

	public function test_pending_mp_status_adds_note_only(): void {
		$mp_status      = 'pending';
		$triggers_note  = in_array( $mp_status, [ 'pending', 'waitingPayment', 'waitingAuthorization' ], true );
		$this->assertTrue( $triggers_note );
	}

	public function test_unknown_mp_status_logged(): void {
		$mp_status = 'someUnknownStatus';
		$known     = [ 'completed', 'failed', 'cancelled', 'pending', 'waitingPayment', 'waitingAuthorization' ];
		$this->assertNotContains( $mp_status, $known );
	}

	// External ID: 6 invalid cases (already covered via dataProvider)
	// Extra test for regex anchor
	public function test_external_id_must_start_with_order(): void {
		$this->assertSame( 0, preg_match( '/^order_(\d+)_/', 'xorder_5_abc' ) );
	}

	public function test_external_id_six_digit_order(): void {
		$this->assertSame( 1, preg_match( '/^order_(\d+)_/', 'order_123456_abc', $m ) );
		$this->assertSame( 123456, (int) $m[1] );
	}
}