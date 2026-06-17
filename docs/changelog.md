# Changelog

All notable changes to ProductBay are documented on this page.

Looking for Pro version changes? See the **[Pro Changelog](./pro-changelog.md)**.

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
