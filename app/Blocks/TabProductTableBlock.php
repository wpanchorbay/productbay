<?php
/**
 * Server-side render callback for the Tab – Product Table block.
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
use WpabProductBay\Frontend\TableRenderer;

/**
 * Class TabProductTableBlock
 *
 * Handles the render_callback for the `productbay/tab-product-table` block.
 * Wraps multiple TableRenderer outputs in accessible tab markup and enqueues
 * a lightweight vanilla-JS tab-switching script.
 *
 * @package WpabProductBay\Blocks
 * @since   1.1.0
 */
class TabProductTableBlock {

	/**
	 * Repository for table data access.
	 *
	 * @var TableRepository
	 * @since 1.1.0
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param TableRepository $repository Table data repository.
	 * @since 1.1.0
	 */
	public function __construct( TableRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Render the Tab – Product Table block.
	 *
	 * @param array  $attributes Block attributes from the editor.
	 * @param string $content    Inner block content (unused for dynamic blocks).
	 * @return string Rendered HTML.
	 * @since 1.1.0
	 */
	public function render( array $attributes, string $content ): string {
		$table_ids  = array_map( 'absint', $attributes['tableIds'] ?? array() );
		$tab_labels = $attributes['tabLabels'] ?? array();
		$active_tab = absint( $attributes['activeTab'] ?? 0 );

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$is_editor   = defined( 'REST_REQUEST' ) && REST_REQUEST && strpos( $request_uri, '/block-renderer/' ) !== false;

		if ( empty( $table_ids ) ) {
			return $this->get_mockup();
		}

		// Ensure active tab index is within bounds.
		if ( $active_tab >= count( $table_ids ) ) {
			$active_tab = 0;
		}

		$this->enqueue_assets();

		// Unique wrapper ID prevents JS conflicts when multiple tab blocks exist on one page.
		$block_id = 'pb-tabs-' . wp_unique_id();

		$renderer = new TableRenderer( $this->repository );

		ob_start();

		// get_block_wrapper_attributes() automatically includes classes/styles from block supports (Color, Typography, etc.).
		$wrapper_attributes = \get_block_wrapper_attributes(
			array(
				'class' => 'productbay-tabs-block',
				'id'    => $block_id,
			)
		);

		// Only suppress clicks in the editor preview (ServerSideRender) — on the frontend all interactions must work normally.
		$onclick = $is_editor ? ' onclick="return false;"' : '';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() is pre-escaped by WordPress core, and $onclick is a trusted constant string.
		echo '<div ' . $wrapper_attributes . $onclick . '>';

		// Tab List — accessible navigation.
		echo '<div class="productbay-tabs-nav" role="tablist">';
		foreach ( $table_ids as $index => $table_id ) {
			$label = isset( $tab_labels[ $index ] ) && $tab_labels[ $index ] !== ''
				? $tab_labels[ $index ]
				: sprintf(
					/* translators: %d: tab index number */
					esc_html__( 'Tab %d', 'productbay' ),
					$index + 1
				);
			$tab_id    = esc_attr( $block_id . '-tab-' . $index );
			$panel_id  = esc_attr( $block_id . '-panel-' . $index );
			$is_active = $index === $active_tab;

			echo '<button'
				. ' id="' . esc_attr( $tab_id ) . '"'
				. ' class="productbay-tab-button' . ( $is_active ? ' is-active' : '' ) . '"'
				. ' role="tab"'
				. ' aria-selected="' . ( $is_active ? 'true' : 'false' ) . '"'
				. ' aria-controls="' . esc_attr( $panel_id ) . '"'
				. ' tabindex="' . ( $is_active ? '0' : '-1' ) . '"'
				. '>'
				. esc_html( $label )
				. '</button>';
		}
		echo '</div>'; // .productbay-tabs-nav

		// Tab Panels — one per table.
		foreach ( $table_ids as $index => $table_id ) {
			$table     = $this->repository->get_table( $table_id );
			$tab_id    = esc_attr( $block_id . '-tab-' . $index );
			$panel_id  = esc_attr( $block_id . '-panel-' . $index );
			$is_active = $index === $active_tab;

			echo '<div'
				. ' id="' . esc_attr( $panel_id ) . '"'
				. ' class="productbay-tab-panel' . ( $is_active ? ' is-active' : '' ) . '"'
				. ' role="tabpanel"'
				. ' aria-labelledby="' . esc_attr( $tab_id ) . '"'
				. ( $is_active ? '' : ' hidden' )
				. '>';

			if ( ! $table ) {
				if ( $is_editor || is_admin() || is_preview() ) {
					echo '<div style="padding:16px; border:1px dashed #fca5a5; background:#fef2f2; border-radius:8px; color:#991b1b; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; font-size: 14px;">'
						. '<div style="font-weight:700; margin-bottom:8px;">⚠️ ProductBay Tabbed Table</div>'
						. sprintf(
							/* translators: %d: table ID */
							\esc_html__( 'The selected table (ID: %d) could not be found. It may have been deleted. Please select a different table or update the block settings.', 'productbay' ),
							absint( $table_id )
						)
						. '</div>';
				} else {
					echo '<p>' . esc_html__( 'Table not found.', 'productbay' ) . '</p>';
				}
			} else {
				$status = $table['status'] ?? 'draft';
				if ( 'publish' !== $status && ! is_preview() && ! is_admin() ) {
					if ( \current_user_can( 'manage_options' ) ) {
						echo '<p style="padding:12px 16px;background:#fef3cd;border:1px solid #e9b006;border-radius:4px;color:#664d03;font-size:14px;">'
							. sprintf(
								/* translators: 1: table title, 2: table status, e.g. "draft" */
								\esc_html__( 'ProductBay: Table "%1$s" is not visible to visitors (status: %2$s). It will appear here once it is published.', 'productbay' ),
								\esc_html( $table['title'] ?? (string) $table_id ),
								\esc_html( TableRepository::status_label( $status ) )
							)
							. '</p>';
					}
				} else {
					$html = $renderer->render( $table );
					if ( empty( trim( $html ) ) && ( $is_editor || is_admin() || is_preview() ) ) {
						echo '<div style="padding:16px; border:1px dashed #fcd34d; background:#fffbeb; border-radius:8px; color:#92400e; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; font-size: 14px;">'
							. '<div style="font-weight:700; margin-bottom:8px;">⚠️ ProductBay Tabbed Table</div>'
							. sprintf(
								/* translators: %s: table title */
								\esc_html__( 'The table "%s" rendered completely empty. Please check your table configuration, product source, and filters.', 'productbay' ),
								\esc_html( $table['title'] )
							)
							. '</div>';
					} else {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- TableRenderer output is safe.
						echo $html;
					}
				}
			}

			echo '</div>'; // .productbay-tab-panel
		}

		echo '</div>'; // .productbay-tabs-block

		return ob_get_clean();
	}

	/**
	 * Returns a high-fidelity HTML mockup for the Tabbed Gutenberg inserter preview.
	 *
	 * @param bool $content_only If true, only returns the inner table mockup without the tab wrapper.
	 * @since 1.1.0
	 * @return string Sample HTML.
	 */
	private function get_mockup( bool $content_only = false ): string {
		if ( ! is_admin() && ! is_preview() ) {
			return '';
		}

		ob_start();
		if ( ! $content_only ) {
			?>
			<div class="productbay-tabs-mockup" style="max-width:100%; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; background:#fff; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; box-shadow:0 4px 12px rgba(0,0,0,0.05);">
				<div style="display:flex; background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:0 8px;">
					<div style="padding:12px 16px; font-size:12px; font-weight:700; color:#4f46e5; border-bottom:2px solid #4f46e5;">Laptops</div>
					<div style="padding:12px 16px; font-size:12px; font-weight:500; color:#64748b;">Tablets</div>
					<div style="padding:12px 16px; font-size:12px; font-weight:500; color:#64748b;">Accessories</div>
				</div>
				<div style="padding:16px;">
			<?php
		}
		?>
		<div style="display:grid; grid-template-columns: 40px 1fr 60px; gap:12px; align-items:center; border-bottom:1px solid #f1f5f9; padding:8px 0;">
			<div style="width:40px; height:40px; background:#f1f5f9; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:9px;">IMG</div>
			<div style="font-size:12px; font-weight:500; color:#334155;">MacBook Pro M3</div>
			<div style="font-size:12px; font-weight:700; color:#4f46e5; text-align:right;">$1,999</div>
		</div>
		<div style="display:grid; grid-template-columns: 40px 1fr 60px; gap:12px; align-items:center; padding:8px 0;">
			<div style="width:40px; height:40px; background:#f1f5f9; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:9px;">IMG</div>
			<div style="font-size:12px; font-weight:500; color:#334155;">Dell XPS 13</div>
			<div style="font-size:12px; font-weight:700; color:#4f46e5; text-align:right;">$1,249</div>
		</div>
		<?php
		if ( ! $content_only ) {
			?>
				</div>
			</div>
			<?php
		}
		return ob_get_clean();
	}

	/**
	 * Enqueue all assets required by the tabbed block.
	 *
	 * Enqueues the shared frontend assets plus the lightweight tab-switcher
	 * script that is only loaded when this block is present on the page.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		// Shared ProductBay frontend assets (CSS + cart JS).
		\wp_enqueue_style(
			'productbay-frontend',
			PRODUCTBAY_URL . 'assets/css/frontend.css',
			array(),
			(string) filemtime( PRODUCTBAY_PATH . 'assets/css/frontend.css' )
		);

		\wp_enqueue_style(
			'productbay-tabs',
			PRODUCTBAY_URL . 'assets/css/block-tabs.css',
			array(),
			(string) filemtime( PRODUCTBAY_PATH . 'assets/css/block-tabs.css' )
		);

		\wp_enqueue_script(
			'productbay-frontend',
			PRODUCTBAY_URL . 'assets/js/frontend.js',
			// 'wc-cart-fragments' powers the live cart refresh (header/mini-cart) when
			// we trigger 'wc_fragment_refresh' after an AJAX add-to-cart. WooCommerce
			// registers it but only enqueues it via the Cart widget, so depend on it
			// here to guarantee it loads on any theme.
			array( 'jquery', 'wc-cart-fragments' ),
			(string) filemtime( PRODUCTBAY_PATH . 'assets/js/frontend.js' ),
			true
		);

		if ( ! \wp_script_is( 'productbay-frontend', 'localized' ) ) {
			\wp_localize_script(
				'productbay-frontend',
				'productbay_frontend',
				array(
					'ajaxurl'               => \admin_url( 'admin-ajax.php' ),
					'nonce'                 => \wp_create_nonce( 'productbay_frontend' ),
					'cart_url'              => \wc_get_cart_url(),
					'currency_symbol'       => \get_woocommerce_currency_symbol(),
					'currency_position'     => \get_option( 'woocommerce_currency_pos', 'left' ),
					'currency_decimals'     => absint( \get_option( 'woocommerce_price_num_decimals', 2 ) ),
					'currency_decimal_sep'  => \wc_get_price_decimal_separator(),
					'currency_thousand_sep' => \wc_get_price_thousand_separator(),
				)
			);
		}

		// Lightweight tab-switcher — only loaded when this block is on the page.
		$tabs_js = PRODUCTBAY_PATH . 'assets/js/block-tabs.js';
		if ( file_exists( $tabs_js ) ) {
			\wp_enqueue_script(
				'productbay-tabs',
				PRODUCTBAY_URL . 'assets/js/block-tabs.js',
				array(), // No deps — pure vanilla JS.
				(string) filemtime( $tabs_js ),
				true
			);
		}

		/**
		 * Fires after tab block assets are enqueued.
		 *
		 * @since 1.1.0
		 */
		\do_action( 'productbay_enqueue_frontend_assets' );
	}
}
