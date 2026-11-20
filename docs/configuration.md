# Configuration

All settings live under the system-config namespace `TopdataSurchargeSW6.config.*`.
They are edited in **Settings → System → Plugins → Topdata Cart Surcharge** and can be
overridden per sales channel in the plugin settings.

## Settings

### `isActive` — Activate surcharge

| | |
| --- | --- |
| Type | Boolean |
| Default | `true` |
| Config key | `TopdataSurchargeSW6.config.isActive` |

Master switch. When off, no surcharge is added and an existing surcharge line item is not
replaced. The cart processor returns early before any calculation.

### `surchargePercentage` — Surcharge in percent

| | |
| --- | --- |
| Type | Float |
| Default | `3.9` |
| Config key | `TopdataSurchargeSW6.config.surchargePercentage` |

Percentage applied to the cart position price. The surcharge is skipped when the value is
`0` or negative.

### `surchargeName` — Surcharge name

| | |
| --- | --- |
| Type | Text |
| Default | `TopdataSurchargeSW6.surchargeName` |
| Config key | `TopdataSurchargeSW6.config.surchargeName` |

The label shown in the cart, checkout summary, order detail and e-mails. The value is
passed through the Symfony translator:

- If you enter a **translator key** (e.g. `TopdataSurchargeSW6.surchargeName`), it is
  translated for the active locale.
- If you enter **plain text**, it is used verbatim regardless of locale.
- If the field is empty, the plugin falls back to `TopdataSurchargeSW6.surchargeName`.

Shipped snippet values for the default key:

| Locale | Value |
| --- | --- |
| `de-DE` | `LSVA` |
| `en-GB` | `Service Surcharge` |
| `fr-FR` | `RPLP` |

To translate the default key, go to **Settings → Shop → Snippets**, open the snippet set
for the language and edit `TopdataSurchargeSW6.surchargeName`.

### `taxId` — Tax rate for surcharge

| | |
| --- | --- |
| Type | Entity single-select (`tax`) |
| Default | none |
| Config key | `TopdataSurchargeSW6.config.taxId` |

The tax rate applied to the surcharge. The processor loads the selected `tax` entity and
uses its `taxRate`. **If this is empty or the referenced tax record cannot be found, no
surcharge is added.**

### `lsvaCustomerField` — Custom field for LSVA eligibility

| | |
| --- | --- |
| Type | Text |
| Default | `topdata_tg_lsva` |
| Config key | `TopdataSurchargeSW6.config.lsvaCustomerField` |

Technical name of the customer custom field that marks a customer as LSVA-liable. The
default field belongs to the `topdata_tg` field set, which is created and filled by an
external integration.

The value is validated against `^[a-zA-Z0-9_]+$`. If it is empty or contains invalid
characters, the plugin falls back to `topdata_tg_lsva`.

Truthy: boolean `true`, numeric `1` / `1.0`, or strings `yes`, `ja`, `true`, `1`.
Falsy: boolean `false`, numeric `0` / `0.0`, or strings `no`, `nein`, `false`, `0`.
Any other value counts as "not set". See [how-it-works.md](how-it-works.md#eligibility-lsva).

### `enableRappenRundung` — Round to 0.05 CHF

| | |
| --- | --- |
| Type | Boolean |
| Default | `false` |
| Config key | `TopdataSurchargeSW6.config.enableRappenRundung` |

Swiss rounding mode. When enabled, the net surcharge, the tax and the total are each
rounded to the nearest `0.05` (`round(value * 20) / 20`). When disabled, normal `0.01`
rounding (`round(value, 2)`) is used.

## Configuration via console

Defaults can be written to the database with the Shopware core commands:

```bash
bin/console system:config:create-plugin-defaults TopdataSurchargeSW6
bin/console system:config:sync
```

Individual values can be inspected or set with:

```bash
bin/console system:config:get  TopdataSurchargeSW6.config.surchargePercentage
bin/console system:config:set  TopdataSurchargeSW6.config.surchargePercentage 3.9
bin/console cache:clear
```

## Sales-channel overrides

Because every setting is read with the sales-channel ID, the values can differ per sales
channel when the plugin settings are saved in a sales-channel-specific scope. The tax
selection and the LSVA field name are resolved per sales channel as well.

## Related pages

- [Installation](installation.md)
- [How it works](how-it-works.md)
- [Troubleshooting](troubleshooting.md)
