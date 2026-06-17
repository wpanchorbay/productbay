=== ProductBay – High-Performance Product Table for WooCommerce ===
Contributors: wpanchorbay, forhadkhan, sankarsan, arifac
Tags: product table for woocommerce, woocommerce product table, woocommerce product list, product table, product list
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build fast and efficient product tables for WooCommerce with AJAX search, bulk add-to-cart, and a  creation wizard with live-preview.

== Description ==

WooCommerce's default grid layout is great for browsing, but it isn't always the right fit for every store. Wholesale shops, large catalogs, B2B order forms, price lists, quick-order tables, and restaurant menus all need something different, a clean, fast, scannable table where customers can compare products, select options, and add to cart in seconds.

**ProductBay** transforms how your WooCommerce products are displayed. Build beautiful, responsive product tables using a guided wizard, no coding required, and embed them anywhere on your site using the native Gutenberg block or a simple shortcode.

-------
[Home](https://wpanchorbay.com/plugins/productbay/) | [Documentation](https://docs.wpanchorbay.com/productbay/)
-------

[youtube https://www.youtube.com/watch?v=gFp_4gpe-lY]

> **ProductBay requires WooCommerce to be installed and active.**

= Perfect For =

* Wholesale & B2B stores
* Large product catalogs
* Quick-order & bulk-order forms
* Price lists & product directories
* Restaurant & food menus
* Digital product libraries 
* Any WooCommerce store who wants easy and efficient product listing

---

= Why ProductBay? =

Unlike traditional table plugins, ProductBay is built on a **modern hybrid architecture**:

* **Admin Panel:** A fully responsive Single Page Application (SPA) built with React 18, TypeScript, and Tailwind CSS, so the table builder feels fast, fluid, and intuitive.
* **Frontend Display:** A lightweight, SEO-friendly rendering engine optimized for Core Web Vitals, so your customers get speed without bloat.
* **Multiple Display Methods:** Add product tables in any page through shortcode or Gutenberg block which allows sigle table, multiple tables and tab options.
* **Permalink:** View product tables in a dedicated page with unique URL.
* **Activity Log:** Track system events and table management actions with a file-based logging system.
* **No External CDN:** No external CDN, no remote scripts, no data ever leaves your server. All assets are bundled locally inside the plugin.

---

= Guided Table Creation Wizard =

Creating a product table has never been easier. ProductBay walks you through a focused **5-step wizard**:

1. **Setup**: Name your table and choose a product source
2. **Columns**: Pick and arrange which data columns to display
3. **Display**: Customize colors, typography, borders, and spacing
4. **Options**: Configure filters, pagination, and sorting defaults
5. **Finish**: Copy your `shortcode` and publish 

= Live Preview =
A **real-time live preview** updates as you make changes. What you see in the wizard is exactly what your customers will see on the front end.

---

= Smart Product Sources =

Control exactly which products appear in each table:

* **By Category**: Show all products from one or more categories
* **By Sale Status** : Display currently on-sale products
* **By Specific IDs**: Handpick individual products
* **All Products**: Pull your entire catalog into the table

---

= Advanced Column Editor =

Full control over what data appears in your table and how it's arranged:

* **Drag-and-Drop Reordering**: Change column order visually with an intuitive interface
* **Column Types**: Product Image, Name, Price, SKU, Description, Add to Cart, Bulk Selection and more
* **Responsive Visibility**: Set custom width for each column in pixel (px), percentage (%) or auto. Configure per-column show/hide rules for desktop/laptop, tablet, and mobile independently
* **Tablet & Phone Columns are optional**: if left empty, the plugin falls back to your desktop column configuration automatically

---

= Seamless WooCommerce Integration =

ProductBay is built for WooCommerce from the ground up:

* **Simple Products**: Standard add-to-cart button
* **Variable Products**: Inline attribute selectors (for each variable) right inside the table row. 
* **Grouped Products**: Inline individual and all product selectors for group items.
* **External / Affiliate Products**: Displays the external buy button correctly
* **AJAX Add-to-Cart**: Products are added without any page reload
* **Bulk Add-to-Cart**: Customers can select multiple products, including variable and grouped products and add them all to the cart at once with a single click
* **Selected Items Panel**: A floating popup showing selected products with individual quantities, pricing, and remove controls

---

= Search & Filter =

Help customers find instantly what they're looking for, without page reloads:

* **Instant AJAX Search**: Live search that filters the product list as users type
* **Category Filter**: Native dropdown to filter by product category
* **Product Type Filter**: Dropdown to filter by Simple, Variable, Grouped, or External product type
* **AJAX Pagination**: Page through results without full page reloads

---

= Image Lightbox =

Product images open in a popup/modal with fullscreen toggling and close controls, built using native resources for maximum performance, with no bloated third-party library required.

---

= Deep Design Customization =

Every table is independently styled. Multiple tables on the same page will never conflict with each other:

* **Colors**: Background, text, borders, alternate rows (zebra striping) and hover states
* **Typography**: Font size and font weight per element
* **Borders**: Border style, width, and radius
* **Spacing**: Cell padding per table

---

= Table Management Dashboard =

A centralized admin dashboard to manage all your tables at a glance:

* **Search Tables**: Find any table quickly by name
* **Filter by Status**: Published or Private
* **Filter by Product Source**: Category, Sale, IDs, or All
* **Bulk Status Update**: Update status of tables at once to private or published
* **Bulk Delete**: Clean up multiple tables at once
* **Date**: See created, modified and published date for each table
* **Activity Log**: Monitor table changes and system events for better oversight and troubleshooting

---

= Developer-Friendly =

ProductBay exposes **30+ action hooks and filters** across all plugin layers, Core, Data, API, Frontend, and Admin, so developers can extend or modify behavior without ever touching plugin files:

* Hook into query arguments, cell output, table styles, and more
* A dedicated **Hooks & Filters reference page** is included directly in the plugin's admin area
* All frontend assets are only loaded on pages where a table shortcode is present

---

= Translation Ready =

**100%** of all user-facing strings are wrapped in WordPress localization functions. ProductBay is fully ready to be translated into any language.

---

= ProductBay Pro =

Get the Pro add-on that will extend ProductBay with advanced capabilities:

* **Advanced Variable Product Layouts**: Popup Modal, Nested Rows, or Separate Rows display modes.
* **Advanced Group Product Layouts**: Enhanced display options for grouped item selection.
* **Price Range Filter**: Interactive slider and input-based price filtering.
* **Pro Columns**: Advanced Custom Fields (ACF) integration and Combined Columns using template tags.
* **Custom "Add to Cart" and "Select Options" Button Text**: Override the default button text globally or per-table.
* **Lazy Loading**: Improved performance with **infinite scroll** or **Load More** button instead of pagination.
* **Import / Export**: Backup, migrate, and share table configurations as JSON files.
* **License Management**: Automated updates and professional support.

Coming Soon - 
* **Quick View Modal**: AJAX-loaded product detail popup.
* **Custom CSS Editor**: Per-table scoped CSS editor in the admin.
* **Advanced Filters**: Sidebar/drawer layouts, attribute filters, and active filter chips.
* **Premium Templates**: One-click professional style presets.
* **Analytics**: Table impressions, click tracking, and conversions dashboard.

---

== Installation ==

= Option A: Install from WordPress.org (Recommended) =

1. In your WordPress admin, go to **Plugins → Add New**.
2. Search for **ProductBay**.
3. Click **Install Now**, then **Activate**.
4. Ensure WooCommerce is installed and active.
5. Navigate to **ProductBay** in the admin menu to create your first table.

= Option B: Manual Upload =

1. Download the plugin `.zip` file.
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Upload the `.zip` file and click **Install Now**, then **Activate**.
4. Navigate to **ProductBay** in the WordPress admin menu.

= Using Your Table =

After creating a table, you can display it using the native **Product Table block** for a live visual preview directly inside the editor, or copy its shortcode (e.g., `[productbay id="1"]`) to paste anywhere on your site.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. ProductBay is a WooCommerce extension and will not function without WooCommerce installed and active.

= Which WooCommerce product types are supported? =

ProductBay supports all four core WooCommerce product types: **Simple**, **Variable**, **Grouped**, and **External/Affiliate**.

= Do you support Gutenberg blocks? =

Yes! ProductBay includes two native Gutenberg blocks that provide a live preview directly within the WordPress editor:
1. **Product Table:** Easily insert a single product table.
2. **Tabbed Product Tables:** Display multiple product tables organized cleanly within an interactive tabbed interface.

= How do I display a product table on a page? =

You can use the native **Product Table block** to insert and preview your table directly in the WordPress block editor. Alternatively, you can copy its shortcode, for example `[productbay id="1"]`, and paste it into any page, post, or widget.

= Can I display multiple tables on the same page? =

Yes. Each table uses its own scoped CSS, so multiple tables on the same page will never conflict with each other's styling.

= Can customers add multiple products to the cart at once? =

Yes. ProductBay includes a **Bulk Add-to-Cart** feature. Customers can select multiple products using checkboxes and add them all to the cart in a single click. A "Selected Items" panel shows a live summary of selections, and a "Clear All" button resets them instantly.

= How do variable products work inside the table? =

Variable products display inline attribute selectors (size, color, etc.) directly in the table row, no need to visit the product page to choose a variation. Variation badges then visually confirm which options were added to the cart.

= Can I configure different columns for desktop, tablet, and mobile? =

Yes. The column editor allows you to configure independent show/hide rules for each column per device size (desktop, tablet, and phone). Tablet and phone columns are completely optional, if left empty, the plugin automatically uses your desktop column configuration for smaller devices.

= Can customers search and filter products in the table? =

Yes. Every table supports instant AJAX search, a price range filter (slider, inputs, or both), a product category dropdown filter, and a product type dropdown filter, all without any page reloads.

= Does the plugin slow down my site? =

No. ProductBay is built with performance as a core priority. Plugin assets are only loaded on pages where a table shortcode is present. Product queries are cached for 30 minutes using a stale-while-revalidate strategy to minimize database load. The frontend rendering engine is intentionally lightweight and optimized for Core Web Vitals.

= Does the plugin call any external services or load remote scripts? =

No. All JavaScript, CSS, and other assets are bundled locally inside the plugin. No data is sent to any external server, and no remote CDN scripts are ever loaded.

= How many tables can I create? =

There is no limit. You can create as many product tables as your store requires.

= Is ProductBay compatible with page builders like Elementor, Divi, or WPBakery? =

Yes. ProductBay uses a standard WordPress shortcode (`[productbay id="X"]`), which is compatible with any page builder that supports shortcodes.

= Is there a Pro version available? =

Yes! ProductBay Pro is now available. It extends the free version with advanced variable layouts, price range filters, custom field support, import/export, and more. You can purchase it at [wpanchorbay.com](https://wpanchorbay.com/plugins/productbay).

= Is ProductBay translation ready? =

Yes. All user-facing strings use WordPress localization functions and the plugin is 100% translation ready.

= Where can I get support? =

Use the support forum on this plugin's WordPress.org page. We aim to respond within 2 business days. You can also reach us directly at [support@wpanchorbay.com](mailto:support@wpanchorbay.com).

== Screenshots ==

1. Create/View/Modify product tables with live preview and smooth experience.
2. The ProductBay dashboard — manage all your tables with status indicators and shortcodes at a glance.
3. Show the product in any page or post using the shortcode througout your website. View products efficiently and filter by catrgory and type. 
4. Add products to cart in bulk using the checkbox and add to cart button. View selected items in the floating panel and remove them if needed.
5. Step 1 of the creation wizard — name your table and choose your product source.
6. Step 2 — the column editor with drag-and-drop reordering and per-device responsive visibility controls.
7. Step 3 — the design panel with live preview updating in real time as you customize colors, typography, and spacing.
8. Step 4 — the options panel for configuring filters, pagination, and sorting.
9. See full-screen live preview of the table as you design it.
10. Step 4 — the table is created and ready to be used.


== Changelog ==

= 1.3.2 =

* Feature: Added core support and hooks for customizable Add to Cart button text and "Select Options" button text (available in Pro).
* Fix: Resolved added-to-cart checkmarks and quantities persistence issues in bulk-add scenarios by syncing with live WooCommerce cart fragments.

= 1.3.1 =

* Compatibility: Verified and tested with WordPress 7.0.

= 1.3.0 =

* Feature: Introduced native **Permalink Pages** for product tables via `productbay_table` Custom Post Type.
* Feature: Grouped products now default to **Inline Dropdown** mode for direct child product selection and add-to-cart.
* Feature: Introduced a comprehensive, file-based **Activity Log** system to track table management and system events.
* Feature: Decoupled cart functionality from AJAX to support native form submissions and improved compatibility.
* Dev: Optimized internal code architecture and registered `productbay_table` CPT with frontend support.

= 1.2.0 =

* Feature: Added new column types: Stock, Date, Taxonomy, and Rating.
* Feature: Introduced Pro Shells for premium features: Custom Field, Combined, Price Range Filter, and Variable & Grouped Products.
* Dev: Unified Pro activation detection across Free plugin layers.

= 1.1.1 =

* Improvement: Block setup experience with "Create New Table" link in placeholders.
* Improvement: Removed misleading product counts from category multiselect filters.
* Improvement: Enhanced reliability of CSS injection into the Block Editor iframe.
* Fix: Isolated table event handlers to prevent cross-tab state interference (e.g. AJAX filter state).

= 1.1.0 =

* Feature: Native Gutenberg blocks for Product Table and Tabbed Product Tables with server-side rendering.
* Improvement: Filters bar repositioned above toolbar for better UX flow.
* Improvement: Admin menu (WooCommerce > Products) renamed from "All Tables" to "Product Tables" for clarity.
* Improvement: Added a "Manage" link to the plugin action links on the Plugins page for quicker access.
* Improvement: Hover highlight improvements across admin UI.
* Improvement: Shortcode display layout refined on the table management page.
* Dev: Restructured codebase for Pro extension architecture.
* Dev: Exposed UI components and settings globally for Pro add-on consumption.

= 1.0.0 =

* Initial release of ProductBay.

== Upgrade Notice ==

= 1.3.0 =
Major functional update: Introduces native permalink pages for tables, decouples cart actions from AJAX, and adds an Activity Log system for better management.

= 1.2.0 =
Security and feature update: Adds new column types (Stock, Date, Taxonomy, Rating) and implements secure Pro-exclusive feature blocking.

= 1.1.1 =
Fixes critical interaction issues with Gutenberg blocks and improves multi-table state isolation.

= 1.1.0 =
Adds native Gutenberg blocks, enhances the admin UI and restructures the codebase for Pro extension architecture.

= 1.0.0 =
Initial release, no upgrade steps required.