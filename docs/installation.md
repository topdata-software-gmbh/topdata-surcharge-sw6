# Installation

## Requirements

| Requirement | Version |
| --- | --- |
| Shopware | `6.7.*` |
| PHP | `8.3` or higher |

The plugin has no database tables or migrations of its own. It uses Shopware core
entities (`tax`, `order_line_item`, `customer`) and the system configuration.

## Installation with Composer

```bash
composer require topdata/topdata-surcharge-sw6
bin/console plugin:refresh
bin/console plugin:install --activate TopdataSurchargeSW6
bin/console cache:clear
```

## Manual installation

1. Copy the plugin into `custom/plugins/TopdataSurchargeSW6`.
2. Install and activate it:

   ```bash
   bin/console plugin:refresh
   bin/console plugin:install --activate TopdataSurchargeSW6
   bin/console cache:clear
   ```

The technical plugin name is `TopdataSurchargeSW6`; the PHP namespace root is
`Topdata\TopdataSurchargeSW6\`.

## Updating

```bash
composer update topdata/topdata-surcharge-sw6
bin/console plugin:update --activate TopdataSurchargeSW6
bin/console cache:clear
```

If a release changes configuration field names or default system-config keys, sync the
defaults into the database:

```bash
bin/console system:config:create-plugin-defaults TopdataSurchargeSW6
bin/console system:config:sync
```

## First-time setup after installation

1. Open **Settings → System → Plugins → Topdata Cart Surcharge**.
2. Enable **Activate surcharge**.
3. Set the **Surcharge in percent** (default `3.9`).
4. Pick a **Tax rate for surcharge** — the surcharge is skipped while this is empty.
5. Optionally adjust the name, the LSVA customer field and Rappenrundung.
6. Save and clear the cache.

For details on each setting see [configuration.md](configuration.md).

## Verifying the install

- The plugin appears as **Topdata Cart Surcharge** in the plugin manager.
- With the surcharge active, a tax rate selected and an eligible customer, the cart shows
  an extra line labelled with `LSVA` (de-DE), `Service Surcharge` (en-GB) or `RPLP` (fr-FR).
- Guests and customers without a truthy LSVA field deliberately do **not** get a surcharge.
  See [how-it-works.md](how-it-works.md#eligibility-lsva).
