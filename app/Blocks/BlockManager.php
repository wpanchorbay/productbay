<?php
/**
 * Orchestrates registration of all ProductBay Gutenberg blocks.
 *
 * @package ProductBay
 */

declare(strict_types=1);

namespace WpabProductBay\Blocks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WpabProductBay\Data\TableRepository;

/**
 * Class BlockManager
 *
 * Registers all Gutenberg blocks and wires their PHP render callbacks.
 * Must be initialised on the WordPress `init` hook.
 *
 * @package WpabProductBay\Blocks
 * @since   1.1.0
 */
class BlockManager {

	/**
	 * Repository for table data access.
	 *
	 * @var TableRepository
	 * @since 1.1.0
	 */
	protected $table_repository;

	/**
	 * Constructor.
	 *
	 * @param TableRepository $repository Table data repository.
	 * @since 1.1.0
	 */
	public function __construct( TableRepository $repository ) {
		$this->table_repository = $repository;
	}

	/**
	 * Register all blocks.
	 *
	 * Called from Plugin::init_components() on the `init` action.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function init() {
		$product_table_block     = new ProductTableBlock( $this->table_repository );
		$tab_product_table_block = new TabProductTableBlock( $this->table_repository );

		// Register the shared frontend CSS handle.
		$css_path = PRODUCTBAY_PATH . 'assets/css/frontend.css';
		\wp_register_style(
			'productbay-frontend',
			PRODUCTBAY_URL . 'assets/css/frontend.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : PRODUCTBAY_VERSION
		);

		// Register the tabs CSS handle.
		$tabs_css_path = PRODUCTBAY_PATH . 'assets/css/block-tabs.css';
		\wp_register_style(
			'productbay-tabs',
			PRODUCTBAY_URL . 'assets/css/block-tabs.css',
			array(),
			file_exists( $tabs_css_path ) ? (string) filemtime( $tabs_css_path ) : PRODUCTBAY_VERSION
		);

		\register_block_type(
			PRODUCTBAY_PATH . 'blocks/product-table',
			array(
				'render_callback'      => array( $product_table_block, 'render' ),
				'style_handles'        => array( 'productbay-frontend' ),
				'editor_style_handles' => array( 'productbay-frontend' ),
			)
		);

		\register_block_type(
			PRODUCTBAY_PATH . 'blocks/tab-product-table',
			array(
				'render_callback'      => array( $tab_product_table_block, 'render' ),
				'style_handles'        => array( 'productbay-frontend', 'productbay-tabs' ),
				'editor_style_handles' => array( 'productbay-frontend', 'productbay-tabs' ),
			)
		);

		// Force the styles into the editor's iFrame for ServerSideRender accuracy.
		\add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_preview_styles' ) );

		// Inject core + add-on CSS directly into the block editor's iframe via WP's settings API.
		\add_filter( 'block_editor_settings_all', array( $this, 'inject_editor_iframe_styles' ) );
	}

	/**
	 * Inject CSS into the block editor's iframe via the settings API.
	 *
	 * This uses the `block_editor_settings_all` filter — the same mechanism
	 * WordPress core uses internally — to inject CSS content directly into the
	 * iframed editor. This is more reliable than `add_editor_style()`, which
	 * can fail depending on theme support configuration.
	 *
	 * @since 1.1.0
	 *
	 * @param array $settings Editor settings array.
	 * @return array Modified editor settings.
	 */
	public function inject_editor_iframe_styles( $settings ) {
		// Core ProductBay CSS files to inject into the editor iframe.
		$css_paths = array(
			PRODUCTBAY_PATH . 'assets/css/frontend.css',
			PRODUCTBAY_PATH . 'assets/css/block-tabs.css',
		);

		/**
		 * Allows add-on plugins to inject their frontend CSS into the block
		 * editor's iframe. Return absolute file system paths to CSS files.
		 *
		 * Example (in Pro plugin):
		 *   add_filter('productbay_block_editor_css_paths', function ($paths) {
		 *       $paths[] = PRODUCTBAY_PRO_PATH . 'assets/css/productbay-pro-frontend.css';
		 *       return $paths;
		 *   });
		 *
		 * @since 1.1.0
		 *
		 * @param string[] $paths Array of absolute CSS file paths.
		 */
		$css_paths = \apply_filters( 'productbay_block_editor_css_paths', $css_paths );

		foreach ( $css_paths as $path ) {
			if ( file_exists( $path ) ) {
				$settings['styles'][] = array(
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local CSS file, not a remote URL.
					'css' => file_get_contents( $path ),
				);
			}
		}

		// Editor-specific overrides for JS-dependent UI elements that can't
		// function correctly in the static ServerSideRender preview.
		$settings['styles'][] = array(
			'css' => '.productbay-price-inputs input[type="number"] { pointer-events: none; }',
		);

		return $settings;
	}

	/**
	 * Enqueue frontend styles in the block editor context.
	 *
	 * Also fires the same hook that add-ons (e.g. Pro) use to enqueue their
	 * frontend CSS, ensuring it is available in the editor page as well.
	 *
	 * @since 1.1.0
	 */
	public function enqueue_editor_preview_styles() {
		\wp_enqueue_style( 'productbay-frontend' );
		\wp_enqueue_style( 'productbay-tabs' );

		// Pass localization data to the block editor JS so we don't rely on relative URLs.
		\wp_add_inline_script(
			'wp-blocks',
			'window.productbayEditorData = ' . wp_json_encode(
				array(
					'adminUrl' => admin_url( 'admin.php?page=productbay' ),
				)
			) . ';',
			'before'
		);

		/**
		 * Fires when block editor preview assets are enqueued.
		 *
		 * The same hook used on the frontend — add-ons that enqueue CSS here
		 * will have their styles available in the editor's outer page context.
		 *
		 * @since 1.1.0
		 */
		\do_action( 'productbay_enqueue_frontend_assets' );
	}
}
