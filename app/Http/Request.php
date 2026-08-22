<?php
/**
 * HTTP request wrapper with JSON input parsing and parameter access.
 *
 * @package ProductBay
 */

declare(strict_types=1);

namespace WpabProductBay\Http;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Request
 *
 * HTTP request wrapper that handles JSON input parsing and provides
 * sanitized access to request parameters. Transparently merges JSON
 * body data into \$_REQUEST for unified access.
 *
 * @since   1.0.0
 * @package WpabProductBay\Http
 */
class Request {


	/**
	 * Raw JSON data from the request body.
	 *
	 * Preserved unsanitized for complex nested structures (e.g. table config).
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $rawData = array();

	/**
	 * Constructor. Parses JSON input from the request body.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->handleJsonInput();
	}

	/**
	 * Handle JSON input for REST API requests.
	 *
	 * @since 1.0.0
	 */
	private function handleJsonInput() {
		if (
		isset( $_SERVER['CONTENT_TYPE'] ) &&
		strpos( sanitize_text_field( wp_unslash( $_SERVER['CONTENT_TYPE'] ) ), 'application/json' ) !== false
		) {
			$input = file_get_contents( 'php://input' );
			$data  = json_decode( $input, true );

			if ( is_array( $data ) ) {
				// Store raw data in object property to prevent loss.
				$this->rawData = $data;
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is handled at the endpoint level
				$_REQUEST = array_merge( $_REQUEST, $data );
			}
		}
	}
	/**
	 * Get a value from $_REQUEST with sanitization.
	 * For 'data' key, returns raw unsanitized data to preserve complex structures.
	 *
	 * @param string $key     Parameter name.
	 * @param mixed  $default Default value if key is not set.
	 * @return mixed Sanitized value or default.
	 * @since 1.0.0
	 */
	public function get( $key, $default = null ) {
		// Special handling for 'data' key - return raw data without sanitization.
		if ( $key === 'data' && isset( $this->rawData['data'] ) ) {
			return $this->rawData['data'];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is handled at the endpoint level
		if ( ! isset( $_REQUEST[ $key ] ) ) {
			return $default;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is sanitized by $this->sanitize() which calls wp_unslash() and sanitize_text_field()
		return $this->sanitize( $_REQUEST[ $key ] );
	}

	/**
	 * Sanitize input.
	 * Preserves complex data structures (arrays/objects) for API data.
	 *
	 * @param mixed $value Raw input value.
	 * @return mixed Sanitized value.
	 * @since 1.0.0
	 */
	public function sanitize( $value ) {
		// Preserve arrays and objects - recursively sanitize array values.
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'sanitize' ), $value );
		}

		// Preserve booleans.
		if ( is_bool( $value ) ) {
			return $value;
		}

		// Preserve numeric values.
		if ( is_numeric( $value ) ) {
			return $value;
		}

		// Preserve null.
		if ( is_null( $value ) ) {
			return $value;
		}

		// Only sanitize actual strings.
		if ( is_string( $value ) ) {
			return \sanitize_text_field( wp_unslash( $value ) );
		}

		// For any other type, return as-is.
		return $value;
	}

	/**
	 * Get integer value.
	 *
	 * @param string $key     Parameter name.
	 * @param int    $default Default value if key is not set.
	 * @return int
	 * @since 1.0.0
	 */
	public function int( $key, $default = 0 ) {
		$value = $this->get( $key, $default );
		return intval( $value );
	}
}
