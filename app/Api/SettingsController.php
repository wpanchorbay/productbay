<?php
/**
 * REST API controller for plugin settings management.
 *
 * @package ProductBay
 */

declare(strict_types=1);

namespace WpabProductBay\Api;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

use WpabProductBay\Http\Request;
use WpabProductBay\Data\ActivityLog;

/**
 * Class SettingsController
 *
 * Manages plugin settings via the REST API.
 * Handles reading, updating, and resetting global plugin configuration.
 *
 * @since   1.0.0
 * @package WpabProductBay\Api
 */
class SettingsController extends ApiController
{

	/**
	 * WordPress option name for plugin settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'productbay_settings';

	/**
	 * Initialize the controller.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request HTTP request handler.
	 */
	public function __construct(Request $request)
	{
		parent::__construct($request);
	}

	/**
	 * Get current plugin settings.
	 *
	 * Returns stored settings merged with defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return array Plugin settings.
	 */
	public function get_settings()
	{
		$settings = get_option(self::OPTION_NAME, $this->defaults());

		/**
		 * Filters settings before returning to the frontend.
		 *
		 * @since 1.0.0
		 *
		 * @param array $settings The current settings.
		 */
		return \apply_filters('productbay_get_settings', $settings);
	}

	/**
	 * Update settings via REST API.
	 *
	 * @param \WP_REST_Request $request The REST request object containing JSON body.
	 * @return array The updated settings.
	 * @since 1.0.0
	 */
	public function update_settings(\WP_REST_Request $request)
	{
		$settings = $request->get_param('settings');

		if (!is_array($settings)) {
			$settings = array();
		}

		$old_settings = get_option(self::OPTION_NAME, array());
		
		// Merge with defaults and old settings to ensure structure and preserve custom keys.
		$settings = array_merge($this->defaults(), $old_settings, $settings);

		update_option(self::OPTION_NAME, $settings);

		\do_action('productbay_settings_updated', $settings);

		// Identify what changed for better logging.
		$changed_keys = array();
		foreach ($settings as $key => $value) {
			if (!isset($old_settings[$key]) || $old_settings[$key] !== $value) {
				$changed_keys[] = $key;
			}
		}

		if (empty($changed_keys)) {
			ActivityLog::info(__('Settings saved', 'productbay'), __('Settings saved without any visible changes.', 'productbay'));
		} else {
			$details = array();
			foreach ($changed_keys as $key) {
				if ($key === 'logging_enabled') {
					/* translators: %s: Enabled or Disabled status */
					$details[] = sprintf(__('Logging %s', 'productbay'), $settings[$key] ? 'Enabled' : 'Disabled');
				} elseif ($key === 'log_retention') {
					/* translators: %d: Number of days */
					$details[] = sprintf(__('Retention set to %d days', 'productbay'), $settings[$key]);
				}
			}

			if (!empty($details)) {
				/* translators: %s: Comma-separated list of log settings changes */
				$summary = sprintf(__('Log settings updated: %s', 'productbay'), implode(', ', $details));
			} else {
				/* translators: %s: Comma-separated list of changed global setting keys */
				$summary = sprintf(__('Global settings updated: %s', 'productbay'), implode(', ', $changed_keys));
			}

			ActivityLog::success(__('Settings updated', 'productbay'), $summary);
		}

		return $settings;
	}

