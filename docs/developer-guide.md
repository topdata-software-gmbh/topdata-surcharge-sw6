# Developer guide

This repository is a Shopware 6.7 **plugin only**. It ships no app, no test suite, no CI
and no build pipeline; it is developed against a host Shopware installation.

## Repository layout

```
composer.json                       Plugin manifest (PSR-4, version, Shopware requirements)
src/TopdataSurchargeSW6.php         Plugin bootstrap class (empty Plugin subclass)
src/Core/Checkout/Cart/             Cart processor (core logic)
src/Service/                        LSVA eligibility service
src/Subscriber/                     Mail template data shaping
src/Resources/config/config.xml     Administration settings schema
src/Resources/config/services.xml   DI wiring
src/Resources/snippet/              Storefront snippets (de-DE, en-GB, fr-FR)
src/Resources/views/storefront/     Twig overrides of Storefront templates
docs/                               This documentation
```

## Namespaces and identifiers

- PHP namespace root: `Topdata\TopdataSurchargeSW6\` → `src/`
- Plugin class (DI bootstrap): `Topdata\TopdataSurchargeSW6\TopdataSurchargeSW6`
- Shopware technical plugin name: `TopdataSurchargeSW6`
- System config prefix: `TopdataSurchargeSW6.config.`
- Surcharge line-item type: `surcharge`
- Surcharge line-item id: `06a3e52f7afc42d69e255068886f1d22`

## Dependency injection

Services are declared explicitly in `src/Resources/config/services.xml`:

| Service | Arguments | Tag |
| --- | --- | --- |
| `...\Service\LsvaTaxApplicabilityService` | `Doctrine\DBAL\Connection`, `SystemConfigService` | – |
| `...\Core\Checkout\Cart\SurchargeCartProcessor` | `SystemConfigService`, `tax.repository`, `translator`, `LsvaTaxApplicabilityService` | `shopware.cart.processor` (priority `4500`) |
| `...\Subscriber\MailSurchargeSubscriber` | `order_line_item.repository` | `kernel.event_subscriber` |

No autowiring/autoconfiguration is enabled. When you add a dependency, add an explicit
`<argument>` and keep constructor parameter order in sync.

## Extension points

- **Calculation** — change `SurchargeCartProcessor::process()`. Keep the early-return
  ordering: active/percentage, product presence, eligibility, idempotency, tax
  resolution, then calculate.
- **Eligibility** — `LsvaTaxApplicabilityService` is self-contained; extend the
  normalization or guest fallback there and keep the public `isLsvaApplicable()` contract.
- **Display** — Twig overrides under `src/Resources/views/storefront/**` extend core
  templates with `sw_extends`. Never render the `surcharge` line item in the generic
  line-item list.
- **Mails** — extend `MailSurchargeSubscriber` if additional virtual fields are needed;
  keep `surchargeLabel` / `surchargeTotal` for backward compatibility.

## Working against a host shop

Run these from the host Shopware root:

```bash
# Pick up composer.json / plugin metadata changes
bin/console plugin:refresh

# First install
bin/console plugin:install --activate TopdataSurchargeSW6

# After code changes
bin/console plugin:update --activate TopdataSurchargeSW6
bin/console cache:clear

# If config.xml keys or defaults changed
bin/console system:config:create-plugin-defaults TopdataSurchargeSW6
bin/console system:config:sync
```

Snippet and Twig changes are picked up after `cache:clear` in development.

## Versioning

The plugin version lives in `composer.json` (`version`, currently `v1.2.0`). Releases are
recorded in [CHANGELOG.md](../CHANGELOG.md). The repository has no release tags, so the
version in `composer.json` is the source of truth.

If you ever add a database migration, bump the `version` in the **same** change; Shopware
only runs migrations during the plugin update lifecycle. There are currently no migrations.

## Database access

`LsvaTaxApplicabilityService` uses `Doctrine\DBAL\Connection` directly for the guest
fallback query. Remember the DBAL 4.x pitfalls when editing it:

- Use `ParameterType` enum values, not `int`.
- `fetchAllAssociative()` returns strings — cast explicitly.
- Use `executeStatement` / `fetchOne` / `fetchAllAssociative`; the old `insert`,
  `update`, `delete` helpers are gone.

The query only reads `customer.custom_fields` and is parameterized for e-mail and id. The
field name is validated by regex before it is interpolated into the JSON path.

## Conventions

- `declare(strict_types=1);` in every PHP file.
- Read every system-config value with the sales-channel ID.
- Keep the surcharge calculation net-based with tax added on top.
- No comments unless they add non-obvious value.
- Do not rename the LSVA contract (`topdata_tg_lsva`, field set `topdata_tg`) — it is an
  external data contract for shop administrators and integrations.

## Related pages

- [How it works](how-it-works.md)
- [Configuration](configuration.md)
- [Troubleshooting](troubleshooting.md)
