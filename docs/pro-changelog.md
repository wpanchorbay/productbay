# Pro Changelog

All notable changes to ProductBay Pro are documented on this page.

Looking for core changes? See the [Core Changelog](./changelog.md).

## 1.0.3

* **Security:** The license key is no longer sent over a connection whose SSL certificate was not verified. A host behind a TLS-intercepting proxy must now supply its CA certificate through the `http_request_args` filter.
* **Security:** The variation popup endpoint for logged-out visitors now only serves published, visible products from published tables, and no longer reveals unpublished products.
* **Fix:** A license the server rejects with an unfamiliar error (for example a revoked license) no longer stays marked active.
* **Fix:** Import now counts tables that could not be saved as failures instead of imports.
* **Improvement:** Requires ProductBay (free) **1.3.5** or higher.
* **Improvement:** Declares compatibility with WooCommerce High-Performance Order Storage (HPOS) and the Cart and Checkout blocks.

## 1.0.2

* **Feature:** Custom **Add to bulk list** and **Added** text — override the labels of the mobile bulk-select toggle globally or per table.
* **Improvement:** The **Select Options** popup is easier to read on phones. Price, quantity and the bulk checkbox now share one line instead of stacking, so a multi-variation popup fits on screen without scrolling.
* **Improvement:** The bulk-select checkbox in the **Select Options** popup now carries an accessible name, so screen readers announce which product it selects.
* **Improvement:** Variation cells are labelled in the stacked mobile layout.
* **Fix:** A license check that fails to reach the server no longer marks a valid license invalid.

## 1.0.1

* **Feature:** Added custom Add to Cart button text and "Select Options" button text customization per-table and globally.

## 1.0.0

* Initial release of ProductBay Pro.
* **Feature:** Price Range Filter with slider, inputs, or combined mode.
* **Feature:** Variable & Grouped Product display modes: Inline, Popup Modal, Nested Rows, Separate Rows.
* **Feature:** Custom Field Column with meta-key discovery, ACF integration.
* **Feature:** Combined Column using template tags.
* **Feature:** Import / Export for table configurations via JSON.
* **Feature:** License activation, validation, and auto-updates mechanism.
