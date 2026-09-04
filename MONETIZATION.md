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

Lemon Squeezy remains the recommended storefront: it can host a clean product page and checkout for digital downloads while acting as merchant of record. A live storefront cannot be created until the owner completes its required account, identity, tax, payout, and terms steps.

Do not create a store, accept terms, supply tax information, pass identity checks, or connect payouts without the business owner. Those are required personal/legal actions.

## Initial Pro pricing

The approved launch prices are:

- **€4.99 lifetime** — introductory one-time Pro purchase. State exactly what is included at the time of sale; do not imply hosted services or an entitlement service.
- **€9.99/year per single site** — annual Pro option for continuing updates and support after the introductory offer.

Neither offer is on sale until a real Pro package exists and the Lemon Squeezy storefront is activated. The free plugin remains fully useful without either purchase.

## Stripe Sandbox check

Stripe Managed Payments was configured only in Sandbox as a technical checkout alternative: one Pro product has a €4.99 one-off price and a €9.99 annual price. This is not a live storefront decision. The static GitHub Pages site cannot safely create Stripe Checkout Sessions without a separate server-side integration; never place a Stripe secret in this repository. Live use still requires the owner's business verification, legal/tax decisions, and a deliberate provider choice.

## Delivery design

1. Customer pays through the selected checkout provider.
2. The provider delivers the private Pro ZIP using its protected digital-download mechanism.
3. The public GitHub repository and releases never contain Pro source or ZIPs.
4. The first paid version does not add a license server. If update entitlement becomes necessary, use provider-issued licenses or signed private downloads only after the customer experience warrants it.

This keeps fixed infrastructure cost at €0 and avoids inventing an operational dependency before sales justify it.
