<?php
/**
 * ProductBay Demo Environment — mu-plugin.
 *
 * Configures the staging site at productbay.wpanchorbay.com for public demos.
 * Handles auto-login, role-based access, password protection, and admin lockdown.
 *
 * DEPLOYMENT: Copy this file to wp-content/mu-plugins/productbay-demo.php
 * on the staging server. mu-plugins load automatically and cannot be deactivated.
 *
 * @package ProductBayDemo
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Only activate on the demo/staging domain.
 * Prevents accidental activation on production if copied to the wrong server.
 */
define( 'PRODUCTBAY_DEMO_DOMAIN', 'productbay.wpanchorbay.com' );

if ( isset( $_SERVER['HTTP_HOST'] ) && $_SERVER['HTTP_HOST'] !== PRODUCTBAY_DEMO_DOMAIN ) {
	// Not the demo site — bail silently.
	return;
}

// ─── Constants ─────────────────────────────────────────────────────────────────

/**
 * The demo user login name.
 */
define( 'PRODUCTBAY_DEMO_USER_LOGIN', 'demo' );

/**
 * The demo user email.
 */
define( 'PRODUCTBAY_DEMO_USER_EMAIL', 'demo@wpanchorbay.com' );

/**
 * Custom capability granted to the demo role for ProductBay admin access.
 * Replaces the default 'manage_options' requirement.
 */
define( 'PRODUCTBAY_DEMO_CAPABILITY', 'manage_productbay' );

/**
 * Bump this version to force a role capabilities refresh on next load.
 */
define( 'PRODUCTBAY_DEMO_ROLE_VERSION', '1.0.0' );

// ═══════════════════════════════════════════════════════════════════════════════
// 1. DEMO USER ROLE
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Register the demo_user role with scoped capabilities.
 *
 * Grants full access to ProductBay and WooCommerce products while
 * explicitly denying user management, plugin/theme management,
 * and core settings.
 *
 * @since 1.0.0
 *
 * @return void
 */
function productbay_demo_setup_role() {
	// Remove existing role first (allows cap updates on version bumps).
	remove_role( 'demo_user' );

	add_role(
		'demo_user',
		'Demo User',
		array(
			// ── Core WordPress ──────────────────────────────────────────
			'read'                     => true,
			'upload_files'             => true,

			// ── Posts (ProductBay tables stored as custom post type) ─────
			'edit_posts'               => true,
			'publish_posts'            => true,
			'delete_posts'             => true,
			'edit_published_posts'     => true,
			'delete_published_posts'   => true,

			// ── Pages (for embedding ProductBay blocks/shortcodes) ──────
			'edit_pages'               => true,
			'publish_pages'            => true,
			'delete_pages'             => true,
			'edit_published_pages'     => true,
			'delete_published_pages'   => true,

			// ── WooCommerce ─────────────────────────────────────────────
			'manage_woocommerce'       => true,
			'edit_products'            => true,
			'publish_products'         => true,
			'delete_products'          => true,
			'edit_published_products'  => true,
			'manage_product_terms'     => true,
			'assign_product_terms'     => true,

			// ── ProductBay Admin Access ─────────────────────────────────
			// Custom capability used via the productbay_admin_capability filter.
			PRODUCTBAY_DEMO_CAPABILITY => true,

			// ── Explicitly Denied ───────────────────────────────────────
			'list_users'               => false,
			'edit_users'               => false,
			'create_users'             => false,
			'delete_users'             => false,
			'promote_users'            => false,
			'remove_users'             => false,
			'install_plugins'          => false,
			'activate_plugins'         => false,
			'delete_plugins'           => false,
			'update_plugins'           => false,
			'edit_plugins'             => false,
			'install_themes'           => false,
			'switch_themes'            => false,
			'edit_themes'              => false,
			'delete_themes'            => false,
			'update_themes'            => false,
			'update_core'              => false,
			'manage_options'           => false,
			'export'                   => false,
			'import'                   => false,
		)
	);
}

// Use a version flag to avoid re-writing the role on every page load.
if ( get_option( 'productbay_demo_role_version' ) !== PRODUCTBAY_DEMO_ROLE_VERSION ) {
	add_action(
		'init',
		function () {
			productbay_demo_setup_role();
			update_option( 'productbay_demo_role_version', PRODUCTBAY_DEMO_ROLE_VERSION );
		},
		0
	);
}

