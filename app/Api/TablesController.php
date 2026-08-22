<?php
/**
 * REST API controller for table CRUD operations.
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
 * Class TablesController
 *
 * Handles CRUD operations for ProductBay table posts via REST API.
 *
 * @since   1.0.0
 * @package WpabProductBay\Api
 */
class TablesController extends ApiController {


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
	 * List all tables.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of formatted table data.
	 */
	public function index() {
		return $this->repository->get_tables();
	}

	/**
	 * Get a single table by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request REST request containing 'id' parameter.
	 * @return array|null Table data or null if not found.
	 */
	public function show( $request ) {
		$id = $request['id'];
		return $this->repository->get_table( $id );
	}

	/**
	 * Create or update a table.
	 *
	 * @since 1.0.0
	 *
	 * @return array Saved table data or error.
	 */
	public function store() {
		// Get raw data from $_REQUEST to avoid sanitization destroying nested structures.
		// The Repository will handle field-specific sanitization (title, etc.).
		$data = $this->request->get( 'data' );

		if ( ! $data ) {
			return array( 'error' => 'No data provided' );
		}

		return $this->repository->save_table( $data );
	}

	/**
	 * Delete a table by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request REST request containing 'id' parameter.
	 * @return \WP_Post|false|null Deleted post object or false/null on failure.
	 */
	public function destroy( $request ) {
		$id = $request['id'];
		return $this->repository->delete_table( $id );
	}
}
