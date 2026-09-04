<?php
/**
 * CSV parsing and safe CSV exports.
 *
 * @package CostImporterForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

class CIWC_CSV {
	const MAX_BYTES = 2097152; // 2 MiB: foreground imports must remain reviewable.
	const MAX_ROWS  = 2000;

	/** @return array<string,mixed>|WP_Error */
	public static function parse_file( $path ) {
		if ( ! is_readable( $path ) || filesize( $path ) > self::MAX_BYTES ) {
			return new WP_Error( 'ciwc_file_size', __( 'The CSV is missing, unreadable, or exceeds the 2 MiB foreground-import limit.', 'cost-importer-for-woocommerce' ) );
		}
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents || false !== strpos( $contents, "\0" ) ) {
			return new WP_Error( 'ciwc_file_contents', __( 'The file is not a safe text CSV.', 'cost-importer-for-woocommerce' ) );
		}
		$contents = self::decode_text( $contents );
		if ( is_wp_error( $contents ) ) {
			return $contents;
		}
		$delimiter = self::detect_delimiter( $contents );
		$rows      = self::rows( $contents, $delimiter );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		if ( count( $rows ) < 2 ) {
			return new WP_Error( 'ciwc_rows', __( 'The CSV needs a header and at least one data row.', 'cost-importer-for-woocommerce' ) );
		}
		$header = array_shift( $rows );
		$header = array_map(
			static function ( $value ) {
				return trim( (string) $value );
			},
			$header 
		);
		if ( ! self::looks_like_header( $header ) ) {
			return new WP_Error( 'ciwc_header', __( 'A recognizable header row was not found. Add headers, then upload again.', 'cost-importer-for-woocommerce' ) );
		}
		return array(
			'delimiter' => $delimiter,
			'header'    => $header,
			'rows'      => $rows,
			'warnings'  => array(),
		);
	}

	/** @return string|WP_Error */
	private static function decode_text( $contents ) {
		if ( 0 === strpos( $contents, "\xEF\xBB\xBF" ) ) {
			return substr( $contents, 3 );
		}
		if ( 0 === strpos( $contents, "\xFF\xFE" ) || 0 === strpos( $contents, "\xFE\xFF" ) ) {
			if ( ! function_exists( 'mb_convert_encoding' ) ) {
				return new WP_Error( 'ciwc_encoding', __( 'This UTF-16 CSV needs the PHP mbstring extension.', 'cost-importer-for-woocommerce' ) );
			}
			return mb_convert_encoding( $contents, 'UTF-8', 'UTF-16' );
		}
		return $contents;
	}

	private static function detect_delimiter( $contents ) {
		$line       = strtok( $contents, "\r\n" );
		$candidates = array( ',', ';', "\t", '|' );
		$best       = ',';
		$best_count = -1;
		foreach ( $candidates as $candidate ) {
			$count = substr_count( (string) $line, $candidate );
			if ( $count > $best_count ) {
				$best_count = $count;
				$best       = $candidate;
			}
		}
		return $best;
	}

	/** @return array<int,array<int,string>>|WP_Error */
	private static function rows( $contents, $delimiter ) {
		$handle = fopen( 'php://temp', 'r+' );
		fwrite( $handle, $contents );
		rewind( $handle );
		$rows = array();
		while ( false !== ( $row = fgetcsv( $handle, 0, $delimiter ) ) ) {
			if ( count( $rows ) >= self::MAX_ROWS + 1 ) {
				fclose( $handle );
				return new WP_Error( 'ciwc_row_limit', __( 'This CSV has more than 2,000 data rows. Use a Pro/background import when available.', 'cost-importer-for-woocommerce' ) );
			}
			if ( array( null ) === $row || array( '' ) === $row ) {
				continue;
			}
			$rows[] = array_map(
				static function ( $value ) {
					return is_string( $value ) ? $value : '';
				},
				$row 
			);
		}
		fclose( $handle );
		return $rows;
	}

	private static function looks_like_header( $header ) {
		$known = array( 'sku', 'product sku', 'variation sku', 'cost', 'cost price', 'purchase price', 'currency', 'product id', 'id' );
		foreach ( $header as $cell ) {
			if ( in_array( strtolower( trim( $cell ) ), $known, true ) ) {
				return true;
			}
		}
		return false;
	}

	/** @return string|WP_Error Normalized decimal, never a float. */
	public static function parse_cost( $value ) {
		$value = trim( str_replace( array( "\xC2\xA0", ' ' ), '', (string) $value ) );
		$value = preg_replace( '/[€$£]/u', '', $value );
		if ( '' === $value || preg_match( '/^[=+@-]/', $value ) ) {
			return new WP_Error( 'ciwc_cost', __( 'Cost is blank, negative, or unsafe.', 'cost-importer-for-woocommerce' ) );
		}
		if ( ! preg_match( '/^[0-9.,\']+$/', $value ) ) {
			return new WP_Error( 'ciwc_cost', __( 'Cost contains unsupported characters.', 'cost-importer-for-woocommerce' ) );
		}
		$value   = str_replace( "'", '', $value );
		$comma   = strrpos( $value, ',' );
		$dot     = strrpos( $value, '.' );
		$decimal = false;
		if ( false !== $comma && false !== $dot ) {
			$decimal = $comma > $dot ? ',' : '.';
		} elseif ( false !== $comma || false !== $dot ) {
			$decimal = false !== $comma ? ',' : '.';
			$after   = strlen( $value ) - strrpos( $value, $decimal ) - 1;
			if ( 3 === $after ) {
				return new WP_Error( 'ciwc_ambiguous_cost', __( 'Cost with a single separator and three trailing digits is ambiguous. Use 1,234.56 or 1234.56.', 'cost-importer-for-woocommerce' ) );
			}
		}
		if ( false !== $decimal ) {
			$parts = explode( $decimal, $value );
			if ( 2 !== count( $parts ) || '' === $parts[0] || '' === $parts[1] || strlen( $parts[1] ) > 4 ) {
				return new WP_Error( 'ciwc_cost', __( 'Cost has an invalid decimal format.', 'cost-importer-for-woocommerce' ) );
			}
			$whole = str_replace( array( ',', '.' ), '', $parts[0] );
			$cost  = $whole . '.' . $parts[1];
		} else {
			$cost = $value;
		}
		if ( ! preg_match( '/^\d+(?:\.\d{1,4})?$/', $cost ) || (float) $cost < 0 ) {
			return new WP_Error( 'ciwc_cost', __( 'Cost is invalid.', 'cost-importer-for-woocommerce' ) );
		}
		return rtrim( rtrim( $cost, '0' ), '.' ) ?: '0';
	}

	public static function safe_cell( $value ) {
		$value = (string) $value;
		return preg_match( '/^[=+\-@]/', ltrim( $value ) ) ? "'" . $value : $value;
	}

	public static function output_csv( $filename, $header, $rows ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array_map( array( __CLASS__, 'safe_cell' ), $header ) );
		foreach ( $rows as $row ) {
			fputcsv( $out, array_map( array( __CLASS__, 'safe_cell' ), $row ) );
		}
		fclose( $out );
		exit;
	}
}
