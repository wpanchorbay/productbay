# REST API

ProductBay registers its own REST API endpoints under the `productbay/v1` namespace. All endpoints require the `manage_options` capability (WordPress administrator).

::: warning Internal API
These REST API endpoints are used internally by the ProductBay admin panel. They are not intended as a public API and may change in future versions. Do not build external integrations against these endpoints.
:::

## Authentication

All endpoints require:
- The user to be **logged in** to WordPress
- The user to have the **`manage_options`** capability
- A valid **WordPress REST API nonce** (handled automatically by the admin panel)

## Base URL

```
/wp-json/productbay/v1/
```

## Endpoints

### Tables

#### List Product Tables

```http
GET /wp-json/productbay/v1/tables
```

Returns an array of all ProductBay tables with their metadata.

**Response:**
```json
[
  {
    "id": 1,
    "title": "All Products",
    "status": "publish",
    "source": { "type": "all" },
    "shortcode": "[productbay id=\"1\"]",
    "created": "2026-01-15T10:30:00",
    "modified": "2026-01-20T14:22:00"
  }
]
```

---

#### Get Single Table

```http
GET /wp-json/productbay/v1/tables/{id}
```

Returns full table data including configuration.

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Table post ID |

---

#### Create / Update Table

```http
POST /wp-json/productbay/v1/tables
```

Creates a new table or updates an existing one. Send the full table configuration in the request body.

| Body Parameter | Type | Description |
|---------------|------|-------------|
| `data` | object | Complete table configuration (title, source, columns, style, settings) |

---

#### Delete Table

```http
DELETE /wp-json/productbay/v1/tables/{id}
```

Permanently deletes a table.

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Table post ID |

---

### Products

#### Search Products

```http
GET /wp-json/productbay/v1/products
```

Search WooCommerce products by name, ID, or SKU.

| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Product name search (fuzzy match) |
| `include` | integer | Exact product ID |
| `sku` | string | SKU exact or prefix match |
| `limit` | integer | Results per page (default: 10) |
| `page` | integer | Page number (default: 1) |

---

#### List Categories

```http
GET /wp-json/productbay/v1/categories
```

Returns all WooCommerce product categories.

**Response:**
```json
[
  {
    "id": 15,
    "name": "Clothing",
    "count": 42,
    "slug": "clothing"
  }
]
```

---

#### Source Statistics

```http
GET /wp-json/productbay/v1/source-stats
```

Returns product and category counts for a source type.

| Parameter | Type | Description |
|-----------|------|-------------|
| `type` | string | Source type: `all` or `sale` |

**Response:**
```json
{
  "categories": 5,
  "products": 120
}
```

---

### Settings

#### Get Settings

```http
GET /wp-json/productbay/v1/settings
```

Returns current plugin settings merged with defaults.

---

#### Update Settings

```http
POST /wp-json/productbay/v1/settings
```

Updates plugin settings.

| Body Parameter | Type | Description |
|---------------|------|-------------|
| `settings` | object | Settings key-value pairs to update |

---

#### Reset All Data

```http
POST /wp-json/productbay/v1/settings/reset
```

::: danger Destructive Action
This endpoint deletes **all tables**, clears all metadata, and resets settings to defaults. This cannot be undone.
:::

**Response:**
```json
{
  "success": true,
  "deleted_tables": 5,
  "settings": { ... }
}
```

---

### System

#### System Status

```http
GET /wp-json/productbay/v1/system/status
```

Returns system information (table counts, WooCommerce status, etc.).

---

#### Mark Onboarded

```http
POST /wp-json/productbay/v1/system/onboard
```

Marks the onboarding process as completed.

---

#### Clear Cache <Badge type="tip" text="Since v1.3.5" />

```http
POST /wp-json/productbay/v1/system/clear-cache
```

Invalidates ProductBay's cached filter-dropdown choices. Bumps the global `productbay_cache_gen`
option, which every ProductBay cache key includes, and clears the plugin's own transient rows.
Returns `{ "success": true }`.

Products, prices, and stock are never cached, so this endpoint cannot change what a table renders.

---

### Preview

#### Generate Preview

```http
POST /wp-json/productbay/v1/preview
```

Renders a table preview from the provided configuration. Used by the Creation Wizard's live preview feature.

---

### Logs

#### Get Activity Logs

```http
GET /wp-json/productbay/v1/logs
```

Returns paginated activity log entries.

| Parameter | Type | Description |
|-----------|------|-------------|
| `limit` | integer | Results per page (default: 50) |
| `page` | integer | Page number (default: 1) |

---

#### Export Logs

```http
GET /wp-json/productbay/v1/logs/export
```

Downloads all activity logs as a CSV file.

---

#### Clear Logs

```http
POST /wp-json/productbay/v1/logs/clear
```

Permanently deletes all activity log entries. This action cannot be undone.
