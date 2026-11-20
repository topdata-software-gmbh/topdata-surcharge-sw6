# How it works

This page describes the runtime behaviour of the plugin: where the surcharge is created,
how it is calculated, when it is suppressed, and how it is displayed in the storefront and
in e-mails.

## Components

| Component | File | Role |
| --- | --- | --- |
| Cart processor | `src/Core/Checkout/Cart/SurchargeCartProcessor.php` | Calculates and adds the surcharge line item |
| Eligibility service | `src/Service/LsvaTaxApplicabilityService.php` | Decides whether a customer is LSVA-liable |
| Mail subscriber | `src/Subscriber/MailSurchargeSubscriber.php` | Exposes the surcharge to mail templates |
| DI wiring | `src/Resources/config/services.xml` | Registers services and the cart-processor tag |
| Settings | `src/Resources/config/config.xml` | Administration fields |
| Snippets | `src/Resources/snippet/storefront.*.json` | Default surcharge names |
| Views | `src/Resources/views/storefront/**` | Storefront overrides |

## Cart pipeline

`SurchargeCartProcessor` implements `CartProcessorInterface` and is registered with
`<tag name="shopware.cart.processor" priority="4500" />`. On every cart calculation it:

1. Reads the configuration for the current sales channel.
2. Returns early when `isActive` is off or `surchargePercentage <= 0`.
3. Resolves the translated surcharge name (falling back to the default snippet key).
4. Returns early when the cart contains no product line items.
5. Runs the LSVA eligibility check. If the customer is **not** eligible, an existing
   surcharge line item is removed and processing stops.
6. Returns early when the surcharge line item is already present (idempotency).
7. Returns early when no `taxId` is configured or the tax record cannot be loaded.
8. Calculates net surcharge, tax and total.
9. Adds a non-removable line item of type `surcharge`.

### Line-item identity

The surcharge uses a fixed id:

```
06a3e52f7afc42d69e255068886f1d22
```

The item is created with `setGood(false)`, `setRemovable(false)` and type `surcharge`.
Because the id is stable, re-running the processor does not duplicate the item, and the
eligibility gate can reliably remove it again.

## Calculation

```
baseAmount = cart.positionPrice            // after promotions/vouchers, excl. shipping
raw        = baseAmount * percentage / 100
```

Default rounding (`enableRappenRundung = false`):

```
netPrice   = round(raw, 2)
taxAmount  = round(netPrice * taxRate / 100, 2)
totalPrice = round(netPrice + taxAmount, 2)
```

Rappenrundung (`enableRappenRundung = true`):

```
netPrice   = round(raw * 20) / 20
taxAmount  = round(netPrice * taxRate / 100 * 20) / 20
totalPrice = round((netPrice + taxAmount) * 20) / 20
```

The configured tax rate is taken from the `tax` entity selected in `taxId`
(`src/Core/Checkout/Cart/SurchargeCartProcessor.php:76-83`).

### Position price vs. line-item totals

The calculation base is `Cart::getPrice()->getPositionPrice()`, i.e. the position price
**including applied promotions/vouchers**, not the naïve sum of product line-item totals.
This is why discounts and vouchers reduce the surcharge automatically.

### Gross, net and tax-free sales channels

The price stored on the line item depends on the sales-channel tax state
(`SurchargeCartProcessor.php:102-103`):

- `TAX_STATE_NET` or `TAX_STATE_FREE` → use the **net** surcharge.
- `TAX_STATE_GROSS` (default B2C) → use the surcharge **total including tax**.

Both variants use the same `CalculatedTax` and `TaxRule` so that Shopware's tax aggregation
stays consistent. Verify both B2C (gross) and B2B (net) carts when changing this logic.

## Eligibility (LSVA)

The gate is implemented in `LsvaTaxApplicabilityService::isLsvaApplicable()`
(`src/Service/LsvaTaxApplicabilityService.php:21`).

```
no customer                         -> not eligible
field value truthy                  -> eligible
field value falsy                   -> not eligible
field value unset + non-guest       -> not eligible
field value unset + guest           -> newest previous guest with same e-mail
                                       that has a value wins; otherwise not eligible
```

### Value normalization

| Input | Result |
| --- | --- |
| `true` / `false` (bool) | as-is |
| `1` / `0` (int) | `true` / `false` |
| `1.0` / `0.0` (float) | `true` / `false` |
| `yes`, `ja`, `true`, `1` (string, case-insensitive) | `true` |
| `no`, `nein`, `false`, `0` (string, case-insensitive) | `false` |
| anything else / missing | unset |

### Guest fallback

Guests often re-register for each order, so a fresh guest row may not yet have the custom
field. When a guest has no value, the service looks up the newest previous guest rows with
the same e-mail (`customer.guest = 1`, ordered by `created_at DESC`, limit 25) and uses the
first one that has a normalized value.

The field name is read from `TopdataSurchargeSW6.config.lsvaCustomerField` and validated;
invalid or empty values fall back to `topdata_tg_lsva`.

## Display

Because the surcharge is a real line item, the default storefront would render it twice
(in the line-item list **and** in the totals). The plugin therefore overrides these views:

| Template | Behaviour |
| --- | --- |
| `component/line-item/line-item.html.twig` | Suppresses the raw `surcharge` line item in the line-item list |
| `component/checkout/offcanvas-cart-summary.html.twig` | Rebuilds the summary: product subtotal = `positionPrice - surcharge`, then a dedicated surcharge row, shipping, taxes and total |
| `layout/header/actions/cart-widget.html.twig` | Excludes the surcharge from the item count and from the displayed subtotal |
| `page/checkout/summary.html.twig` | Shows the product subtotal without the surcharge and lists the surcharge as its own row |
| `page/account/order-history/order-detail.html.twig` | Adds the surcharge label and total to the order detail summary |

All templates use `{% sw_extends '@Storefront/...' %}`. Any additional template that
iterates `lineItems` should either filter out `type == 'surcharge'` or render it
explicitly.

## Order confirmation mails

`MailSurchargeSubscriber` listens to `MailBeforeValidateEvent`. For every outgoing mail
that has an `order` in its template data it:

1. Loads the first order line item of type `surcharge` for the order.
2. Injects `surchargeLabel` and `surchargeTotal` into the mail template data.
3. Removes all `surcharge` items from `order.lineItems` so they are not rendered twice.

Mail templates can use the variables directly:

```twig
{% if surchargeTotal %}
    <tr>
        <td>{{ surchargeLabel }}</td>
        <td>{{ surchargeTotal|currency(order.currency.isoCode) }}</td>
    </tr>
{% endif %}
```

> Note: the subscriber intentionally mutates `order.lineItems` (it replaces the collection
> with one that excludes `surcharge` items). The surcharge is exposed separately via the
> two template variables.

## Snippets

| Locale | `TopdataSurchargeSW6.surchargeName` |
| --- | --- |
| `de-DE` | `LSVA` |
| `en-GB` | `Service Surcharge` |
| `fr-FR` | `RPLP` |

## Related pages

- [Configuration](configuration.md)
- [Developer guide](developer-guide.md)
- [Troubleshooting](troubleshooting.md)
