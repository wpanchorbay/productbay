# Changelog

All notable changes to ProductBay are documented on this page.

Looking for Pro version changes? See the **[Pro Changelog](./pro-changelog.md)**.

## 1.3.5

* **Security:** `TableRepository::delete_table()` did not verify that the given ID belonged to a `productbay_table`, while `get_table()` always had. A `DELETE` to `productbay/v1/tables/{id}` therefore force-deleted whatever post carried that ID — a page, a product, an order — with no trash step.
* **Security:** Saving a table did not verify that the given ID belonged to a `productbay_table` either. A crafted save request from an administrator-level account could turn another post — a page, a product — into a table, overwrite its title and status, and make it deletable through the table delete endpoint. `TableRepository::save_table()` now refuses an ID that is not a table.
* **Security:** The activity log export passed its `date` parameter straight into a `glob()` pattern and `ActivityLog::get_logs()` into a file path, so `date=../debug` read files outside the log directory. Both now go through a single validator that requires a real calendar date.
* **Security:** A table's saved column ID reached a CSS selector through `esc_attr()`, which does not escape braces or semicolons, so it could close its own rule and inject page-wide CSS. Now `sanitize_html_class()`.
* **Security:** The bulk add-to-cart endpoint — reachable without logging in — looped over an unbounded `items` array and trusted the browser's `variation_id` and attributes. It now caps the list (see `productbay_max_bulk_items`) and verifies the variation's parent.
* **Security:** `Request` parsed the JSON body in its constructor on `plugins_loaded` and merged it into `$_REQUEST` for *every* JSON request on the site, including other plugins' REST namespaces. It is now parsed lazily and kept on the instance.
* **Fix:** Tables no longer list products the shop hides. WooCommerce applies its `product_visibility` exclusion in `WC_Query::get_tax_query()`, which bails on anything that is not the main query — and every ProductBay render is a secondary `WP_Query`, so catalog-hidden products (and out-of-stock ones on stores with *Hide out of stock items* enabled) were showing. See `productbay_excluded_visibility_terms`.
* **Improvement:** Declared `custom_order_tables` (HPOS) and `cart_checkout_blocks` compatibility and added the `WC tested up to` header. WooCommerce treats an undeclared but WooCommerce-aware plugin as HPOS-incompatible, which disables the HPOS toggle for stores not yet migrated.
* **Improvement:** `productbay-frontend` no longer declares `wc-cart-fragments` as a dependency. WordPress resolves a script's loading strategy by recursing into its dependents, so our un-deferred handle was silently stripping the `defer` WooCommerce registers that script with; and a plugin deregistering it took our script down with it. It is enqueued instead, and our own script now asks for `defer`.
* **Performance:** `format_table()` ran an admin-only product count query on every frontend render; the render paths now opt out. The taxonomy column used `wp_get_post_terms()` (a fresh term query per row) instead of `get_the_terms()`, and the image column primed nothing. Measured: 34.3ms/21 queries → 26.1ms/19 queries on a 5,000-product catalogue, and 30 → 26 queries on a table with images.
* **Fix:** The `productbay_table` CPT no longer appears in site search results or the nav-menu picker (`exclude_from_search`, `show_in_nav_menus`).
* **Dev:** The table read/save/delete REST endpoints return `WP_Error` responses instead of reporting failure as HTTP 200.
* **Fix:** ProductBay's stylesheet and script are now cacheable by the browser. Four enqueue paths used the current timestamp as the asset version, so every visitor re-downloaded the storefront CSS and JS on every page view, and every admin screen re-downloaded the admin bundle. Because the version sits in the URL it is a cache *key*, not a revalidation token — a changed one means the browser cannot even send a conditional request and must re-fetch the whole file, so `time()` was strictly worse than shipping no version at all. Assets are now versioned by the plugin version, and the admin bundle by the content hash webpack already emits into `admin.asset.php`.
* **Fix:** The **category** and **product type** dropdowns above a table no longer keep offering a choice after the products behind it have moved. 1.3.4 cached the resolved list for 12 hours and expired it on time alone, so a category could linger in the dropdown for up to half a day after the last product left it. The list is now rebuilt whenever a product, a product's categories, or a table is edited.
* **Fix:** Searching or filtering a table down to a single page no longer leaves the previous page links behind. The server correctly renders no pager when the result fits on one page, but the storefront script treated that empty response as "nothing to do" and kept the old bar — so the pager still offered the unfiltered page count, and following one of those links rendered "No products found" for a search that had in fact matched.
* **Fix:** The **Add to Cart** button on a variable product no longer stays stuck reading "Adding..." after a successful add. The item really was added, but the button's label was only restored by the badge re-render, which for variable products updates the variation badges and never touches the button.
* **Fix:** Typing in a table's search box and then changing or clearing the text quickly could leave the table showing results for the old text. A slow answer for the earlier search could arrive after the newer one and overwrite it. Only the latest search or filter request now updates the table.
* **Fix:** Deleting the plugin with **Delete data on uninstall** enabled (see [Uninstallation](./guide/uninstallation.md)) now also removes the cached filter choices and the cache counter it left in the database.
* **Feature:** A **Clear cache** button under **Settings → Plugin Settings** rebuilds those filter choices immediately.
* **Dev:** `PRODUCTBAY_DEV_MODE` now does what `.env.example` always described. It was defined in the main plugin file and read nowhere; it now selects the asset-version strategy — file modification time in development, so an edited file is never stale while untouched files stay cached; the plugin version (or build content hash) in production. `filemtime()` is deliberately not used in production: it records when a file was written, not what is in it, so a backup restore, a migration plugin, or a staging-to-live push would expire every visitor's cache for no reason.
* **Dev:** Added a PHPUnit test suite. The project previously had no automated tests of any kind.

