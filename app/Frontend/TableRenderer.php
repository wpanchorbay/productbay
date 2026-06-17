<?php
/**
 * Server-side HTML/CSS renderer for product tables.
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
 * Class TableRenderer
 *
 * Renders product tables on the frontend using WP_Query.
 * Generates HTML markup and scoped CSS from table configuration.
 *
 * @since   1.0.0
 * @package ProductBay
 */
class TableRenderer
{


	/**
	 * Repository for table data access.
	 *
	 * @var TableRepository
	 * @since 1.0.0
	 */
	protected $repository;

	/**
	 * Cart settings for the current render context.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $cart_settings = array(
		'enable' => true,
		'showQuantity' => true,
	);

	/**
	 * Lightbox feature enabled flag for the current render context.
	 *
	 * @var bool
	 * @since 1.0.2
	 */
	protected $lightbox_enabled = true;

	/**
	 * Custom Add to Cart text.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $add_to_cart_text = '';

	/**
	 * Custom "Select Options" / opener button text for variable/grouped products.
	 * This is separate from add_to_cart_text — it controls the popup/nested trigger
	 * buttons (e.g., "Select Options", "View Products"), NOT the final purchase buttons.
	 *
	 * @var string
	 * @since 1.0.4
	 */
	protected $select_options_text = '';

	/**
	 * Initialize the renderer.
	 *
	 * @param TableRepository $repository Table repository instance.
	 * @since 1.0.0
	 */
	public function __construct(TableRepository $repository)
	{
		$this->repository = $repository;
	}

	/**
	 * Initialize hooks (Shortcode).
	 *
	 * @since 1.0.0
	 */
	public function init()
	{
		// Registration is done in Plugin.php, but if we need hooks specific to renderer.
	}

