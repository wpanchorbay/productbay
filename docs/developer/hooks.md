# Hooks & Filters

ProductBay provides an extensive set of WordPress action hooks and filters, enabling developers to extend, customize, and integrate with the plugin without modifying its core source files.

::: info Since v1.0.0
All hooks listed on this page were introduced in ProductBay **1.0.0**, unless the hook notes a later version.
:::

## Hook Naming Convention

All hooks are prefixed with `productbay_` to avoid collisions:
- **Actions** — Use `do_action( 'productbay_*' )`
- **Filters** — Use `apply_filters( 'productbay_*' )`

---

## Core

Hooks fired during the plugin lifecycle.

### `productbay_loaded`

Fires after all free plugin components are initialized. This is the primary hook for add-on plugins to bootstrap themselves.

| Type | Action |
|------|--------|
| **Parameters** | `$plugin` *(Plugin)* — The main plugin instance |
| **File** | `app/Core/Plugin.php` |

```php
add_action( 'productbay_loaded', function( $plugin ) {
    // Your add-on logic here
} );
```

### `productbay_admin_init`

Fires after the admin component is set up (inside `is_admin()` context only).

| Type | Action |
|------|--------|
| **Parameters** | `$admin` *(Admin)* — The admin instance |
| **File** | `app/Core/Plugin.php` |

---

## Data Layer

Hooks around table CRUD operations in `TableRepository`.

### `productbay_before_save_table`

Filters table data before it is persisted.

| Type | Filter |
|------|--------|
| **Parameters** | `$data` *(array)*, `$id` *(int)* |
| **Returns** | `array` — Modified table data |
| **File** | `app/Data/TableRepository.php` |

```php
add_filter( 'productbay_before_save_table', function( $data, $id ) {
    // Validate or modify data before save
    return $data;
}, 10, 2 );
```

### `productbay_after_save_table`

Fires after a table is successfully saved.

| Type | Action |
|------|--------|
| **Parameters** | `$post_id` *(int)*, `$data` *(array)* |
| **File** | `app/Data/TableRepository.php` |

### `productbay_after_delete_table`

Fires after a table is deleted.

| Type | Action |
|------|--------|
| **Parameters** | `$id` *(int)* — The deleted post ID |
| **File** | `app/Data/TableRepository.php` |

### `productbay_table_data`

Filters the formatted table data returned by the repository.

| Type | Filter |
|------|--------|
| **Parameters** | `$table_data` *(array)*, `$post` *(WP_Post)* |
| **Returns** | `array` — Modified table data |
| **File** | `app/Data/TableRepository.php` |

```php
add_filter( 'productbay_table_data', function( $data, $post ) {
    $data['my_pro_field'] = get_post_meta( $post->ID, '_my_pro_meta', true );
    return $data;
}, 10, 2 );
```

---

## API Layer

Hooks for extending the REST API and settings.

### `productbay_register_routes`

Fires after all core REST routes are registered. Use this to register additional endpoints.

| Type | Action |
|------|--------|
| **Parameters** | `$router` *(Router)* — The router instance |
| **File** | `app/Http/Router.php` |

```php
add_action( 'productbay_register_routes', function( $router ) {
    register_rest_route( 'productbay/v1', '/my-endpoint', [ /* ... */ ] );
} );
```

### `productbay_default_settings`

Filters the default plugin settings array.

| Type | Filter |
|------|--------|
| **Parameters** | `$defaults` *(array)* |
| **Returns** | `array` — Modified defaults |
| **File** | `app/Api/SettingsController.php` |

### `productbay_get_settings`

Filters settings before they are returned to the frontend.

| Type | Filter |
|------|--------|
| **Parameters** | `$settings` *(array)* |
| **Returns** | `array` — Modified settings |
| **File** | `app/Api/SettingsController.php` |

### `productbay_settings_updated`

Fires after settings are saved.

| Type | Action |
|------|--------|
| **Parameters** | `$settings` *(array)* — The saved settings |
| **File** | `app/Api/SettingsController.php` |

### `productbay_system_status`

