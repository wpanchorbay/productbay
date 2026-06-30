<?php
/**
 * Frontend AJAX handler for table filtering and bulk add-to-cart.
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
use WpabProductBay\Http\Request;

/**
 * Class AjaxRenderer
 *
 * Handles frontend AJAX requests for table filtering, search, pagination,
 * and bulk add-to-cart operations. Registered via wp_ajax hooks.
 *
 * @since   1.0.0
 * @package WpabProductBay\Frontend
 */
class AjaxRenderer
{

	/**
	 * The table repository instance.
	 *
	 * @var TableRepository
	 * @since 1.0.0
	 */
	protected $repository;

	/**
	 * The HTTP request handler.
	 *
	 * @var Request
	 * @since 1.0.0
	 */
	protected $request;

	/**
	 * Initialize the AJAX renderer.
	 *
	 * @since 1.0.0
	 *
	 * @param TableRepository $repository Table data repository.
	 * @param Request         $request    HTTP request handler.
	 */
	public function __construct(TableRepository $repository, Request $request)
	{
		$this->repository = $repository;
		$this->request = $request;
	}

	/**
	 * Register AJAX action hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init()
	{
		\add_action('wp_ajax_productbay_filter', array($this, 'handle_filter'));
		\add_action('wp_ajax_nopriv_productbay_filter', array($this, 'handle_filter'));

		\add_action('wp_ajax_productbay_bulk_add_to_cart', array($this, 'handle_bulk_add_to_cart'));
		\add_action('wp_ajax_nopriv_productbay_bulk_add_to_cart', array($this, 'handle_bulk_add_to_cart'));

		\add_action('wp_ajax_productbay_get_cart_data', array($this, 'handle_get_cart_data'));
		\add_action('wp_ajax_nopriv_productbay_get_cart_data', array($this, 'handle_get_cart_data'));
	}

	/**
	 * Return the current ProductBay cart quantity map.
	 *
	 * Used by the frontend to re-sync the in-table "added" badges when the cart
	 * changes outside of the table (e.g. the customer removes or reduces an item
	 * in the mini-cart, cart block, or classic cart widget).
	 *
	 * @since 1.2.0
	 *
	 * @return void Sends JSON response and exits.
	 */
	public function handle_get_cart_data()
	{
		if (!check_ajax_referer('productbay_frontend', 'nonce', false)) {
			\wp_send_json_error(array('message' => 'Invalid nonce'));
		}

		if (!function_exists('WC') || !WC()->cart) {
			\wp_send_json_error(array('message' => 'Cart unavailable'));
		}

		\wp_send_json_success(array('cart_data' => TableRenderer::get_cart_data()));
	}

