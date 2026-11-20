# Topdata Surcharge for Shopware 6

A Shopware 6 plugin that adds a configurable percentage surcharge to the shopping cart.
The surcharge is calculated from the cart position price, displayed as its own cart line
item, and can be limited to customers flagged as LSVA-liable (Swiss heavy vehicle charge).

## Features

- Configurable surcharge percentage (default: `3.9 %`)
- Optional Swiss **Rappenrundung** (rounds to the nearest `0.05`)
- Selectable tax rate applies to the surcharge
- Correct handling of gross, net and tax-free sales channels (B2C / B2B)
- Surcharge name is a translator key or plain text, with shipped `de-DE`, `en-GB` and `fr-FR` snippets
- Eligibility gate: surcharge only for customers whose LSVA custom field is truthy
- Guest fallback: reuses the most recent value of a previous guest with the same e-mail
- Dedicated display in offcanvas cart, cart widget, checkout summary and order detail
- Included in order confirmation mails via `surchargeLabel` / `surchargeTotal`
- Enable/disable the entire feature from the administration

## Requirements

- Shopware `6.7.*`
- PHP `8.3` or higher

## Installation

With Composer:

```bash
composer require topdata/topdata-surcharge-sw6
bin/console plugin:install --activate TopdataSurchargeSW6
```

Manual installation and upgrade instructions are in [docs/installation.md](docs/installation.md).

## Configuration overview

After activation go to **Settings → System → Plugins → Topdata Cart Surcharge** and
configure the following. Full descriptions, defaults and examples are in
[docs/configuration.md](docs/configuration.md).

| Setting | Default | Purpose |
| --- | --- | --- |
| `isActive` | `true` | Enable or disable the surcharge |
| `surchargePercentage` | `3.9` | Percentage of the cart position price |
| `surchargeName` | `TopdataSurchargeSW6.surchargeName` | Translator key or literal label |
| `taxId` | – | Tax rate applied to the surcharge |
| `lsvaCustomerField` | `topdata_tg_lsva` | Customer custom field used for eligibility |
| `enableRappenRundung` | `false` | Round the surcharge to `0.05` |

## How the surcharge is calculated

The surcharge is treated as a **net** amount with tax added on top:

```
base            = cart position price (after promotions/vouchers, excl. shipping)
surcharge (net) = round(base * percentage / 100, 2)
tax             = round(surcharge * taxRate / 100, 2)
total           = surcharge + tax
```

Example (net prices, surcharge `3.9 %`, tax `8.1 %`):

```
base                    = 135.60
surcharge (net)         = 135.60 * 0.039        = 5.29
tax (8.1 % of surcharge)= 5.29   * 0.081        = 0.43
surcharge total         = 5.72
```

With **Rappenrundung** enabled every intermediate amount is rounded to `0.05` instead of
`0.01`. See [docs/how-it-works.md](docs/how-it-works.md) for the full data flow.

## Documentation

- [docs/installation.md](docs/installation.md) — install, update and cache commands
- [docs/configuration.md](docs/configuration.md) — every setting explained
- [docs/how-it-works.md](docs/how-it-works.md) — cart processor, eligibility, mail and templates
- [docs/developer-guide.md](docs/developer-guide.md) — services, extension points, conventions
- [docs/troubleshooting.md](docs/troubleshooting.md) — common problems and fixes

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT License — see [LICENSE](LICENSE) for details.

## Author

Topdata Software GmbH — <https://www.topdata.de>
