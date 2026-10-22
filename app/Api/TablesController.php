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
	 * @return array|\WP_Error Table data, or a 404 error if there is no such table.
	 */
	public function show( $request ) {
		$id    = $request['id'];
		$table = $this->repository->get_table( $id );

		// rest_ensure_response() wraps a bare null as HTTP 200 with a null body,
		// so a missing table used to read as success to any client checking the
		// status code — including this plugin's own admin app.
		if ( null === $table ) {
			return $this->error(
				__( 'Table not found.', 'productbay' ),
				'productbay_table_not_found',
				404
			);
		}

		return $table;
	}

	/**
	 * Create or update a table.
	 *
	 * @since 1.0.0
	 *
	 * @return array|\WP_Error Saved table data, or an error.
	 */
	public function store() {
		// Raw data, not sanitized here: sanitizing would flatten the nested
		// config structures. The repository sanitizes per field.
		$data = $this->request->get( 'data' );

		if ( ! $data || ! is_array( $data ) ) {
			return $this->error(
				__( 'No table data provided.', 'productbay' ),
				'productbay_missing_data',
				400
			);
		}

		$saved = $this->repository->save_table( $data );

		if ( ! $saved && ! empty( $data['id'] ) ) {
			return $this->error(
				__( 'Table not found.', 'productbay' ),
				'productbay_table_not_found',
				404
			);
		}

		if ( ! $saved ) {
			return $this->error(
				__( 'The table could not be saved.', 'productbay' ),
				'productbay_save_failed',
				500
			);
		}

		return $saved;
	}

	/**
	 * Delete a table by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request REST request containing 'id' parameter.
	 * @return array|\WP_Error Deletion result, or an error.
	 */
	public function destroy( $request ) {
		$id = $request['id'];

		// delete_table() returns false both for "not one of our tables" and for
		// a failed delete; either way it is not a success and must not be
		// reported as HTTP 200.
		if ( ! $this->repository->delete_table( $id ) ) {
			return $this->error(
				__( 'Table not found, or it could not be deleted.', 'productbay' ),
				'productbay_delete_failed',
				404
			);
		}

		return array(
			'deleted' => true,
			'id'      => (int) $id,
		);
	}
}
