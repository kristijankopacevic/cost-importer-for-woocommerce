<?php
/**
 * Persistence for import audit records and reversible changes.
 *
 * @package CostImporterForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

class CIWC_Repository {
	public static function imports_table() {
		global $wpdb;
		return $wpdb->prefix . 'ciwc_imports';
	}

	public static function changes_table() {
		global $wpdb;
		return $wpdb->prefix . 'ciwc_import_changes';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$imports = self::imports_table();
		$changes = self::changes_table();
		dbDelta(
			"CREATE TABLE {$imports} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			import_uuid char(36) NOT NULL,
			status varchar(20) NOT NULL,
			filename varchar(255) NOT NULL,
			target_meta_key varchar(191) NOT NULL,
			currency char(3) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			summary longtext NOT NULL,
			unmatched longtext NOT NULL,
			created_at datetime NOT NULL,
			completed_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY import_uuid (import_uuid),
			KEY created_at (created_at)
		) {$charset};" 
		);
		dbDelta(
			"CREATE TABLE {$changes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			import_id bigint(20) unsigned NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			meta_key varchar(191) NOT NULL,
			old_exists tinyint(1) NOT NULL DEFAULT 0,
			old_value longtext NULL,
			new_value varchar(100) NOT NULL,
			applied tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY import_id (import_id),
			KEY product_id (product_id)
		) {$charset};" 
		);
	}

	public static function uninstall() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::changes_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table name is a fixed prefix.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::imports_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table name is a fixed prefix.
	}

	public static function create_import( $data ) {
		global $wpdb;
		$wpdb->insert(
			self::imports_table(),
			array(
				'import_uuid'     => $data['uuid'],
				'status'          => 'processing',
				'filename'        => $data['filename'],
				'target_meta_key' => $data['target_meta_key'],
				'currency'        => $data['currency'],
				'user_id'         => get_current_user_id(),
				'summary'         => wp_json_encode( $data['summary'] ),
				'unmatched'       => wp_json_encode( $data['unmatched'] ),
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function stage_change( $import_id, $product_id, $meta_key, $old_exists, $old_value, $new_value ) {
		global $wpdb;
		$wpdb->insert(
			self::changes_table(),
			array(
				'import_id'  => $import_id,
				'product_id' => $product_id,
				'meta_key'   => $meta_key,
				'old_exists' => $old_exists ? 1 : 0,
				'old_value'  => $old_value,
				'new_value'  => $new_value,
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function mark_applied( $change_id ) {
		global $wpdb;
		$wpdb->update( self::changes_table(), array( 'applied' => 1 ), array( 'id' => $change_id ), array( '%d' ), array( '%d' ) );
	}

	public static function complete_import( $import_id, $status, $summary ) {
		global $wpdb;
		$wpdb->update(
			self::imports_table(),
			array(
				'status'       => $status,
				'summary'      => wp_json_encode( $summary ),
				'completed_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $import_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function get_import( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::imports_table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	public static function get_changes( $import_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::changes_table() . ' WHERE import_id = %d AND applied = 1 ORDER BY id ASC', $import_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	public static function history() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::imports_table() . ' ORDER BY id DESC LIMIT 30', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name and limit.
	}
}
