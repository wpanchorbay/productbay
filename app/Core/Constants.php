<?php
/**
 * Centralized plugin constants (version, slugs, capabilities).
 *
 * @package ProductBay
 */

declare(strict_types=1);

namespace WpabProductBay\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Constants
 *
 * Centralized constants for the ProductBay plugin.
 *
 * @package WpabProductBay\Core
 * @since 1.0.0
 */
class Constants {


	/**
	 * Plugin Version
	 *
	 * @since 1.0.0
	 */
	public const VERSION = \PRODUCTBAY_VERSION;

	/**
	 * Plugin Slug
	 *
	 * @since 1.0.0
	 */
	public const PLUGIN_SLUG = 'productbay';

	/**
	 * Menu Slug
	 *
	 * @since 1.0.0
	 */
	public const MENU_SLUG = 'productbay';

	/**
	 * Text Domain
	 *
	 * @since 1.0.0
	 */
	public const TEXT_DOMAIN = 'productbay';

	/**
	 * User Capability required to access settings
	 *
	 * @since 1.0.0
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Menu Icon (Base64 SVG)
	 *
	 * @since 1.0.0
	 */
	/**
	 * Get the required admin capability (filterable for demo/delegation).
	 *
	 * Allows external code (e.g., an mu-plugin) to override the default
	 * 'manage_options' capability requirement to support restricted roles.
	 *
	 * @since 1.3.1
	 *
	 * @return string WordPress capability string.
	 */
	public static function get_capability(): string {
		return (string) \apply_filters( 'productbay_admin_capability', self::CAPABILITY );
	}

	public const MENU_ICON = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCA2NCA2NCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNNDggMGM4LjgzNyAwIDE2IDcuMTYzIDE2IDE2djMyYzAgOC44MzctNy4xNjMgMTYtMTYgMTZIMTZDNy4xNjMgNjQgMCA1Ni44MzcgMCA0OFYxNkMwIDcuMTYzIDcuMTYzIDAgMTYgMHpNMjEuNzU2IDEzLjQyOGE4LjUzNCA4LjUzNCAwIDAgMC04LjUzMyA4LjUzM3YyMC4yNjdhOC41MzMgOC41MzMgMCAwIDAgOC41MzMgOC41MzNoMjAuMjY3YTguNTMzIDguNTMzIDAgMCAwIDguNTMzLTguNTM0VjIxLjk2MmE4LjUzNCA4LjUzNCAwIDAgMC04LjUzMy04LjUzM3ptMy4yMDMgMTcuNnYxNS40NjZoLTMuMjAzYTQuMjY3IDQuMjY3IDAgMCAxLTQuMjY3LTQuMjY3di0xMS4yem0yMS4zMyAwdjExLjJhNC4yNjcgNC4yNjcgMCAwIDEtNC4yNjcgNC4yNjZIMjkuMjI3VjMxLjAyN3pNMjQuOTYgMTcuNjkzdjkuMDY3aC03LjQ3di00LjhhNC4yNjcgNC4yNjcgMCAwIDEgNC4yNjctNC4yNjd6bTE3LjA2NCAwYTQuMjY3IDQuMjY3IDAgMCAxIDQuMjY2IDQuMjY3djQuOEgyOS4yMjZ2LTkuMDY3eiIgZmlsbD0id2hpdGUiLz48L3N2Zz4=';
}