## 1.3.4

* **Feature:** Product tables are now mobile-responsive. On phones each row reflows into a labeled **stacked card** (default), or you can keep the classic **horizontal-scroll** table — selectable per table under **Display → Layout on phones**.
* **Improvement:** Stacked cards are the new default and apply to existing tables automatically — no table is left with cramped or cut-off columns on small screens.
* **Improvement:** Mobile cards now lead with the product name, followed by the add-to-cart controls and a full-width **Add to bulk list** button in place of the small checkbox, so every control is comfortable to tap.
* **Improvement:** Card controls now fill the width of the card on phones — the variation and grouped-product dropdowns, the quantity stepper, and the add-to-cart button share a single 44px height, and the stepper is now a `−` / field / `+` control sized for thumbs.
* **Improvement:** Grouped products keep their dropdown and quantity stepper on one row wherever there is room, stacking only on very narrow screens.
* **Improvement:** Every storefront control on phones now meets the 44px minimum touch target, and the taxonomy filter bar wraps instead of pushing the page sideways on narrow screens.
* **Improvement:** Bulk selection is now called the **bulk list** across the storefront and the table builder — the toggle, the **View** button, and the floating panel share the same wording, and the per-table option is now **Bulk List Panel**.
* **Improvement:** On phones the bulk actions now float in a rounded bar pinned to the bottom of the viewport as soon as a selection exists, keeping the bulk button, **View**, and **Clear** reachable however far you scroll — and the bar hides again once the list is empty.
* **Improvement:** The bulk list popup opens above that bar, fits within the screen, and scrolls internally instead of running off the edge, with a tinted header matching its footer.
* **Improvement:** Wide, many-column tables now get an automatic minimum width and an edge scroll hint so they scroll comfortably instead of squishing.
* **Fix:** The **category** and **product type** filters above a table now only offer values present in that table. They were previously built from every category and product type on the store, so a table scoped to one category still offered the whole catalog — and selecting any of those choices rendered an empty table.
* **Fix:** Tables with a `draft` or `pending` status are no longer labelled **Private** in the dashboard, the table builder, or the admin-only notice rendered in place of an unpublished table. Private means published but restricted to capable logged-in users; draft means not published at all — the notice now names the actual status.
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
