<?php
/**
 * ReadmeClaimsTest.
 *
 * This file is a scar record. No linter reads prose, so the plugin header,
 * package.json, and readme.txt drift silently against each other until a
 * WordPress.org reviewer (or a confused user) notices. Every assertion below
 * exists because that specific fact has drifted, or plausibly could drift,
 * at release time — most of them are release-day copy/paste fields nobody
 * double-checks under deadline pressure.
 *
 * These tests read the live values out of the shipping files at runtime.
 * None of them re-type a literal the test is supposed to be guarding: if a
 * value here ever hardcodes "1.3.4" or similar, the test has stopped doing
 * its job.
 *
 * @package WpabProductBay
 */

namespace WpabProductBay\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Cross-checks shipping prose (readme.txt) against shipping code (productbay.php, package.json).
 */
class ReadmeClaimsTest extends TestCase {

	/**
	 * Absolute path to the plugin root (two levels up from tests/Unit).
	 *
	 * @var string
	 */
	private static $root;

	/**
	 * Contents of productbay.php.
	 *
	 * @var string
	 */
	private static $plugin_file;

	/**
	 * Contents of readme.txt.
	 *
	 * @var string
	 */
	private static $readme;

	/**
	 * Decoded package.json.
	 *
	 * @var array
	 */
	private static $package_json;

	/**
	 * Load the shipping files once for the whole test case.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$root         = \dirname( __DIR__, 2 );
		self::$plugin_file  = \file_get_contents( self::$root . '/productbay.php' );
		self::$readme       = \file_get_contents( self::$root . '/readme.txt' );
		self::$package_json = \json_decode( \file_get_contents( self::$root . '/package.json' ), true );
	}

	/**
	 * Extract a `Field: value` line from a WordPress plugin header docblock.
	 *
	 * @param string $field Field label, e.g. "Version".
	 * @return string|null The trimmed value, or null if not found.
	 */
	private static function header_field( $field ) {
		$pattern = '/^[ \t]*\*[ \t]*' . \preg_quote( $field, '/' ) . ':[ \t]*(.+?)[ \t]*$/m';
		if ( \preg_match( $pattern, self::$plugin_file, $matches ) ) {
			return $matches[1];
		}
		return null;
	}

	/**
	 * Extract a `Field: value` line from readme.txt's top metadata block.
	 *
	 * @param string $field Field label, e.g. "Stable tag".
	 * @return string|null The trimmed value, or null if not found.
	 */
	private static function readme_field( $field ) {
		$pattern = '/^' . \preg_quote( $field, '/' ) . ':[ \t]*(.+?)[ \t]*$/m';
		if ( \preg_match( $pattern, self::$readme, $matches ) ) {
			return $matches[1];
		}
		return null;
	}

	/**
	 * Extract the string value of a `define( 'NAME', 'value' )` statement.
	 *
	 * @param string $constant_name Constant name, e.g. "PRODUCTBAY_VERSION".
	 * @return string|null The value, or null if not found.
	 */
	private static function defined_constant( $constant_name ) {
		$pattern = '/define\(\s*[\'"]' . \preg_quote( $constant_name, '/' ) . '[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]\s*\)/';
		if ( \preg_match( $pattern, self::$plugin_file, $matches ) ) {
			return $matches[1];
		}
		return null;
	}

	/**
	 * Return the substring of readme.txt between `== $heading ==` and the
	 * next top-level `== ... ==` heading (or end of file).
	 *
	 * @param string $heading Section heading text, e.g. "Changelog".
	 * @return string|null The section body, or null if the heading is absent.
	 */
	private static function readme_section( $heading ) {
		$pattern = '/==\s*' . \preg_quote( $heading, '/' ) . '\s*==(.*?)(?=\n==\s|\z)/s';
		if ( \preg_match( $pattern, self::$readme, $matches ) ) {
			return $matches[1];
		}
		return null;
	}

	/**
	 * The Version header, PRODUCTBAY_VERSION constant, package.json version,
	 * and readme.txt Stable tag are four independent places that all encode
	 * "the current version". A bump that touches only some of them ships a
	 * plugin that lies about its own version to at least one consumer.
	 */
	public function test_version_agrees_across_plugin_header_constant_package_json_and_readme() {
		$header_version   = self::header_field( 'Version' );
		$constant_version = self::defined_constant( 'PRODUCTBAY_VERSION' );
		$package_version  = self::$package_json['version'] ?? null;
		$stable_tag       = self::readme_field( 'Stable tag' );

		$this->assertNotNull( $header_version, 'Could not find "Version:" in the productbay.php header.' );
		$this->assertNotNull( $constant_version, 'Could not find the PRODUCTBAY_VERSION define() in productbay.php.' );
		$this->assertNotNull( $package_version, 'Could not find "version" in package.json.' );
		$this->assertNotNull( $stable_tag, 'Could not find "Stable tag:" in readme.txt.' );

		$this->assertSame( $header_version, $constant_version, 'productbay.php "Version:" header does not match its own PRODUCTBAY_VERSION constant.' );
		$this->assertSame( $header_version, $package_version, 'productbay.php "Version:" header does not match package.json "version".' );
		$this->assertSame( $header_version, $stable_tag, 'productbay.php "Version:" header does not match readme.txt "Stable tag:".' );
	}

	/**
	 * The plugin header also carries its own "Stable tag" line (WordPress.org
	 * convention), which is easy to bump in one place and forget in the
	 * other since it duplicates the "Version" line one row down.
	 */
	public function test_plugin_header_stable_tag_matches_version() {
		$header_version    = self::header_field( 'Version' );
		$header_stable_tag = self::header_field( 'Stable tag' );

		$this->assertNotNull( $header_stable_tag, 'Could not find "Stable tag:" in the productbay.php header.' );
		$this->assertSame( $header_version, $header_stable_tag, 'productbay.php header "Version:" and "Stable tag:" disagree.' );
	}

