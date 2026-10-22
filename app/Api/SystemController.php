<?php
/**
 * REST API controller for system status and onboarding.
 *
 * @package ProductBay
 */

declare(strict_types=1);

namespace WpabProductBay\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WpabProductBay\Http\Request;
use WpabProductBay\Data\ActivityLog;
use WpabProductBay\Data\CacheInvalidator;
use WpabProductBay\Data\TableRepository;

/**
 * Class SystemController
 *
 * Provides system status and onboarding management endpoints.
 * Returns environment info (WC status, product/table counts, plugin version)
 * and handles first-time setup completion.
 *
 * @since   1.0.0
 * @package WpabProductBay\Api
 */
class SystemController extends ApiController {


	/**
	 * The table repository instance.
	 *
	 * @var TableRepository
	 * @since 1.0.0
	 */
	protected $repository;

	/**
	 * Initialize the controller.
	 *
	 * @since 1.0.0
	 *
	 * @param TableRepository $repository Table data repository.
	 * @param Request         $request    HTTP request handler.
	 */
	public function __construct( TableRepository $repository, Request $request ) {
		parent::__construct( $request );
		$this->repository = $repository;
	}

	/**
	 * Get system status.
	 *
	 * This method is called when the user loads the dashboard page.
	 * It returns the system status, including whether WooCommerce is active,
	 * the number of products, the number of tables, and the plugin version.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_status() {
		$wc_active     = class_exists( 'WooCommerce' );
		$product_count = 0;

		if ( $wc_active ) {
			$query         = new \WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			$product_count = $query->found_posts;
		}

		$tables      = $this->repository->get_tables();
		$table_count = count( $tables );

		$status = array(
			'wc_active'     => $wc_active,
			'product_count' => $product_count,
			'table_count'   => $table_count,
			'version'       => PRODUCTBAY_VERSION,
		);

		/**
		 * Filters the system status data.
		 *
		 * @since 1.0.0
		 *
		 * @param array $status The system status data.
		 */
		return \apply_filters( 'productbay_system_status', $status );
	}

	/**
	 * Mark onboarding as completed.
	 *
	 * This method is called when the user completes the onboarding process.
	 * It updates the `productbay_onboarding_completed` option to `true`.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function mark_onboarded() {
		update_option( 'productbay_onboarding_completed', true );
		return array(
			'success' => true,
		);
	}

	/**
	 * Clear ProductBay's cached filter-dropdown choices.
	 *
	 * Bumps the global cache generation counter (CacheInvalidator::bump()),
	 * which is what actually guarantees every cached entry becomes
	 * unreachable, regardless of where transients are stored. The direct
	 * row deletion below is best-effort cleanup only, not the mechanism
	 * that makes this safe — on a site running an external persistent
	 * object cache (Redis/Memcached), transients live there rather than in
	 * wp_options, and this SQL can't reach them at all.
	 *
	 * @return array
	 * @since 1.3.5
	 */
	public function clear_cache() {
		global $wpdb;

		CacheInvalidator::bump();

		// Best-effort cleanup only — see the docblock above. Scoped with
		// LIKE to ProductBay's own transient option rows.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time admin action, caching not needed.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_productbay_filter_opts_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_productbay_filter_opts_' ) . '%'
			)
		);

		ActivityLog::success(
			'Cache cleared',
			'Cached table filter options were cleared from Plugin Settings.'
		);

		return array(
			'success' => true,
		);
	}
}
