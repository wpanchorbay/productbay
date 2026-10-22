<?php
/**
 * Global cache-generation counter invalidation.
 *
 * @package ProductBay
 */

declare(strict_types=1);

namespace WpabProductBay\Data;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CacheInvalidator
 *
 * Owns the single global `productbay_cache_gen` counter and bumps it
 * whenever a product, its stock, its terms, or a ProductBay table changes.
 * Every ProductBay cache key includes this counter, so bumping it
 * invalidates everything the plugin has cached in one write.
 *
 * Today the only consumer is the resolved filter-option list cached by
 * TableRenderer::get_available_filter_options(). Before 1.3.5 that cache
 * expired on its 12-hour TTL alone, so a table could keep offering a
 * category for up to twelve hours after its products had left it.
 *
 * This is deliberately coarse: any product edit flushes every table's
 * cache, rather than attempting per-table or per-product invalidation.
 * Coarse is correct by construction — there is nothing subtle to get
 * wrong — where precise invalidation would need to reason about which
 * tables could possibly be affected by a given change.
 *
 * @since 1.3.5
 * @package WpabProductBay\Data
 */
class CacheInvalidator {

	/**
	 * Option name for the global cache-invalidation generation counter.
	 *
	 * @since 1.3.5
	 */
	const OPTION_GENERATION = 'productbay_cache_gen';

	/**
	 * Guards against bumping the counter more than once per request.
	 *
	 * A bulk product import or a multi-item order can fire several of the
	 * hooks below within a single request; only the first bump matters.
	 *
	 * @var bool
	 * @since 1.3.5
	 */
	private static $bumped = false;

	/**
	 * Register the invalidation hooks.
	 *
	 * @return void
	 * @since 1.3.5
	 */
	public static function init() {
		// A product (or variation) post changed — covers publish, trash,
		// and ordinary edits, including via the block editor and REST.
		\add_action( 'clean_post_cache', array( __CLASS__, 'on_clean_post_cache' ), 10, 2 );

		// WooCommerce's own "this product changed" signal. This is what
		// covers scheduled sales, which change _price with no post save.
		\add_action( 'woocommerce_delete_product_transients', array( __CLASS__, 'bump' ) );

		// Stock quantity changes for simple products and variations.
		\add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'bump' ) );
		\add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'bump' ) );
		\add_action( 'woocommerce_updated_product_stock', array( __CLASS__, 'bump' ) );

		// Category/tag/attribute term assignment changed for a product.
		\add_action( 'set_object_terms', array( __CLASS__, 'on_set_object_terms' ), 10, 4 );

		// A ProductBay table was saved or deleted (via the REST-backed
		// repository, and via the WP post-save hook as a defensive backstop).
		\add_action( 'productbay_after_save_table', array( __CLASS__, 'bump' ) );
		\add_action( 'productbay_after_delete_table', array( __CLASS__, 'bump' ) );
		\add_action( 'save_post_productbay_table', array( __CLASS__, 'bump' ) );
	}

	/**
	 * Bump the counter only when the cleared post is a product or variation.
	 *
	 * @param int      $id   Post ID whose cache was cleared.
	 * @param \WP_Post $post The post object.
	 * @return void
	 * @since 1.3.5
	 */
	public static function on_clean_post_cache( $id, $post ) {
		if ( isset( $post->post_type ) && in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			self::bump();
		}
	}

	/**
	 * Bump the counter only when the changed terms are on a product taxonomy.
	 *
	 * @param int    $object_id Object ID the terms were set on.
	 * @param array  $terms     Term slugs or IDs.
	 * @param array  $tt_ids    Term taxonomy IDs.
	 * @param string $taxonomy  Taxonomy name.
	 * @return void
	 * @since 1.3.5
	 */
	public static function on_set_object_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, \get_object_taxonomies( 'product' ), true ) ) {
			self::bump();
		}
	}

	/**
	 * Increment the generation counter, at most once per request.
	 *
	 * @return void
	 * @since 1.3.5
	 */
	public static function bump() {
		if ( self::$bumped ) {
			return;
		}
		self::$bumped = true;

		\update_option( self::OPTION_GENERATION, self::get_generation() + 1, true );
	}

	/**
	 * Get the current cache-invalidation generation counter.
	 *
	 * @return int
	 * @since 1.3.5
	 */
	public static function get_generation(): int {
		return (int) \get_option( self::OPTION_GENERATION, 1 );
	}
}
