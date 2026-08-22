# Shortcodes

ProductBay uses WordPress shortcodes to embed product tables on any page, post, or widget area.

## Basic Usage

After creating a table, you'll receive a shortcode:

```
[productbay id="1"]
```

Paste this shortcode into any page or post to display the table.

## Parameters

| Parameter | Required | Description             | Example               |
|-----------|----------|-------------------------|-----------------------|
|    `id`   |    Yes   | The table ID to display | `[productbay id="1"]` |

::: info  
Currently, the shortcode accepts a single `id` parameter.
:::

## How to Embed

### Block Editor (Gutenberg)
1. Add a new **Shortcode** block
2. Paste the shortcode: `[productbay id="1"]`
3. Publish or update the page

### Classic Editor
1. Paste the shortcode directly into the editor content
2. Publish or update the page

### Widgets
1. Go to **Appearance → Widgets**
2. Add a **Text** or **Custom HTML** widget
3. Paste the shortcode
4. Save the widget

### Page Builders
Most page builders (Elementor, Beaver Builder, Divi, etc.) support WordPress shortcodes. Look for a "Shortcode" element or module and paste the code.

## Multiple Tables on One Page

You can embed multiple tables on the same page by using multiple shortcodes:

```html
<h2>Sale Products</h2>
[productbay id="1"]

<h2>New Arrivals</h2>
[productbay id="2"]
```

Each table is rendered with **instance-scoped CSS**, meaning their styles won't conflict with each other.

## Finding the Shortcode

There are two ways to find a table's shortcode:

1. **Table Dashboard** — The shortcode is displayed in the "Shortcode" column. Click it to copy.
2. **After Creating/Editing** — The shortcode is shown on the Finish step of the wizard.

## Troubleshooting

### Table not showing?
- Verify the **table ID** is correct
- Check that the table status is **Published** — draft, pending, and private tables do not render for non-admin users
- Make sure **WooCommerce is active**

### Empty table?
- Verify your product source has matching products
- Check that products are **published** in WooCommerce
- Review any query modifiers (stock status filters, price ranges, excluded IDs)

::: tip Admin Notice
If a table is not published — draft, pending, or private — administrators will see a yellow notice in its place. Regular visitors will see nothing.
:::