// ═══════════════════════════════════════════════════════════════════════════════
// 2. PRODUCTBAY CAPABILITY OVERRIDE
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Override ProductBay's admin capability requirement.
 *
 * The free plugin checks `Constants::get_capability()` which applies the
 * `productbay_admin_capability` filter (defaulting to 'manage_options').
 * We swap it for our custom capability so demo users can access ProductBay
 * without the full power of manage_options.
 *
 * @since 1.0.0
 *
 * @return string The custom demo capability.
 */
add_filter(
	'productbay_admin_capability',
	function () {
		return PRODUCTBAY_DEMO_CAPABILITY;
	}
);

// ═══════════════════════════════════════════════════════════════════════════════
// 3. AUTO-LOGIN
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Automatically log in visitors as the Demo User.
 *
 * Fires on `init` at priority 1 (before most plugins load).
 * Skips auto-login for wp-login.php, wp-cron.php, AJAX, REST, and CLI
 * so the real administrator can still log in manually.
 *
 * @since 1.0.0
 */
add_action(
	'init',
	function () {
		// Already logged in — nothing to do.
		if ( is_user_logged_in() ) {
			return;
		}

		// ONLY auto-login if the specific query parameter is present.
		if ( ! isset( $_GET['auto-login'] ) || 'true' !== $_GET['auto-login'] ) {
			return;
		}

		// Skip contexts where auto-login would be harmful or unnecessary.
		if (
			defined( 'DOING_CRON' ) && DOING_CRON ||
			defined( 'DOING_AJAX' ) && DOING_AJAX ||
			defined( 'REST_REQUEST' ) && REST_REQUEST ||
			defined( 'WP_CLI' ) ||
			defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST
		) {
			return;
		}

		// Allow real admin access through the login page.
		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
			return;
		}

		// Find the demo user.
		$demo_user = get_user_by( 'login', PRODUCTBAY_DEMO_USER_LOGIN );

		// Create the demo user if it doesn't exist yet.
		if ( ! $demo_user ) {
			$user_id = wp_insert_user(
				array(
					'user_login'   => PRODUCTBAY_DEMO_USER_LOGIN,
					'user_pass'    => wp_generate_password( 32, true, true ),
					'user_email'   => PRODUCTBAY_DEMO_USER_EMAIL,
					'role'         => 'demo_user',
					'display_name' => 'Demo User',
				)
			);

			if ( is_wp_error( $user_id ) ) {
				return; // Silently fail — don't break the site.
			}

			$demo_user = get_user_by( 'ID', $user_id );
		}

		if ( ! $demo_user ) {
			return;
		}

		// Authenticate the visitor as the demo user.
		wp_set_current_user( $demo_user->ID );
		wp_set_auth_cookie( $demo_user->ID, true );

		// Always redirect to the ProductBay admin dashboard to land nicely.
		wp_safe_redirect( admin_url( 'admin.php?page=productbay' ) );
		exit;
	},
	1
);

// ═══════════════════════════════════════════════════════════════════════════════
// 4. PASSWORD & PROFILE PROTECTION
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Block password reset requests for the demo user.
 *
 * @since 1.0.0
 *
 * @param bool $allow   Whether to allow the password reset.
 * @param int  $user_id The user ID.
 * @return bool
 */
add_filter(
	'allow_password_reset',
	function ( $allow, $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( $user && in_array( 'demo_user', (array) $user->roles, true ) ) {
			return false;
		}
		return $allow;
	},
	10,
	2
);

/**
 * Block profile update submissions for demo users.
 *
 * @since 1.0.0
 */
add_action(
	'personal_options_update',
	function () {
		if ( current_user_can( 'manage_options' ) ) {
			return; // Real admin — allow.
		}
		wp_die(
			esc_html__( 'Profile editing is disabled on the demo site.', 'productbay' ),
			esc_html__( 'Action Blocked', 'productbay' ),
			array( 'back_link' => true )
		);
	}
);

/**
 * Redirect demo users away from profile.php.
 *
 * @since 1.0.0
 */
add_action(
	'load-profile.php',
	function () {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=productbay' ) );
		exit;
	}
);

/**
 * Hide password fields and submit button on the profile page (CSS fallback).
 *
 * @since 1.0.0
 */
add_action(
	'admin_head-profile.php',
	function () {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<style>
			.user-pass1-wrap,
			.user-pass2-wrap,
			#password,
			.pw-weak,
			#submit,
			.user-email-wrap,
			.user-sessions-wrap,
			.user-application-passwords-wrap { display: none !important; }
		</style>';
	}
);

