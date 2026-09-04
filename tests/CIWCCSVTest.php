<?php

use PHPUnit\Framework\TestCase;

class CIWCCSVTest extends TestCase {
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
}