Filters the system status data (used by the Dashboard).

| Type | Filter |
|------|--------|
| **Parameters** | `$status` *(array)* |
| **Returns** | `array` — Modified status data |
| **File** | `app/Api/SystemController.php` |

```php
add_filter( 'productbay_system_status', function( $status ) {
    $status['pro_license'] = 'active';
    return $status;
} );
```

---

## Frontend Rendering

The most critical layer for extending table output. All hooks are in `TableRenderer.php` unless noted otherwise.

### `productbay_query_args`

Filters `WP_Query` arguments before the product query executes.

| Type | Filter |
|------|--------|
| **Parameters** | `$args` *(array)*, `$source` *(array)*, `$settings` *(array)* |
| **Returns** | `array` — Modified query args |
| **File** | `app/Frontend/TableRenderer.php` |

```php
add_filter( 'productbay_query_args', function( $args, $source, $settings ) {
    // Example: only show featured products
    $args['tax_query'][] = [
        'taxonomy' => 'product_visibility',
        'field'    => 'name',
        'terms'    => 'featured',
    ];
    return $args;
}, 10, 3 );
```

### `productbay_table_columns`

Filters the columns array before rendering.

| Type | Filter |
|------|--------|
| **Parameters** | `$columns` *(array)*, `$table_id` *(int)* |
| **Returns** | `array` — Modified columns |

### `productbay_cell_output`

Filters a single cell's HTML output.

| Type | Filter |
|------|--------|
| **Parameters** | `$cell_html` *(string)*, `$col` *(array)*, `$product` *(WC_Product)* |
| **Returns** | `string` — Modified HTML |

```php
add_filter( 'productbay_cell_output', function( $html, $col, $product ) {
    if ( $col['type'] === 'my_custom_type' ) {
        return '<span>' . esc_html( $product->get_attribute('brand') ) . '</span>';
    }
    return $html;
}, 10, 3 );
```

### `productbay_table_output`

Filters the complete table HTML before it is returned.

| Type | Filter |
|------|--------|
| **Parameters** | `$html` *(string)*, `$table` *(array)* |
| **Returns** | `string` — Modified HTML |

### `productbay_table_styles`

Filters the generated scoped CSS for a table.

| Type | Filter |
|------|--------|
| **Parameters** | `$css` *(string)*, `$table` *(array)* |
| **Returns** | `string` — Modified CSS |

### Button & toggle text filters

Four filters override the customer-facing labels on a table's action controls. Each receives an empty string plus the table's resolved cart settings, so returning a non-empty string wins. Returning `''` falls through to the global **Cart Customization** setting, and then to the built-in translatable default.

These are the seam ProductBay Pro uses to implement per-table custom text; a filter added at the default priority will run alongside it, so use a later priority if you need to override Pro.

| Filter | Default label | Since |
|--------|---------------|-------|
| `productbay_add_to_cart_text` | WooCommerce's add-to-cart text | 1.3.2 |
| `productbay_select_options_text` | "Select Options" / "View Products" | 1.3.2 |
| `productbay_bulk_list_text` | "Add to bulk list" | 1.3.4 |
| `productbay_bulk_list_added_text` | "Added" | 1.3.4 |

| Type | Filter |
|------|--------|
| **Parameters** | `$text` *(string)* — empty by default, `$cart_settings` *(array)* — the table's resolved cart settings |
| **Returns** | `string` — The label to render, or `''` to fall back |

```php
// Call the bulk list an "order sheet" on every table.
add_filter( 'productbay_bulk_list_text', function( $text, $cart_settings ) {
    return __( 'Add to order sheet', 'my-textdomain' );
}, 10, 2 );

add_filter( 'productbay_bulk_list_added_text', function( $text, $cart_settings ) {
    return __( 'On sheet', 'my-textdomain' );
}, 10, 2 );
```

