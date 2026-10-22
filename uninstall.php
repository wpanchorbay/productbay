<?php
/**
 * Uninstall ProductBay
 *
 * This file is called when the plugin is deleted from the WordPress admin dashboard.
 * It is responsible for cleaning up all plugin-related data from the database,
 * ensuring no "ghost data" or orphan tables/options are left behind.
 *
 * @since      1.0.0
 * @package    ProductBay
 * @author     WPAnchorBay
 * @copyright  2026 WPAnchorBay
 * @license    GPL-2.0-or-later
 * @link       https://wpanchorbay.com/products/productbay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Cleanup Logic:
 *
 * 1. Check if the user has enabled "Delete data on uninstall" (Default: true)
 * 2. Delete all 'productbay_table' posts (Custom Post Type)
 * 3. Delete all post meta associated with these posts
 * 4. Delete plugin options and settings
 *
	 * @since 1.0.0
 */

$productbay_settings = get_option( 'productbay_settings' );

/**
 * Robust Boolean Check:
 * If the setting is not in the database, we default to 'true'
 * (as defined in the plugin's default configuration).
 *
	 * @since 1.0.0
 */
$productbay_delete_on_uninstall = true;
if ( is_array( $productbay_settings ) && isset( $productbay_settings['delete_on_uninstall'] ) ) {
	$productbay_delete_on_uninstall = (bool) $productbay_settings['delete_on_uninstall'];
}

// Proceed only if delete_on_uninstall is true (enabled by default).
if ( $productbay_delete_on_uninstall ) {

	// 1. Delete all Table posts.
	// We fetch only IDs for better performance during batch deletion.
	$productbay_tables = get_posts(
		array(
			'post_type'   => 'productbay_table',
			'numberposts' => -1,
			'post_status' => 'any',
			'fields'      => 'ids',
		)
	);

	if ( ! empty( $productbay_tables ) ) {
		foreach ( $productbay_tables as $productbay_table_id ) {
			// Force delete (true) bypasses the trash and removes all associated meta.
			wp_delete_post( $productbay_table_id, true );
		}
	}

	// 2. Delete Plugin Options.
	delete_option( 'productbay_settings' );
	delete_option( 'productbay_onboarding_completed' );
	delete_option( 'productbay_cache_gen' );

	// 3. Clear the cached filter choices. Transients are keyed by a hash, so they
	// are matched by prefix; the timeout rows go with them.
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup during uninstall, caching not needed.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_productbay_filter_opts_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_productbay_filter_opts_' ) . '%'
		)
	);

	// 4. Extra Safety Net: Clear all ProductBay metadata across the site.
	// Includes all current meta keys and the legacy _productbay_config key.
	$productbay_meta_keys = array(
		'_productbay_source',
		'_productbay_columns',
		'_productbay_settings',
		'_productbay_style',
		'_productbay_config', // Legacy key.
	);
	foreach ( $productbay_meta_keys as $productbay_meta_key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup during uninstall, caching not needed
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->postmeta WHERE meta_key = %s",
				$productbay_meta_key
			)
		);
	}

	// 5. Delete activity log files.
	$productbay_log_dir = WP_CONTENT_DIR . '/productbay-logs';
	if ( is_dir( $productbay_log_dir ) ) {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$wp_filesystem->delete( $productbay_log_dir, true );
	}

	// 6. Clear log pruning cron event.
	$productbay_cron_timestamp = wp_next_scheduled( 'productbay_prune_logs' );
	if ( $productbay_cron_timestamp ) {
		wp_unschedule_event( $productbay_cron_timestamp, 'productbay_prune_logs' );
	}
}
