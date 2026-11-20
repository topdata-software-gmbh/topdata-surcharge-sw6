# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). The version
in `composer.json` is the source of truth; the repository is not tagged.

## [Unreleased]

### Added

- LSVA eligibility gate: the surcharge is only calculated for customers whose configured
  custom field is truthy (`yes`, `ja`, `true`, `1`). The field name is configurable
  (`lsvaCustomerField`, default `topdata_tg_lsva`) and validated before use.
- Guest fallback: guests without a value reuse the most recent value of a previous guest
  row with the same e-mail address.
- Dedicated storefront display for the surcharge in the offcanvas cart summary, the cart
  widget, the checkout summary and the order detail page. The raw `surcharge` line item is
  suppressed in the generic line-item list and in the cart item counter.
- Mail template support: `surchargeLabel` and `surchargeTotal` variables are injected on
  `MailBeforeValidateEvent`, and `surcharge` items are stripped from `order.lineItems` to
  avoid double rendering.
- `docs/` documentation (installation, configuration, how it works, developer guide,
  troubleshooting) and this changelog.

### Changed

- The surcharge base is now the cart **position price** (`positionPrice`), which includes
  applied promotions and vouchers, instead of the sum of product line-item totals.
- The LSVA custom-field contract is aligned on `topdata_tg_lsva` (field set `topdata_tg`).

### Fixed

- Removed leftover debug/storefront override templates that rendered test banners on
  header, cart and confirm pages.

## [1.2.0] - 2026-06-19

### Added

- Swiss **Rappenrundung**: optional rounding of net surcharge, tax and total to the nearest
  `0.05` (`enableRappenRundung`).
- Multi-language surcharge names via Shopware snippets for `de-DE` (`LSVA`), `en-GB`
  (`Service Surcharge`) and `fr-FR` (`RPLP`). The configured name can still be plain text.

### Fixed

- Correct net-price handling for the surcharge in B2B/net sales channels.

## [1.1.0] - 2026-06-18

### Added

- Configurable tax rate for the surcharge via a `tax` entity single-select (`taxId`).
- Dynamic surcharge lookup by a stable line-item id instead of a hardcoded position.

### Changed

- The surcharge is calculated on a **net** basis, with tax added on top.
- `CalculatedTax` / `CalculatedTaxCollection` moved to the Shopware `Tax` namespace.

### Fixed

- Correct `CalculatedTax` constructor argument order.
- Correct net total calculation in `SurchargeCartProcessor`.

## [1.0.0] - 2026-05-22

### Added

- Initial public release: a configurable percentage surcharge line item in the cart.
- Administration settings for activation, percentage, name and tax rate.
- Translated surcharge name using a translator key or plain text.
- Plugin metadata aligned on the `TopdataSurchargeSW6` technical name and the
  `Topdata\TopdataSurchargeSW6\` namespace.
- MIT license and README.
