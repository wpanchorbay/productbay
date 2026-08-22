# Requirements

Before installing ProductBay, make sure your environment meets the following requirements.

## System Requirements

| Requirement | Minimum Version | Recommended |
|-------------|----------------|-------------|
| **WordPress** | 6.8+ | Latest stable |
| **WooCommerce** | 6.1+ | Latest stable |
| **PHP** | 7.4+ | 8.0+ |

::: warning WooCommerce Required
ProductBay is a WooCommerce extension. It **will not activate** without WooCommerce installed and active.
:::

## Product Requirements

ProductBay is designed to display your existing WooCommerce products. To create and preview tables, you must have:

- **At least one published product** in your WooCommerce store.
- If using category-based tables, products must be assigned to those categories.

::: tip
If you're just starting out, we recommend adding a few test products or [importing sample data](https://docs.woocommerce.com/document/importing-woocommerce-sample-data/) from WooCommerce.
:::

## Browser Compatibility

### Admin Panel (Table Builder)

The React-based admin panel works best in modern evergreen browsers:

- Chrome / Edge (latest)
- Firefox (latest)
- Safari (latest)

### Frontend Tables

Product tables rendered on the frontend are built with standard HTML, CSS, and minimal JavaScript. They work in all modern browsers, including mobile browsers.

## Hosting

ProductBay works on any standard WordPress hosting. No special server configuration is required. All assets (JavaScript, CSS) are bundled locally within the plugin — **no external CDN or remote scripts are loaded**.
