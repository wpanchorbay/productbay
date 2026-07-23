# Uninstallation

This page explains how to properly remove ProductBay from your WordPress site and what happens to your data.

## Deactivation vs. Uninstallation

| Action | What Happens |
|--------|-------------|
| **Deactivate** | The plugin is disabled but your tables, settings, and data are preserved. You can reactivate later without losing anything. |
| **Delete (Uninstall)** | The plugin is removed from your site. Whether data is deleted depends on your settings. |

## Controlling Data Removal

ProductBay gives you control over what happens when the plugin is deleted:

1. Go to **ProductBay → Settings** in your WordPress admin
2. Select **Plugin Settings** tab
3. Look for the **"Delete data on uninstall"** toggle
4. Toggle it based on your preference:

| Setting | Effect on Uninstall |
|---------|-------------------|
| **Enabled** (default) | All ProductBay data is permanently deleted: tables, table configurations, plugin settings, and onboarding state |
| **Disabled** | Data is preserved in the database even after the plugin is deleted. Useful if you plan to reinstall later. |

::: danger Data Deletion Is Permanent
When "Delete data on uninstall" is enabled, deleting the plugin will **permanently remove** all your tables and settings. This cannot be undone. Make sure to export or back up any important configurations before uninstalling.
:::

## What Gets Deleted

When the plugin is uninstalled with data deletion enabled, the following is removed:

- **All ProductBay table posts** (custom post type: `productbay_table`)
- **Table configuration metadata** (the `_productbay_source`, `_productbay_columns`, `_productbay_settings`, and `_productbay_style` post meta keys, plus the retired legacy `_productbay_config` key)
- **Plugin settings** (`productbay_settings` option)
- **Onboarding state** (`productbay_onboarding_completed` option)

## How to Uninstall

1. Go to **Plugins** in your WordPress admin
2. Click **Deactivate** next to ProductBay
3. Click **Delete** to remove the plugin
4. If "Delete data on uninstall" was enabled, all data is now removed

## Reinstalling

If you previously disabled data deletion before uninstalling, your tables and settings will still be in the database. Simply reinstall and activate ProductBay — your data will be restored automatically.
