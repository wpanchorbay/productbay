<?php
/**
 * Table change tracking helper.
 *
 * Compares old and new table states to generate human-readable
 * change summaries for the activity log.
 *
 * @package ProductBay
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WpabProductBay\Data;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TableLogger
 *
 * Handles the differencing logic for table updates.
 */
class TableLogger {

	/**
	 * Stored "before" state.
	 *
	 * @var array|null
	 */
	private static $old_state = null;

	/**
	 * Capture the current state of a table before it is updated.
	 *
	 * @param int $id Table ID.
	 * @return void
	 */
	public static function capture( int $id ) {
		if ( $id <= 0 ) {
			self::$old_state = null;
			return;
		}

		$repo            = new TableRepository();
		self::$old_state = $repo->get_table( $id );
	}

	/**
	 * Generate a summary of changes between the captured state and new data.
	 *
	 * @param array $new_data The new table data being saved.
	 * @return string Human-readable summary of changes.
	 */
	public static function get_summary( array $new_data ): string {
		if ( ! is_array( self::$old_state ) ) {
			return __( 'Internal change tracking data not available.', 'productbay' );
		}

		$changes = array();
		$old     = self::$old_state;

		// 1. Title
		if ( isset( $new_data['title'] ) && $new_data['title'] !== $old['title'] ) {
			$changes[] = sprintf(
				/* translators: 1: Old title, 2: New title */
				__( 'Title changed: "%1$s" → "%2$s"', 'productbay' ),
				$old['title'],
				$new_data['title']
			);
		}

		// 2. Status
		if ( isset( $new_data['status'] ) && $new_data['status'] !== $old['status'] ) {
			$changes[] = sprintf(
				/* translators: 1: Old status, 2: New status */
				__( 'Status changed: %1$s → %2$s', 'productbay' ),
				ucfirst( $old['status'] ),
				ucfirst( $new_data['status'] )
			);
		}

		// 3. Source
		$old_source = $old['source'] ?? array();
		$new_source = $new_data['source'] ?? array();
		if ( $old_source !== $new_source ) {
			$old_type = $old_source['type'] ?? 'all';
			$new_type = $new_source['type'] ?? 'all';

			if ( $old_type !== $new_type ) {
				$changes[] = sprintf(
					/* translators: 1: Old source type, 2: New source type */
					__( 'Product source changed from %1$s to %2$s', 'productbay' ),
					ucfirst( $old_type ),
					ucfirst( $new_type )
				);
			} else {
				$changes[] = __( 'Product source selection updated.', 'productbay' );
			}
		}

		// 4. Columns
		$old_cols = $old['columns'] ?? array();
		$new_cols = $new_data['columns'] ?? array();
		if ( $old_cols !== $new_cols ) {
			$old_count = count( $old_cols );
			$new_count = count( $new_cols );

			if ( $old_count !== $new_count ) {
				$changes[] = sprintf(
					/* translators: 1: Old count, 2: New count */
					__( 'Column count changed: %1$d → %2$d', 'productbay' ),
					$old_count,
					$new_count
				);
			} else {
				$changes[] = __( 'Table columns reordered or modified.', 'productbay' );
			}
		}

		// 5. Settings & Style
		$old_settings = $old['settings'] ?? array();
		$new_settings = $new_data['settings'] ?? array();
		if ( $old_settings !== $new_settings ) {
			$changes[] = __( 'Display settings updated.', 'productbay' );
		}

		$old_style = $old['style'] ?? array();
		$new_style = $new_data['style'] ?? array();
		if ( $old_style !== $new_style ) {
			$changes[] = __( 'Styling tokens modified.', 'productbay' );
		}

		if ( empty( $changes ) ) {
			return __( 'No significant field changes detected (likely a metadata or timestamp update).', 'productbay' );
		}

		return implode(
			"\n",
			array_map(
				function ( $c ) {
					return '• ' . $c;
				},
				$changes
			)
		);
	}
}
