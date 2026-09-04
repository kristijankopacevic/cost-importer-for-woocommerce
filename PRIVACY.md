# Privacy

Cost Importer for WooCommerce does not collect telemetry, analytics, usage data, customer data, or supplier CSV data.

CSV parsing, product matching, previews, updates, history, rollback data, and unmatched reports stay in the WordPress installation. Preview data is held in a user-bound WordPress transient for up to 30 minutes. Audit history is stored in two WordPress database tables and is removed on uninstall.

The optional self-update integration asks `https://api.github.com/repos/kristijankopacevic/cost-importer-for-woocommerce/releases/latest` for public release metadata through WordPress’s update mechanism. It uses HTTPS, no API token, and a 12-hour cache. GitHub receives the request’s normal network metadata (such as IP address and user agent), not the imported CSV.
