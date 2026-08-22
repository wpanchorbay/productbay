<?php
/**
 * Main plugin bootstrap class — loads dependencies and initializes components.
 *
 * @package ProductBay
 */

declare(strict_types=1);

namespace WpabProductBay\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WpabProductBay\Admin\Admin;
use WpabProductBay\Blocks\BlockManager;
use WpabProductBay\Data\ActivityLog;
use WpabProductBay\Data\TableLogger;
use WpabProductBay\Data\SettingsLogger;

/**
 * Class Plugin
 *
 * The main plugin class which handles the loading of all dependencies.
 *
 * @package WpabProductBay\Core
 * @since 1.0.0
 */
class Plugin {


	/**
	 * Repository for table data access.
	 *
	 * @var \WpabProductBay\Data\TableRepository
	 * @since 1.0.0
	 */
	protected $table_repository;

	/**
	 * HTTP request wrapper instance.
	 *
	 * @var \WpabProductBay\Http\Request
	 * @since 1.0.0
	 */
	protected $request;

	/**
	 * Run the plugin.
	 *
	 * Loads dependencies and initializes all plugin components.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function run() {
		$this->load_dependencies();
		$this->init_components();
		$this->init_logging();

		// Register Custom Post Type.
		\add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Register the Table Custom Post Type.
	 *
	 * @since 1.2.0
	 */
	public function register_post_type() {
		register_post_type(
			'productbay_table',
			array(
				'label'              => 'ProductBay Tables',
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => false,
				'show_in_menu'       => false,
				'query_var'          => true,
				'rewrite'            => array(
					'slug'       => 'productbay/product-table',
					'with_front' => false,
				),
				'capability_type'    => 'post',
				'has_archive'        => false,
				'hierarchical'       => false,
				'supports'           => array( 'title' ),
			)
		);
	}

	/**
	 * Load all dependencies for the plugin.
	 *
	 * Initializes the TableRepository and Request classes.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function load_dependencies() {
		$this->table_repository = new \WpabProductBay\Data\TableRepository();
		$this->request          = new \WpabProductBay\Http\Request();
	}

	/**
	 * Initialize the plugin components.
	 *
	 * Sets up the Admin area, API Router, and Frontend renderers.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function init_components() {
		// Gutenberg Blocks (must run on 'init', not 'plugins_loaded').
		$block_manager = new BlockManager( $this->table_repository );
		\add_action( 'init', array( $block_manager, 'init' ) );

		// Admin Area.
		if ( is_admin() ) {
			$admin = new Admin( $this->table_repository, $this->request );
			\add_action( 'admin_menu', array( $admin, 'register_menu' ) );
			\add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_scripts' ) );

			// Admin bar - registered inside is_admin() since Admin class is instantiated here.
			// Priority 100 ensures it appears after core items.
			\add_action( 'admin_bar_menu', array( $admin, 'register_admin_bar' ), 100 );

			// Filter plugin name in plugins list.
			\add_filter( 'all_plugins', array( $admin, 'change_plugin_display_name' ) );

			// Add plugin action links.
			\add_filter( 'plugin_action_links_' . PRODUCTBAY_PLUGIN_BASENAME, array( $admin, 'add_plugin_action_links' ) );

			/**
			 * Fires after the admin component is initialized.
			 *
			 * @since 1.0.0
			 *
			 * @param Admin $admin The Admin instance.
			 */
			\do_action( 'productbay_admin_init', $admin );
		}

		// API Router.
		$router = new \WpabProductBay\Http\Router( $this->table_repository, $this->request );
		$router->init();

		// Frontend.
		$table_renderer = new \WpabProductBay\Frontend\TableRenderer( $this->table_repository );
		// TableRenderer no longer handles shortcode directy.

		$shortcode = new \WpabProductBay\Frontend\Shortcode( $this->table_repository );
		$shortcode->init();

		$ajax_renderer = new \WpabProductBay\Frontend\AjaxRenderer( $this->table_repository, $this->request );
		$ajax_renderer->init();

		/**
		 * Fires after all plugin components are loaded and initialized.
		 *
		 * This is the primary hook for add-on plugins to bootstrap themselves.
		 *
		 * @since 1.0.0
		 *
		 * @param Plugin $plugin The main Plugin instance.
		 */
		\do_action( 'productbay_loaded', $this );
	}

	/**
	 * Initialize the activity logging system.
	 *
	 * Registers the cron hook for log pruning and hooks into
	 * existing plugin actions to auto-log key events.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	private function init_logging() {
		// Cron: daily log file pruning.
		\add_action( ActivityLog::CRON_HOOK, array( ActivityLog::class, 'prune' ) );

		// Capture state before table save for detailed diffing.
		\add_filter(
			'productbay_before_save_table',
			function ( array $data, int $id ) {
				TableLogger::capture( $id );
				return $data;
			},
			10,
			2
		);

		// Auto-log: table saved (create or update).
		\add_action(
			'productbay_after_save_table',
			function ( int $post_id, array $data ) {
				$title  = $data['title'] ?? 'Untitled Table';
				$is_new = empty( $data['id'] ) || (int) $data['id'] === 0;

				if ( $is_new ) {
					$columns_count = ! empty( $data['columns'] ) ? count( $data['columns'] ) : 0;
					ActivityLog::success(
						'Table created',
						sprintf( 'Table "%s" (ID: %d) created with %d column(s).', $title, $post_id, $columns_count )
					);
				} else {
					ActivityLog::success(
						'Table updated',
						/* translators: 1: Table title, 2: Table ID */
						sprintf( __( 'Table "%1$s" (ID: %2$d) modified.', 'productbay' ), $title, $post_id ),
						TableLogger::get_summary( $data )
					);
				}
			},
			10,
			2
		);

		// Auto-log: table deleted.
		\add_action(
			'productbay_after_delete_table',
			function ( int $id ) {
				ActivityLog::info(
					'Table deleted',
					sprintf( 'Table (ID: %d) permanently deleted.', $id )
				);
			}
		);

		// Auto-log: settings updated.
		\add_filter(
			'pre_update_option_productbay_settings',
			function ( $value ) {
				SettingsLogger::capture();
				return $value;
			}
		);

		\add_action(
			'productbay_settings_updated',
			function ( array $settings ) {
				ActivityLog::success(
					'Settings updated',
					'Global plugin settings saved.',
					SettingsLogger::get_summary( $settings )
				);
			}
		);
	}
}