::: tip
The bulk-list labels are always rendered — they give the select checkbox its accessible name on desktop, and become the visible toggle button on [stacked mobile cards](/features/display-customization#layout-on-phones).
:::

### `productbay_filter_options`

Filters the choices offered by the category and product type dropdowns above a table.

Options are resolved from the table's own base product set — the source scope, ignoring the visitor's current selection — so the dropdowns never offer a value that would render an empty table. Use this filter to add a choice back (for example a category the table does not contain yet but soon will) or to drop one.

| Type | Filter |
|------|--------|
| **Parameters** | `$options` *(array)*, `$source` *(array)*, `$settings` *(array)*, `$table_id` *(int)* |
| **Returns** | `array` — `product_cat` as a list of `['slug' => …, 'name' => …]`, `product_type` as a map of slug => label |

```php
add_filter( 'productbay_filter_options', function( $options, $source, $settings, $table_id ) {
    // Always offer the Clearance category, even while it is empty.
    $options['product_cat'][] = array( 'slug' => 'clearance', 'name' => __( 'Clearance', 'my-textdomain' ) );
    return $options;
}, 10, 4 );
```

### `productbay_filter_options_cache_ttl`

Filters how long resolved filter choices stay cached, in seconds. Resolving them costs one ID-only product query plus two term queries, so the result is cached per table; the cache key carries a hash of the resolved query args, so editing a table's source takes effect immediately, while catalog changes are picked up when the transient expires.

Return `0` to disable caching — useful on a store whose product categories change constantly.

| Type | Filter |
|------|--------|
| **Parameters** | `$ttl` *(int)* — default `12 * HOUR_IN_SECONDS`, `$table_id` *(int)* |
| **Returns** | `int` — Lifetime in seconds, or `0` to skip caching |

### `productbay_before_table` / `productbay_after_table`

Actions fired before and after the table wrapper `<div>`.

| Type | Action |
|------|--------|
| **Parameters** | `$table` *(array)* |

### `productbay_before_row` / `productbay_after_row`

Actions fired before and after each product row `<tr>`.

| Type | Action |
|------|--------|
| **Parameters** | `$product` *(WC_Product)*, `$table` *(array)* |

### `productbay_toolbar_start` / `productbay_toolbar_end`

Actions to inject content at the start or end of the toolbar area (above the table, where search and bulk actions live).

| Type | Action |
|------|--------|
| **Parameters** | `$table` *(array)* |

---

## Frontend AJAX

Hooks for AJAX operations in `AjaxRenderer.php`.

### `productbay_ajax_filter_response`

Filters the AJAX response for table filtering/search/pagination.

| Type | Filter |
|------|--------|
| **Parameters** | `$response` *(array)*, `$table` *(array)* |
| **Returns** | `array` — Modified response |

### `productbay_after_bulk_add_to_cart`

Fires after a bulk add-to-cart operation completes.

| Type | Action |
|------|--------|
| **Parameters** | `$added_count` *(int)*, `$errors` *(array)* |

---

## Shortcode

Hooks in `Shortcode.php`.

### `productbay_shortcode_atts`

Filters the parsed shortcode attributes.

| Type | Filter |
|------|--------|
| **Parameters** | `$atts` *(array)* |
| **Returns** | `array` — Modified attributes |

### `productbay_enqueue_frontend_assets`

Action to enqueue additional frontend assets when a ProductBay shortcode is rendered.

| Type | Action |
|------|--------|
| **Parameters** | *(none)* |

---

## Admin

Hooks in `Admin.php`.

### `productbay_after_register_menu`

Fires after all admin menu items are registered.

| Type | Action |
|------|--------|
| **Parameters** | *(none)* |

### `productbay_admin_script_data`

Filters the data passed to the React admin app via `wp_localize_script`.

| Type | Filter |
|------|--------|
| **Parameters** | `$data` *(array)* |
| **Returns** | `array` — Modified script data |

```php
add_filter( 'productbay_admin_script_data', function( $data ) {
    $data['proActive'] = true;
    $data['license']   = 'valid';
    return $data;
} );
```

### `productbay_enqueue_admin_assets`

Action to enqueue additional admin assets on ProductBay pages.

| Type | Action |
|------|--------|
| **Parameters** | *(none)* |
