<?php
/** Minimal dependency-free tests for parser and formula-safe export helpers. */
define( 'ABSPATH', __DIR__ );
class WP_Error {
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $value ) { return $value; }
require dirname( __DIR__ ) . '/includes/class-ciwc-csv.php';

$tests = 0;
$failures = array();
$expect = static function( $actual, $expected, $label ) use ( &$tests, &$failures ) {
	++$tests;
	if ( $actual !== $expected ) { $failures[] = $label . ' expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ); }
};
$expect( CIWC_CSV::parse_cost( '12,50' ), '12.5', 'EU decimal' );
$expect( CIWC_CSV::parse_cost( '1.234,56' ), '1234.56', 'EU thousands' );
$expect( CIWC_CSV::parse_cost( '1,234.56' ), '1234.56', 'US thousands' );
$expect( CIWC_CSV::parse_cost( '12.50' ), '12.5', 'US decimal' );
$tests++; if ( ! is_wp_error( CIWC_CSV::parse_cost( '' ) ) ) { $failures[] = 'blank cost rejected'; }
$tests++; if ( ! is_wp_error( CIWC_CSV::parse_cost( '=1+1' ) ) ) { $failures[] = 'formula cost rejected'; }
$tests++; if ( ! is_wp_error( CIWC_CSV::parse_cost( '1,234' ) ) ) { $failures[] = 'ambiguous separator rejected'; }
$expect( CIWC_CSV::safe_cell( '=SUM(A1:A2)' ), "'=SUM(A1:A2)", 'formula-safe export' );
$expect( CIWC_CSV::safe_cell( 'ordinary SKU' ), 'ordinary SKU', 'ordinary export' );
if ( $failures ) { fwrite( STDERR, "FAIL " . count( $failures ) . "/{$tests}\n" . implode( "\n", $failures ) . "\n" ); exit( 1 ); }
echo "PASS {$tests} tests\n";
