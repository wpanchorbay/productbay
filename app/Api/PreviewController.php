<?php
/**
 * REST API controller for live table preview rendering.
 *
 * @package ProductBay
 */

declare(strict_types=1);

namespace WpabProductBay\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WpabProductBay\Http\Request;
use WpabProductBay\Data\TableRepository;
use WpabProductBay\Frontend\TableRenderer;

/**
 * Class PreviewController
 *
 * Handles live preview rendering for the table editor.
 * Accepts table configuration via POST and returns rendered HTML.
 *
 * @since   1.0.0
 * @package WpabProductBay\Api
 */
class PreviewController extends ApiController {


	/**
	 * The table repository instance.
	 *
	 * @var TableRepository
	 * @since 1.0.0
	 */
	protected $repository;

	/**
	 * Initialize the controller.
	 *
	 * @since 1.0.0
	 *
	 * @param TableRepository $repository Table data repository.
	 * @param Request         $request    HTTP request handler.
	 */
	public function __construct( TableRepository $repository, Request $request ) {
		parent::__construct( $request );
		$this->repository = $repository;
	}

	/**
	 * Render the table preview.
	 *
	 * @param \WP_REST_Request $request REST API request object.
	 * @return \WP_REST_Response
	 * @since 1.0.0
	 */
	public function preview( $request ) {
		$data = $request->get_json_params();

		// If 'data' wrapper exists (like in save), unwrap it.
		if ( isset( $data['data'] ) ) {
			$data = $data['data'];
		}

		// Initialize Renderer.
		// We might want to inject this, but for now instantiating is fine as it has simple dependencies.
		$renderer = new TableRenderer( $this->repository );

		// Render the HTML.
		$html = $renderer->render( $data );

		// Include the frontend CSS URL so the React preview iframe.
		// can load the base stylesheet for proper style isolation.
		$css_file = PRODUCTBAY_PATH . 'assets/css/frontend.css';
		$css_ver  = file_exists( $css_file ) ? filemtime( $css_file ) : PRODUCTBAY_VERSION;
		$base_css = PRODUCTBAY_URL . 'assets/css/frontend.css?ver=' . $css_ver;

		/**
		 * Filters the array of CSS URLs to load in the live preview iframe.
		 *
		 * @since 1.0.0
		 * @param array $css_urls Array of CSS URLs.
		 */
		$css_urls = apply_filters( 'productbay_preview_css_urls', array( $base_css ) );

		return \rest_ensure_response(
			array(
				'html'    => $html,
				'cssUrls' => $css_urls,
			)
		);
	}
}
