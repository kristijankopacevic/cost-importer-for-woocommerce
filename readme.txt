=== Cost Importer for WooCommerce ===
Contributors: cost-importer-for-woocommerce
Tags: woocommerce, csv import, product cost, supplier costs, inventory
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely map, preview, confirm, import, audit, and roll back supplier product costs from CSV files.

== Description ==

Cost Importer for WooCommerce uses exact product and variation SKU matching, a visible preview, and an explicit UPDATE COSTS confirmation. Invalid, duplicate, unmatched, ambiguous, and currency-mismatched rows never become zero and are excluded from updates.

The default target is the plugin's own internal cost field. Compatible third-party fields appear only when their owner has identified them. Supplier CSV data stays on the WordPress installation.

== Installation ==

1. Download the ZIP from the project's GitHub Releases page.
2. In WordPress, go to Plugins > Add New > Upload Plugin and upload the ZIP.
3. Activate it with WooCommerce active, then open WooCommerce > Cost Importer.

== Changelog ==

= 1.0.0 =
* First public release with review-first CSV cost imports, unmatched reports, import history, and guarded rollback.
