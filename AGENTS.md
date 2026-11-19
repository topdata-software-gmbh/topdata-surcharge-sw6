# AGENTS.md — Topdata Surcharge for Shopware 6

A Shopware 6.7 plugin that adds a configurable percentage surcharge line item to the cart.
Repository is **plugin-only** — there is no app, no test suite, no CI config, no build pipeline inside this directory. All development happens against a host Shopware 6.7 installation with `composer require topdata/topdata-surcharge-sw6` (or symlinking `src/` + `composer.json` into `custom/plugins/TopdataSurchargeSW6`).

## Layout

```
composer.json                      Plugin manifest. PSR-4: Topdata\TopdataSurchargeSW6\ -> src/
src/TopdataSurchargeSW6.php        Shopware Plugin lifecycle (empty subclass)
src/Core/Checkout/Cart/            Cart processor (the core logic)
src/Subscriber/                    Mail template data shaping
src/Resources/config/config.xml    Admin plugin configuration schema
src/Resources/config/services.xml  DI wiring
src/Resources/snippet/             Storefront snippets (de-DE, en-GB, fr-FR)
src/Resources/views/storefront/    Twig {% sw_extends %} of Storefront templates
.opencode/                         Local OpenCode config (inherits global AGENTS.md)
```

PHP namespace root: `Topdata\TopdataSurchargeSW6\` (note: case in `SurchargeSW6`).
Shopware DI bootstrap class: `Topdata\TopdataSurchargeSW6\TopdataSurchargeSW6` (declared in `composer.json` `extra.shopware-plugin-class`).

## How it works (entrypoints)

- `src/Core/Checkout/Cart/SurchargeCartProcessor.php:31` — `CartProcessorInterface::process()`. Registered via `<tag name="shopware.cart.processor" priority="4500" />` in `services.xml`. Reads five system-config keys under the `TopdataSurchargeSW6.config.*` namespace (`isActive`, `surchargePercentage`, `surchargeName`, `taxId`, `enableRappenRundung`); the LSVA gate additionally reads `lsvaCustomerField`. Adds a non-removable `LineItem` with a **hardcoded ID** `06a3e52f7afc42d69e255068886f1d22` and type `surcharge`.
- `src/Service/LsvaTaxApplicabilityService.php:21` — gate that decides whether the surcharge is calculated at all. Requires a `CustomerEntity` in the `SalesChannelContext` whose custom field (default `topdata_tg_lsva`, configurable via `lsvaCustomerField`) normalizes to `true` (`yes`/`ja`/`true`/`1`). `no`/`nein`/`false`/`0` disables it. Guests without a value fall back to the newest previous guest row with the same e-mail that has a value. No customer ⇒ no surcharge, and `SurchargeCartProcessor.php:60` removes any existing surcharge line item.
- The `surchargeName` config value is treated as a **translator key** (default `TopdataSurchargeSW6.surchargeName`) — translated via `TranslatorInterface`. If empty, it falls back to the same key. Plain text is used verbatim.
- `src/Subscriber/MailSurchargeSubscriber.php` — On `MailBeforeValidateEvent`, finds the order's `surcharge` line item, strips all `surcharge` line items from `order.lineItems` (so they don't double-render in mail templates), and injects `surchargeLabel` + `surchargeTotal` into the mail template data.
- Storefront templates all use `{% sw_extends '@Storefront/...' %}` to override offcanvas cart summary, line items, cart widget, checkout summary, and order-detail views.

## Quirks / gotchas

- **Hardcoded surcharge LineItem ID** `06a3e52f7afc42d69e255068886f1d22` (`SurchargeCartProcessor.php:56`). The processor early-returns if the cart already has it, so it won't double-add across re-runs.
- **Tax calculation depends on sales-channel tax state** (`SurchargeCartProcessor.php:93`): uses net price when `TAX_STATE_NET` or `TAX_STATE_FREE`, gross price otherwise. B2B vs B2C behavior diverges here — verify both modes when changing calculation logic.
- **Two rounding modes** (`SurchargeCartProcessor.php:79-88`):
  - Default (`enableRappenRundung = false`): `round(..., 2)`.
  - Swiss Rappenrundung (`enableRappenRundung = true`): `round(x * 20) / 20` — rounds to nearest 0.05.
- **Surcharge base = `$toCalculate->getPrice()->getPositionPrice()`** (`SurchargeCartProcessor.php:86`), i.e. the cart position price including promotions, not the sum of product line-item totals. The offcanvas template at `src/Resources/views/storefront/component/checkout/offcanvas-cart-summary.html.twig:17` recomputes the displayed product subtotal as `positionPrice - surchargeAmount` so the surcharge is not double-counted.
- **Mail subscriber mutates `order.lineItems`** (`MailSurchargeSubscriber.php:60`) by replacing the collection with one that excludes `surcharge` line items. This is intentional — the surcharge is exposed separately via `surchargeLabel` / `surchargeTotal`.
- **Plugin class name typo in `composer.json`**: `extra.label.de-DE` reads "Topdate Warenkorb Aufschlag" (should be "Topdaten"). Don't fix in isolation — confirm with maintainers first.
- **LSVA custom-field contract:** the customer custom field is `topdata_tg_lsva` in the field set `topdata_tg`. These names are an external data contract visible to shop administrators and are created/filled by an external integration, not by this plugin. Do not rename them; only the configurable lookup key `TopdataSurchargeSW6.config.lsvaCustomerField` may point to a different field.
- The surcharge appears as a cart line item type `'surcharge'` — any new Twig template iterating `lineItems` should either filter it out or render it explicitly (see offcanvas template for the pattern).

## Setup for development

This repo has no build, lint, or test commands of its own. All commands run from a host Shopware installation with the plugin installed (via Composer or symlink into `custom/plugins/`):

```bash
# Install / refresh after pulling changes
bin/console plugin:refresh
bin/console plugin:install --activate TopdataSurchargeSW6   # first time
bin/console plugin:update --activate TopdataSurchargeSW6     # existing install

# Clear caches so Twig / config.xml / services.xml changes take effect
bin/console cache:clear

# If you changed config.xml field names or system-config keys, sync to DB
bin/console system:config:create-plugin-defaults TopdataSurchargeSW6
bin/console system:config:sync

# Snippet changes are picked up automatically after cache:clear in dev.
```

There is no PHPUnit suite, no PHPStan/CS-Fixer config, and no CI workflow in this repo. Quality gates come from the host project. Do not introduce them locally without coordinating with the host repo's conventions.

## Boundaries

- **Do not search, read, or run commands outside this directory.** Parent dirs (`/home/marc/workspaces/shop-infra/sw6-plugin-registry-checked-out/`, `/home/marc/...`) are out of scope per user instruction.
- This repo **does not depend on** parent tooling (no workspace manifests, no monorepo config). Treat each plugin repo as standalone.