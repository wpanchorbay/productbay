# ProductBay Demo Environment — Deployment Guide

## Files in this directory

| File | Deploy To | Purpose |
|---|---|---|
| `productbay-demo.php` | `wp-content/mu-plugins/` | All demo logic (auto-login, roles, restrictions) |
| `productbay-demo-reset.sh` | `/home/YOUR_USER/` | Cron script to reset site every 4 hours |

## Deployment Steps

### 1. Upload the mu-plugin

```bash
# On the staging server
mkdir -p /var/www/html/wp-content/mu-plugins
cp productbay-demo.php /var/www/html/wp-content/mu-plugins/
```

The mu-plugin is **domain-locked** to `productbay.wpanchorbay.com`. It will silently deactivate on any other domain.

### 2. Configure the Base State

Set up the demo site exactly how you want it:
- Install & activate ProductBay Free + Pro
- Activate the Pro license key
- Create sample WooCommerce products
- Create sample ProductBay tables
- Set up the theme and any pages
- Configure WooCommerce settings

Then create the snapshots:

```bash
# Export the database
wp db export /home/YOUR_USER/productbay-demo-base.sql --path=/var/www/html

# Snapshot the uploads directory
tar -czf /home/YOUR_USER/productbay-uploads-base.tar.gz \
    -C /var/www/html/wp-content uploads
```

### 3. Deploy the reset script

```bash
cp productbay-demo-reset.sh /home/YOUR_USER/
chmod +x /home/YOUR_USER/productbay-demo-reset.sh

# Update the YOUR_USER placeholder in the script
sed -i 's/YOUR_USER/actual_username/g' /home/YOUR_USER/productbay-demo-reset.sh
```

### 4. Configure the cron job

In Hostinger hPanel → Cron Jobs:

```
0 */4 * * * /home/YOUR_USER/productbay-demo-reset.sh > /dev/null 2>&1
```

### 5. Admin & Auto-Login Access

- **Visitors:** Navigate to `https://productbay.wpanchorbay.com/wp-admin/admin.php?auto-login=true` to access the plugin dashboard as the Demo User.
- **Real Admins:** Navigate to `https://productbay.wpanchorbay.com/wp-login.php` to log in with real admin credentials.

## Updating the Base State

After making changes you want to persist across resets:

```bash
# Re-export the database
wp db export /home/YOUR_USER/productbay-demo-base.sql --path=/var/www/html

# Re-snapshot uploads
tar -czf /home/YOUR_USER/productbay-uploads-base.tar.gz \
    -C /var/www/html/wp-content uploads
```

## Changing the domain

Edit the `PRODUCTBAY_DEMO_DOMAIN` constant in `productbay-demo.php`:

```php
define( 'PRODUCTBAY_DEMO_DOMAIN', 'your-new-domain.com' );
```
