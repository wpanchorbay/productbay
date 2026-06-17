<?php
/**
 * ProductBay
 *
 * @package           productbay
 * @author            WPAnchorBay
 * @copyright         2026 WPAnchorBay
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       ProductBay – High-Performance Product Table for WooCommerce
 * Plugin URI:        https://wpanchorbay.com/products/productbay
 * Source URI:        https://github.com/wpanchorbay/productbay
 * Description:       WooCommerce product tables with search, filters, and pagination for high-converting, responsive product listings and easy browsing.
 * Version:           1.3.2
 * Stable tag:        1.3.2
 * Requires at least: 6.8
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Author:            WPAnchorBay
 * Author URI:        https://wpanchorbay.com/
 * Text Domain:       productbay
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.1
 */

// Namespace - ProductBay.
namespace WpabProductBay;

/**
 * Prevent Direct File Access
 * Abort if this file is called directly.
 */
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Global Constants
 * Prefixed with PRODUCTBAY_
 */
define('PRODUCTBAY_VERSION', '1.3.2');
define('PRODUCTBAY_PLUGIN_NAME', 'productbay');
define('PRODUCTBAY_TEXT_DOMAIN', 'productbay');
define('PRODUCTBAY_OPTION_NAME', 'productbay');
define('PRODUCTBAY_URL', \plugin_dir_url(__FILE__));
define('PRODUCTBAY_PATH', \plugin_dir_path(__FILE__));
define('PRODUCTBAY_PLUGIN_BASENAME', \plugin_basename(__FILE__));

// Autoloader (must be loaded before using any composer packages).
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Load Environment Variables from .env file (development only)
 * The .env file should NOT be included in production releases.
 * If .env doesn't exist, the plugin uses production defaults.
 *
 * @since 1.0.0
 */
$productbay_dotenv_path = __DIR__ . '/.env';
if (\file_exists($productbay_dotenv_path) && \class_exists('Dotenv\\Dotenv')) {
	$productbay_dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
	$productbay_dotenv->safeLoad();
}

/**
 * Development Mode Constant
 * Loaded from .env file if available, defaults to false for production.
 * When true: enables file-based cache busting, debug features, etc.
 * When false: uses production-optimized settings.
 */
define('PRODUCTBAY_DEV_MODE', \filter_var(getenv('PRODUCTBAY_DEV_MODE') ? getenv('PRODUCTBAY_DEV_MODE') : false, FILTER_VALIDATE_BOOLEAN));

/**
 * Plugin activation hook.
 *
 * Schedules the daily log pruning cron event.
 *
 * @since 1.2.0
 */
function productbay_activate()
{
	if (!\wp_next_scheduled(Data\ActivityLog::CRON_HOOK)) {
		\wp_schedule_event(time(), 'daily', Data\ActivityLog::CRON_HOOK);
	}

	// Register post type and flush rewrite rules to prevent 404s on fresh installs.
	$plugin = new Core\Plugin();
	$plugin->register_post_type();
	\flush_rewrite_rules();

	Data\ActivityLog::info('Plugin activated', 'ProductBay v' . PRODUCTBAY_VERSION . ' activated.');
}
\register_activation_hook(__FILE__, __NAMESPACE__ . '\\productbay_activate');

/**
 * Plugin deactivation hook.
 *
 * Clears the log pruning cron event.
 *
 * @since 1.2.0
 */
function productbay_deactivate()
{
	$timestamp = \wp_next_scheduled(Data\ActivityLog::CRON_HOOK);
	if ($timestamp) {
		\wp_unschedule_event($timestamp, Data\ActivityLog::CRON_HOOK);
	}

	Data\ActivityLog::info('Plugin deactivated', 'ProductBay v' . PRODUCTBAY_VERSION . ' deactivated.');
}
\register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\productbay_deactivate');

/**
 * Initialization
 *
 * @since 1.0.0
 */
function productbay_init()
{
	$plugin = new Core\Plugin();
	$plugin->run();
}
add_action('plugins_loaded', __NAMESPACE__ . '\\productbay_init');