# Monetization plan

## Free release

Cost Importer for WooCommerce 1.0.0 is a genuinely useful GPL-compatible free plugin. Its CSV upload, mapping, SKU and variation matching, preview, explicit confirmation, safe update, history, unmatched CSV, and safe rollback are not paywalled.

Distribution has no mandatory monthly cost:

- Source and public ZIP: GitHub and GitHub Releases
- Landing page: GitHub Pages
- Update metadata: public GitHub Releases API, without a token
- Telemetry and license server: none

## Future Pro checkout

The recommended provider is Lemon Squeezy. It is a merchant of record, supports digital downloads, subscriptions, and license keys, and handles sales-tax/VAT collection and remittance for platform sales. Its current published base platform fee is $0.50 plus 5% of the order total; international, PayPal, subscription, payout, affiliate, and currency-conversion cases can add fees. There is no stated mandatory monthly platform fee.

Sources consulted 2026-09-04:

- [Lemon Squeezy fees](https://docs.lemonsqueezy.com/help/getting-started/fees)
- [Lemon Squeezy sales tax and VAT](https://docs.lemonsqueezy.com/help/payments/sales-tax-vat)
- [Lemon Squeezy product overview](https://www.lemonsqueezy.com/)
- [Payhip VAT/tax handling](https://payhip.com/features/vat-taxes) — viable fallback, but it requires connecting a payment processor.
- [Gumroad fees](https://gumroad.com/help/article/66-gumroads-fees.html) — viable fallback, but its direct-sale fee is higher.

Do not create a store, accept terms, supply tax information, pass identity checks, or connect payouts without the business owner. Those are required personal/legal actions.

## Initial Pro offer

Test an early-adopter **€29 lifetime** Pro offer, limited in quantity and clearly described as such. After validation, move to **€29/year per single site**. This is deliberately below comparable WooCommerce product-import/cost tooling while avoiding an unsupported promise of a perpetual SaaS service.

## Delivery design

1. Customer pays through the selected checkout provider.
2. The provider delivers the private Pro ZIP using its protected digital-download mechanism.
3. The public GitHub repository and releases never contain Pro source or ZIPs.
4. The first paid version does not add a license server. If update entitlement becomes necessary, use provider-issued licenses or signed private downloads only after the customer experience warrants it.

This keeps fixed infrastructure cost at €0 and avoids inventing an operational dependency before sales justify it.
