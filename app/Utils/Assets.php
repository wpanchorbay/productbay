<?php
/**
 * Shared asset cache-busting helper.
 *
 * @package ProductBay
 */

declare(strict_types=1);

namespace WpabProductBay\Utils;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 *
 * Resolves the version string used to cache-bust an enqueued script or
 * style, honoring PRODUCTBAY_DEV_MODE consistently across every enqueue
 * call site (frontend shortcode, blocks, and admin).
 *
 * @since 1.3.5
 * @package WpabProductBay\Utils
 */
class Assets {

	/**
	 * Resolve the cache-busting version string for an asset file.
	 *
	 * The version is part of the URL, so it is a *cache key*, not a
	 * revalidation token: change it and the browser cannot send a
	 * conditional request at all — it must fetch the whole file again. So
	 * it has to change when, and only when, the bytes change.
	 *
	 * Production uses `PRODUCTBAY_VERSION`, or the build's own content hash
	 * when the caller has one. For a plugin distributed through
	 * WordPress.org those bytes change exactly when the plugin version
	 * changes — there is no separate asset deploy — so the version is an
	 * accurate signal, is identical on every server and every site, and
	 * costs no filesystem call.
	 *
	 * `filemtime()` is deliberately NOT used in production. It records when
	 * a file was *written*, not what is in it, so anything that rewrites a
	 * file with identical content resets it: a host's backup restore, a
	 * migration plugin, a staging-to-live push, re-uploading over SFTP, or
	 * this repo's own `scripts/deploy-dist.sh` (`cp -r`, no `-p`). Each of
	 * those would silently expire every visitor's cached CSS and JS for no
	 * reason, and on multi-server hosting two servers can extract the same
	 * update seconds apart and then disagree about the version of identical
	 * files.
	 *
	 * Development is the one case where the file genuinely changes without
	 * the version changing, so there `filemtime()` is exactly right — and
	 * still better than `time()`, which would re-download every asset on
	 * every reload even when nothing was touched.
	 *
	 * @param string $absolute_path Absolute filesystem path to the asset.
	 * @param string $content_hash  Optional build-time content hash (e.g. the
	 *                              `version` key in a `*.asset.php` file).
	 * @return string
	 * @since 1.3.5
	 */
	public static function version( string $absolute_path, string $content_hash = '' ): string {
		if ( \defined( 'PRODUCTBAY_DEV_MODE' ) && \PRODUCTBAY_DEV_MODE ) {
			$mtime = \file_exists( $absolute_path ) ? \filemtime( $absolute_path ) : false;

			return false !== $mtime ? (string) $mtime : (string) time();
		}

		if ( '' !== $content_hash ) {
			return $content_hash;
		}

		return PRODUCTBAY_VERSION;
	}
}