	/**
	 * Handle table filter/search/pagination AJAX requests.
	 *
	 * Validates nonce, retrieves table config, and returns rendered rows + pagination.
	 *
	 * @since 1.0.0
	 *
	 * @return void Sends JSON response and exits.
	 */
	public function handle_filter()
	{
		if (!check_ajax_referer('productbay_frontend', 'nonce', false)) {
			\wp_send_json_error(array('message' => 'Invalid nonce'));
		}

		$table_id = intval($_POST['table_id'] ?? 0);
		$s = sanitize_text_field(wp_unslash($_POST['s'] ?? ''));
		$paged = intval($_POST['paged'] ?? 1);
		$page_url = esc_url_raw(wp_unslash($_POST['page_url'] ?? ''));
		$price_min = isset($_POST['price_min']) && $_POST['price_min'] !== '' ? floatval($_POST['price_min']) : null;
		$price_max = isset($_POST['price_max']) && $_POST['price_max'] !== '' ? floatval($_POST['price_max']) : null;
		$type = isset($_POST['product_type']) ? sanitize_text_field(wp_unslash($_POST['product_type'])) : '';

		// Categories can arrive as an array (multi-select) or comma-separated string.
		// jQuery sends arrays as product_cat[] but PHP may also receive product_cat.
		$category = array();
		$raw_cat_key = isset($_POST['product_cat']) ? 'product_cat' : (isset($_POST['product_cat[]']) ? 'product_cat[]' : ''); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above
		if ($raw_cat_key && isset($_POST[$raw_cat_key])) {
			$raw_cats = wp_unslash($_POST[$raw_cat_key]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below per element
			if (is_array($raw_cats)) {
				$category = array_map('sanitize_text_field', $raw_cats);
			}
			else {
				$category = array_map('sanitize_text_field', array_map('trim', explode(',', $raw_cats)));
			}
			$category = array_filter($category);
		}

		if (!$table_id) {
			\wp_send_json_error(array('message' => 'Invalid table ID'));
		}

		$table = $this->repository->get_table($table_id);
		if (!$table) {
			\wp_send_json_error(array('message' => 'Table not found'));
		}

		// Security Hardening: Ensure table is published.
		if ('publish' !== ($table['status'] ?? '')) {
			\wp_send_json_error(array('message' => 'This table is not available for public viewing.'));
		}

		// Initialize renderer.
		$renderer = new TableRenderer($this->repository);

		$response = $renderer->render_ajax_response(
			$table,
			array(
			's' => $s,
			'paged' => $paged,
			'page_url' => $page_url,
			'price_min' => $price_min,
			'price_max' => $price_max,
			'product_cat' => $category,
			'product_type' => $type,
		)
		);

		/**
		 * Filters the AJAX filter response.
		 *
		 * @since 1.0.0
		 *
		 * @param array $response The response data (html, pagination).
		 * @param array $table    The table configuration.
		 */
		$response = \apply_filters('productbay_ajax_filter_response', $response, $table);

		\wp_send_json_success($response);
	}

	/**
	 * Handle bulk add-to-cart AJAX requests.
	 *
	 * Validates nonce, processes each item (with stock and variation checks),
	 * and returns success/error counts.
	 *
	 * @since 1.0.0
	 *
	 * @return void Sends JSON response and exits.
	 */
	public function handle_bulk_add_to_cart()
	{
		if (!check_ajax_referer('productbay_frontend', 'nonce', false)) {
			\wp_send_json_error(array('message' => 'Invalid nonce'));
		}

		$raw_items = wp_unslash($_POST['items'] ?? array()); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below per field
		if (empty($raw_items) || !is_array($raw_items)) {
			\wp_send_json_error(array('message' => __('No products selected', 'productbay')));
		}

		$added_count = 0;
		$errors = array();

		foreach ($raw_items as $item) {
			$product_id = intval($item['product_id'] ?? 0);
			$quantity = max(1, intval($item['quantity'] ?? 1));
			$variation_id = intval($item['variation_id'] ?? 0);
			$attributes = array();

			if (!empty($item['attributes']) && is_array($item['attributes'])) {
				foreach ($item['attributes'] as $key => $value) {
					$attributes[sanitize_text_field($key)] = sanitize_text_field($value);
				}
			}

			if (!$product_id) {
				continue;
			}

			$product = \wc_get_product($product_id);
			if (!$product) {
				$errors[] = sprintf(
					/* translators: %d: product ID */
					__('Product #%d not found.', 'productbay'),
					$product_id
				);
				continue;
			}

			// Skip non-purchasable types.
			if ($product->is_type('external') || $product->is_type('grouped')) {
				continue;
			}

			// Check stock via official WC API.
			if (!$product->is_in_stock()) {
				$errors[] = sprintf(
					/* translators: %s: product name */
					__('"%s" is out of stock.', 'productbay'),
					$product->get_name()
				);
				continue;
			}

			// Validate quantity against stock (if managed and no backorders).
			if ($product->managing_stock() && !$product->backorders_allowed()) {
				$stock_qty = $product->get_stock_quantity();
				if (null !== $stock_qty && $quantity > $stock_qty) {
					$quantity = $stock_qty; // Cap to available stock.
				}
			}

			// For variable products, validate variation.
			if ($product->is_type('variable')) {
				if (!$variation_id) {
					$errors[] = sprintf(
						/* translators: %s: product name */
						__('Please select options for "%s".', 'productbay'),
						$product->get_name()
					);
					continue;
				}

				$variation = \wc_get_product($variation_id);
				if (!$variation || !$variation->is_in_stock()) {
					$errors[] = sprintf(
						/* translators: %s: product name */
						__('Selected variation for "%s" is unavailable.', 'productbay'),
						$product->get_name()
					);
					continue;
				}

				// Validate variation stock quantity.
				if ($variation->managing_stock() && !$variation->backorders_allowed()) {
					$var_stock = $variation->get_stock_quantity();
					if (null !== $var_stock && $quantity > $var_stock) {
						$quantity = $var_stock;
					}
				}
			}

			try {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WooCommerce hook
				$passed_validation = \apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $attributes);

				if ($passed_validation) {
					$cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $attributes);
					if (!$cart_item_key) {
						$errors[] = sprintf(
							/* translators: %s: product name */
							__('Could not add "%s" to cart.', 'productbay'),
							$product->get_name()
						);
					}
					else {
						++$added_count;
					}
				}
				else {
					// Usually WC adds notices if validation fails, let's grab them.
					$wc_errors = wc_get_notices('error');
					if (!empty($wc_errors)) {
						foreach ($wc_errors as $notice) {
							$errors[] = wp_strip_all_tags($notice['notice']);
						}
						wc_clear_notices();
					}
				}
			}
			catch (\Exception $e) {
				$errors[] = $e->getMessage();
			}
		}

		if ($added_count > 0) {
			/**
			 * Fires after a successful bulk add-to-cart operation.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $added_count Number of products added.
			 * @param array $errors      Any errors encountered.
			 */
			\do_action('productbay_after_bulk_add_to_cart', $added_count, $errors);

			$response = array(
				/* translators: %d: number of products added to cart */
				'message' => sprintf(__('%d product(s) added to cart.', 'productbay'), $added_count),
				'added_count' => $added_count,
			);
			if (!empty($errors)) {
				$response['warnings'] = $errors;
			}

			// Mirror WooCommerce core's AJAX add-to-cart payload so the frontend can
			// refresh the cart UI (header count / mini-cart) without a page reload.
			// Computing the fragments here — in the same request that added the item —
			// guarantees they reflect the just-updated cart, on both classic (cart
			// fragments) and block (Mini-Cart block) themes.
			if (function_exists('WC') && WC()->cart) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WooCommerce hook.
				$response['fragments'] = \apply_filters('woocommerce_add_to_cart_fragments', array());
				$response['cart_hash'] = WC()->cart->get_cart_hash();
				// Authoritative ProductBay quantity map used to re-render in-table badges.
				$response['cart_data']  = TableRenderer::get_cart_data();
			}

			\wp_send_json_success($response);
		}
		else {
			\wp_send_json_error(
				array(
				'message' => __('Failed to add products to cart.', 'productbay'),
				'errors' => $errors,
			)
			);
		}
	}
}