	/**
	 * "Requires at least" (minimum WP version) must agree between the code
	 * that WordPress core reads to decide whether to allow activation and
	 * the prose a store owner reads before installing.
	 */
	public function test_requires_at_least_agrees_between_plugin_header_and_readme() {
		$header_value = self::header_field( 'Requires at least' );
		$readme_value = self::readme_field( 'Requires at least' );

		$this->assertNotNull( $header_value, 'Could not find "Requires at least:" in the productbay.php header.' );
		$this->assertNotNull( $readme_value, 'Could not find "Requires at least:" in readme.txt.' );
		$this->assertSame( $header_value, $readme_value, '"Requires at least" disagrees between productbay.php and readme.txt.' );
	}

	/**
	 * "Requires PHP" gates activation via WordPress core; readme.txt tells
	 * the human. They must name the same minimum.
	 */
	public function test_requires_php_agrees_between_plugin_header_and_readme() {
		$header_value = self::header_field( 'Requires PHP' );
		$readme_value = self::readme_field( 'Requires PHP' );

		$this->assertNotNull( $header_value, 'Could not find "Requires PHP:" in the productbay.php header.' );
		$this->assertNotNull( $readme_value, 'Could not find "Requires PHP:" in readme.txt.' );
		$this->assertSame( $header_value, $readme_value, '"Requires PHP" disagrees between productbay.php and readme.txt.' );
	}

	/**
	 * "Tested up to" is a compatibility claim reviewed by WordPress.org and
	 * shown to users on the plugin page; it must match what the header
	 * actually declares.
	 */
	public function test_tested_up_to_agrees_between_plugin_header_and_readme() {
		$header_value = self::header_field( 'Tested up to' );
		$readme_value = self::readme_field( 'Tested up to' );

		$this->assertNotNull( $header_value, 'Could not find "Tested up to:" in the productbay.php header.' );
		$this->assertNotNull( $readme_value, 'Could not find "Tested up to:" in readme.txt.' );
		$this->assertSame( $header_value, $readme_value, '"Tested up to" disagrees between productbay.php and readme.txt.' );
	}

	/**
	 * The plugin header's Text Domain must literally be "productbay" — this
	 * is what wraps every __(), _e(), etc. call in the codebase.
	 */
	public function test_text_domain_is_productbay() {
		$this->assertSame( 'productbay', self::header_field( 'Text Domain' ) );
	}

	/**
	 * WordPress resolves translation files (and, historically, some tooling)
	 * from the plugin's own directory slug. If the Text Domain header ever
	 * diverges from the folder ProductBay actually ships in, translations
	 * silently stop loading for anyone who installs the packaged zip.
	 */
	public function test_text_domain_matches_plugin_directory_slug() {
		$slug = \basename( self::$root );

		$this->assertSame( $slug, self::header_field( 'Text Domain' ), 'Plugin "Text Domain:" does not match the plugin directory name ("' . $slug . '").' );
	}

	/**
	 * The single most commonly forgotten release-day step: bumping the
	 * version number everywhere else but forgetting to add (or update) the
	 * "= X.Y.Z =" changelog heading, leaving the changelog permanently one
	 * release behind.
	 */
	public function test_newest_changelog_heading_matches_current_version() {
		$changelog = self::readme_section( 'Changelog' );
		$this->assertNotNull( $changelog, 'Could not find "== Changelog ==" in readme.txt.' );

		$this->assertSame(
			1,
			\preg_match( '/^=\s*([0-9][0-9.]*)\s*=\s*$/m', $changelog, $matches ),
			'Could not find a "= X.Y.Z =" heading at the top of the Changelog section.'
		);

		$newest_changelog_version = $matches[1];
		$current_version          = self::header_field( 'Version' );

		$this->assertSame(
			$current_version,
			$newest_changelog_version,
			'The newest Changelog heading ("' . $newest_changelog_version . '") does not match the current plugin version ("' . $current_version . '"). Did the changelog entry for this release get forgotten?'
		);
	}

	/**
	 * Every "== Upgrade Notice ==" entry must point at a version that
	 * actually has a Changelog entry — otherwise the upgrade notice is
	 * advertising a release that (per the changelog) doesn't exist.
	 */
	public function test_every_upgrade_notice_heading_has_a_matching_changelog_heading() {
		$changelog      = self::readme_section( 'Changelog' );
		$upgrade_notice = self::readme_section( 'Upgrade Notice' );

		$this->assertNotNull( $changelog, 'Could not find "== Changelog ==" in readme.txt.' );
		$this->assertNotNull( $upgrade_notice, 'Could not find "== Upgrade Notice ==" in readme.txt.' );

		\preg_match_all( '/^=\s*([0-9][0-9.]*)\s*=\s*$/m', $changelog, $changelog_matches );
		\preg_match_all( '/^=\s*([0-9][0-9.]*)\s*=\s*$/m', $upgrade_notice, $notice_matches );

		$changelog_versions = $changelog_matches[1];
		$notice_versions    = $notice_matches[1];

		$this->assertNotEmpty( $notice_versions, 'Found no "= X.Y.Z =" headings inside "== Upgrade Notice ==".' );

		foreach ( $notice_versions as $version ) {
			$this->assertContains(
				$version,
				$changelog_versions,
				'"== Upgrade Notice ==" has an entry for ' . $version . ', but the Changelog has no matching "= ' . $version . ' =" heading.'
			);
		}
	}
}
