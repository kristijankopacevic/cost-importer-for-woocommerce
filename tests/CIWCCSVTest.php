<?php

use PHPUnit\Framework\TestCase;

class CIWCCSVTest extends TestCase {
	private function csv_file( $contents ) {
		$path = tempnam( sys_get_temp_dir(), 'ciwc-' );
		file_put_contents( $path, $contents );
		return $path;
	}

	private function parse_temporary_csv( $contents ) {
		$path = $this->csv_file( $contents );
		try {
			return CIWC_CSV::parse_file( $path );
		} finally {
			unlink( $path );
		}
	}

	public function test_parses_eu_decimal() {
		self::assertSame( '12.5', CIWC_CSV::parse_cost( '12,50' ) );
	}

	public function test_parses_eu_thousands() {
		self::assertSame( '1234.56', CIWC_CSV::parse_cost( '1.234,56' ) );
	}

	public function test_parses_us_thousands() {
		self::assertSame( '1234.56', CIWC_CSV::parse_cost( '1,234.56' ) );
	}

	public function test_parses_us_decimal() {
		self::assertSame( '12.5', CIWC_CSV::parse_cost( '12.50' ) );
	}

	public function test_rejects_blank_formula_and_ambiguous_costs() {
		self::assertTrue( is_wp_error( CIWC_CSV::parse_cost( '' ) ) );
		self::assertTrue( is_wp_error( CIWC_CSV::parse_cost( '=1+1' ) ) );
		self::assertTrue( is_wp_error( CIWC_CSV::parse_cost( '1,234' ) ) );
	}

	public function test_escapes_formula_cells_in_reports() {
		self::assertSame( "'=SUM(A1:A2)", CIWC_CSV::safe_cell( '=SUM(A1:A2)' ) );
		self::assertSame( 'ordinary SKU', CIWC_CSV::safe_cell( 'ordinary SKU' ) );
	}

	public function test_detects_semicolon_csv_and_header() {
		$parsed = $this->parse_temporary_csv( "SKU;Cost;Currency\nMUG-BLUE;12,50;EUR\n" );

		self::assertIsArray( $parsed );
		self::assertSame( ';', $parsed['delimiter'] );
		self::assertSame( array( 'SKU', 'Cost', 'Currency' ), $parsed['header'] );
	}

	public function test_rejects_nul_and_oversized_files() {
		self::assertTrue( is_wp_error( $this->parse_temporary_csv( "SKU,Cost\nMUG,1\0\n" ) ) );
		self::assertTrue( is_wp_error( $this->parse_temporary_csv( str_repeat( 'A', CIWC_CSV::MAX_BYTES + 1 ) ) ) );
	}

	public function test_rejects_more_than_foreground_row_limit() {
		$contents = "SKU,Cost\n" . str_repeat( "MUG,12.50\n", CIWC_CSV::MAX_ROWS + 1 );

		self::assertTrue( is_wp_error( $this->parse_temporary_csv( $contents ) ) );
	}
}