	/**
	 * Render the table HTML based on configuration
	 *
	 * Handles product querying, filtering, and HTML generation.
	 * Use public access to allow calling from PreviewController and Shortcode.
	 *
	 * @param array $table        Full table configuration matching ProductTable interface.
	 * @param array $runtime_args Runtime arguments (search, sort, paged).
	 * @return string HTML content
	 * @since 1.0.0
	 */
	public function render($table, $runtime_args = array())
	{
		// Ensure we have a valid table structure.
		$table_id = $table['id'] ?? 0;

		// Generate a unique ID for this render instance (handling multiple tables per page).
		$unique_id = 'productbay-table-' . ($table_id ? $table_id : 'preview-' . wp_rand(1000, 9999));

		$source = $table['source'] ?? array();
		$columns = $table['columns'] ?? array();
		$settings = $table['settings'] ?? array();
		$style = $table['style'] ?? array();

		/**
		 * Filters the columns array before rendering.
		 *
		 * @since 1.0.0
		 *
		 * @param array $columns  The columns configuration.
		 * @param int   $table_id The table post ID.
		 */
		$columns = \apply_filters('productbay_table_columns', $columns, $table_id);

		// Store cart settings for use in render methods.
		$this->cart_settings = wp_parse_args(
			$settings['cart'] ?? array(),
			array(
				'enable' => true,
				'showQuantity' => true,
			)
		);

		// Store lightbox settings for use in render methods.
		$this->lightbox_enabled = isset($settings['features']['lightbox']) ? $settings['features']['lightbox'] : true;

		// Pass the showChildCount feature flag into cart_settings so render_cell can access it.
		$this->cart_settings['_show_child_count'] = $settings['features']['showChildCount'] ?? true;

		/**
		 * Filter the Add to Cart text.
		 *
		 * @since 1.0.0
		 *
		 * @param string $text          The custom text.
		 * @param array  $cart_settings The cart settings for the table.
		 */
		$add_to_cart_text = \apply_filters('productbay_add_to_cart_text', '', $this->cart_settings);
		if (empty($add_to_cart_text)) {
			$global_settings = \get_option('productbay_settings', array());
			if (!empty($global_settings['add_to_cart_text'])) {
				$add_to_cart_text = $global_settings['add_to_cart_text'];
			}
		}
		$this->add_to_cart_text = $add_to_cart_text;

		/**
		 * Resolve the opener button text ("Select Options" / "View Products").
		 * Uses a dedicated filter separate from the add-to-cart text.
		 *
		 * @since 1.0.4
		 *
		 * @param string $text          The custom opener text.
		 * @param array  $cart_settings The cart settings for the table.
		 */
		$select_options_text = \apply_filters('productbay_select_options_text', '', $this->cart_settings);
		if (empty($select_options_text)) {
			$global_settings_for_opener = \get_option('productbay_settings', array());
			if (!empty($global_settings_for_opener['select_options_text'])) {
				$select_options_text = $global_settings_for_opener['select_options_text'];
			}
		}
		$this->select_options_text = $select_options_text;

		// 1. Prepare Query Arguments.
		$args = $this->build_query_args($source, $settings, $runtime_args);

		/**
		 * Filters WP_Query arguments before the product query executes.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args         WP_Query arguments.
		 * @param array $settings     The table settings.
		 * @param array $runtime_args The runtime arguments (search, AJAX filters).
		 */
		$args = \apply_filters('productbay_query_args', $args, $settings, $runtime_args);

		// 2. Execute Query.
		$query = new \WP_Query($args);

		// 3. Generate Styles.
		$css = $this->generate_styles("#{$unique_id}", $style, $columns, $settings);

		/**
		 * Filters the generated scoped CSS for a table.
		 *
		 * @since 1.0.0
		 *
		 * @param string $css   The generated CSS.
		 * @param array  $table The full table configuration.
		 */
		$css = \apply_filters('productbay_table_styles', $css, $table);

		// 4. Build HTML.
		ob_start();

		// Output Styles — all user-controlled values are sanitized inside generate_styles().
		// wp_kses() with empty allowed-HTML strips any stray tags while preserving valid CSS.
		echo '<style>' . wp_kses($css, array('"', '\'')) . '</style>';

		// Bulk select configuration (used throughout the render).
		$bulk_select = $settings['features']['bulkSelect'] ?? array(
			'enabled' => true,
			'position' => 'last',
			'width' => array(
				'value' => 64,
				'unit' => 'px',
			),
		);
		$bulk_position = $bulk_select['position'] ?? 'last';

		$features_config = wp_json_encode(
			array(
				'variationBadges' => !empty($settings['features']['variationBadges']),
				'clearAllButton' => isset($settings['features']['clearAllButton']) ? $settings['features']['clearAllButton'] : true,
				'cartEnabled' => !empty($settings['cart']['enable']),
			)
		);

		echo '<div class="productbay-wrapper" id="' . esc_attr($unique_id) . '" data-table-id="' . esc_attr((string) $table_id) . '" data-select-position="' . esc_attr($bulk_position) . '" data-features="' . esc_attr($features_config) . '">';

		// Seed initial WooCommerce cart data for variations tracking
		$cart_data = self::get_cart_data();
		echo '<div class="productbay-cart-data" style="display:none;" data-cart="' . esc_attr((string) wp_json_encode($cart_data)) . '"></div>';

		/**
		 * Fires before the table wrapper content.
		 *
		 * @since 1.0.0
		 *
		 * @param array $table The full table configuration.
		 */
		\do_action('productbay_before_table', $table);

		// Filters Bar.
		ob_start();

		$has_tax_filters = !empty($settings['filters']['enabled']);

		if ($has_tax_filters) {
			echo '<span class="productbay-filters-heading">' . esc_html__('Filter', 'productbay') . '</span>';
		}

		// Taxonomy Filters (from free plugin).
		$this->render_taxonomy_filters($settings, $runtime_args);

		/**
		 * Action to render additional filters (e.g. from Pro extensions).
		 *
		 * @since 1.0.0
		 *
		 * @param array $settings The table settings.
		 * @param array $source   The table source configuration.
		 */
		\do_action('productbay_render_filters', $settings, $source);

		if ($has_tax_filters || !empty($settings['features']['priceFilter']['enabled'])) {
			echo '<button type="button" class="productbay-filters-clear" title="' . esc_attr__('Reset all filters to default', 'productbay') . '">' . esc_html__('Clear', 'productbay') . '</button>';
		}

		$filters_html = ob_get_clean();

		if (trim($filters_html) !== '') {
			echo '<div class="productbay-filters-bar">';
			echo $filters_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is built by trusted render methods
			echo '</div>';
		}

		// Toolbar: Bulk Actions + Search.
		echo '<div class="productbay-toolbar">';

		/**
		 * Fires at the start of the toolbar area.
		 *
		 * @since 1.0.0
		 *
		 * @param array $table The full table configuration.
		 */
		\do_action('productbay_toolbar_start', $table);

		// Bulk Actions (Add to Cart Button).
		if ($bulk_select['enabled']) {
			echo '<div class="productbay-bulk-actions">';

			echo '<div class="productbay-btn-group">';
			echo '<button class="productbay-button productbay-btn-bulk" disabled>';
			echo '<svg class="productbay-icon-cart" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg> ';
			echo esc_html( !empty($this->add_to_cart_text) ? $this->add_to_cart_text : __('Add to Cart', 'productbay') );
			echo '</button>';

			// Selected Items Panel Trigger
			if (!isset($settings['features']['selectedItemsPanel']['enabled']) || $settings['features']['selectedItemsPanel']['enabled']) {
				echo '<button class="productbay-button productbay-btn-panel" disabled title="' . esc_attr__('View selected items', 'productbay') . '">';
				echo esc_html__('View', 'productbay') . ' (<span class="productbay-panel-count">0</span>)';
				echo '</button>';
			}
			echo '</div>'; // End .productbay-btn-group

			echo '</div>'; // End .productbay-bulk-actions.
		}

		// Search & Filter Bar (if enabled).
		if (!empty($settings['features']['search'])) {
			$this->render_search_bar($settings, $runtime_args['s'] ?? '');
		}

		// Price Range Filter was here, moved outside toolbar.

		/**
		 * Fires at the end of the toolbar area.
		 *
		 * @since 1.0.0
		 *
		 * @param array $table The full table configuration.
		 */
		\do_action('productbay_toolbar_end', $table);

		echo '</div>'; // End Toolbar.


		echo '<div class="productbay-table-container">';
		echo '<table class="productbay-table">';

		// Table Header.
		echo '<thead><tr>';

		// Select All Column (Bulk Select - First).
		if ($bulk_select['enabled'] && $bulk_position === 'first') {
			echo '<th class="productbay-col-select"><input type="checkbox" class="productbay-select-all" /></th>';
		}

		foreach ($columns as $col) {
			// Check visibility.
			if ($this->should_hide_column($col)) {
				continue;
			}

			$th_classes = $this->get_column_classes($col);
			$th_style = $this->get_column_styles($col);

			echo '<th class="' . esc_attr(implode(' ', $th_classes)) . '" style="' . esc_attr($th_style) . '">';
			if (!empty($col['advanced']['showHeading'])) {
				echo esc_html($col['heading']);
			}
			echo '</th>';
		}

		// Bulk Select - Last Position.
		if ($bulk_select['enabled'] && $bulk_position === 'last') {
			echo '<th class="productbay-col-select"><input type="checkbox" class="productbay-select-all" /></th>';
		}

		echo '</tr></thead>';

		// Table Body.
		echo '<tbody>';

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce global
				global $product;

				// Ensure global product is set (for WC functions).
				if (!is_object($product)) {
					$product = wc_get_product(get_the_ID());
				}
				// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

				$product_type = $product->get_type();
				$in_stock = $product->is_in_stock() ? '1' : '0';

				/**
				 * Fires before each product row.
				 *
				 * @since 1.0.0
				 *
				 * @param \WC_Product $product The current product.
				 * @param array       $table   The full table configuration.
				 */
				\do_action('productbay_before_row', $product, $table);

				echo '<tr data-product-type="' . esc_attr($product_type) . '" data-product-id="' . esc_attr((string) $product->get_id()) . '" data-in-stock="' . esc_attr($in_stock) . '">';

				// Bulk Select - First Position.
				if ($bulk_select['enabled'] && ($bulk_select['position'] ?? 'last') === 'first') {
					$can_select = $product->is_in_stock() && !$product->is_type('external') && !$product->is_type('grouped') && !$product->is_type('variable') && $product->is_purchasable();
					echo '<td class="productbay-col-select">';
					echo '<input type="checkbox" class="productbay-select-product" value="' . esc_attr((string) $product->get_id()) . '" data-price="' . esc_attr((string) $product->get_price()) . '"' . ($can_select ? '' : ' disabled') . ' />';
					echo '</td>';
				}

				foreach ($columns as $col) {
					if ($this->should_hide_column($col)) {
						continue;
					}

					$td_classes = $this->get_column_classes($col);

					echo '<td class="' . esc_attr(implode(' ', $td_classes)) . '">';
					$this->render_cell($col, $product);
					echo '</td>';
				}

				// Bulk Select - Last Position.
				if ($bulk_select['enabled'] && ($bulk_select['position'] ?? 'last') === 'last') {
					$can_select = $product->is_in_stock() && !$product->is_type('external') && !$product->is_type('grouped') && !$product->is_type('variable') && $product->is_purchasable();
					echo '<td class="productbay-col-select">';
					echo '<input type="checkbox" class="productbay-select-product" value="' . esc_attr((string) $product->get_id()) . '" data-price="' . esc_attr((string) $product->get_price()) . '"' . ($can_select ? '' : ' disabled') . ' />';
					echo '</td>';
				}

				echo '</tr>';

				/**
				 * Fires after each product row.
				 *
				 * @since 1.0.0
				 *
				 * @param \WC_Product $product The current product.
				 * @param array       $table   The full table configuration.
				 */
				\do_action('productbay_after_row', $product, $table);
			}
			wp_reset_postdata();
		} else {
			$colspan = count(
				array_filter(
					$columns,
					function ($c) {
						return !$this->should_hide_column($c);
					}
				)
			);

			// Add +1 if bulk select enabled.
			if ($bulk_select['enabled'] ?? true) {
				++$colspan;
			}
			echo '<tr><td colspan="' . intval($colspan) . '" class="productbay-empty">' . esc_html__('No products found.', 'productbay') . '</td></tr>';
		}

		echo '</tbody>';
		echo '</table>';
		echo '</div>'; // End .productbay-table-container.

		// Pagination (if enabled).
		if (!empty($settings['features']['pagination'])) {
			$this->render_pagination($query, $settings, $runtime_args);
		}

		// Lightbox markup
		if ($this->lightbox_enabled) {
			echo '<dialog class="productbay-lightbox">';
			echo '<div class="productbay-lightbox-backdrop"></div>';
			echo '<div class="productbay-lightbox-content">';
			echo '<img src="" alt="" class="productbay-lightbox-img" />';
			echo '<div class="productbay-lightbox-actions">';
			echo '<button class="productbay-lightbox-fullscreen" aria-label="' . esc_attr__('Toggle Fullscreen', 'productbay') . '">';
			echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="productbay-icon-maximize"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>';
			echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="productbay-icon-minimize" style="display:none;"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path></svg>';
			echo '</button>';
			echo '<button class="productbay-lightbox-close" aria-label="' . esc_attr__('Close', 'productbay') . '">';
			echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
			echo '</button>';
			echo '</div>'; // close .productbay-lightbox-actions
			echo '</div>'; // close .productbay-lightbox-content
			echo '</dialog>';
		}

		echo '</div>'; // .productbay-wrapper.

		/**
		 * Fires after the table wrapper content.
		 *
		 * @since 1.0.0
		 *
		 * @param array $table The full table configuration.
		 */
		\do_action('productbay_after_table', $table);

		$html = ob_get_clean();

		/**
		 * Filters the complete table HTML output.
		 *
		 * @since 1.0.0
		 *
		 * @param string $html  The rendered HTML.
		 * @param array  $table The full table configuration.
		 */
		return \apply_filters('productbay_table_output', $html, $table);
	}

	/**
	 * Build WP_Query arguments from source configuration.
	 *
	 * @param array $source       Source configuration (categories, products, etc.).
	 * @param array $settings     Table settings (pagination, features).
	 * @param array $runtime_args Runtime arguments (paged, search, sort).
	 * @return array WP_Query arguments.
	 * @since 1.0.0
	 */
	private function build_query_args($source, $settings, $runtime_args = array())
	{
		$args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => $settings['pagination']['limit'] ?? 10,
			'orderby' => $source['sort']['orderBy'] ?? 'date',
			'order' => $source['sort']['order'] ?? 'DESC',
		);

		// Ensure proper paging is set.
		// TODO: Handle 'paged' query var for frontend pagination.
		$paged = $runtime_args['paged'] ?? ((get_query_var('paged')) ? get_query_var('paged') : 1);
		$args['paged'] = $paged;

		// Handle Search.
		if (!empty($runtime_args['s'])) {
			$args['s'] = sanitize_text_field($runtime_args['s']);
		}

		$source_type = $source['type'] ?? 'all';
		$query_args = $source['queryArgs'] ?? array();

		switch ($source_type) {
			case 'specific':
				if (!empty($query_args['postIds'])) {
					$args['post__in'] = $query_args['postIds'];
					$args['orderby'] = 'post__in'; // Preserve specific order.
				} else {
					// No products selected.
					$args['post__in'] = array(0);
				}
				break;

			case 'category':
				if (!empty($query_args['categoryIds'])) {
					$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for category filtering
						array(
							'taxonomy' => 'product_cat',
							'field' => 'term_id',
							'terms' => $query_args['categoryIds'],
							'operator' => 'IN',
						),
					);
				} else {
					// No categories selected, return no products.
					$args['post__in'] = array(0);
				}
				break;

			case 'sale':
				$sale_ids = \wc_get_product_ids_on_sale();
				$args['post__in'] = !empty($sale_ids) ? $sale_ids : array(0);
				break;
		}

		// Initialize tax_query if not already set.
		if (!isset($args['tax_query'])) {
			$args['tax_query'] = array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for category/type filtering logic
		}

		// Handle Runtime Override Filters (from frontend dropdowns or URL parameters)
		if (!empty($runtime_args['product_cat'])) {
			$cat_terms = $runtime_args['product_cat'];
			if (!is_array($cat_terms)) {
				$cat_terms = array_map('trim', explode(',', $cat_terms));
			}
			$cat_terms = array_map('sanitize_text_field', $cat_terms);
			$cat_terms = array_filter($cat_terms);

			if (!empty($cat_terms)) {
				$args['tax_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for runtime category filtering
					'taxonomy' => 'product_cat',
					'field' => 'slug',
					'terms' => $cat_terms,
					'operator' => 'IN',
				);
			}
		}

		if (!empty($runtime_args['product_type'])) {
			$args['tax_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for runtime product type filtering
				'taxonomy' => 'product_type',
				'field' => 'slug',
				'terms' => sanitize_text_field($runtime_args['product_type']),
				'operator' => 'IN',
			);
		}

		// Query type logic for features.
		// Handle Excludes.
		if (!empty($query_args['excludes'])) {
			$args['post__not_in'] = $query_args['excludes']; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Required for product exclusion
		}

		// Handle Stock Status.
		$stock_status = $query_args['stockStatus'] ?? 'any';
		if ($stock_status !== 'any') {
			$args['meta_query'][] = array(
				'key' => '_stock_status',
				'value' => $stock_status,
			);
		}

		return $args;
	}

	/**
	 * Render a single cell content.
	 *
	 * @param array       $col     Column configuration.
	 * @param \WC_Product $product WooCommerce product object.
	 * @since 1.0.0
	 */
	private function render_cell($col, $product)
	{
		$type     = $col['type'];
		$settings = $col['settings'] ?? array();

		// Security: Block Pro-only columns if the Pro version is not active.
		// This prevents "vulnerabilities" where users could manually craft table configs
		// to use premium features without a license.
		$pro_types = array( 'combined', 'cf' );
		if ( in_array( $type, $pro_types, true ) && ! defined( 'PRODUCTBAY_PRO_VERSION' ) ) {
			return;
		}

		ob_start();

		switch ($type) {

			case 'image':
				$size = $settings['imageSize'] ?? 'thumbnail';
				$img = $product->get_image($size);
				$full_url = wp_get_attachment_image_url((int) $product->get_image_id(), 'large');

				if ($this->lightbox_enabled && $full_url) {
					echo '<div class="productbay-lightbox-trigger" data-full-url="' . esc_url($full_url) . '">' . wp_kses_post($img) . '</div>';
				} elseif (($settings['linkTarget'] ?? '') === 'product') {
					echo '<a href="' . esc_url($product->get_permalink()) . '">' . wp_kses_post($img) . '</a>';
				} else {
					echo wp_kses_post($img);
				}
				break;

			case 'name':
				echo '<a href="' . esc_url($product->get_permalink()) . '" class="productbay-product-title">' . esc_html($product->get_name()) . '</a>';

				/**
				 * Show child count subtitle for variable/grouped products.
				 * Gated by the 'showChildCount' feature setting (default: true).
				 * Uses WC_Product::get_children() to count without loading full objects.
				 */
				$show_child_count = $this->cart_settings['_show_child_count'] ?? true;
				if ($show_child_count && ($product->is_type('variable') || $product->is_type('grouped'))) {
					$children_ids = $product->get_children();
					$child_count = count($children_ids);
					if ($child_count > 0) {
						/* translators: %d: number of available product options/variations */
						$subtitle = sprintf(_n('%d option available', '%d options available', $child_count, 'productbay'), $child_count);
						echo '<span class="productbay-product-subtitle">' . esc_html($subtitle) . '</span>';
					}
				}
				break;

			case 'price':
				echo '<span class="productbay-price">' . wp_kses_post($product->get_price_html()) . '</span>';
				break;

			case 'sku':
				echo esc_html($product->get_sku());
				break;

			case 'stock':
				$stock_status   = $product->get_stock_status();
				$stock_quantity = $product->get_stock_quantity();
				$is_in_stock    = $product->is_in_stock();

				if ('outofstock' === $stock_status) {
					echo '<span class="productbay-stock-status out-of-stock" style="color: #dc3232;">' . esc_html__('Out of stock', 'productbay') . '</span>';
				} elseif ('onbackorder' === $stock_status) {
					echo '<span class="productbay-stock-status on-backorder" style="color: #e6a817;">' . esc_html__('On backorder', 'productbay') . '</span>';
				} else {
					if ($product->managing_stock() && $stock_quantity !== null) {
						/* translators: %d: stock quantity */
						echo '<span class="productbay-stock-status in-stock" style="color: #46b450;">' . esc_html(sprintf(__('%d in stock', 'productbay'), $stock_quantity)) . '</span>';
					} else {
						echo '<span class="productbay-stock-status in-stock" style="color: #46b450;">' . esc_html__('In stock', 'productbay') . '</span>';
					}
				}
				break;

			case 'button':
				$this->render_button_cell($product);
				break;

			case 'summary':
				echo wp_kses_post(wp_trim_words($product->get_short_description(), 10));
				break;

			case 'date':
				$date_obj = $product->get_date_created();
				if ($date_obj) {
					echo esc_html($date_obj->date_i18n(get_option('date_format')));
				}
				break;

			case 'tax':
				$taxonomy = $settings['taxonomy'] ?? 'product_cat';
				$link     = !empty($settings['linkToArchive']);
				$terms    = \wp_get_post_terms($product->get_id(), sanitize_key($taxonomy));

				if (!is_wp_error($terms) && !empty($terms)) {
					$parts = array();
					foreach ($terms as $term) {
						if ($link) {
							$parts[] = '<a href="' . esc_url(get_term_link($term)) . '">' . esc_html($term->name) . '</a>';
						} else {
							$parts[] = esc_html($term->name);
						}
					}
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each part is individually escaped above.
					echo implode(', ', $parts);
				}
				break;

			case 'rating':
				$rating = $product->get_average_rating();
				$display_format = $settings['displayFormat'] ?? 'stars';

				if ($rating > 0) {
					if ('woocommerce' === $display_format) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo wc_get_rating_html($rating, $product->get_rating_count());
					} elseif ('text' === $display_format) {
						/* translators: %s: average star rating, e.g. "4.5" */
						echo '<span class="productbay-rating-text">' . esc_html(sprintf(__('Rated %s out of 5', 'productbay'), $rating)) . '</span>';
					} else {
						// Default: stars (custom SVG implementation)
						$percentage = ( $rating / 5 ) * 100;
						$star_svg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
						$stars_html = str_repeat($star_svg, 5);
						$allowed_svg = array(
							'svg'  => array(
								'width'   => true,
								'height'  => true,
								'viewbox' => true,
								'fill'    => true,
								'xmlns'   => true,
							),
							'path' => array(
								'd' => true,
							),
						);

						/* translators: %s: average star rating, e.g. "4.5" */
						echo '<div class="productbay-custom-stars" title="' . esc_attr(sprintf(__('Rated %s out of 5', 'productbay'), $rating)) . '" style="position: relative; display: inline-flex; vertical-align: middle;">';
						echo '<div class="productbay-stars-bg" style="color: #e5e7eb; display: flex;">' . wp_kses($stars_html, $allowed_svg) . '</div>';
						echo '<div class="productbay-stars-active" style="color: #f59e0b; display: flex; position: absolute; top: 0; left: 0; overflow: hidden; white-space: nowrap; width: ' . esc_attr(strval($percentage)) . '%;">' . wp_kses($stars_html, $allowed_svg) . '</div>';
						echo '</div>';
					}
				} else {
					echo '<span class="productbay-no-rating">' . esc_html__('No ratings', 'productbay') . '</span>';
				}
				break;

			case 'combined':
				$elements  = $settings['elements'] ?? array();
				$layout    = $settings['layout'] ?? 'inline';
				$separator = $settings['separator'] ?? '';

				if (!empty($elements) && is_array($elements)) {
					echo '<div class="productbay-combined-cell ' . esc_attr('layout-' . $layout) . '" style="display: ' . esc_attr('inline' === $layout ? 'flex' : 'block') . '; gap: 8px; flex-wrap: wrap; align-items: center;">';

					$count = count($elements);
					foreach ($elements as $index => $element) {
						// Backward compatibility: handle string elements
						if (is_string($element)) {
							$element = array(
								'type'     => $element,
								'settings' => array(),
							);
						}

						$el_type     = $element['type'] ?? '';
						$el_settings = $element['settings'] ?? array();

						if ($el_type) {
							$el_html = '';

							// Recursively get cell content
							ob_start();
							$this->render_cell(array(
								'type'     => $el_type,
								'settings' => $el_settings,
							), $product);
							$el_html = ob_get_clean();

							if ($el_html !== '') {
								$prefix = $el_settings['prefix'] ?? '';
								$suffix = $el_settings['suffix'] ?? '';

								echo '<div class="productbay-combined-element element-' . esc_attr($el_type) . '" style="display: ' . ( 'inline' === $layout ? 'inline-flex' : 'block' ) . '; align-items: center; gap: 4px;">';
								if ($prefix) {
									echo '<span class="productbay-element-prefix">' . esc_html($prefix) . '</span>';
								}
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is escaped within render_cell recursive call.
								echo $el_html;
								if ($suffix) {
									echo '<span class="productbay-element-suffix">' . esc_html($suffix) . '</span>';
								}
								echo '</div>';

								// Add separator if inline and not last
								if ('inline' === $layout && $separator && $index < $count - 1) {
									echo '<span class="productbay-element-separator" style="margin: 0 4px; opacity: 0.5;">' . esc_html($separator) . '</span>';
								}
							}
						}
					}
					echo '</div>';
				}
				break;

			default:
				// Custom columns handled by filter later
				break;
		}

		$cell_html = ob_get_clean();

		/**
		 * Filters cell output for both native and custom column types.
		 *
		 * Pro or third-party plugins can use this to override any cell rendering.
		 *
		 * @since 1.0.0
		 *
		 * @param string      $cell_html The default cell HTML generated by the switch.
		 * @param array       $col       The column configuration.
		 * @param \WC_Product $product   The WooCommerce product.
		 */
		$cell_html = \apply_filters('productbay_cell_output', $cell_html, $col, $product);

		if ($cell_html !== '') {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is safely built and filtered. Third parties must escape their own output.
			echo $cell_html;
		}
	}

	/**
	 * Render the button cell based on product type and stock status.
	 * Respects cart settings:
	 *   - cart.enable (AJAX): When false, button links to product page.
	 *   - cart.showQuantity: When false, quantity input is hidden.
	 *
	 * @param \WC_Product $product WooCommerce product object.
	 * @since 1.0.0
	 */
	private function render_button_cell($product)
	{
		$ajax_enabled = !empty($this->cart_settings['enable']);
		$show_quantity = !empty($this->cart_settings['showQuantity']);

		// External/Affiliate: always link out to external URL.
		if ($product->is_type('external')) {
			$url = $product->get_product_url();
			$text = $product->get_button_text() ?: __('Buy product', 'productbay');
			echo '<div class="productbay-btn-cell">';
			echo '<a href="' . esc_url($url) . '" class="productbay-button productbay-btn-external" target="_blank" rel="noopener noreferrer">' . esc_html($text) . '</a>';
			echo '</div>';
			return;
		}

		// Grouped: conditionally render inline select or fallback to standard view options button
		if ($product->is_type('grouped')) {
			$grouped_mode = $this->table_config['settings']['features']['groupedProductMode'] ?? 'inline';
			
			// Legacy fallback for old table configurations
			if (empty($this->table_config['settings']['features']['groupedProductMode']) && !empty($this->table_config['settings']['features']['variationsMode'])) {
				$legacy = $this->table_config['settings']['features']['variationsMode'];
				$grouped_mode = ($legacy === 'inline') ? 'inline' : $legacy;
			}

			// If Pro is disabled, advanced modes aren't available, so force 'inline' natively
			if (!defined('PRODUCTBAY_PRO_VERSION') && in_array($grouped_mode, ['popup', 'nested', 'separate'], true)) {
				$grouped_mode = 'inline';
			}

			// Core now supports inline mode natively. Other advanced modes require Pro.
			if ($grouped_mode === 'inline') {
				$this->render_grouped_inline_select($product);
				return;
			}

			$grouped_btn_text = !empty($this->select_options_text) ? $this->select_options_text : __('View Options', 'productbay');
			echo '<div class="productbay-btn-cell">';
			echo '<a href="' . esc_url($product->get_permalink()) . '" class="productbay-button productbay-btn-grouped">' . esc_html($grouped_btn_text) . '</a>';
			echo '</div>';
			return;
		}

		// Out of stock: disabled button.
		if (!$product->is_in_stock()) {
			echo '<div class="productbay-btn-cell">';
			echo '<button class="productbay-button productbay-btn-outofstock" disabled>' . esc_html__('Out of Stock', 'productbay') . '</button>';
			echo '</div>';
			return;
		}



		// Variable: render attribute dropdowns + quantity + add to cart.
		if ($product->is_type('variable')) {
			$this->render_variable_button_cell($product);
			return;
		}

		// Simple (or any other purchasable type): quantity + add to cart.
		$is_purchasable = $product->is_purchasable();

		if (!$ajax_enabled) {
			echo '<form method="POST" action="" class="productbay-btn-cell">';
			echo '<input type="hidden" name="add-to-cart" value="' . esc_attr((string) $product->get_id()) . '" />';
		} else {
			echo '<div class="productbay-btn-cell">';
		}

		if ($is_purchasable && $show_quantity) {
			$this->render_quantity_input($product);
		}

		$disabled_attr = $is_purchasable ? '' : ' disabled';
		$btn_type = $ajax_enabled ? 'button' : 'submit';

		echo '<button type="' . esc_attr($btn_type) . '" class="productbay-button productbay-btn-addtocart" data-product-id="' . esc_attr((string) $product->get_id()) . '"' . esc_attr($disabled_attr) . '>';
		echo esc_html( !empty($this->add_to_cart_text) ? $this->add_to_cart_text : $product->add_to_cart_text() );
		echo '</button>';

		if (!$ajax_enabled) {
			echo '</form>';
		} else {
			echo '</div>';
		}
	}

	/**
	 * Render an inline `<select>` dropdown for grouped products.
	 *
	 * Uses $product->get_children() and wc_get_product() to build a dropdown
	 * listing child products by name, with price data embedded for JS to read.
	 * When a child is selected, the add-to-cart button targets that child's ID.
	 *
	 * @param \WC_Product $product The WooCommerce product object.
	 */
	private function render_grouped_inline_select($product)
	{
		$children_ids = $product->get_children();
		if (empty($children_ids)) {
			// Fallback if no children
			$fallback_text = !empty($this->select_options_text) ? $this->select_options_text : __('View Options', 'productbay');
			echo '<div class="productbay-btn-cell">';
			echo '<a href="' . esc_url($product->get_permalink()) . '" class="productbay-button productbay-btn-grouped">' . esc_html($fallback_text) . '</a>';
			echo '</div>';
			return;
		}

		// Build child data for the select dropdown
		$children_data = [];
		foreach ($children_ids as $child_id) {
			$child = wc_get_product($child_id);
			if (!$child || !$child->is_purchasable() || !$child->is_in_stock()) {
				continue;
			}
			$children_data[] = [
				'id'    => $child->get_id(),
				'name'  => $child->get_name(),
				'price' => $child->get_price(),
				'price_html' => $child->get_price_html(),
			];
		}

		if (empty($children_data)) {
			// Fallback if no purchasable children
			echo '<div class="productbay-btn-cell">';
			echo '<button class="productbay-button productbay-btn-outofstock" disabled>' . esc_html__('Out of Stock', 'productbay') . '</button>';
			echo '</div>';
			return;
		}

		$show_qty = !empty($this->cart_settings['showQuantity']);
		$ajax_enabled = !empty($this->cart_settings['enable']);

		if (!$ajax_enabled) {
			echo '<form method="POST" action="" class="productbay-btn-cell productbay-grouped-inline-wrap" data-children="' . esc_attr((string) wp_json_encode($children_data)) . '">';
			echo '<input type="hidden" name="add-to-cart" value="" class="productbay-grouped-hidden-id" />';
		} else {
			echo '<div class="productbay-btn-cell productbay-grouped-inline-wrap" data-children="' . esc_attr((string) wp_json_encode($children_data)) . '">';
		}
		?>
			<select class="productbay-grouped-child-select">
				<option value=""><?php esc_html_e('Select a product...', 'productbay'); ?></option>
				<?php if (count($children_data) > 1): ?>
					<option value="__all__"><?php esc_html_e('Select All', 'productbay'); ?></option>
				<?php endif; ?>
				<?php foreach ($children_data as $child): ?>
					<option value="<?php echo esc_attr((string) $child['id']); ?>"
							data-price="<?php echo esc_attr((string) $child['price']); ?>">
						<?php echo esc_html($child['name']); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="productbay-grouped-inline-price productbay-price"></span>
			<?php if ($show_qty): ?>
			<div class="productbay-qty-wrap">
				<input type="number" class="<?php echo !$ajax_enabled ? 'qty' : 'productbay-qty'; ?>" name="quantity" value="1" min="1" step="1" disabled />
				<div class="productbay-qty-btns">
					<button type="button" class="productbay-qty-btn productbay-qty-plus" aria-label="<?php esc_attr_e('Increase', 'productbay'); ?>" disabled>▲</button>
					<button type="button" class="productbay-qty-btn productbay-qty-minus" aria-label="<?php esc_attr_e('Decrease', 'productbay'); ?>" disabled>▼</button>
				</div>
			</div>
			<?php endif; ?>
			<?php 
			$btn_type = $ajax_enabled ? 'button' : 'submit';
			?>
			<button type="<?php echo esc_attr($btn_type); ?>" class="productbay-button productbay-btn-addtocart productbay-grouped-add-btn"
					data-product-id="" disabled>
				<?php echo esc_html( !empty($this->add_to_cart_text) ? $this->add_to_cart_text : __('Add to Cart', 'productbay') ); ?>
			</button>
		<?php
		if (!$ajax_enabled) {
			echo '</form>';
		} else {
			echo '</div>';
		}
	}

	/**
	 * Render variation attribute dropdowns and add-to-cart button for variable products.
	 * Uses WC_Product_Variable::get_variation_attributes() and get_available_variations().
	 *
	 * @param \WC_Product_Variable $product WooCommerce variable product object.
	 * @since 1.0.0
	 */
	private function render_variable_button_cell($product)
	{
		$attributes = $product->get_variation_attributes();
		$available_variations = $product->get_available_variations('array');
		$ajax_enabled = !empty($this->cart_settings['enable']);

		if (!$ajax_enabled) {
			echo '<form method="POST" action="" class="productbay-btn-cell productbay-variable-wrap" data-product-id="' . esc_attr((string) $product->get_id()) . '" data-product-variations="' . esc_attr((string) wp_json_encode($available_variations)) . '">';
			echo '<input type="hidden" name="add-to-cart" value="' . esc_attr((string) $product->get_id()) . '" />';
		} else {
			echo '<div class="productbay-btn-cell productbay-variable-wrap" data-product-id="' . esc_attr((string) $product->get_id()) . '" data-product-variations="' . esc_attr((string) wp_json_encode($available_variations)) . '">';
		}

		// Attribute dropdowns.
		echo '<div class="productbay-variation-selects">';
		foreach ($attributes as $attribute_name => $options) {
			$attr_label = wc_attribute_label($attribute_name, $product);
			$sanitized_name = sanitize_title($attribute_name);
			$field_name = 'attribute_' . $sanitized_name;

			echo '<select name="' . esc_attr($field_name) . '" class="productbay-variation-select" data-attribute-name="' . esc_attr($field_name) . '">';
			echo '<option value="">' . esc_html($attr_label) . '&hellip;</option>';

			foreach ($options as $option) {
				$option_label = $option;
				// For taxonomy-based attributes, get the term name.
				if (taxonomy_exists($attribute_name)) {
					$term = get_term_by('slug', $option, $attribute_name);
					if ($term && !is_wp_error($term)) {
						$option_label = $term->name;
					}
				}
				echo '<option value="' . esc_attr($option) . '">' . esc_html($option_label) . '</option>';
			}
			echo '</select>';
		}
		echo '</div>';

		// Hidden variation ID input.
		echo '<input type="hidden" name="variation_id" class="productbay-variation-id" value="" />';

		// Variation price display.
		echo '<span class="productbay-variation-price"></span>';

		// Quantity + Add to Cart (disabled until variation selected).
		$is_purchasable = $product->is_purchasable();
		$show_quantity = !empty($this->cart_settings['showQuantity']);
		$btn_type = $ajax_enabled ? 'button' : 'submit';

		echo '<div class="productbay-btn-cell">';
		if ($is_purchasable && $show_quantity) {
			$this->render_quantity_input($product);
		}
		echo '<button type="' . esc_attr($btn_type) . '" class="productbay-button productbay-btn-addtocart" data-product-id="' . esc_attr((string) $product->get_id()) . '" disabled>';
		echo esc_html( !empty($this->add_to_cart_text) ? $this->add_to_cart_text : __('Add to cart', 'productbay') );
		echo '</button>';
		echo '</div>';

		if (!$ajax_enabled) {
			echo '</form>';
		} else {
			echo '</div>';
		}
	}

	/**
	 * Render a quantity number input with stock-aware constraints.
	 * Uses WC_Product::get_stock_quantity() and backorders_allowed().
	 *
	 * @param \WC_Product $product WooCommerce product object.
	 * @since 1.0.0
	 */
	private function render_quantity_input($product)
	{
		$min = 1;
		$max = '';
		$stock_qty = $product->get_stock_quantity();

		// Only set max if stock is managed and backorders are not allowed.
		if ($product->managing_stock() && !$product->backorders_allowed() && $stock_qty !== null) {
			$max = $stock_qty;
		}

		echo '<div class="productbay-qty-wrap">';
		echo '<input type="number" name="quantity" class="productbay-qty" value="1" min="' . esc_attr((string) $min) . '"';
		if ($max !== '') {
			echo ' max="' . esc_attr((string) $max) . '"';
		}
		echo ' step="1" />';
		echo '<div class="productbay-qty-btns">';
		echo '<button type="button" class="productbay-qty-btn productbay-qty-plus" aria-label="' . esc_attr__('Increase quantity', 'productbay') . '">&#9650;</button>';
		echo '<button type="button" class="productbay-qty-btn productbay-qty-minus" aria-label="' . esc_attr__('Decrease quantity', 'productbay') . '">&#9660;</button>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Sanitize a CSS color value.
	 *
	 * Accepts hex colors (#rgb, #rrggbb, #rrggbbaa), rgb(), rgba(), hsl(), and hsla().
	 * Returns an empty string for any value that does not match safe patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param string $color Raw color value from config.
	 * @return string Sanitized color or empty string.
	 */
	private function sanitize_css_color($color)
	{
		if (!is_string($color)) {
			return '';
		}

		$color = trim($color);

		// Hex: #rgb, #rrggbb, #rrggbbaa.
		if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
			return $color;
		}

		// Match rgb(), rgba(), hsl(), hsla() with only safe characters (digits, commas, spaces, dots, %).
		if (preg_match('/^(rgb|rgba|hsl|hsla)\([0-9,.\s%\/]+\)$/', $color)) {
			return $color;
		}

		return '';
	}

	/**
	 * Sanitize a CSS dimensional value (e.g. "16px", "1.5rem", "100%").
	 *
	 * Strips anything that isn't a number, dot, or an allowed unit.
	 * Returns an empty string if the value does not match safe patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Raw CSS value from config.
	 * @return string Sanitized CSS dimensional value or empty string.
	 */
	private function sanitize_css_value($value)
	{
		if (!is_string($value)) {
			return '';
		}

		$value = trim($value);

		// Match number + optional unit (px, %, em, rem, pt).
		if (preg_match('/^[0-9]+(\.[0-9]+)?(px|%|em|rem|pt)?$/', $value)) {
			return $value;
		}

		return '';
	}

	/**
	 * Sanitize a column width unit against an allow-list.
	 *
	 * @since 1.0.0
	 *
	 * @param string $unit Raw unit string.
	 * @return string Sanitized unit or 'auto'.
	 */
	private function sanitize_css_unit($unit)
	{
		$allowed = array('px', '%', 'em', 'rem', 'auto');
		return in_array($unit, $allowed, true) ? $unit : 'auto';
	}

	/**
	 * Generate scoped CSS based on the table's style configuration.
	 *
	 * All user-controlled values (colors, sizes, border styles) are sanitized
	 * before interpolation to prevent CSS injection.
	 *
	 * @since 1.0.0
	 *
	 * @param string $selector CSS selector for scoping (e.g. #id or .class).
	 * @param array  $style    Style configuration from the table.
	 * @param array  $columns  Column definitions for width styles.
	 * @param array  $settings Table settings (features, pagination, cart, etc.).
	 * @return string Generated CSS string.
	 */
	public function generate_styles($selector, $style, $columns, $settings = array())
	{
		$css = '';
		$header = $style['header'] ?? array();
		$body = $style['body'] ?? array();
		$button = $style['button'] ?? array();
		$layout = $style['layout'] ?? array();
		$hover = $style['hover'] ?? array();

		$typography = $style['typography'] ?? array();

		// Header Styles.
		$css .= "{$selector} .productbay-table thead th {";
		$h_bg = $this->sanitize_css_color($header['bgColor'] ?? '');
		$h_text = $this->sanitize_css_color($header['textColor'] ?? '');
		$h_font = $this->sanitize_css_value($header['fontSize'] ?? '');
		if ($h_bg) {
			$css .= "background-color: {$h_bg};";
		}
		if ($h_text) {
			$css .= "color: {$h_text};";
		}
		if ($h_font) {
			$css .= "font-size: {$h_font};";
		}

		if (!empty($typography['headerFontWeight'])) {
			$weight_map = array(
				'normal' => '400',
				'bold' => '600',
				'extrabold' => '800',
			);
			$weight = $weight_map[$typography['headerFontWeight']] ?? '600';
			$css .= "font-weight: {$weight};";
		}

		if (!empty($typography['headerTextTransform'])) {
			$transform_map = array(
				'uppercase' => 'uppercase',
				'lowercase' => 'lowercase',
				'capitalize' => 'capitalize',
				'normal-case' => 'none',
			);
			$transform = $transform_map[$typography['headerTextTransform']] ?? 'uppercase';
			$css .= "text-transform: {$transform};";
			if ($transform === 'none') {
				$css .= 'letter-spacing: normal;';
			}
		}
		$css .= '}';

		// Body Styles — base td.
		$b_bg = $this->sanitize_css_color($body['bgColor'] ?? '');
		$b_text = $this->sanitize_css_color($body['textColor'] ?? '');
		$css .= "{$selector} .productbay-table tbody td {";
		$css .= 'vertical-align: top;';
		if ($b_bg) {
			$css .= "background-color: {$b_bg};";
		}
		if ($b_text) {
			$css .= "color: {$b_text};";
		}
		$css .= '}';

		// Body text color: override specific child elements that have hardcoded colors.
		if ($b_text) {
			$css .= "{$selector} .productbay-table tbody td .productbay-product-title,";
			$css .= "{$selector} .productbay-table tbody td a:not(.productbay-button),";
			$css .= "{$selector} .productbay-table tbody td .productbay-price,";
			$css .= "{$selector} .productbay-table tbody td .productbay-price ins,";
			$css .= "{$selector} .productbay-table tbody td .productbay-price ins .woocommerce-Price-amount,";
			$css .= "{$selector} .productbay-table tbody td .productbay-price del,";
			$css .= "{$selector} .productbay-table tbody td .productbay-price del .woocommerce-Price-amount {";
			$css .= "color: {$b_text} !important;";
			$css .= '}';
		}

		// Layout & Spacing Styles.
		$allowed_border_styles = array('none', 'solid', 'dashed', 'dotted', 'double');
		$raw_border_style = $layout['borderStyle'] ?? '';
		$b_style = in_array($raw_border_style, $allowed_border_styles, true) ? $raw_border_style : 'solid';
		$b_color = $this->sanitize_css_color($layout['borderColor'] ?? '#e2e8f0');

		if ($b_style === 'none') {
			$css .= "{$selector} .productbay-table-container { border: none; }";
			$css .= "{$selector} .productbay-table th, {$selector} .productbay-table td { border: none; }";
		} elseif ($b_style && $b_color) {
			$css .= "{$selector} .productbay-table-container { border: 1px {$b_style} {$b_color}; }";
			$css .= "{$selector} .productbay-table th, {$selector} .productbay-table td { border-bottom: 1px {$b_style} {$b_color}; }";
		} elseif ($b_color) {
			$css .= "{$selector} .productbay-table-container { border-color: {$b_color}; }";
			$css .= "{$selector} .productbay-table th, {$selector} .productbay-table td { border-bottom-color: {$b_color}; }";
		}

		$radius_enabled = $layout['borderRadiusEnabled'] ?? true;
		if ($radius_enabled && isset($layout['borderRadius'])) {
			$radius = intval($layout['borderRadius']);
			$css .= "{$selector} .productbay-table-container { border-radius: " . (string) $radius . 'px; }';
		} elseif (!$radius_enabled) {
			$css .= "{$selector} .productbay-table-container { border-radius: 0; }";
		}

		if (!empty($layout['cellPadding'])) {
			$cell_padding_map = array(
				'compact' => '8px 12px',
				'normal' => '12px 16px',
				'spacious' => '16px 24px',
			);
			$padding = $cell_padding_map[$layout['cellPadding']] ?? '12px 16px';
			$css .= "{$selector} .productbay-table th, {$selector} .productbay-table td { padding: {$padding}; }";
		}

		// Alternate Rows.
		if (!empty($body['rowAlternate'])) {
			$alt_bg = $this->sanitize_css_color($body['altBgColor'] ?? '');
			$alt_text = $this->sanitize_css_color($body['altTextColor'] ?? '');
			$css .= "{$selector} .productbay-table tbody tr:nth-child(even) td {";
			if ($alt_bg) {
				$css .= "background-color: {$alt_bg};";
			}
			if ($alt_text) {
				$css .= "color: {$alt_text};";
			}
			$css .= '}';

			// Alt row text color: override specific child elements.
			if ($alt_text) {
				$css .= "{$selector} .productbay-table tbody tr:nth-child(even) td .productbay-product-title,";
				$css .= "{$selector} .productbay-table tbody tr:nth-child(even) td a:not(.productbay-button),";
				$css .= "{$selector} .productbay-table tbody tr:nth-child(even) td .productbay-price,";
				$css .= "{$selector} .productbay-table tbody tr:nth-child(even) td .productbay-price ins,";
				$css .= "{$selector} .productbay-table tbody tr:nth-child(even) td .productbay-price ins .woocommerce-Price-amount,";
				$css .= "{$selector} .productbay-table tbody tr:nth-child(even) td .productbay-price del,";
				$css .= "{$selector} .productbay-table tbody tr:nth-child(even) td .productbay-price del .woocommerce-Price-amount {";
				$css .= "color: {$alt_text} !important;";
				$css .= '}';
			}
		}

		// Hover Effect.
		if (!empty($hover['rowHoverEnabled'])) {
			$hov_bg = $this->sanitize_css_color($hover['rowHoverBgColor'] ?? '');
			$hov_text = $this->sanitize_css_color($hover['rowHoverTextColor'] ?? '');
			$css .= "{$selector} .productbay-table tbody tr:hover td {";
			if ($hov_bg) {
				$css .= "background-color: {$hov_bg};";
			}
			if ($hov_text) {
				$css .= "color: {$hov_text};";
			}
			$css .= '}';

			// Hover text color: override specific child elements.
			if ($hov_text) {
				$css .= "{$selector} .productbay-table tbody tr:hover td .productbay-product-title,";
				$css .= "{$selector} .productbay-table tbody tr:hover td a:not(.productbay-button),";
				$css .= "{$selector} .productbay-table tbody tr:hover td .productbay-price,";
				$css .= "{$selector} .productbay-table tbody tr:hover td .productbay-price ins,";
				$css .= "{$selector} .productbay-table tbody tr:hover td .productbay-price ins .woocommerce-Price-amount,";
				$css .= "{$selector} .productbay-table tbody tr:hover td .productbay-price del,";
				$css .= "{$selector} .productbay-table tbody tr:hover td .productbay-price del .woocommerce-Price-amount {";
				$css .= "color: {$hov_text} !important;";
				$css .= '}';
			}
		}

		// Button Styles via class override.
		$btn_bg = $this->sanitize_css_color($button['bgColor'] ?? '');
		$btn_text = $this->sanitize_css_color($button['textColor'] ?? '');
		$btn_radius = $this->sanitize_css_value($button['borderRadius'] ?? '');
		$btn_hov_bg = $this->sanitize_css_color($button['hoverBgColor'] ?? '');
		$btn_hov_text = $this->sanitize_css_color($button['hoverTextColor'] ?? '');

		$css .= "{$selector} .productbay-button {";
		$css .= 'display: inline-flex;';
		$css .= 'align-items: center;';
		$css .= 'justify-content: center;';
		$css .= 'transition: background-color 0.2s ease, color 0.2s ease;';
		if ($btn_bg) {
			$css .= "background-color: {$btn_bg} !important;";
		}
		if ($btn_text) {
			$css .= "color: {$btn_text} !important;";
		}
		if ($btn_radius) {
			$css .= "border-radius: {$btn_radius};";
		}
		$css .= '}';

		$css .= "{$selector} .productbay-button:hover {";
		if ($btn_hov_bg) {
			$css .= "background-color: {$btn_hov_bg} !important;";
		}
		if ($btn_hov_text) {
			$css .= "color: {$btn_hov_text} !important;";
		}
		$css .= '}';

		// Added to cart checkmark (SVG).
		$css .= "{$selector} .productbay-button.added {";
		$css .= 'gap: 6px;';
		$css .= '}';
		$css .= "{$selector} .productbay-table .button.added::after {";
		$css .= "content: '';";
		$css .= 'display: inline-block;';
		$css .= 'width: 16px;';
		$css .= 'height: 16px;';
		$css .= "-webkit-mask-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E\");";
		$css .= "mask-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E\");";
		$css .= '-webkit-mask-size: contain;';
		$css .= 'mask-size: contain;';
		$css .= '-webkit-mask-repeat: no-repeat;';
		$css .= 'mask-repeat: no-repeat;';
		$css .= '-webkit-mask-position: center;';
		$css .= 'mask-position: center;';
		$css .= 'background-color: currentColor;';
		$css .= '}';

		// Image styles.
		$css .= "{$selector} img {";
		$css .= 'max-width: 100%;';
		$css .= 'height: auto;';
		$css .= 'display: block;';
		$css .= 'padding: 2px;';
		$css .= 'border-radius: 3px;';
		$css .= '}';

		// Column Widths.
		foreach ($columns as $col) {
			$width = $col['advanced']['width'] ?? array(
				'value' => 0,
				'unit' => 'auto',
			);
			$w_value = intval($width['value']);
			$w_unit = $this->sanitize_css_unit($width['unit'] ?? 'auto');
			if ($w_value > 0 && $w_unit !== 'auto') {
				$css .= "{$selector} .productbay-col-" . esc_attr((string) $col['id']) . ' { width: ' . (string) $w_value . "{$w_unit}; }";
			}
		}

		// Bulk Select Width.
		$bulk_select = $settings['features']['bulkSelect'] ?? array(
			'enabled' => true,
			'position' => 'last',
			'width' => array(
				'value' => 64,
				'unit' => 'px',
			),
		);
		if ($bulk_select['enabled']) {
			$width = $bulk_select['width'];
			$bs_value = intval($width['value']);
			$bs_unit = $this->sanitize_css_unit($width['unit'] ?? 'px');
			if ($bs_value > 0 && $bs_unit !== 'auto') {
				$css .= "{$selector} .productbay-col-select { width: " . (string) $bs_value . "{$bs_unit}; }";
			}
		}

		return $css;
	}

	/**
	 * Check whether a column should be hidden from output.
	 *
	 * @param array $col Column configuration.
	 * @return bool True if the column visibility is set to 'none'.
	 * @since 1.0.0
	 */
	private function should_hide_column($col)
	{
		// Manual visibility override.
		if ( ( $col['advanced']['visibility'] ?? 'default' ) === 'none' ) {
			return true;
		}

		// Security: Automatically hide Pro-only columns if Pro version is not active.
		$pro_types = array( 'combined', 'cf' );
		if ( in_array( $col['type'] ?? '', $pro_types, true ) && ! defined( 'PRODUCTBAY_PRO_VERSION' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get CSS classes for a column, including responsive visibility classes.
	 *
	 * Breakpoints:
	 *   Mobile:  <= 767px
	 *   Tablet:  768px – 1023px
	 *   Desktop: >= 1024px
	 *
	 * @param array $col Column configuration.
	 * @return string[]
	 * @since 1.0.0
	 */
	private function get_column_classes($col)
	{
		$classes = array('productbay-col-' . (string) $col['id']);

		$visibility = $col['advanced']['visibility'] ?? 'default';

		$visibility_class_map = array(
			'desktop' => 'productbay-desktop-only',
			'tablet' => 'productbay-tablet-only',
			'mobile' => 'productbay-mobile-only',
			'not-mobile' => 'productbay-hide-mobile',
			'not-desktop' => 'productbay-hide-desktop',
			'not-tablet' => 'productbay-hide-tablet',
			'min-tablet' => 'productbay-min-tablet',
		);

		if (isset($visibility_class_map[$visibility])) {
			$classes[] = $visibility_class_map[$visibility];
		}

		return $classes;
	}

	/**
	 * Get inline styles for a column.
	 *
	 * @param array $col Column configuration.
	 * @return string Inline CSS string (currently empty, reserved for future use).
	 * @since 1.0.0
	 */
	private function get_column_styles($col)
	{
		return '';
	}



	/**
	 * Render taxonomy and product type filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings     Table settings.
	 * @param array $runtime_args Runtime arguments.
	 * @return void
	 */
	private function render_taxonomy_filters($settings, $runtime_args)
	{
		$filters = $settings['filters'] ?? array();
		if (empty($filters['enabled'])) {
			return;
		}

		$show_category = !isset($filters['showCategory']) || !empty($filters['showCategory']);
		$show_type = !isset($filters['showType']) || !empty($filters['showType']);

		echo '<div class="productbay-taxonomy-filters">';

		// Product Category Filter (custom checkbox dropdown).
		if ($show_category) {
			$categories = get_terms(
				array(
					'taxonomy' => 'product_cat',
					'hide_empty' => true,
				)
			);

			if (!is_wp_error($categories) && !empty($categories)) {
				// Support comma-separated slugs for multi-select.
				$current_cats = array();
				if (!empty($runtime_args['product_cat'])) {
					if (is_array($runtime_args['product_cat'])) {
						$current_cats = $runtime_args['product_cat'];
					} else {
						$current_cats = array_map('trim', explode(',', $runtime_args['product_cat']));
					}
				}

				$selected_count = count($current_cats);
				$trigger_text = $selected_count > 0
					/* translators: %d: number of selected categories */
					? sprintf(esc_html__('%s selected', 'productbay'), (string) $selected_count)
					: esc_html__('All Categories', 'productbay');

				echo '<div class="productbay-multiselect" data-filter="product_cat">';
				echo '<button type="button" class="productbay-multiselect-trigger">';
				echo '<span class="productbay-multiselect-text">' . esc_html($trigger_text) . '</span>';
				echo '</button>';
				echo '<div class="productbay-multiselect-dropdown">';

				foreach ($categories as $category) {
					$checked = in_array($category->slug, $current_cats, true) ? ' checked' : '';
					echo '<label class="productbay-multiselect-option">';
					echo '<input type="checkbox" value="' . esc_attr($category->slug) . '" ' . checked(in_array($category->slug, $current_cats, true), true, false) . ' />';
					echo '<span>' . esc_html($category->name) . '</span>';
					echo '</label>';
				}

				echo '</div>';
				echo '</div>';
			}
		}

		// Separator between category & type.
		// if ($show_category && $show_type) {
		// 	echo '<span class="productbay-filter-separator"></span>';
		// }

		// Product Type Filter.
		if ($show_type) {
			$types = wc_get_product_types();

			if (!empty($types)) {
				$current_type = $runtime_args['product_type'] ?? '';

				echo '<select class="productbay-filter-select" data-filter="product_type">';
				echo '<option value="">' . esc_html__('All Types', 'productbay') . '</option>';

				foreach ($types as $type_key => $type_label) {
					echo '<option value="' . esc_attr($type_key) . '" ' . selected($current_type, $type_key, false) . '>' . esc_html($type_label) . '</option>';
				}

				echo '</select>';
			}
		}

		echo '</div>';
	}

	/**
	 * Render the search bar HTML.
	 *
	 * @param array  $settings Table settings.
	 * @param string $value    Current search value.
	 * @return void
	 * @since 1.0.0
	 */
	private function render_search_bar($settings, $value = '')
	{
		// Placeholder for search input.
		// Placeholder for search input.
		echo '<div class="productbay-search ' . (!empty($value) ? 'has-value' : '') . '">';
		echo '<input type="text" value="' . esc_attr($value) . '" placeholder="' . esc_attr__('Search products...', 'productbay') . '" />';
		echo '<span class="productbay-search-clear" title="' . esc_attr__('Clear', 'productbay') . '"></span>';
		echo '</div>';
	}

	/**
	 * Render pagination links.
	 *
	 * @param \WP_Query $query        The current query object.
	 * @param array     $settings     Table settings.
	 * @param array     $runtime_args Runtime arguments (page_url, etc.).
	 * @return void
	 * @since 1.0.0
	 */
	private function render_pagination($query, $settings, $runtime_args = array())
	{
		$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
		// Override paged if passed in query args (for AJAX).
		if (!empty($query->query['paged'])) {
			$paged = $query->query['paged'];
		}

		$total = $query->max_num_pages;
		$mode  = $settings['pagination']['mode'] ?? 'standard';

		if ($total > 1) {
			echo '<div class="productbay-pagination" data-mode="' . esc_attr($mode) . '" data-current="' . esc_attr((string)max(1, $paged)) . '" data-total="' . esc_attr((string)$total) . '">';

			if ('standard' === $mode || !defined('PRODUCTBAY_PRO_VERSION')) {
				$base_url = !empty($runtime_args['page_url']) ? $runtime_args['page_url'] : get_pagenum_link(999999999);
				$base = str_replace('999999999', '%#%', $base_url);
				if (strpos($base, '%#%') === false) {
					$base = add_query_arg('paged', '%#%', $base);
				}

				echo wp_kses_post(
					paginate_links(
						array(
							'base' => $base,
							'format' => '',
							'current' => max(1, $paged),
							'total' => $total,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						)
					)
				);
			} elseif ('load_more' === $mode) {
				if ($paged < $total) {
					echo '<button type="button" class="productbay-load-more-btn productbay-button">';
					echo esc_html__('Load More', 'productbay');
					echo '<span class="productbay-spinner" style="display:none; margin-left: 8px;"></span>';
					echo '</button>';
				}
			} elseif ('infinite' === $mode) {
				if ($paged < $total) {
					echo '<div class="productbay-infinite-sentinel">';
					echo '<span class="productbay-spinner"></span>';
					echo '</div>';
				}
			}

			echo '</div>';
		}
	}

	/**
	 * Extracts all current cart items grouped exactly like frontend.js expects.
	 *
	 * @return array Cart tracking data.
	 * @since 1.0.2
	 */
	public static function get_cart_data()
	{
		if (!function_exists('WC') || !WC()->cart) {
			return array();
		}

		$cart_items = WC()->cart->get_cart();
		$data = array();

		foreach ($cart_items as $cart_item_key => $cart_item) {
			$product_id = $cart_item['product_id'];
			$variation_id = $cart_item['variation_id'];
			$quantity = $cart_item['quantity'];
			$attributes = array();

			if ($variation_id) {
				$attributes = $cart_item['variation'] ?? array();
			}

			// Replicate JS buildCartKey logic
			$attr_values = array();
			foreach ($attributes as $key => $val) {
				if (!empty($val)) {
					$attr_values[] = $val;
				}
			}
			sort($attr_values);
			$attr_str = implode('|', $attr_values);

			$cart_key = $variation_id ? "{$product_id}:" . ($attr_str ? $attr_str : $variation_id) : (string) $product_id;

			if (isset($data[$cart_key])) {
				$data[$cart_key]['quantity'] += $quantity;
			} else {
				$data[$cart_key] = array(
					'productId' => $product_id,
					'variationId' => $variation_id,
					'quantity' => $quantity,
					'attributes' => $attributes,
				);
			}
		}

		// Convert to list of pairs for JS Map instantiation
		$entries = array();
		foreach ($data as $key => $val) {
			$entries[] = array($key, $val);
		}

		return $entries;
	}

	/**
	 * Render AJAX response (rows and pagination)
	 *
	 * @param array $table        Full table configuration.
	 * @param array $runtime_args Runtime arguments (search, sort, paged).
	 * @return array
	 * @since 1.0.0
	 */
	public function render_ajax_response($table, $runtime_args)
	{
		$source = $table['source'] ?? array();
		$columns = $table['columns'] ?? array();
		$settings = $table['settings'] ?? array();

		// Store cart settings for use in render methods.
		$this->cart_settings = wp_parse_args(
			$settings['cart'] ?? array(),
			array(
				'enable' => true,
				'showQuantity' => true,
			)
		);

		// Store lightbox settings for use in render methods.
		$this->lightbox_enabled = isset($settings['features']['lightbox']) ? $settings['features']['lightbox'] : true;

		// Pass the showChildCount feature flag into cart_settings so render_cell can access it.
		$this->cart_settings['_show_child_count'] = $settings['features']['showChildCount'] ?? true;

		/**
		 * Set the Add to Cart text for AJAX renders.
		 * Mirrors the logic in render() — filter first, then global option fallback.
		 *
		 * @since 1.0.4
		 */
		$add_to_cart_text = \apply_filters('productbay_add_to_cart_text', '', $this->cart_settings);
		if (empty($add_to_cart_text)) {
			$global_settings = \get_option('productbay_settings', array());
			if (!empty($global_settings['add_to_cart_text'])) {
				$add_to_cart_text = $global_settings['add_to_cart_text'];
			}
		}
		$this->add_to_cart_text = $add_to_cart_text;

		/**
		 * Set the opener button text for AJAX renders.
		 *
		 * @since 1.0.4
		 */
		$select_options_text = \apply_filters('productbay_select_options_text', '', $this->cart_settings);
		if (empty($select_options_text)) {
			$global_settings_for_opener = \get_option('productbay_settings', array());
			if (!empty($global_settings_for_opener['select_options_text'])) {
				$select_options_text = $global_settings_for_opener['select_options_text'];
			}
		}
		$this->select_options_text = $select_options_text;

		/**
		 * Fires before the AJAX table render, allowing Pro modules to capture table config.
		 * This is critical for persistence — without this hook, the Pro module's
		 * set_current_table() never fires during AJAX, causing mode fallback to 'inline'.
		 *
		 * @since 1.0.3
		 *
		 * @param array $table The full table configuration.
		 */
		\do_action('productbay_before_table', $table);

		$args = $this->build_query_args($source, $settings, $runtime_args);

		/**
		 * Filters the table query arguments.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args         WP_Query arguments.
		 * @param array $settings     Table settings.
		 * @param array $runtime_args Runtime arguments from AJAX.
		 */
		$args = \apply_filters('productbay_query_args', $args, $settings, $runtime_args);

		$query = new \WP_Query($args);

		ob_start();
		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce global
				global $product;
				if (!is_object($product)) {
					$product = \wc_get_product(get_the_ID());
				}
				// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

				$product_type = $product->get_type();
				$in_stock = $product->is_in_stock() ? '1' : '0';

				/**
				 * Fires before each product row during AJAX rendering.
				 *
				 * @since 1.0.3
				 *
				 * @param \WC_Product $product The current product.
				 * @param array       $table   The full table configuration.
				 */
				\do_action('productbay_before_row', $product, $table);

				echo '<tr data-product-type="' . esc_attr($product_type) . '" data-product-id="' . esc_attr((string) $product->get_id()) . '" data-in-stock="' . esc_attr($in_stock) . '">';

				// Bulk Select - First.
				$bulk_select = $settings['features']['bulkSelect'] ?? array(
					'enabled' => true,
					'position' => 'last',
				);
				if ($bulk_select['enabled'] && ($bulk_select['position'] ?? 'last') === 'first') {
					$can_select = $product->is_in_stock() && !$product->is_type('external') && !$product->is_type('grouped') && !$product->is_type('variable') && $product->is_purchasable();
					echo '<td class="productbay-col-select">';
					echo '<input type="checkbox" class="productbay-select-product" value="' . esc_attr((string) $product->get_id()) . '" data-price="' . esc_attr((string) $product->get_price()) . '"' . ($can_select ? '' : ' disabled') . ' />';
					echo '</td>';
				}

				foreach ($columns as $col) {
					if ($this->should_hide_column($col)) {
						continue;
					}
					$td_classes = $this->get_column_classes($col);
					echo '<td class="' . esc_attr(implode(' ', $td_classes)) . '">';
					$this->render_cell($col, $product);
					echo '</td>';
				}

				// Bulk Select - Last.
				if ($bulk_select['enabled'] && ($bulk_select['position'] ?? 'last') === 'last') {
					$can_select = $product->is_in_stock() && !$product->is_type('external') && !$product->is_type('grouped') && !$product->is_type('variable') && $product->is_purchasable();
					echo '<td class="productbay-col-select">';
					echo '<input type="checkbox" class="productbay-select-product" value="' . esc_attr((string) $product->get_id()) . '" data-price="' . esc_attr((string) $product->get_price()) . '"' . ($can_select ? '' : ' disabled') . ' />';
					echo '</td>';
				}
				echo '</tr>';

				/**
				 * Fires after each product row during AJAX rendering.
				 * Critical for Pro module nested rows — without this hook,
				 * nested row containers are never injected during AJAX refresh.
				 *
				 * @since 1.0.3
				 *
				 * @param \WC_Product $product The current product.
				 * @param array       $table   The full table configuration.
				 */
				\do_action('productbay_after_row', $product, $table);
			}
			wp_reset_postdata();
		} else {
			$colspan = count(
				array_filter(
					$columns,
					function ($c) {
						return !$this->should_hide_column($c);
					}
				)
			);
			// Add +1 if bulk select enabled.
			if ($settings['features']['bulkSelect']['enabled'] ?? true) {
				++$colspan;
			}
			echo '<tr><td colspan="' . esc_attr((string) $colspan) . '" class="productbay-empty">' . esc_html__('No products found.', 'productbay') . '</td></tr>';
		}
		$rows = ob_get_clean();

		ob_start();
		if (!empty($settings['features']['pagination'])) {
			$this->render_pagination($query, $settings, $runtime_args);
		}
		$pagination = ob_get_clean();

		return array(
			'html' => $rows,
			'pagination' => $pagination,
		);
	}
}