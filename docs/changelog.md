# Changelog

All notable changes to ProductBay are documented on this page.

Looking for Pro version changes? See the **[Pro Changelog](./pro-changelog.md)**.

## 1.3.4

* **Feature:** Product tables are now mobile-responsive. On phones each row reflows into a labeled **stacked card** (default), or you can keep the classic **horizontal-scroll** table — selectable per table under **Display → Layout on phones**.
* **Improvement:** Stacked cards are the new default and apply to existing tables automatically — no table is left with cramped or cut-off columns on small screens.
* **Improvement:** Mobile cards now lead with the product name, followed by the add-to-cart controls and a full-width **Add to bulk list** button in place of the small checkbox, so every control is comfortable to tap.
* **Improvement:** Bulk selection is now called the **bulk list** across the storefront and the table builder — the toggle, the **View** button, and the floating panel share the same wording, and the per-table option is now **Bulk List Panel**.
* **Improvement:** Wide, many-column tables now get an automatic minimum width and an edge scroll hint so they scroll comfortably instead of squishing.
* **Fix:** The **category** and **product type** filters above a table now only offer values present in that table. They were previously built from every category and product type on the store, so a table scoped to one category still offered the whole catalog — and selecting any of those choices rendered an empty table.
* **Fix:** Tables with a `draft` or `pending` status are no longer labelled **Private** in the dashboard and the table builder. Private means published but restricted to capable logged-in users; draft means not published at all.
* **Dev:** Added the `productbay_bulk_list_text` and `productbay_bulk_list_added_text` filters so the bulk-list toggle labels can be customized (available in Pro).
* **Dev:** Added the `productbay_filter_options` and `productbay_filter_options_cache_ttl` filters to adjust or re-cache the resolved filter choices.

## 1.3.3

* **Fix:** The cart icon (header count / mini-cart) and the add-to-cart button confirmation now update instantly when adding to cart, without a page refresh, on both classic themes (cart fragments) and block themes (the Mini-Cart block).
* **Improvement:** The in-table "added" badge now stays in sync when items are removed or reduced from the cart outside the table (mini-cart, Cart block, or classic cart widget).

## 1.3.2

* **Feature:** Added core support and hooks for customizable Add to Cart button text and "Select Options" button text (available in Pro).

## 1.3.1

* **Compatibility:** Verified and tested with WordPress 7.0.

## 1.3.0

* **Feature:** Introduced native **Permalink Pages** for product tables via `productbay_table` Custom Post Type.
* **Feature:** Grouped products now default to **Inline Dropdown** mode for direct child product selection and add-to-cart.
* **Feature:** Introduced a comprehensive, file-based **Activity Log** system to track table management and system events.
* **Feature:** Decoupled cart functionality from AJAX to support native form submissions and improved compatibility.
* **Dev:** Optimized internal code architecture and registered `productbay_table` CPT with frontend support.

## 1.2.0

* **Feature:** Added new column types: Stock, Date, Taxonomy, and Rating.
* **Feature:** Introduced Pro integration for premium features: Custom Field, Combined, Price Range Filter, and Variable & Grouped Products.
* **Dev:** Unified Pro activation detection across Free plugin layers.

## 1.1.1

* **Improvement:** Block setup experience with "Create New Table" link in placeholders.
* **Improvement:** Removed misleading product counts from category multiselect filters.
* **Improvement:** Enhanced reliability of CSS injection into the Block Editor iframe.
* **Fix:** Isolated table event handlers to prevent cross-tab state interference (e.g. AJAX filter state).

## 1.1.0

* **Feature:** Native Gutenberg blocks for Product Table and Tabbed Product Tables with server-side rendering.
* **Improvement:** Filters bar repositioned above toolbar for better UX flow.
* **Improvement:** Admin menu (WooCommerce > Products) renamed from "All Tables" to "Product Tables" for clarity.
* **Improvement:** Added a "Manage" link to the plugin action links on the Plugins page for quicker access.
* **Improvement:** Hover highlight improvements across admin UI.
* **Improvement:** Shortcode display layout refined on the table management page.
* **Dev:** Restructured codebase for Pro extension architecture.
* **Dev:** Exposed UI components and settings globally for Pro add-on consumption.

## 1.0.0

*Initial Release*
