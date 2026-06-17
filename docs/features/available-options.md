---
title: Available Options
description: Reference Documentation for Table Configuration Options in ProductBay
---

# Available Options

The **Options Panel** provides comprehensive control over the functionality, display, and user experience of your product tables. Below is a detailed breakdown of all available options categorized by their respective sections.

## Table Controls

Configure core table functionality and user controls.

* **Enable Search Bar**
  * **Functionality**: Adds a search input field to the table.
  * **Option Type**: Toggle
  * **Description**: Allows users to quickly search through products directly within the table.

* **Enable Pagination**
  * **Functionality**: Activates pagination for the table.
  * **Option Type**: Toggle
  * **Description**: Breaks down large product lists into smaller, manageable pages.

* **Products Per Page**
  * **Functionality**: Determines the number of items per page limit.
  * **Option Type**: Number Input
  * **Description**: How many products to display on each page (min: 1, max: 500). Only relevant when pagination or load more styles are active.

* **Pagination Style**
  * **Functionality**: Defines how extra products are loaded or navigated.
  * **Option Type**: Select Dropdown
  * **Sub-options**:
    * `Standard (Numbers)`: Classic numbered pagination links at the bottom.
    * `Load More Button`: Displays a button to append more products to the current list. <ProBadge />
    * `Infinite Scroll`: Automatically loads more products as the user scrolls down the page. <ProBadge />

* **Enable Image Lightbox**
  * **Functionality**: Adds an interactive image viewer.
  * **Option Type**: Toggle
  * **Description**: Shows the full-size product image in a popup overlay when a user clicks on the product thumbnail.

---

## Taxonomy & Type Filters

Configure frontend dropdown filters to help users find specific products.

* **Enable Categories Filter**
  * **Functionality**: Adds a category filter dropdown.
  * **Option Type**: Toggle
  * **Description**: Allows users to filter table products by their WooCommerce product categories.

* **Enable Product Type Filter**
  * **Functionality**: Adds a product type filter dropdown.
  * **Option Type**: Toggle
  * **Description**: Allows users to filter the table by basic product types (e.g., Simple, Variable, Grouped).

* **Price Range Filter** <ProBadge />
  * **Functionality**: Adds a slider for price-based filtering.
  * **Option Type**: Slider / Range Input
  * **Description**: Allows users to dynamically filter products by a specific price range. 

---

## Cart Functionality

Configure Add to Cart behavior and selection tracking.

* **AJAX Add to Cart**
  * **Functionality**: Toggles AJAX-powered add to cart requests.
  * **Option Type**: Toggle
  * **Description**: When enabled, adds products to the cart inline without reloading the page. When disabled, the cart button functions as a link that redirect users to the single product page.

* **Show Quantity Selector**
  * **Functionality**: Enables quantity adjustment for products.
  * **Option Type**: Toggle
  * **Description**: Displays a quantity input slider/field next to the Add to Cart button for simple products.

* **Show Clear All Button**
  * **Functionality**: Adds a bulk clear control.
  * **Option Type**: Toggle
  * **Description**: Displays a standalone button to instantly clear or uncheck all selected products currently tracked in the active list.

* **Selected Items View Panel**
  * **Functionality**: Enables the floating selected items overview.
  * **Option Type**: Toggle
  * **Description**: Shows a floating, collapsible panel that displays all the items a user has selected or added to cart, along with their individual quantities.

* **Custom Add to Cart Text** <ProBadge />
  * **Functionality**: Overrides the default "Add to Cart" button text for the specific table.
  * **Option Type**: Text Input
  * **Description**: Allows you to set a custom call-to-action (e.g., "Buy Now", "Select Options") specifically for the current table, overriding the global setting.

* **Custom "Select Options" Button Text** <ProBadge />
  * **Functionality**: Overrides the default text for "Select Options" / "View Products" buttons.
  * **Option Type**: Text Input
  * **Description**: Allows you to set a custom text for buttons that open popups or nested rows, separating navigation action from actual purchase buttons.

---

## Variable & Grouped Products

Advanced configuration for complex WooCommerce product types. Some display modes and variations require the PRO plugin.

* **Grouped Products**
  * **Functionality**: Defines the display behavior when viewing products containing multiple child simple products.
  * **Option Type**: Select Dropdown
  * **Sub-options**:
    * `Inline Dropdown`: Shows a dropdown allowing the selection of child nested products directly in the row.
    * `Popup Modal`: Opens a modal displaying all child products within the group. <ProBadge /> 
    * `Nested Rows`: Renders child products directly in expandable rows nested under the parent product. <ProBadge /> 
    * `Separate Rows`: Displays the child products in the main table layout, separated into standard individual rows. <ProBadge /> 

* **Variable Products**
  * **Functionality**: Defines the display behavior for products with attribute variations. 
  * **Option Type**: Select Dropdown
  * **Sub-options**:
    * `Inline Dropdown`: Supported natively. Allows variations selection through inline dropdown elements on the parent row.
    * `Popup Modal`: Opens a targeted popup to easily select exact variations. <ProBadge /> 
    * `Nested Rows`: Variations are displayed logically beneath the main product row as sub-rows. <ProBadge /> 
    * `Separate Rows`: Every variation gets injected as a completely separate row entity. <ProBadge /> 

* **Expand Nested Rows**
  * **Functionality**: Determines initial state for nested products. 
  * **Option Type**: Toggle
  * **Dependancy**: Only available when `Nested Rows` is selected as a Group or Variable display mode.
  * **Description**: Show nested variation/child rows expanded by default instead of having them initially collapsed. AJAX Add to Cart


* **Show Options Count**
  * **Functionality**: Displays the total count of variation options.
  * **Option Type**: Toggle
  * **Description**: Renders an "X options available" subtitle directly below the main product name to clarify variant availability.

* **Variation Badges**
  * **Functionality**: Highlighting tracked cart configurations.
  * **Option Type**: Toggle
  * **Description**: Shows visual badges indicating exactly which variations have been added to the active cart flow.
