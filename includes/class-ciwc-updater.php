<?php
/**
 * Queries public GitHub release metadata for plugin updates.
 *
 * Token-free updates from the public GitHub release API.
 *
 * @package CostImporterForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

class CIWC_Updater {
	/**
	 * Singleton instance.
	 *
	 * @var CIWC_Updater|null
	 */
	private static $instance;
	const API = 'https://api.github.com/repos/kristijankopacevic/cost-importer-for-woocommerce/releases/latest';

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check' ) );
		add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
	}

	public function check( $transient ) {
		if ( empty( $transient->checked ) || ! isset( $transient->checked[ plugin_basename( CIWC_FILE ) ] ) ) {
			return $transient;
		}
		$release = get_site_transient( 'ciwc_github_release' );
		if ( false === $release ) {
			$response = wp_safe_remote_get(
				self::API,
				array(
					'timeout' => 5,
					'headers' => array( 'Accept' => 'application/vnd.github+json' ),
				) 
			);
			$release  = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
			set_site_transient( 'ciwc_github_release', is_array( $release ) ? $release : array(), 12 * HOUR_IN_SECONDS );
		}
		if ( empty( $release['tag_name'] ) || empty( $release['assets'] ) ) {
			return $transient;
		}
		$version = ltrim( (string) $release['tag_name'], 'v' );
		$asset   = null;
		foreach ( $release['assets'] as $candidate ) {
			if ( 'cost-importer-for-woocommerce.zip' === ( $candidate['name'] ?? '' ) && 0 === strpos( $candidate['browser_download_url'] ?? '', 'https://github.com/' ) ) {
				$asset = $candidate;
				break;
			}
		}
		if ( ! $asset || ! version_compare( $version, CIWC_VERSION, '>' ) ) {
			return $transient;
		}
		$transient->response[ plugin_basename( CIWC_FILE ) ] = (object) array(
			'slug'        => 'cost-importer-for-woocommerce',
			'plugin'      => plugin_basename( CIWC_FILE ),
			'new_version' => $version,
			'url'         => 'https://github.com/kristijankopacevic/cost-importer-for-woocommerce',
			'package'     => esc_url_raw( $asset['browser_download_url'] ),
		);
		return $transient;
	}

	public function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'cost-importer-for-woocommerce' !== $args->slug ) {
			return $result;
		}
		return (object) array(
			'name'     => 'Cost Importer for WooCommerce',
			'slug'     => 'cost-importer-for-woocommerce',
			'version'  => CIWC_VERSION,
			'homepage' => 'https://github.com/kristijankopacevic/cost-importer-for-woocommerce',
			'sections' => array( 'description' => 'Safe CSV supplier cost imports with review, history, unmatched reports, and guarded rollback.' ),
		);
	}
}
