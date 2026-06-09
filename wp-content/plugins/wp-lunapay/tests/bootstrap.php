<?php
/**
 * PHPUnit bootstrap for WP LunaPay unit tests.
 *
 * Defines minimal WordPress/WooCommerce stubs so the tests can run
 * without a live WordPress installation.
 *
 * @package WPLunaPay
 */

// ------------------------------------------------------------------
// Constants
// ------------------------------------------------------------------

define( 'ABSPATH',                  __DIR__ . '/../' );
define( 'WP_LUNAPAY_PLUGIN_DIR',    __DIR__ . '/../' );
define( 'WP_LUNAPAY_PLUGIN_URL',    'https://example.com/wp-content/plugins/wp-lunapay/' );
define( 'WP_LUNAPAY_VERSION',       '1.0.0-test' );
define( 'WP_LUNAPAY_PLUGIN_FILE',   __DIR__ . '/../wp-lunapay.php' );

// ------------------------------------------------------------------
// Composer autoloader
// ------------------------------------------------------------------

require_once __DIR__ . '/../vendor/autoload.php';

// ------------------------------------------------------------------
// WordPress function stubs
// ------------------------------------------------------------------

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, string $url = '' ): string {
		if ( is_array( $args ) ) {
			$query = http_build_query( $args );
			return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . $query;
		}
		return $url;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, int $options = 0 ): string|false {
		return json_encode( $data, $options );
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	function wp_remote_request( string $url, array $args = [] ): array|WP_Error {
		if ( isset( $GLOBALS['wp_remote_request_stub'] ) && is_callable( $GLOBALS['wp_remote_request_stub'] ) ) {
			return ( $GLOBALS['wp_remote_request_stub'] )( $url, $args );
		}
		return [ 'response' => [ 'code' => 200 ], 'body' => '{}' ];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( array $response ): string {
		return $response['body'] ?? '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( array $response ): int {
		return (int) ( $response['response']['code'] ?? 200 );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) );
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( string $color ): string {
		if ( preg_match( '/^#([a-fA-F0-9]{3}){1,2}$/', $color ) ) {
			return $color;
		}
		return '';
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( string $text, string $domain = 'default' ): void {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'human_time_diff' ) ) {
	function human_time_diff( int $from, int $to = 0 ): string {
		$diff = abs( ( $to ?: time() ) - $from );
		return $diff . ' seconds';
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

// ------------------------------------------------------------------
// WordPress class stubs
// ------------------------------------------------------------------

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private mixed  $data;

		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string    { return $this->code; }
		public function get_error_message(): string { return $this->message; }
		public function get_error_data(): mixed     { return $this->data; }
	}
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	abstract class WC_Payment_Gateway {
		public string $id          = '';
		public string $title       = '';
		public string $description = '';
		public bool   $has_fields  = false;
		public array  $supports    = [];
		public string $icon        = '';
		public string $order_button_text = '';
		public string $method_title      = '';
		public string $method_description = '';

		protected array $settings = [];

		public function init_settings(): void {}
		public function init_form_fields(): void {}

		public function get_option( string $key, mixed $default = '' ): mixed {
			return $this->settings[ $key ] ?? $default;
		}

		public function set_option( string $key, mixed $value ): void {
			$this->settings[ $key ] = $value;
		}

		public function is_available(): bool { return true; }
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {
		private int    $id     = 0;
		private array  $meta   = [];
		private string $status = 'pending';
		private float  $total  = 0.0;
		private string $email  = '';

		public function __construct( int $id = 0 ) { $this->id = $id; }

		public function get_id(): int             { return $this->id; }
		public function get_total(): float        { return $this->total; }
		public function get_status(): string      { return $this->status; }
		public function get_billing_email(): string { return $this->email; }

		public function get_meta( string $key ): mixed {
			return $this->meta[ $key ] ?? '';
		}

		public function update_meta_data( string $key, mixed $value ): void {
			$this->meta[ $key ] = $value;
		}

		public function save(): void {}

		public function set_total( float $total ): void   { $this->total = $total; }
		public function set_status( string $status ): void { $this->status = $status; }
		public function set_email( string $email ): void  { $this->email = $email; }
	}
}

// ------------------------------------------------------------------
// Load plugin classes under test
// ------------------------------------------------------------------

require_once WP_LUNAPAY_PLUGIN_DIR . 'includes/class-wp-lunapay-api.php';