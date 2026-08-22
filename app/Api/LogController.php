<?php
/**
 * REST API controller for activity log management.
 *
 * @package ProductBay
 * @since   1.2.0
 */

declare(strict_types=1);

namespace WpabProductBay\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WpabProductBay\Data\ActivityLog;
use WpabProductBay\Http\Request;

/**
 * Class LogController
 *
 * Provides REST API endpoints for viewing and managing activity logs.
 * All endpoints require manage_options capability.
 *
 * @package WpabProductBay\Api
 * @since   1.2.0
 */
class LogController extends ApiController {


	/**
	 * Initialize the controller.
	 *
	 * @since 1.2.0
	 *
	 * @param Request $request HTTP request handler.
	 */
	public function __construct( Request $request ) {
		parent::__construct( $request );
	}

	/**
	 * List log entries with filtering and pagination.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return array Log entries with pagination metadata.
	 */
	public function index( \WP_REST_Request $request ) {
		$args = array(
			'date'     => $request->get_param( 'date' ) ?: \current_time( 'Y-m-d' ),
			'level'    => $request->get_param( 'level' ) ?: '',
			'user'     => $request->get_param( 'user' ) ?: '',
			'search'   => $request->get_param( 'search' ) ?: '',
			'page'     => (int) ( $request->get_param( 'page' ) ?: 1 ),
			'per_page' => (int) ( $request->get_param( 'per_page' ) ?: 50 ),
		);

		return ActivityLog::get_logs( $args );
	}

	/**
	 * Export logs for a specific day as a downloadable file.
	 *
	 * @since 1.3.0
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return void
	 */
	public function export( \WP_REST_Request $request ): void {
		$date  = $request->get_param( 'date' ) ?: \current_time( 'Y-m-d' );
		$range = $request->get_param( 'range' ) ?: 'day';
		$dir   = ActivityLog::get_log_dir();

		if ( ! $dir ) {
			\wp_die( \esc_html__( 'Log directory not found.', 'productbay' ), \esc_html__( 'Export Failed', 'productbay' ), array( 'response' => 500 ) );
		}

		$files_to_stream = array();
		$filename        = 'productbay-log-' . $date . '.txt';

		if ( $range === 'day' ) {
			$files_to_stream = $this->get_daily_files( $dir, $date );
		} else {
			$days     = ( $range === 'week' ) ? 7 : 30;
			$filename = 'productbay-log-' . $range . '-' . $date . '.txt';

			for ( $i = 0; $i < $days; $i++ ) {
				$target_date     = gmdate( 'Y-m-d', strtotime( "-{$i} days", strtotime( $date ) ) );
				$files_to_stream = array_merge( $files_to_stream, $this->get_daily_files( $dir, $target_date ) );
			}
		}

		if ( empty( $files_to_stream ) ) {
			\wp_die( \esc_html__( 'No logs found for the selected range.', 'productbay' ), \esc_html__( 'Export Failed', 'productbay' ), array( 'response' => 404 ) );
		}

		// Security headers.
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );

		// Stream files.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		foreach ( $files_to_stream as $file ) {
			if ( file_exists( $file ) ) {
				echo "\n--- FILE: " . \esc_html( basename( $file ) ) . " ---\n";
				echo $wp_filesystem->get_contents( $file ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		$total_size = array_sum( array_map( 'filesize', $files_to_stream ) );

		ActivityLog::info(
			__( 'Logs exported', 'productbay' ),
			/* translators: 1: Log range (e.g. day or week), 2: Formatted file size */
			sprintf( __( 'Log range "%1$s" exported (Size: %2$s).', 'productbay' ), $range, size_format( $total_size ) )
		);

		exit;
	}

	/**
	 * Get all log files for a specific date (including rotated parts).
	 *
	 * @param string $dir  Log directory.
	 * @param string $date Date string.
	 * @return array List of file paths.
	 */
	private function get_daily_files( string $dir, string $date ): array {
		$pattern = $dir . '/' . $date . '*.log';
		$files   = glob( $pattern );
		return $files ?: array();
	}

	/**
	 * Clear all log files.
	 *
	 * @since 1.2.0
	 *
	 * @return array Result with count of deleted files.
	 */
	public function clear() {
		$deleted = ActivityLog::clear_all();

		ActivityLog::info(
			__( 'Logs cleared', 'productbay' ),
			/* translators: %d: Number of deleted log files */
			sprintf( __( '%d daily log file(s) were permanently deleted.', 'productbay' ), $deleted )
		);

		return array( 'success' => true );
	}
}