	/**
	 * Reset all plugin data to factory defaults.
	 *
	 * Performs a full cleanup: deletes all saved tables (CPT posts),
	 * clears associated post meta, removes settings and onboarding state.
	 * This restores the plugin to its initial "just installed" state.
	 *
	 * @return array{success: bool, deleted_tables: int, settings: array} Reset result with defaults.
	 * @since 1.0.0
	 */
	public function reset_settings()
	{
		// 1. Delete all ProductBay table posts.
		$tables = get_posts(
			array(
			'post_type' => 'productbay_table',
			'numberposts' => -1,
			'post_status' => 'any',
			'fields' => 'ids',
		)
		);

		$deleted_count = 0;
		if (!empty($tables)) {
			foreach ($tables as $table_id) {
				wp_delete_post($table_id, true);
				++$deleted_count;
			}
		}

		// 2. Clear all ProductBay post meta across the site.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup, caching not needed
		$wpdb->query(
			$wpdb->prepare(
			"DELETE FROM $wpdb->postmeta WHERE meta_key = %s",
			'_productbay_config'
		)
		);

		// 3. Delete plugin settings.
		delete_option(self::OPTION_NAME);

		// 4. Reset onboarding state.
		delete_option('productbay_onboarding_completed');

		ActivityLog::warning(
			'Factory reset',
			sprintf('All data cleared. %d table(s) deleted. Settings restored to defaults.', $deleted_count)
		);

		return array(
			'success' => true,
			'deleted_tables' => $deleted_count,
			'settings' => $this->defaults(),
		);
	}

	/**
	 * Get default plugin settings.
	 *
	 * Provides the full default configuration structure for new installations
	 * and as a fallback for missing settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array Default settings array.
	 */
	private function defaults()
	{
		$defaults = array(
			'add_to_cart_text' => 'Add to Cart',
			'products_per_page' => 10,
			'logging_enabled' => true,
			'log_retention' => 30, // Default to 30 days.
			'show_admin_bar' => true,
			'delete_on_uninstall' => true,
			'design' => array(
				'header_bg' => '#f3f4f6',
				'border_color' => '#e5e7eb',
			),
			// Default configuration for new tables.
			'table_defaults' => array(
				'source' => array(
					'type' => 'all',
					'queryArgs' => array(
						'stockStatus' => 'any',
					),
					'sort' => array(
						'orderBy' => 'date',
						'order' => 'DESC',
					),
				),
				'style' => array(
					'header' => array(
						'bgColor' => '#f0f0f1',
						'textColor' => '#333333',
						'fontSize' => '16px',
					),
					'body' => array(
						'bgColor' => '#ffffff',
						'textColor' => '#444444',
						'rowAlternate' => false,
						'altBgColor' => '#f9f9f9',
						'altTextColor' => '#444444',
						'borderColor' => '#e5e5e5',
					),
					'button' => array(
						'bgColor' => '#2271b1',
						'textColor' => '#ffffff',
						'borderRadius' => '4px',
						'icon' => 'cart',
						'hoverBgColor' => '#135e96',
						'hoverTextColor' => '#ffffff',
					),
					'layout' => array(
						'borderStyle' => 'solid',
						'borderColor' => '#e5e5e5',
						'borderRadius' => '0px',
						'cellPadding' => 'normal',
					),
					'typography' => array(
						'headerFontWeight' => 'bold',
					),
					'hover' => array(
						'rowHoverEnabled' => true,
						'rowHoverBgColor' => '#f5f5f5',
					),
					'responsive' => array(
						'mode' => 'standard',
					),
				),
				'settings' => array(
					'features' => array(
						'search' => true,
						'sorting' => true,
						'pagination' => true,
						'export' => false,
						'variableGrouped' => false,
						'priceFilter' => array(
							'enabled' => false,
							'mode' => 'both',
							'step' => 1,
							'customMin' => null,
							'customMax' => null,
						),
						'variationsMode' => 'inline',
					),
					'pagination' => array(
						'limit' => 10,
						'position' => 'bottom',
					),
					'cart' => array(
						'enable' => true,
						'method' => 'button',
						'showQuantity' => true,
						'ajaxAdd' => true,
					),
					'filters' => array(
						'enabled' => true,
						'showCategory' => true,
						'showType' => true,
						'activeTaxonomies' => array('product_cat'),
						'showPriceRange' => false,
					),
				),
			),
		);

		/**
		 * Filters the default plugin settings.
		 *
		 * @since 1.0.0
		 *
		 * @param array $defaults The default settings array.
		 */
		return \apply_filters('productbay_default_settings', $defaults);
	}
}