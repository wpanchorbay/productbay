#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════════
# ProductBay Demo Environment — State Reset Script
# ═══════════════════════════════════════════════════════════════════════════════
#
# Restores the demo site database and uploads to the pre-configured "Base State".
# Designed to run via system cron every 4 hours on Hostinger.
#
# CRON SCHEDULE (add via Hostinger hPanel → Cron Jobs):
#   0 */4 * * * /home/YOUR_USER/productbay-demo-reset.sh > /dev/null 2>&1
#
# INITIAL SETUP (run once after configuring the demo site):
#   1. Export the base database:
#      wp db export /home/YOUR_USER/productbay-demo-base.sql --path=/var/www/html
#
#   2. Snapshot the uploads directory:
#      tar -czf /home/YOUR_USER/productbay-uploads-base.tar.gz \
#          -C /var/www/html/wp-content uploads
#
#   3. Make this script executable:
#      chmod +x /home/YOUR_USER/productbay-demo-reset.sh
#
# ═══════════════════════════════════════════════════════════════════════════════

set -euo pipefail

# ─── Configuration ──────────────────────────────────────────────────────────────
# Update these paths to match your Hostinger directory structure.

WP_PATH="/var/www/html"
BASE_SQL="/home/YOUR_USER/productbay-demo-base.sql"
BASE_UPLOADS="/home/YOUR_USER/productbay-uploads-base.tar.gz"
UPLOADS_DIR="${WP_PATH}/wp-content/uploads"
LOG_FILE="/home/YOUR_USER/demo-reset.log"

# WP-CLI path — adjust if your Hostinger plan uses a different location.
# Run `which wp` via SSH to find the correct path.
WP_CLI="wp"

# ─── Preflight Checks ──────────────────────────────────────────────────────────

if [ ! -f "$BASE_SQL" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: Base SQL file not found: $BASE_SQL" >> "$LOG_FILE"
    exit 1
fi

if [ ! -f "$BASE_UPLOADS" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: Base uploads archive not found: $BASE_UPLOADS" >> "$LOG_FILE"
    exit 1
fi

# ─── Reset Process ──────────────────────────────────────────────────────────────

echo "$(date '+%Y-%m-%d %H:%M:%S') - Starting demo reset..." >> "$LOG_FILE"

# Step 1: Import the base database (overwrites all tables).
echo "$(date '+%Y-%m-%d %H:%M:%S') - Importing database..." >> "$LOG_FILE"
$WP_CLI db import "$BASE_SQL" --path="$WP_PATH" 2>> "$LOG_FILE"

# Step 2: Restore uploads to base state.
echo "$(date '+%Y-%m-%d %H:%M:%S') - Restoring uploads..." >> "$LOG_FILE"
rm -rf "$UPLOADS_DIR"
tar -xzf "$BASE_UPLOADS" -C "${WP_PATH}/wp-content" 2>> "$LOG_FILE"

# Step 3: Flush WordPress object cache.
echo "$(date '+%Y-%m-%d %H:%M:%S') - Flushing caches..." >> "$LOG_FILE"
$WP_CLI cache flush --path="$WP_PATH" 2>> "$LOG_FILE" || true

# Step 4: Delete all transients (forces fresh license validation, etc.).
$WP_CLI transient delete --all --path="$WP_PATH" 2>> "$LOG_FILE" || true

# Step 5: Flush rewrite rules (ensures custom post types / REST routes work).
$WP_CLI rewrite flush --path="$WP_PATH" 2>> "$LOG_FILE" || true

# ─── Cleanup ────────────────────────────────────────────────────────────────────

# Keep the log file from growing forever — trim to last 200 lines.
if [ -f "$LOG_FILE" ]; then
    tail -n 200 "$LOG_FILE" > "${LOG_FILE}.tmp" && mv "${LOG_FILE}.tmp" "$LOG_FILE"
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') - Demo reset complete ✓" >> "$LOG_FILE"
