<?php
/**
 * Data access layer for ProductBay table custom post type.
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
 * Class TableRepository
 *
 * Data access layer for ProductBay table posts (CPT: productbay_table).
 * Handles CRUD operations, product count queries, and data formatting.
 *
 * @since   1.0.0
 * @package WpabProductBay\Data
 */
class TableRepository {


	/**
	 * Post Type Name
	 */
	const POST_TYPE = 'productbay_table';

	/**
	 * Get all tables.
	 *
	 * @since 1.0.0
	 */
	public function get_tables() {
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'any', // Return all tables (publish, private, etc.).
			)
		);

		$tables = array();
		foreach ( $query->posts as $post ) {
			$tables[] = $this->format_table( $post );
		}
		return $tables;
	}

	/**
	 * Get single table.
	 *
	 * @param int $id Post ID.
	 * @return array|null Table data or null if not found.
	 * @since 1.0.0
	 */
	public function get_table( $id ) {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		return $this->format_table( $post );
	}

	/**
	 * Save table.
	 *
	 * @param array $data Table data from the frontend.
	 * @return array Saved table data or error array.
	 * @since 1.0.0
	 */
	public function save_table( $data ) {
		$id = isset( $data['id'] ) ? intval( $data['id'] ) : 0;

		/**
		 * Filters table data before it is persisted.
		 *
		 * @since 1.0.0
		 *
		 * @param array $data The table data from the frontend.
		 * @param int   $id   The table post ID (0 for new tables).
		 */
		$data = \apply_filters( 'productbay_before_save_table', $data, $id );

		// Frontend sends 'title' and 'status', not 'tableTitle' and 'tableStatus'.
		$title            = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : 'Untitled Table';
		$allowed_statuses = array( 'publish', 'private', 'draft', 'pending' );
		$raw_status       = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'publish';
		$status           = in_array( $raw_status, $allowed_statuses, true ) ? $raw_status : 'publish';

		// Extract components.
		$source   = isset( $data['source'] ) ? $data['source'] : array();
		$columns  = isset( $data['columns'] ) ? $data['columns'] : array();
		$settings = isset( $data['settings'] ) ? $data['settings'] : array();
		$style    = isset( $data['style'] ) ? $data['style'] : array();

		$post_data = array(
			'post_title'  => $title,
			'post_type'   => self::POST_TYPE,
			'post_status' => $status,
			'meta_input'  => array(
				'_productbay_source'   => $source,
				'_productbay_columns'  => $columns,
				'_productbay_settings' => $settings,
				'_productbay_style'    => $style,
				// Validating existence of legacy key removal.
				'_productbay_config'   => '', // Clear legacy config to avoid confusion.
			),
		);

		if ( $id > 0 ) {
			$post_data['ID'] = $id;
			$post_id         = wp_update_post( $post_data );
		} else {
			$post_id = wp_insert_post( $post_data );
		}

		if ( is_wp_error( $post_id ) ) {
			return array( 'error' => $post_id->get_error_message() );
		}

		/**
		 * Fires after a table is successfully saved.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $post_id The saved post ID.
		 * @param array $data    The table data that was saved.
		 */
		\do_action( 'productbay_after_save_table', $post_id, $data );

		return $this->get_table( $post_id );
	}

	/**
	 * Delete table.
	 *
	 * @param int $id Post ID.
	 * @return \WP_Post|false|null Deleted post object or false/null on failure.
	 * @since 1.0.0
	 */
	public function delete_table( $id ) {
		$result = wp_delete_post( $id, true );

		/**
		 * Fires after a table is deleted.
		 *
		 * @since 1.0.0
		 *
		 * @param int $id The deleted post ID.
		 */
		\do_action( 'productbay_after_delete_table', $id );

		return $result;
	}

	/**
	 * Format a post into a table data array.
	 *
	 * @param \WP_Post $post WordPress post object.
	 * @return array Formatted table data.
	 * @since 1.0.0
	 */
	private function format_table( $post ) {
		// Retrieve individual meta keys.
		$source   = get_post_meta( $post->ID, '_productbay_source', true );
		$source   = $source ? $source : array();
		$columns  = get_post_meta( $post->ID, '_productbay_columns', true );
		$columns  = $columns ? $columns : array();
		$settings = get_post_meta( $post->ID, '_productbay_settings', true );
		$settings = $settings ? $settings : array();
		$style    = get_post_meta( $post->ID, '_productbay_style', true );
		$style    = $style ? $style : array();

		$table_data = array(
			'id'           => $post->ID,
			'title'        => $post->post_title,
			'status'       => $post->post_status,
			'date'         => $post->post_date,
			'modifiedDate' => $post->post_modified,
			'shortcode'    => '[productbay id="' . $post->ID . '"]',
			'permalink'    => get_permalink( $post->ID ),
			'productCount' => $this->get_product_count( $source ),
			'source'       => $source,
			'columns'      => $columns,
			'settings'     => $settings,
			'style'        => $style,
		);

		/**
		 * Filters the formatted table data returned by the repository.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $table_data The formatted table data.
		 * @param \WP_Post $post       The original post object.
		 */
		return \apply_filters( 'productbay_table_data', $table_data, $post );
	}

	/**
	 * Efficiently count the number of products matching a table's source rules.
	 * Uses fields => 'ids' and limits query to 1 post, relying on found_posts for the count.
	 *
	 * @param array $source The table's source configuration array.
	 * @return int The number of matching products.
	 * @since 1.0.0
	 */
	private function get_product_count( $source ) {
		// Require WooCommerce.
		if ( ! function_exists( 'wc_get_products' ) ) {
			return 0;
		}

		$source_type = $source['type'] ?? 'all';
		$query_args  = $source['queryArgs'] ?? array();

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			// Optimize for counting.
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		);

		switch ( $source_type ) {
			case 'specific':
				if ( ! empty( $query_args['postIds'] ) ) {
					$args['post__in'] = $query_args['postIds'];
				} else {
					return 0; // No products selected.
				}
				break;

			case 'category':
				if ( ! empty( $query_args['categoryIds'] ) ) {
					$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							array(
							'taxonomy' => 'product_cat',
							'field'    => 'term_id',
							'terms'    => $query_args['categoryIds'],
							'operator' => 'IN',
						),
					);
				} else {
					return 0; // No categories selected.
				}
				break;

			case 'sale':
				$sale_ids = \wc_get_product_ids_on_sale();
				if ( empty( $sale_ids ) ) {
					return 0;
				}
				$args['post__in'] = $sale_ids;
				break;
		}

		// Handle Excludes.
		if ( ! empty( $query_args['excludes'] ) ) {
			$args['post__not_in'] = $query_args['excludes']; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
		}

		// Handle Stock Status.
		$stock_status = $query_args['stockStatus'] ?? 'any';
		if ( 'any' !== $stock_status ) {
			$args['meta_query'][] = array(
				'key'   => '_stock_status',
				'value' => $stock_status,
			);
		}

		// Handle Price Range.
		if ( isset( $query_args['priceRange']['min'] ) || isset( $query_args['priceRange']['max'] ) ) {
			$min = $query_args['priceRange']['min'] ?? 0;
			$max = $query_args['priceRange']['max'];

			$args['meta_query'][] = array(
				'key'     => '_price',
				'value'   => array( $min, $max ? $max : 999999999 ),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
		}

		$query = new \WP_Query( $args );
		return (int) $query->found_posts;
	}

	/**
	 * Human-readable label for a table's post status.
	 *
	 * The builder only ever sets `publish` or `private`, but save() also
	 * accepts `draft` and `pending`, so a table created through the REST API,
	 * an import, or WP-CLI can arrive with either. Naming the status keeps the
	 * admin notices from calling every unpublished table "private", which is a
	 * meaningfully different thing: private means published but restricted to
	 * capable logged-in users, draft means not published at all.
	 *
	 * Mirrors `src/utils/tableStatus.ts` on the admin side.
	 *
	 * @since 1.3.4
	 *
	 * @param string $status The table's post status.
	 * @return string Translated lower-case label, or the raw status when
	 *                another plugin has registered one we do not map.
	 */
	public static function status_label( string $status ): string {
		switch ( $status ) {
			case 'publish':
				return \_x( 'published', 'table status', 'productbay' );
			case 'private':
				return \_x( 'private', 'table status', 'productbay' );
			case 'draft':
				return \_x( 'draft', 'table status', 'productbay' );
			case 'pending':
				return \_x( 'pending review', 'table status', 'productbay' );
			default:
				return $status;
		}
	}
}
