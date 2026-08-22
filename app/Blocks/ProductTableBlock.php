<?php
/**
 * Server-side render callback for the Product Table block.
 *
 * @package ProductBay
 */

declare(strict_types=1);

namespace WpabProductBay\Blocks;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

use WpabProductBay\Data\TableRepository;
use WpabProductBay\Frontend\TableRenderer;

/**
 * Class ProductTableBlock
 *
 * Handles the render_callback for the `productbay/product-table` block.
 * Delegates all rendering to the existing TableRenderer — identical output
 * to the [productbay] shortcode.
 *
 * @package WpabProductBay\Blocks
 * @since   1.1.0
 */
class ProductTableBlock
{
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
	public function __construct(TableRepository $repository)
	{
		$this->repository = $repository;
	}

	/**
	 * Render the Product Table block on the frontend.
	 *
	 * @param array  $attributes Block attributes from the editor.
	 * @param string $content    Inner block content (unused for dynamic blocks).
	 * @return string Rendered HTML.
	 * @since 1.1.0
	 */
	public function render(array $attributes, string $content): string
	{
		$table_id = absint($attributes['tableId'] ?? 0);

		$request_uri = isset($_SERVER['REQUEST_URI']) ? \sanitize_text_field(\wp_unslash($_SERVER['REQUEST_URI'])) : '';
		$is_editor   = defined('REST_REQUEST') && REST_REQUEST && strpos($request_uri, '/block-renderer/') !== false;

		if (!$table_id) {
			return $this->get_mockup();
		}

		$table = $this->repository->get_table($table_id);

		if (!$table) {
			if ($is_editor || is_admin() || is_preview()) {
				return '<div ' . \get_block_wrapper_attributes() . '>'
					. '<div style="padding:16px; border:1px dashed #fca5a5; background:#fef2f2; border-radius:8px; color:#991b1b; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; font-size: 14px;">'
					. '<div style="font-weight:700; margin-bottom:8px;">⚠️ ProductBay Table Block</div>'
					. sprintf(
						/* translators: %d: table ID */
						\esc_html__('The selected table (ID: %d) could not be found. It may have been deleted. Please select a different table or update the block settings.', 'productbay'),
						$table_id
					)
					. '</div></div>';
			}
			return '';
		}

		// Only render published tables on the frontend.
		if ('publish' !== $table['status'] && !is_preview()) {
			if (\current_user_can('manage_options')) {
				return '<p style="padding:12px 16px;background:#fef3cd;border:1px solid #e9b006;border-radius:4px;color:#664d03;font-size:14px;">'
					. sprintf(
						/* translators: 1: table title, 2: table status, e.g. "draft" */
						\esc_html__('ProductBay: Table "%1$s" is not visible to visitors (status: %2$s). It will appear here once it is published.', 'productbay'),
						\esc_html($table['title']),
						\esc_html(TableRepository::status_label($table['status']))
					)
					. '</p>';
			}
			return '';
		}

		$this->enqueue_assets();

		$renderer = new TableRenderer($this->repository);
		$html = $renderer->render($table);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() and TableRenderer output are safe.
		return '<div ' . \get_block_wrapper_attributes() . '>' . $html . '</div>';
	}

	/**
	 * Returns a high-fidelity HTML mockup for the Gutenberg inserter preview.
	 *
	 * @since 1.1.0
	 * @return string Sample HTML.
	 */
	private function get_mockup(): string
	{
		if (!is_admin() && !is_preview()) {
			return '';
		}

		ob_start();
		?>
		<div class="productbay-table-mockup" style="max-width:100%; border:1px solid #e2e8f0; border-radius:12px; padding:16px; background:#fff; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
			<div style="font-weight:700; margin-bottom:12px; font-size:15px; color:#1e293b;">⚡ Sample Product Table</div>
			<div style="display:grid; grid-template-columns: 48px 1fr 70px; gap:12px; align-items:center; border-bottom:1px solid #f1f5f9; padding:8px 0;">
				<div style="width:48px; height:48px; background:#f1f5f9; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:10px;">IMG</div>
				<div style="font-size:13px; font-weight:500; color:#334155;">Premium Wireless Headphones</div>
				<div style="font-size:13px; font-weight:700; color:#4f46e5; text-align:right;">$299.00</div>
			</div>
			<div style="display:grid; grid-template-columns: 48px 1fr 70px; gap:12px; align-items:center; border-bottom:1px solid #f1f5f9; padding:8px 0;">
				<div style="width:48px; height:48px; background:#f1f5f9; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:10px;">IMG</div>
				<div style="font-size:13px; font-weight:500; color:#334155;">Portable Bluetooth Speaker</div>
				<div style="font-size:13px; font-weight:700; color:#4f46e5; text-align:right;">$89.00</div>
			</div>
			<div style="display:grid; grid-template-columns: 48px 1fr 70px; gap:12px; align-items:center; padding:8px 0;">
				<div style="width:48px; height:48px; background:#f1f5f9; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:10px;">IMG</div>
				<div style="font-size:13px; font-weight:500; color:#334155;">Smart Watch Series 9</div>
				<div style="font-size:13px; font-weight:700; color:#4f46e5; text-align:right;">$399.00</div>
			</div>
			<div style="margin-top:12px; padding-top:12px; border-top:1px solid #f1f5f9; text-align:center; font-size:11px; color:#64748b; font-style:italic;">
				<?php \esc_html_e('Select a table to see live content', 'productbay'); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue frontend CSS and JS required by the table.
	 *
	 * Mirrors the logic in Shortcode::enqueue_assets() so users get identical
	 * asset loading whether the table is embedded via shortcode or block.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function enqueue_assets()
	{
		\wp_enqueue_style(
			'productbay-frontend',
			PRODUCTBAY_URL . 'assets/css/frontend.css',
			array(),
			(string) filemtime(PRODUCTBAY_PATH . 'assets/css/frontend.css')
		);

		\wp_enqueue_script(
			'productbay-frontend',
			PRODUCTBAY_URL . 'assets/js/frontend.js',
			// 'wc-cart-fragments' powers the live cart refresh (header/mini-cart) when
			// we trigger 'wc_fragment_refresh' after an AJAX add-to-cart. WooCommerce
			// registers it but only enqueues it via the Cart widget, so depend on it
			// here to guarantee it loads on any theme.
			array('jquery', 'wc-cart-fragments'),
			(string) filemtime(PRODUCTBAY_PATH . 'assets/js/frontend.js'),
			true
		);

		if (!\wp_script_is('productbay-frontend', 'localized')) {
			\wp_localize_script(
				'productbay-frontend',
				'productbay_frontend',
				array(
					'ajaxurl'            => \admin_url('admin-ajax.php'),
					'nonce'              => \wp_create_nonce('productbay_frontend'),
					'cart_url'           => \wc_get_cart_url(),
					'currency_symbol'    => \get_woocommerce_currency_symbol(),
					'currency_position'  => \get_option('woocommerce_currency_pos', 'left'),
					'currency_decimals'  => absint(\get_option('woocommerce_price_num_decimals', 2)),
					'currency_decimal_sep'  => \wc_get_price_decimal_separator(),
					'currency_thousand_sep' => \wc_get_price_thousand_separator(),
				)
			);
		}

		/**
		 * Fires after frontend assets are enqueued by a block render.
		 *
		 * @since 1.1.0
		 */
		\do_action('productbay_enqueue_frontend_assets');
	}
}
