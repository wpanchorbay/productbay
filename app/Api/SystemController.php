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
}
