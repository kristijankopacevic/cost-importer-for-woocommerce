<?php
/** Lightweight WordPress shims for isolated parser tests. */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->message = $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function __( $value ) {
	return $value;
}

require dirname( __DIR__ ) . '/includes/class-ciwc-csv.php';
