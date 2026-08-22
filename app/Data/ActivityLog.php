<?php
/**
 * File-based activity logging engine.
 *
 * Writes one JSON-lines log file per day to wp-content/productbay-logs/.
 * Provides static convenience methods (info, success, warning, error)
 * and query methods for the REST API.
 *
 * @package ProductBay
 * @since   1.2.0
 */

declare(strict_types=1);

namespace WpabProductBay\Data;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ActivityLog
 *
 * High-performance, file-based activity logger.
 * Stores one log file per day in wp-content/productbay-logs/.
 *
 * @package WpabProductBay\Data
 * @since   1.2.0
 */
class ActivityLog {


	/**
	 * Log directory name inside wp-content.
	 */
	const DIR_NAME = 'productbay-logs';

	/**
	 * Number of days to retain log files.
	 */
	const RETENTION_DAYS = 30;

	/**
	 * Cron hook name for auto-pruning.
	 */
	const CRON_HOOK = 'productbay_prune_logs';

	/**
	 * Maximum size for a single log file (10MB).
	 */
	const MAX_FILE_SIZE = 10485760;

	/**
	 * Valid log levels.
	 */
	const LEVELS = array( 'info', 'success', 'warning', 'error' );

	// -------------------------------------------------------------------------
	// Static Convenience Methods
	// -------------------------------------------------------------------------

	/**
	 * Log an informational message.
	 *
	 * @since 1.2.0
	 *
	 * @param string $title   Short log title.
	 * @param string $details Optional detail text.
	 * @return void
	 */
	public static function info( string $title, string $details = '' ): void {
		self::log( 'info', $title, $details );
	}

	/**
	 * Log a success message.
	 *
	 * @since 1.2.0
	 *
	 * @param string $title   Short log title.
	 * @param string $details Optional detail text.
	 * @return void
	 */
	public static function success( string $title, string $details = '' ): void {
		self::log( 'success', $title, $details );
	}

	/**
	 * Log a warning message.
	 *
	 * @since 1.2.0
	 *
	 * @param string $title   Short log title.
	 * @param string $details Optional detail text.
	 * @return void
	 */
	public static function warning( string $title, string $details = '' ): void {
		self::log( 'warning', $title, $details );
	}

	/**
	 * Log an error message.
	 *
	 * @since 1.2.0
	 *
	 * @param string $title   Short log title.
	 * @param string $details Optional detail text.
	 * @return void
	 */
	public static function error( string $title, string $details = '' ): void {
		self::log( 'error', $title, $details );
	}

	// -------------------------------------------------------------------------
	// Core Write Method
	// -------------------------------------------------------------------------

	/**
	 * Write a log entry to today's log file.
	 *
	 * @since 1.2.0
	 *
	 * @param string $level   One of: info, success, warning, error.
	 * @param string $title   Short summary (max 255 chars).
	 * @param string $details Optional extended details.
	 * @return void
	 */
	public static function log( string $level, string $title, string $details = '' ): void {
		$settings = get_option( 'productbay_settings', array() );
		if ( isset( $settings['logging_enabled'] ) && ! $settings['logging_enabled'] ) {
			return;
		}

		$dir = self::get_log_dir();
		if ( ! $dir ) {
			return; // Could not create log directory.
		}

		// Ensure we don't fail if mbstring is missing.
		$safe_title = function_exists( 'mb_substr' )
			? mb_substr( $title, 0, 255 )
			: substr( $title, 0, 255 );

		$entry = array(
			'level'   => $level,
			'title'   => $safe_title,
			'details' => $details,
			'time'    => \current_time( 'c' ), // ISO 8601 in site timezone.
			'user'    => self::get_current_username(),
			'context' => array(
				'ip'     => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'url'    => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
				'method' => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '',
				'ua'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			),
		);

		// For errors, capture stack trace and environment state.
		if ( $level === 'error' ) {
			$entry['backtrace'] = self::get_backtrace();
			$entry['env']       = array(
				'php'        => PHP_VERSION,
				'wp'         => $GLOBALS['wp_version'] ?? 'unknown',
				'productbay' => defined( 'PRODUCTBAY_VERSION' ) ? PRODUCTBAY_VERSION : 'unknown',
			);
		}

		$file = self::get_current_log_file( $dir );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents(
			$file,
			\wp_json_encode( $entry, JSON_UNESCAPED_UNICODE ) . "\n",
			FILE_APPEND | LOCK_EX
		);

		/**
		 * Fires after a log entry is written.
		 *
		 * @since 1.2.0
		 *
		 * @param array $entry The log entry data.
		 */
		\do_action( 'productbay_log_created', $entry );
	}

