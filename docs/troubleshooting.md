# Troubleshooting

Most issues fall into one of four buckets: the feature is disabled/incomplete, the
customer is not eligible, caches are stale, or the display overrides are not active.
Clear the cache first:

```bash
bin/console cache:clear
```

## The surcharge does not appear

Check, in this order:

1. **`isActive`** is enabled for the current sales channel.
2. **`surchargePercentage`** is greater than `0`.
3. **`taxId`** is set and the referenced tax record still exists. An empty or dangling
   `taxId` silently skips the surcharge.
4. The cart contains at least one **product** line item.
5. **The customer is eligible** — see the next section.
6. The config was synced after a change:

   ```bash
   bin/console system:config:get TopdataSurchargeSW6.config.taxId
   bin/console system:config:get TopdataSurchargeSW6.config.isActive
   ```

### The customer is not eligible

The surcharge is only calculated for LSVA-liable customers by design.

- **Guest / anonymous visitor** → no customer object → no surcharge.
- **Logged-in customer with the field unset** → not eligible.
- **Field set to a falsy value** (`no`, `nein`, `false`, `0`) → not eligible.
- **Field set to something unrecognized** → treated as unset.

Verify the custom field name and value:

```bash
bin/console system:config:get TopdataSurchargeSW6.config.lsvaCustomerField
```

The value must be the **technical** custom-field name (default `topdata_tg_lsva`), and the
stored value must be one of `yes`, `ja`, `true`, `1` (truthy) or `no`, `nein`, `false`,
`0` (falsy). See [how-it-works.md](how-it-works.md#eligibility-lsva).

### Guest fallback is not applied

For guests the plugin reuses the value of the newest previous guest row with the **same
e-mail**:

- E-mails must match exactly (the lookup is case-sensitive as stored).
- Only the newest 25 previous guest rows are inspected.
- Earlier rows must contain a valid truthy/falsy value.

If none of these hold, the guest is treated as not eligible.

## The surcharge appears for a customer who should not have it

- A surcharge line item from an earlier cart state is removed automatically only when the
  processor runs with an eligible check that returns false. Ensure the cart is
  recalculated (open the cart / reload checkout).
- Confirm the configured field name points at the field the integration actually writes.
  A typo in `lsvaCustomerField` falls back to `topdata_tg_lsva`; if the integration uses a
  different field, set it explicitly.
- Clear the cache after changing the config.

## Wrong percentage or amount

- The base is the **cart position price** (`positionPrice`), which already reflects
  promotions and vouchers. This is not the sum of product line-item totals.
- The surcharge is calculated **net**, with tax added on top. Compare against net prices.
- With **Rappenrundung** enabled, net surcharge, tax and total are each rounded to `0.05`
  instead of `0.01`, so amounts can differ by up to a few cents from the default mode.
- Verify the tax rate: `taxId` must point at the intended `tax` record (e.g. `8.1 %`,
  `2.6 %`).

## The name shows a raw key such as `TopdataSurchargeSW6.surchargeName`

- The field value is a translator key that has no snippet in the active language.
- Add the snippet under **Settings → Shop → Snippets**, or enter plain text in the
  `surchargeName` setting to bypass translation.

## The surcharge is shown twice, or the cart count is wrong

The plugin suppresses the raw line item and rebuilds the totals in its Twig overrides. If
that is not happening:

- Confirm the overrides from `src/Resources/views/storefront/**` are compiled:
  `bin/console cache:clear`.
- A theme or another plugin overriding the same blocks can win. Check the effective
  template with the storefront in dev mode.
- Any custom template iterating `lineItems` must filter `type == 'surcharge'` or render it
  explicitly.

## Vouchers behave unexpectedly

Because the base is the position price, a voucher that reduces the position price also
reduces the surcharge. A voucher that is added as a separate non-position line item may
not affect it. This is intentional; see
[how-it-works.md](how-it-works.md#position-price-vs-line-item-totals).

## The surcharge is missing or duplicated in order confirmation mails

- The mail subscriber exposes `surchargeLabel` and `surchargeTotal` and removes
  `surcharge` items from `order.lineItems`.
- If a custom mail template renders the full line-item list, it must use the two variables
  instead of expecting the surcharge to be in `order.lineItems`.
- Clear the cache after changing mail templates.

## Nothing changes after editing config, Twig, snippets or XML

Shopware caches configuration, Twig and snippets. Always run:

```bash
bin/console cache:clear
```

After changing `config.xml` field names or defaults, also run:

```bash
bin/console system:config:create-plugin-defaults TopdataSurchargeSW6
bin/console system:config:sync
```

## Where to look next

- [Configuration](configuration.md)
- [How it works](how-it-works.md)
- [Developer guide](developer-guide.md)
