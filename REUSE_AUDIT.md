# Reuse audit

Audit date: 2026-09-04. No prior WooCommerce plugin was found; this product is new and does not restart an existing project.

| Component | Source | Classification | Decision |
|---|---|---|---|
| Strict money/minor-unit approach | `products/shopify/profitguard/profit-guard/app/lib/profitguard.ts` | ADAPT | Native PHP decimal parser; unknown never becomes zero. |
| CSV quoting, supplier validation, confirmation flow | Shopify ProfitGuard parser/routes | ADAPT | Native WordPress admin workflow, nonce/capability guards. |
| Bounded imports and history model | Shopify ProfitGuard batch/jobs | ADAPT / REFERENCE_ONLY | 2 MiB/2,000 row foreground limit and WP audit tables. |
| Delimiter/header/multilingual mapping ideas | `_system/marginguard/src/lib/parsing/*` | ADAPT | Native parser and conservative mapping suggestions. |
| Exact matching and row diagnostics | `_system/marginguard/src/lib/matching/*` | ADAPT | Exact SKU, variation SKU, selected ID fallback only. |
| Formula-safe CSV export | `_system/marginguard/src/lib/export/csv.ts` | ADAPT | PHP export prefixes formula-triggering cells. |
| Shopify/Prisma runtime, React screens, generated builds | Shopify ProfitGuard / MarginGuard | DO_NOT_USE | Platform-specific or generated; no code copied. |
| Existing reports/ZIPs/node_modules | vault and Shopify directories | REFERENCE_ONLY | Evidence only; excluded from product. |

No proprietary or platform-specific source was copied. The new PHP implementation is GPL-2.0-or-later.
