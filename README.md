# Cost Importer for WooCommerce

Safely import supplier costs from a CSV without turning missing data into zero or silently overwriting product metadata.

## Free features

- CSV upload with comma, semicolon, tab, and pipe detection
- Manual mapping for SKU, cost, optional currency, and an explicitly enabled product-ID fallback
- Exact matching for simple-product and variation SKUs
- EU (`12,50`, `1.234,56`) and US (`12.50`, `1,234.56`) decimal handling
- Preview counts for matched, unmatched, ambiguous, invalid, and duplicate rows
- Exact `UPDATE COSTS` confirmation before changes
- Internal cost storage by default, matched-only updates, history, unmatched CSV export, and safe rollback
- HPOS compatibility declaration; uses WooCommerce product APIs

## Installation

1. Download `cost-importer-for-woocommerce.zip` from [GitHub Releases](https://github.com/kristijankopacevic/cost-importer-for-woocommerce/releases).
2. In WordPress, go to **Plugins → Add New → Upload Plugin** and upload the ZIP.
3. Activate it with WooCommerce active, then open **WooCommerce → Cost Importer**.
4. Upload a supplier CSV, map its columns, inspect the preview, and type `UPDATE COSTS` only when it is correct.

The default target is `_ciwc_cost`. Compatible external targets are displayed only when the owning plugin is detected or intentionally registers a target through `ciwc_cost_targets`.

## Safety model

Blank, malformed, negative, ambiguous, duplicate, unmatched, and currency-mismatched rows are excluded from updates. Product ID lookup is never used unless selected explicitly. Rollback restores only rows whose current value still equals the value written by that import.

The foreground limit is 2 MiB / 2,000 rows. This prevents a long-running browser request from looking like a complete import while operating only partially. Background imports are a future Pro candidate.

## Privacy

No telemetry, analytics, tracking, or supplier data leaves the site. The only external request is WordPress’s normal update check to the public GitHub Releases API, cached for 12 hours; it sends a HTTPS request with no token and receives public release metadata. See [PRIVACY.md](PRIVACY.md).

## Development

Run `composer install`, then `composer run test` for the PHPUnit parser/export suite and `composer run lint` for WPCS. The release workflow also performs a current WordPress + WooCommerce fresh install, Plugin Check against the allow-listed runtime payload, HPOS declaration check, browser import/rollback/report smoke test, and ZIP validation. `scripts/package.ps1` creates an allow-listed merchant ZIP in `dist/`.

Synthetic supplier examples are in [`samples/`](samples/). The five screenshots used by the landing page come from the same browser smoke test; no product UI images are fabricated.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
