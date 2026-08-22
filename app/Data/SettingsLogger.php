<?php
/**
 * Global settings change tracking helper.
 *
 * Compares old and new global plugin settings to generate
 * human-readable change summaries for the activity log.
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
 * Class SettingsLogger
 *
 * Handles the differencing logic for global plugin settings updates.
 */
class SettingsLogger {

	/**
	 * Stored "before" state.
	 *
	 * @var array|null
	 */
	private static $old_state = null;

	/**
	 * Capture the current global settings before they are updated.
	 *
	 * @return void
	 */
	public static function capture() {
		self::$old_state = get_option( 'productbay_settings', array() );
	}

	/**
	 * Generate a summary of changes between the captured state and new settings.
	 *
	 * @param array $new_settings The new settings array being saved.
	 * @return string|null Human-readable summary of changes, or null if no changes.
	 */
	public static function get_summary( array $new_settings ) {
		if ( ! is_array( self::$old_state ) ) {
			return null;
		}

		$changes = array();
		$old     = self::$old_state;

		// 1. General Settings
		$comparisons = array(
			'add_to_cart_text'    => __( '"Add to Cart" text', 'productbay' ),
			'products_per_page'   => __( 'Products per page', 'productbay' ),
			'show_admin_bar'      => __( 'Admin bar visibility', 'productbay' ),
			'delete_on_uninstall' => __( 'Delete data on uninstall', 'productbay' ),
		);

		foreach ( $comparisons as $key => $label ) {
			if ( isset( $new_settings[ $key ] ) && isset( $old[ $key ] ) && $new_settings[ $key ] !== $old[ $key ] ) {
				$old_val = is_bool( $old[ $key ] ) ? ( $old[ $key ] ? 'On' : 'Off' ) : $old[ $key ];
				$new_val = is_bool( $new_settings[ $key ] ) ? ( $new_settings[ $key ] ? 'On' : 'Off' ) : $new_settings[ $key ];

				$changes[] = sprintf(
					/* translators: 1: Setting label, 2: Old value, 3: New value */
					__( '%1$s changed: %2$s → %3$s', 'productbay' ),
					$label,
					$old_val,
					$new_val
				);
			}
		}

		// 2. Design Defaults
		$old_design = $old['design'] ?? array();
		$new_design = $new_settings['design'] ?? array();
		if ( $old_design !== $new_design ) {
			$changes[] = __( 'Design defaults updated (colors/borders).', 'productbay' );
		}

		// 3. Table Defaults
		$old_table_defaults = $old['table_defaults'] ?? array();
		$new_table_defaults = $new_settings['table_defaults'] ?? array();
		if ( $old_table_defaults !== $new_table_defaults ) {
			$changes[] = __( 'Default table configuration updated.', 'productbay' );
		}

		if ( empty( $changes ) ) {
			return null;
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
