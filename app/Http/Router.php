<?php
/**
 * REST API route registration and permission checks.
 *
 * @package ProductBay
 */

declare(strict_types=1);

namespace WpabProductBay\Http;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WpabProductBay\Data\TableRepository;

/**
 * Class Router
 *
 * Registers all REST API routes for the ProductBay plugin.
 * Maps HTTP methods and endpoints to their respective controller callbacks
 * with capability-based permission checks.
 *
 * @since   1.0.0
 * @package WpabProductBay\Http
 */
class Router {


	/**
	 * Repository for table data access.
	 *
	 * @var TableRepository
	 * @since 1.0.0
	 */
	protected $repository;

	/**
	 * HTTP request wrapper instance.
	 *
	 * @var Request
	 * @since 1.0.0
	 */
	protected $request;

	/**
	 * Constructor.
	 *
	 * @param TableRepository $repository Table repository instance.
	 * @param Request         $request    HTTP request instance.
	 * @since 1.0.0
	 */
	public function __construct( TableRepository $repository, Request $request ) {
		$this->repository = $repository;
		$this->request    = $request;
	}

	/**
	 * Initialize the router by hooking into rest_api_init.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init() {
		\add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST API routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		$controller = new \WpabProductBay\Api\TablesController( $this->repository, $this->request );

		// List Tables.
		// Uses read_permission_check (edit_posts) so editors/authors can populate
		// the block editor dropdown without needing manage_options.
		\register_rest_route(
			'productbay/v1',
			'/tables',
			array(
				'methods'             => 'GET',
				'callback'            => array( $controller, 'index' ),
				'permission_callback' => array( $this, 'read_permission_check' ),
			)
		);

		// Create/Update Table.
		\register_rest_route(
			'productbay/v1',
			'/tables',
			array(
				'methods'             => 'POST',
				'callback'            => array( $controller, 'store' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		// Get Single Table.
		// Also uses read_permission_check so block ServerSideRender works for editors.
		\register_rest_route(
			'productbay/v1',
			'/tables/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $controller, 'show' ),
				'permission_callback' => array( $this, 'read_permission_check' ),
			)
		);

		// Delete Table.
		\register_rest_route(
			'productbay/v1',
			'/tables/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $controller, 'destroy' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		// Settings.
		$settings_controller = new \WpabProductBay\Api\SettingsController( $this->request );
		\register_rest_route(
			'productbay/v1',
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $settings_controller, 'get_settings' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		\register_rest_route(
			'productbay/v1',
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $settings_controller, 'update_settings' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		\register_rest_route(
			'productbay/v1',
			'/settings/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( $settings_controller, 'reset_settings' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		\register_rest_route(
			'productbay/v1',
			'/system/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( new \WpabProductBay\Api\SystemController( $this->repository, $this->request ), 'get_status' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		\register_rest_route(
			'productbay/v1',
			'/system/onboard',
			array(
				'methods'             => 'POST',
				'callback'            => array( new \WpabProductBay\Api\SystemController( $this->repository, $this->request ), 'mark_onboarded' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		// Products & Categories.
		$products_controller = new \WpabProductBay\Api\ProductsController( $this->request );

		\register_rest_route(
			'productbay/v1',
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $products_controller, 'index' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		\register_rest_route(
			'productbay/v1',
			'/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( $products_controller, 'categories' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		\register_rest_route(
			'productbay/v1',
			'/source-stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $products_controller, 'sourceStats' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		// Live Preview.
		$preview_controller = new \WpabProductBay\Api\PreviewController( $this->repository, $this->request );
		\register_rest_route(
			'productbay/v1',
			'/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $preview_controller, 'preview' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		// Activity Logs.
		$log_controller = new \WpabProductBay\Api\LogController( $this->request );

		\register_rest_route(
			'productbay/v1',
			'/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $log_controller, 'index' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		\register_rest_route(
			'productbay/v1',
			'/logs/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $log_controller, 'export' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		\register_rest_route(
			'productbay/v1',
			'/logs/clear',
			array(
				'methods'             => 'POST',
				'callback'            => array( $log_controller, 'clear' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		/**
		 * Fires after all core REST routes are registered.
		 *
		 * Use this to register additional endpoints under the productbay/v1 namespace.
		 *
		 * @since 1.0.0
		 *
		 * @param Router $router The Router instance.
		 */
		\do_action( 'productbay_register_routes', $this );
	}



	/**
	 * Check if the current user has permission to access the API.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user has 'manage_options' capability.
	 */
	public function permission_check() {
		return \current_user_can( \WpabProductBay\Core\Constants::get_capability() );
	}

	/**
	 * Check if the current user can read tables.
	 *
	 * Used for GET /tables and GET /tables/{id} so that Contributors, Authors,
	 * and Editors can populate the block editor dropdown and trigger ServerSideRender
	 * previews without needing full admin access.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if user has 'edit_posts' capability.
	 */
	public function read_permission_check() {
		return \current_user_can( 'edit_posts' );
	}
}
