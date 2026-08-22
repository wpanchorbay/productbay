# Frequently Asked Questions

## General

### Does this plugin require WooCommerce?

Yes. ProductBay is a WooCommerce extension and **will not function** without WooCommerce installed and active. The plugin checks for WooCommerce on activation and will display a notice if it's missing.

### Which WordPress versions are supported?

ProductBay requires **WordPress 6.8 or later**. We recommend always running the latest stable version of WordPress.

### Which PHP versions are supported?

ProductBay requires **PHP 7.4 or later**. We recommend **PHP 8.0+** for the best performance and compatibility.

## ProductBay Pro <ProBadge />

### Is there a Pro version?

Yes! **ProductBay Pro** is now available. It adds advanced features like a Price Range Filter, advanced display modes for Variable and Grouped products (Popup Modals, Nested Rows), a powerful Custom Field column, and Import/Export capabilities.

### What does ProductBay Pro include?

You can see a full feature comparison between the Free and Pro versions on our [homepage](/).

## License & Updates

### Do I need a license key to use ProductBay?
No. The core version of ProductBay is 100% free forever. A license key is only needed if you purchase ProductBay Pro to access premium features.

### How do I activate my Pro license key?
After purchasing, you can enter your license key under **ProductBay → Settings → License**. For step-by-step instructions, see our [License Activation](/features/license) guide.

### Can I use Pro on multiple sites?

This depends on the license plan you purchased. Single-site licenses allow activation on one production domain, while Plus and Agency licenses allow activation on multiple sites. Check your WPAnchorBay account for details.

### How do I update ProductBay Pro?

As long as your license is active, ProductBay Pro receives automatic updates directly through your WordPress Plugins dashboard, just like the free version.

## Product Tables

### How do I display a table on a page?

After creating a table in the ProductBay admin, copy its shortcode (e.g., `[productbay id="1"]`) and paste it into any page, post, or widget using the Shortcode block. See the [Shortcodes guide](/features/shortcodes) for details.

### Can I display multiple tables on the same page?

Yes! Each table has its own **scoped CSS**, so multiple tables on the same page will not conflict with each other's styling. Simply add multiple shortcodes:

```
[productbay id="1"]
[productbay id="2"]
```

### Which product types are supported?

ProductBay supports all standard WooCommerce product types:
- ✅ **Simple** products
- ✅ **Variable** products (with inline attribute selectors)
- ✅ **Grouped** products
- ✅ **External / Affiliate** products

### Can customers select product variations in the table?

Yes! Variable products display inline dropdown selectors for each attribute (size, color, etc.) directly within the table row. Customers can choose variations and add to cart without visiting the product page.

## Performance & Privacy

### Will it slow down my site?

ProductBay is built with performance in mind:
- Assets are loaded **only on pages** where a table shortcode is present
- The frontend is rendered in PHP and enhanced with a small jQuery script — no heavy JavaScript framework, no client-side table library
- The filter dropdown choices above a table are cached in a transient (12 hours by default) so building them never costs a query on a normal page view

### Does this plugin call any external services?

**No.** All JavaScript, CSS, and other assets are bundled locally within the plugin. No data is sent to, or loaded from, any external server.

::: info Note for Pro Users
ProductBay Pro contacts the WPAnchorBay license server solely for license activation and validation. No user data or product data is transmitted.
:::

## Translation & Localization

### Is it translation ready?

Yes. 100% of user-facing strings use WordPress localization functions (`__()`, `_e()`, `@wordpress/i18n`). The plugin is fully prepared for translation into any language.

### How do I translate the plugin?

1. Use a translation plugin like **Loco Translate** or **WPML**
2. Or create a `.po` file for your language based on the included `languages/productbay.pot` file
3. Place the translation files in `wp-content/languages/plugins/`

## Troubleshooting

### My table is not showing on the page

Check the following:
1. **Table status** — Make sure the table is set to "Published". A draft, pending, or private table renders for administrators only
2. **Correct shortcode** — Verify the table ID in the shortcode matches your table
3. **WooCommerce active** — Ensure WooCommerce is installed and active
4. **Products exist** — Make sure the product source has matching published products

### I see a yellow notice instead of my table

This means the table is not published — it is draft, pending, or private. Only administrators see this notice; regular visitors see nothing. To fix it, edit the table and change its status to **Published**.

### The table looks broken or unstyled

- Clear your browser cache and any WordPress caching plugins
- Check for CSS conflicts with your theme — ProductBay uses scoped CSS, but aggressive theme styles could interfere
- Make sure you're running a supported WordPress version

## Support

### Where can I get help?

- Use the support forum on the [WordPress.org plugin page](https://wordpress.org/support/plugin/productbay/)
- Report bugs via the [GitHub Issue Tracker](https://github.com/wpanchorbay/productbay/issues)
- We aim to respond within 2 business days
