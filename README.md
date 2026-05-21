# Richpanel Analytics – Magento 2 Extension

Magento 2 module that integrates your store with [Richpanel](https://richpanel.com) — syncing customers, orders, shipments, and cart activity, and embedding the Richpanel helpdesk/messenger on the storefront.

- **Module:** `Richpanel_Analytics`
- **Composer package:** `richpanel/analytics-magento2-extension`
- **Version:** 3.1.0
- **License:** OSL-3.0 / AFL-3.0

## Requirements

- PHP 7.4, 8.0, 8.1, 8.2, or 8.3
- Magento 2 (`magento/framework` >= 102.0.0, < 104.0.0)
- A Richpanel account with an API Token and API Secret

## Features

- Embeds the Richpanel messenger/widget on the storefront (`//cdn.richpanel.com/js/richpanel-root.js`)
- Observers stream live events to Richpanel:
  - Customer login / register / update
  - Add-to-cart and remove-from-cart
  - Order placement and order updates
  - Shipment creation
- Admin-triggered historical order import with configurable sync duration
- Cron job (`OrderUpdateCheck`) that periodically syncs recently updated orders
- Per-store-view configuration (each store view can have its own API credentials)
- Encrypts payloads using the store's API secret (SHA-256 signatures)

## Installation

### Via composer (recommended)

This package is served directly from GitHub (not Packagist), so first register the
repository in your Magento project's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/ebanolopes/magento-2-richpanel"
    }
  ]
}
```

Then require it (pick the version constraint that matches a published Git tag):

```bash
composer require richpanel/analytics-magento2-extension:^3.1
bin/magento module:enable Richpanel_Analytics
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
bin/magento cache:flush
```

### Manual install

1. Copy the `Analytics/` directory into `app/code/Richpanel/Analytics/` in your Magento root.
2. Run the same `bin/magento` commands as above.

## Configuration

1. In the Magento admin, go to **Stores → Configuration → Richpanel → Richpanel Helpdesk → General Configuration**.
2. Set **Enabled** to `Yes`.
3. Enter your **API Token** and **API Secret** from the Richpanel dashboard (Settings → Installation).
4. Choose a **Sync Duration** for historical order import.
5. Select a specific store view (top-left scope switcher) and click **Import orders** to backfill.

Config path: `richpanel_analytics/general/{enable,api_key,api_secret,rp_duration}`

## Module layout

```
Analytics/
├── Block/                   Frontend & admin blocks (widget render, import button)
├── Controller/Adminhtml/    Admin import endpoint (Import/Ajax)
├── Cron/                    OrderUpdateCheck scheduled job
├── CustomerData/            Storefront customer section data
├── Helper/                  Data, Client, serializers (order/shipment/image)
├── Model/                   Analytics, Import, config sources
├── Observer/                Event listeners (order, shipment, cart, customer)
├── etc/                     module.xml, events.xml, crontab.xml, di.xml, system.xml, csp_whitelist.xml
├── i18n/                    Translations
├── view/                    Layouts, templates, JS, CSS
├── composer.json
└── registration.php
```

## Support

Issues or questions: **support@richpanel.com**
