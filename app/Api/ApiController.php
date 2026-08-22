<?php
/**
 * Abstract base controller providing standardized REST API responses.
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

/**
 * Class ApiController
 *
 * Base controller for all API endpoints.
 * Handles common dependencies and standardized responses.
 *
 * @package WpabProductBay\Api
 * @since 1.0.0
 */
abstract class ApiController {


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
	 * @param Request $request HTTP request instance.
	 * @since 1.0.0
	 */
	public function __construct( Request $request ) {
		$this->request = $request;
	}

	/**
	 * Standard success response structure.
	 *
	 * @param mixed $data Response payload.
	 * @param int   $status HTTP status code.
	 * @return \WP_REST_Response
	 * @since 1.0.0
	 */
	protected function success( $data = array(), $status = 200 ) {
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Standard error response structure.
	 *
	 * @param string $message Error description.
	 * @param string $code Error code identifier.
	 * @param int    $status HTTP status code.
	 * @return \WP_Error
	 * @since 1.0.0
	 */
	protected function error( $message, $code = 'bad_request', $status = 400 ) {
		return new \WP_Error(
			$code,
			$message,
			array( 'status' => $status )
		);
	}
}
