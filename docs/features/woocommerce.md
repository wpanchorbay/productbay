# WooCommerce Integration

ProductBay is built as a WooCommerce extension, providing deep integration with WooCommerce's product system, cart, and checkout.

## Supported Product Types

| Product Type | Support | Notes |
|-------------|---------|-------|
| **Simple** | Full | Standard add-to-cart with quantity |
| **Variable** | Full | Inline attribute selectors (Free) or Popup/Nested/Separate rows (Pro) |
| **Grouped** | Full | Inline dropdown (Free) or Popup/Nested/Separate rows (Pro) |
| **External / Affiliate** | Full | "Buy Now" button linking to external URL |

## Add to Cart

### AJAX Add to Cart
When enabled, products are added to the cart **without a page reload**. An inline confirmation (a checkmark with the quantity in cart) appears on the button, and your theme's **cart icon / mini-cart updates instantly** — no refresh required.

This works on both classic themes (via WooCommerce cart fragments) and block themes (via the Mini-Cart block), and provides a seamless shopping experience, especially for tables with many products.

::: tip Live cart sync <Badge type="tip" text="Since v1.3.3" />
The button's "added" indicator stays in sync with the live cart. If a customer **removes or reduces** an item elsewhere — the mini-cart, the Cart block, or the classic cart widget — the matching table button updates automatically.
:::

### Quantity Selector
Enable per-row quantity inputs so customers can specify how many units to add. The input respects WooCommerce's min/max and step settings for each product.

### Bulk Add to Cart
ProductBay supports adding multiple products at once. The products a customer has picked are called the **bulk list**:

1. Enable the checkbox column by turning on cart features
2. Customers add products to the bulk list using the checkboxes
3. Click the "Add Selected to Cart" button
4. Everything in the bulk list is added in a single AJAX request

::: tip On phones <Badge type="tip" text="Since v1.3.4" />
When a table uses the [stacked-card mobile layout](/features/display-customization#layout-on-phones), the checkbox is replaced by a full-width **Add to bulk list** button at the bottom of each card, which reads **Added** once the product is in the list. Pro can rename both labels — see [Cart Customization](/features/plugin-settings#cart-customization).
:::

::: tip
Bulk add-to-cart works with simple products. Variable products need their attributes selected individually before they can be added.
:::

### Variation Badges
When enabled, small badges appear on each row indicating which specific variations have been added to the cart. This helps customers keep track of their selections without opening the cart.

Toggle **Variation Badges** in the **Options** step under **Cart Functionality**.

### Show Clear All Button
When bulk selection is active, a **Clear All** button can be shown to instantly deselect all currently selected products. This saves customers from having to individually uncheck each product.

Toggle **Show Clear All Button** in the **Options** step under **Cart Functionality**.

### Bulk List Panel
A floating panel that displays everything in the bulk list with individual quantities. This gives customers a running summary of what they're about to add to cart before clicking the bulk action button. Open it with the **View (n)** button in the table toolbar.

Toggle **Bulk List Panel** in the **Options** step under **Cart Functionality**.

## Variable Products

By default, ProductBay displays variable products using inline attribute selectors:

- **Dropdowns** appear for each attribute (e.g., Size, Color)
- Selecting attributes updates the **price** in real-time
- The **Add to Cart** button activates once all required attributes are selected
- **Out of stock** variations are automatically disabled

### Advanced Variable Modes <ProBadge /> <Badge type="tip" text="Since v1.2.0" />

ProductBay Pro unlocks **3 additional display modes** for variable products, allowing for a vastly superior shopping experience:

1. **Popup Modal:** Opens a full-featured modal where customers can see all variations, select quantities, and add multiple variations at once using bulk selection.
2. **Nested Rows:** Expandable child rows that display all variations directly below the parent row.
3. **Separate Rows:** Each variation is split into its own independent row in the main table.

[Read the Advanced Modes guide &rarr;](/features/variable-grouped-modes)

## Grouped Products

Grouped products are displayed using an **Inline Dropdown** by default. <Badge type="tip" text="Since v1.3.0" /> Customers can select a child product from the dropdown, set a quantity, and add to cart — all without leaving the table.

### Advanced Grouped Modes <ProBadge /> <Badge type="tip" text="Since v1.2.0" />

ProductBay Pro allows you to display grouped products directly within the table using 3 additional modes:

1. **Popup Modal:** A full modal listing all child products for easy selection.
2. **Nested Rows:** Expandable child rows containing grouped items underneath the parent.
3. **Separate Rows:** Treats each child product as a separate standalone table row.

[Read the Advanced Modes guide &rarr;](/features/variable-grouped-modes)

## Show Options Count

When enabled, a subtle **"X options available"** subtitle appears below the product name for Variable and Grouped products. This gives customers a quick indication of how many choices are available without expanding anything.

Toggle **Show Options Count** in the **Options** step under **Variable & Grouped Products**.

## Price Display

Prices are rendered using WooCommerce's native formatting:
- **Currency symbol** and position (before/after)
- **Decimal separator** and thousand separator
- **Sale prices** shown with strikethrough on the regular price
- **Variable price ranges** (e.g., "$10.00 – $25.00") when no variation is selected

## Cart URL
After adding a product to cart, a "View Cart" link appears, directing customers to the WooCommerce cart page.
