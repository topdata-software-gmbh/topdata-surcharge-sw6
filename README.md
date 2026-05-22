# Topdata Surcharge for Shopware 6

A Shopware 6 plugin that adds a configurable percentage surcharge to the shopping cart. The surcharge is calculated based on the cart total and can be customized through the Shopware administration.

## Features

- Configurable surcharge percentage (default: 3.9%)
- Customizable surcharge name/label
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
   - **Surcharge Name**: The label shown in the cart (e.g., "Service-Aufschlag")

## Requirements

- Shopware 6.7 or higher
- PHP 8.3 or higher

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Author

Topdata GmbH