# Topdata Surcharge for Shopware 6

A Shopware 6 plugin that adds a configurable percentage surcharge to the shopping cart. The surcharge is calculated based on the cart total and can be customized through the Shopware administration.

## Features

- Configurable surcharge percentage (default: 3.9%)
- Multi-language support using Shopware snippets
- Enable/disable the surcharge via administration
- Automatically applied to cart

## Installation

### Via Composer

```bash
composer require topdata/topdata-surcharge-sw6
bin/console plugin:install --activate TopdataSurchargeSW6
```

### Manual Installation

1. Copy this plugin to `custom/plugins/TopdataSurchargeSW6`
2. Run: `bin/console plugin:install --activate TopdataSurchargeSW6`

## Configuration

After installation, configure the plugin in Shopware Administration:

1. Go to **Settings** → **System** → **Plugins**
2. Find **Topdata Cart Surcharge** and click on it
3. Configure the following settings:
   - **Active**: Enable or disable the surcharge
   - **Surcharge Percentage**: The percentage to charge (e.g., 3.9)
   - **Surcharge Name**: The translation key or label shown in the cart.
     - **Default**: `TopdataSurchargeSW6.surchargeName`
     - You can use a custom translation key or enter plain text directly. If plain text is entered, it will be used regardless of the chosen locale.
   - **Tax ID**: The tax rate applied to the surcharge

### Translating Surcharge Names
The default value uses Shopware snippets. To customize translations:
1. Go to **Settings** → **Shop** → **Snippets** in your Shopware Administration.
2. Select your language snippet set.
3. Search for the key `TopdataSurchargeSW6.surchargeName`.
4. Enter your localized text (e.g., "LSVA" for German, "Service Surcharge" for English, "RPLP" for French).

### Calculation

The surcharge is calculated on the **sum of all product net prices** and treated as a **net price** with tax added on top.

```
totalPrice (net, taxRate=2.6%): 135.60
Surcharge (3.9% [per plugin setting] "LSVA", net, taxRate=8.1% [per plugin setting]): 135.60 * 0.039 = 5.29
Shipping (net, taxRate=8.1%) = 10.95

VAT 2.6% = 135.60 * 0.026 = 3.53
VAT 8.1% = (10.95 + 5.29) * 0.081 = 1.32

Gesamtsumme = 135.60 + 5.29 + 10.95 + 3.53 + 1.32 = 156.69 (maybe rounded to 156.70 because of Rappenrundung)
```

## Requirements

- Shopware 6.7 or higher
- PHP 8.3 or higher

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Author

Topdata GmbH