	// -------------------------------------------------------------------------
	// Query Methods (for REST API)
	// -------------------------------------------------------------------------

	/**
	 * Get log entries with pagination and filtering.
	 *
	 * @since 1.2.0
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type string $date   Date in Y-m-d format. Default: today.
	 *     @type string $level  Filter by level. Default: '' (all).
	 *     @type string $search Search in title and details. Default: ''.
	 *     @type int    $page   Page number (1-indexed). Default: 1.
	 *     @type int    $per_page Items per page. Default: 50.
	 * }
	 * @return array{entries: array, total: int, page: int, per_page: int, available_dates: array}
	 */
	public static function get_logs( array $args = array() ): array {
		$date     = $args['date'] ?? \current_time( 'Y-m-d' );
		$level    = $args['level'] ?? '';
		$search   = $args['search'] ?? '';
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 50 ) ) );

		$dir = self::get_log_dir();

		// Get available dates (log files that exist).
		$available_dates = self::get_available_dates();

		$file = $dir . '/' . $date . '.log';
		if ( ! $dir || ! file_exists( $file ) ) {
			return array(
				'entries'         => array(),
				'total'           => 0,
				'page'            => $page,
				'per_page'        => $per_page,
				'available_dates' => $available_dates,
			);
		}

		// Read and parse the log file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $file );
		if ( false === $raw || '' === $raw ) {
			return array(
				'entries'         => array(),
				'total'           => 0,
				'page'            => $page,
				'per_page'        => $per_page,
				'available_dates' => $available_dates,
			);
		}

		$lines   = array_filter( explode( "\n", trim( $raw ) ) );
		$entries = array();
		$users   = array();

		foreach ( $lines as $line ) {
			$entry = json_decode( $line, true );
			if ( ! is_array( $entry ) ) {
				continue;
			}

			// Collect unique users.
			$user_name = $entry['user'] ?? '';
			if ( $user_name && ! in_array( $user_name, $users, true ) ) {
				$users[] = $user_name;
			}

			// Filter by level.
			if ( $level && ( $entry['level'] ?? '' ) !== $level ) {
				continue;
			}

			// Filter by user.
			$filter_user = $args['user'] ?? '';
			if ( $filter_user && ( $entry['user'] ?? '' ) !== $filter_user ) {
				continue;
			}

			// Filter by search.
			if ( $search ) {
				$haystack = strtolower( ( $entry['title'] ?? '' ) . ' ' . ( $entry['details'] ?? '' ) );
				if ( false === strpos( $haystack, strtolower( $search ) ) ) {
					continue;
				}
			}

			$entries[] = $entry;
		}

		// Reverse so newest entries appear first.
		$entries = array_reverse( $entries );
		$total   = count( $entries );

		sort( $users );

		// Paginate.
		$offset  = ( $page - 1 ) * $per_page;
		$entries = array_slice( $entries, $offset, $per_page );

		return array(
			'entries'         => $entries,
			'total'           => $total,
			'page'            => $page,
			'per_page'        => $per_page,
			'available_dates' => $available_dates,
			'users'           => $users, // Metadata for UI filters.
		);
	}

	/**
	 * Get list of dates that have log files.
	 *
	 * @since 1.2.0
	 *
	 * @return array List of date strings (Y-m-d), newest first.
	 */
	public static function get_available_dates(): array {
		$dir = self::get_log_dir();
		if ( ! $dir || ! is_dir( $dir ) ) {
			return array();
		}

		$files = glob( $dir . '/*.log' );
		if ( empty( $files ) ) {
			return array();
		}

		$dates = array();
		foreach ( $files as $file ) {
			$basename = basename( $file, '.log' );
			// Validate date format.
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $basename ) ) {
				$dates[] = $basename;
			}
		}

		rsort( $dates ); // Newest first.
		return $dates;
	}

	/**
	 * Clear all log files.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of files deleted.
	 */
	public static function clear_all(): int {
		$dir = self::get_log_dir();
		if ( ! $dir || ! is_dir( $dir ) ) {
			return 0;
		}

		$files   = glob( $dir . '/*.log' );
		$deleted = 0;

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( @unlink( $file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					++$deleted;
				}
			}
		}

		return $deleted;
	}

	// -------------------------------------------------------------------------
	// Pruning
	// -------------------------------------------------------------------------

	/**
	 * Delete log files older than the retention period.
	 *
	 * Called by WP-Cron daily via the productbay_prune_logs hook.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of files pruned.
	 */
	public static function prune(): int {
		$settings       = get_option( 'productbay_settings', array() );
		$retention_days = (int) ( $settings['log_retention'] ?? self::RETENTION_DAYS );
		$retention_days = (int) \apply_filters( 'productbay_log_retention_days', $retention_days );

		$dir = self::get_log_dir();
		if ( ! $dir || ! is_dir( $dir ) ) {
			return 0;
		}

		$files  = glob( $dir . '/*.log' );
		$pruned = 0;
		$cutoff = strtotime( "-{$retention_days} days" );

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				$basename  = basename( $file, '.log' );
				$file_time = strtotime( $basename );

				if ( false !== $file_time && $file_time < $cutoff ) {
					if ( @unlink( $file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
						++$pruned;
					}
				}
			}
		}

		if ( $pruned > 0 ) {
			self::info( 'Logs pruned', sprintf( '%d log file(s) older than %d days removed.', $pruned, $retention_days ) );
		}

		return $pruned;
	}

	// -------------------------------------------------------------------------
	// Directory & Security
	// -------------------------------------------------------------------------

	/**
	 * Get (and create if needed) the log directory path.
	 *
	 * Creates the directory with .htaccess and index.php for security.
	 *
	 * @since 1.2.0
	 *
	 * @return string|false Absolute path to log directory, or false on failure.
	 */
	public static function get_log_dir() {
		$dir = WP_CONTENT_DIR . '/' . self::DIR_NAME;

		if ( ! is_dir( $dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			if ( ! @mkdir( $dir, 0755, true ) ) {
				return false;
			}

			// Security: block direct web access.
			self::write_security_files( $dir );
		}

		return $dir;
	}

	/**
	 * Write .htaccess and index.php to prevent direct file access.
	 *
	 * @since 1.2.0
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private static function write_security_files( string $dir ): void {
		// .htaccess — deny all.
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $htaccess, "deny from all\n" );
		}

		// index.php — silence is golden.
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Remove the entire log directory.
	 *
	 * Used during plugin uninstall.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public static function delete_log_dir(): void {
		$dir = WP_CONTENT_DIR . '/' . self::DIR_NAME;

		if ( ! is_dir( $dir ) ) {
			return;
		}

		// Delete all files in the directory.
		$files = glob( $dir . '/*' );
		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					@unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
		}

		// Also delete hidden files (.htaccess).
		$hidden = glob( $dir . '/.*' );
		if ( ! empty( $hidden ) ) {
			foreach ( $hidden as $file ) {
				if ( is_file( $file ) ) {
					@unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
		}

		@rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Get the current WordPress username.
	 *
	 * @since 1.2.0
	 *
	 * @return string Username or 'system' for cron/CLI.
	 */
	private static function get_current_username(): string {
		$user = \wp_get_current_user();
		if ( $user && $user->ID > 0 ) {
			return $user->user_login;
		}

		// WP-Cron, CLI, or unauthenticated context.
		if ( \defined( 'DOING_CRON' ) && DOING_CRON ) {
			return 'cron';
		}

		if ( \defined( 'WP_CLI' ) && WP_CLI ) {
			return 'wp-cli';
		}

		return 'system';
	}

	/**
	 * Get the current log file path with size-based rotation support.
	 *
	 * Returns YYYY-MM-DD.log, or YYYY-MM-DD.1.log if the first is full.
	 *
	 * @since 1.3.0
	 *
	 * @param string $dir Log directory.
	 * @return string Full path to log file.
	 */
	private static function get_current_log_file( string $dir ): string {
		$base_name = \current_time( 'Y-m-d' );
		$file      = $dir . '/' . $base_name . '.log';
		$index     = 0;

		while ( file_exists( $file ) && filesize( $file ) >= self::MAX_FILE_SIZE ) {
			++$index;
			$file = $dir . '/' . $base_name . '.' . $index . '.log';
		}

		return $file;
	}

	/**
	 * Capture a truncated, safe stack trace.
	 *
	 * @since 1.3.0
	 *
	 * @return array Truncated backtrace frames.
	 */
	private static function get_backtrace(): array {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$frames = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
		$clean  = array();

		// Skip ActivityLog methods.
		foreach ( $frames as $frame ) {
			if ( isset( $frame['class'] ) && $frame['class'] === self::class ) {
				continue;
			}
			$clean[] = array(
				'file'     => isset( $frame['file'] ) ? str_replace( ABSPATH, '', $frame['file'] ) : 'unknown',
				'line'     => $frame['line'] ?? 0,
				'function' => $frame['function'] ?? '',
			);
		}

		return array_slice( $clean, 0, 10 );
	}
}