// ═══════════════════════════════════════════════════════════════════════════════
// 5. ADMIN MENU & PAGE RESTRICTIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Remove admin menu items that demo users should not access.
 *
 * @since 1.0.0
 */
add_action(
	'admin_menu',
	function () {
		// Real admin sees everything.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		// Remove top-level menus.
		remove_menu_page( 'options-general.php' );   // Settings.
		remove_menu_page( 'tools.php' );             // Tools.
		remove_menu_page( 'users.php' );             // Users.
		remove_menu_page( 'plugins.php' );           // Plugins.
		remove_menu_page( 'themes.php' );            // Appearance.
		remove_menu_page( 'update-core.php' );       // Updates.
		remove_menu_page( 'profile.php' );           // Profile (standalone).

		// Remove Updates sub-item from Dashboard.
		remove_submenu_page( 'index.php', 'update-core.php' );
	},
	999
);

/**
 * Redirect demo users away from restricted admin screens.
 *
 * @since 1.0.0
 */
add_action(
	'current_screen',
	function () {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$blocked_screens = array(
			'users',
			'user-edit',
			'user-new',
			'profile',
			'plugins',
			'plugin-install',
			'plugin-editor',
			'themes',
			'theme-install',
			'theme-editor',
			'customize',
			'options-general',
			'options-writing',
			'options-reading',
			'options-discussion',
			'options-media',
			'options-permalink',
			'options-privacy',
			'update-core',
			'export',
			'import',
			'site-health',
		);

		if ( in_array( $screen->id, $blocked_screens, true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=productbay' ) );
			exit;
		}
	}
);

// ═══════════════════════════════════════════════════════════════════════════════
// 6. LICENSE ENDPOINT PROTECTION
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Block non-admin users from modifying the Pro license via REST API.
 *
 * The LicenseController already checks manage_options, but this adds an
 * explicit safeguard at the REST dispatch level with a clear error message.
 *
 * @since 1.0.0
 */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		$route  = $request->get_route();
		$method = $request->get_method();

		// Only guard write operations (POST, DELETE) on the license endpoint.
		if (
			preg_match( '#^/productbay/v1/pro/license#', $route )
			&& 'GET' !== $method
			&& ! current_user_can( 'manage_options' )
		) {
			return new WP_Error(
				'rest_forbidden',
				__( 'License management is disabled on the demo site.', 'productbay' ),
				array( 'status' => 403 )
			);
		}

		return $result;
	},
	10,
	3
);

// ═══════════════════════════════════════════════════════════════════════════════
// 7. ADDITIONAL HARDENING
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Prevent demo users from logging out.
 *
 * Since they'll be auto-logged-in anyway, blocking logout avoids
 * a confusing redirect loop. Redirects to ProductBay admin instead.
 *
 * @since 1.0.0
 */
add_action(
	'wp_logout',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=productbay' ) );
			exit;
		}
	},
	1
);

/**
 * Remove the "Log Out" link from the admin bar for demo users.
 *
 * @since 1.0.0
 */
add_action(
	'admin_bar_menu',
	function ( $wp_admin_bar ) {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		// Remove "Edit My Profile" and "Log Out" from the user account menu.
		$wp_admin_bar->remove_node( 'user-actions' );

		// Re-add the user info node without the logout action.
		$current_user = wp_get_current_user();
		$wp_admin_bar->add_node(
			array(
				'id'    => 'user-actions',
				'title' => sprintf(
					/* translators: %s: display name */
					__( 'Howdy, %s', 'productbay' ),
					'<span class="display-name">' . esc_html( $current_user->display_name ) . '</span>'
				),
				'parent' => 'top-secondary',
				'meta'   => array( 'class' => 'with-avatar' ),
			)
		);
	},
	9999
);

/**
 * Display a subtle demo notice in the admin footer.
 *
 * @since 1.0.0
 */
add_filter(
	'admin_footer_text',
	function () {
		if ( current_user_can( 'manage_options' ) ) {
			return '';
		}

		return sprintf(
			'<span style="color: #888;">🎭 %s — %s</span>',
			esc_html__( 'ProductBay Demo Environment', 'productbay' ),
			esc_html__( 'This site resets every 4 hours.', 'productbay' )
		);
	}
);

/**
 * Replace the "Thank you for creating with WordPress" update nag.
 *
 * @since 1.0.0
 */
add_filter(
	'update_footer',
	function () {
		if ( current_user_can( 'manage_options' ) ) {
			return '';
		}
		return '';
	},
	999
);
