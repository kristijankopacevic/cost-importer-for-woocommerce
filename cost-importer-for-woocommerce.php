<?php
/**
 * Plugin Name: Cost Importer for WooCommerce
 * Description: Safely preview, confirm, import, audit, and rollback supplier costs from CSV.
 * Version: 1.0.1
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Cost Importer for WooCommerce contributors
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cost-importer-for-woocommerce
 * Update URI: https://github.com/kristijankopacevic/cost-importer-for-woocommerce
 *
 * @package CostImporterForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'CIWC_VERSION', '1.0.1' );
define( 'CIWC_FILE', __FILE__ );
define( 'CIWC_DIR', plugin_dir_path( __FILE__ ) );
define( 'CIWC_URL', plugin_dir_url( __FILE__ ) );

require_once CIWC_DIR . 'includes/class-ciwc-csv.php';
require_once CIWC_DIR . 'includes/class-ciwc-repository.php';
require_once CIWC_DIR . 'includes/class-ciwc-plugin.php';
require_once CIWC_DIR . 'includes/class-ciwc-updater.php';

register_activation_hook( __FILE__, array( 'CIWC_Repository', 'install' ) );
register_uninstall_hook( __FILE__, 'ciwc_uninstall' );

function ciwc_uninstall() {
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		return;
	}
	CIWC_Repository::uninstall();
}

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CIWC_FILE, true );
		}
	} 
);

add_action(
	'plugins_loaded',
	static function () {
		if ( class_exists( 'WooCommerce' ) ) {
			CIWC_Plugin::instance();
			CIWC_Updater::instance();
		}
	} 
);
