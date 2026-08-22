# Display Customization

ProductBay gives you granular control over the visual appearance of each table. Every table has its own scoped CSS, so styles never leak between tables.

## Header Styling

| Property | Description | Default |
|----------|-------------|---------|
| **Background Color** | Header row background | `#f0f0f1` |
| **Text Color** | Header text color | `#333333` |
| **Font Weight** | Header font weight (Normal, Bold, Extra Bold) | Bold |
| **Text Transform** | Header text capitalization (Uppercase, Lowercase, Capitalize, Normal) | Uppercase |

## Body Styling

| Property | Description | Default |
|----------|-------------|---------|
| **Background Color** | Body rows background | `#ffffff` |
| **Text Color** | Body text color | `#444444` |

### Alternating Rows (Zebra Striping)

Enable the **Alternate Rows** toggle to apply different background colors to even and odd rows. This improves readability for tables with many rows.

When toggled **on**, two additional color pickers appear:

| Property | Description | Default |
|----------|-------------|---------|
| **Alternate Background** | Background for even rows | `#f9f9f9` |
| **Alternate Text** | Text color for even rows | `#444444` |

When toggled **off**, all rows use the same Body background and text colors.

## Button Styling

The add-to-cart button can be fully customized with both default and hover states:

| Property | Description | Default |
|----------|-------------|---------|
| **Background Color** | Button background | `#2271b1` |
| **Text Color** | Button text | `#ffffff` |
| **Hover Background** | Background on hover | `#135e96` |
| **Hover Text Color** | Text color on hover | `#ffffff` |

## Layout

| Property | Description | Default |
|----------|-------------|---------|
| **Border Style** | Table border style (None, Solid, Dashed) | Solid |
| **Border Color** | Table border color (disabled when border style is None) | `#e5e5e5` |
| **Border Radius** | Toggle on/off + numeric px value for corner rounding | On, `0px` |
| **Cell Padding** | Space inside each cell (Compact, Normal, Spacious) | Normal |

### Border Radius
The border radius has its own **enable toggle**. When off, the table has sharp corners regardless of the px value. When on, you can set a custom pixel value (0–24px).

### Cell Padding Options
- **Compact** — Tight spacing for dense tables
- **Normal** — Standard spacing (default)
- **Spacious** — Extra spacing for readability

## Layout on Phones <Badge type="tip" text="Since v1.3.4" />

A table with more than a few columns cannot fit a phone screen, so ProductBay reflows it. Pick the behaviour per table under **Display → Layout on phones**; it applies below **767px** and never changes the desktop or tablet layout.

| Mode | Description |
|------|-------------|
| **Stacked cards** *(default)* | Each row becomes a self-contained card. The table header is hidden and every value is labeled with its column heading instead. |
| **Horizontal scroll** | Keeps the classic table and lets the customer swipe it sideways. Best when column alignment matters, such as a price list meant to be compared row by row. |

### How a stacked card is arranged
1. **Product name** as the card heading.
2. **Labeled values** — one line per visible column, each prefixed with its column heading (this is why headings stay useful even when the header row is hidden).
3. **Add to cart controls** — variation dropdowns, the quantity stepper, and the button. They fill the width of the card and share a common height so they line up, and every one of them clears the 44px minimum tap target. Grouped products keep their dropdown and stepper side by side unless the screen is too narrow to read both.
4. **Add to bulk list** — a full-width toggle button that replaces the desktop checkbox, showing **Added** once the product is in the bulk list.

::: tip
Per-column [responsive visibility](/features/column-editor#responsive-visibility) still applies inside cards. A column set to "Hide on mobile" stays hidden, so you can trim a card down to just the essentials.
:::

::: info Existing tables
Stacked cards apply automatically to tables created before v1.3.4 — there is nothing to migrate. Switch a table to **Horizontal scroll** if you prefer the old behaviour.
:::

## Hover Effects

The **Row Hover Effect** has its own **enable toggle**. When on, rows are visually highlighted when the cursor passes over them.

| Property | Description | Default |
|----------|-------------|---------|
| **Row Hover** | Enable/disable row hover highlighting | Enabled |
| **Hover Background** | Row background on hover | `#f5f5f5` |
| **Hover Text Color** | Row text color on hover | *(inherit)* |

When the toggle is off, the hover color pickers are greyed out.

## Instance-Scoped CSS

Each table generates a unique CSS scope. This means:
- Multiple tables on the same page won't share or override each other's styles
- Table styles won't interfere with your theme's CSS
- Your theme's CSS won't break the table layout

## Live Preview

All design changes are shown in real-time in the preview panel during the [Creation Wizard](/features/creation-wizard). What you see in the preview is exactly what visitors will see on the frontend.
