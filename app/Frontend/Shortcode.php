<?php
/**
 * Shortcode registration and rendering for the [productbay] tag.
 *
 * @package ProductBay
 */

declare(strict_types=1);

namespace WpabProductBay\Frontend;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

use WpabProductBay\Data\TableRepository;

/**
 * Class Shortcode
 *
 * Handles the registration and rendering of proper shortcodes.
 *
 * @package WpabProductBay\Frontend
 * @since 1.0.0
 */
class Shortcode
{

	/**
	 * Repository for table data access.
	 *
	 * @var TableRepository
	 * @since 1.0.0
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param TableRepository $repository Table repository instance.
	 * @since 1.0.0
	 */
	public function __construct(TableRepository $repository)
	{
		$this->repository = $repository;
	}

	/**
	 * Initialize the shortcode.
	 *
	 * @since 1.0.0
	 */
	public function init()
	{
		add_shortcode('productbay', array($this, 'render_product_table'));
		add_filter('woocommerce_add_to_cart_fragments', array($this, 'add_cart_fragments'));
		add_filter('the_content', array($this, 'render_table_on_permalink'));
	}

	/**
	 * Render the product table automatically on its permalink page.
	 *
	 * @param string $content Existing content.
	 * @return string
	 * @since 1.2.0
	 */
	public function render_table_on_permalink($content)
	{
		if (is_singular('productbay_table') && in_the_loop() && is_main_query()) {
			$table_id = get_the_ID();
			$table = $this->repository->get_table($table_id);
			
			if (!$table) {
				return $content;
			}
			
			if ('publish' !== $table['status'] && !current_user_can('manage_options')) {
				return $content;
			}
			
			$this->enqueue_assets();
			
			$renderer = new TableRenderer($this->repository);
			$html = $renderer->render($table);
			
			if ('publish' !== $table['status']) {
				$notice = '<p style="padding:12px 16px;background:#fef3cd;border:1px solid #e9b006ff;border-radius:4px;color:#664d03;font-size:14px;margin-bottom:20px;">'
					. sprintf(
						/* translators: %s: table title */
						esc_html__('ProductBay: Previewing private table "%s". This URL is not accessible to public visitors.', 'productbay'),
						esc_html($table['title'])
					)
					. '</p>';
				$html = $notice . $html;
			}
			
			return $html;
		}
		
		return $content;
	}

	/**
	 * Add custom cart fragments to sync productbay tracked quantities.
	 *
	 * @param array $fragments Existing fragments.
	 * @return array
	 * @since 1.0.2
	 */
	public function add_cart_fragments($fragments)
	{
		$cart_data = \WpabProductBay\Frontend\TableRenderer::get_cart_data();
		$fragments['div.productbay-cart-data'] = '<div class="productbay-cart-data" style="display:none;" data-cart="' . esc_attr(wp_json_encode($cart_data)) . '"></div>';
		return $fragments;
	}

	/**
	 * Render the product table shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 * @since 1.0.0
	 */
	public function render_product_table($atts)
	{
		$this->enqueue_assets();

		$atts = shortcode_atts(
			array(
			'id' => 0,
		),
			$atts,
			'productbay'
		);

		/**
		 * Filters the parsed shortcode attributes.
		 *
		 * @since 1.0.0
		 *
		 * @param array $atts The shortcode attributes.
		 */
		$atts = \apply_filters('productbay_shortcode_atts', $atts);

		$table_id = intval($atts['id']);

		if (!$table_id) {
			return '';
		}

		$table = $this->repository->get_table($table_id);

		if (!$table) {
			return '';
		}

		// Only render published tables on the frontend.
		if ('publish' !== $table['status']) {
			// Show a helpful notice to admins so they know why the table isn't rendering.
			if (current_user_can('manage_options')) {
				return '<p style="padding:12px 16px;background:#fef3cd;border:1px solid #e9b006ff;border-radius:4px;color:#664d03;font-size:14px;">'
					. sprintf(
					/* translators: %s: table title */
					esc_html__('ProductBay: Table "%s" is currently private. It will appear here once it is published.', 'productbay'),
					esc_html($table['title'])
				)
					. '</p>';
			}
			return '';
		}

		// Instantiate renderer (or inject if we refactor Plugin.php).
		$renderer = new TableRenderer($this->repository);
		return $renderer->render($table);
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_assets()
	{
		$css_file = PRODUCTBAY_PATH . 'assets/css/frontend.css';
		$css_ver = (string)time();

		\wp_enqueue_style(
			'productbay-frontend',
			PRODUCTBAY_URL . 'assets/css/frontend.css',
			array(),
			$css_ver
		);

		\wp_enqueue_script(
			'productbay-frontend',
			PRODUCTBAY_URL . 'assets/js/frontend.js',
			// 'wc-cart-fragments' powers the live cart refresh (header/mini-cart) when
			// we trigger 'wc_fragment_refresh' after an AJAX add-to-cart. WooCommerce
			// registers it but only enqueues it via the Cart widget, so depend on it
			// here to guarantee it loads on any theme.
			array('jquery', 'wc-cart-fragments'),
			(string)time(),
			true
		);

		\wp_localize_script(
			'productbay-frontend',
			'productbay_frontend',
			array(
			'ajaxurl' => \admin_url('admin-ajax.php'),
			'nonce' => \wp_create_nonce('productbay_frontend'),
			'cart_url' => wc_get_cart_url(),
			'currency_symbol' => get_woocommerce_currency_symbol(),
			'currency_position' => get_option('woocommerce_currency_pos', 'left'),
			'currency_decimals' => absint(get_option('woocommerce_price_num_decimals', 2)),
			'currency_decimal_sep' => wc_get_price_decimal_separator(),
			'currency_thousand_sep' => wc_get_price_thousand_separator(),
		)
		);

		/**
		 * Fires after frontend assets are enqueued.
		 *
		 * Use this to enqueue additional frontend scripts or styles.
		 *
		 * @since 1.0.0
		 */
		\do_action('productbay_enqueue_frontend_assets');
	}
}