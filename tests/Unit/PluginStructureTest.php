<?php
/**
 * PluginStructureTest.
 *
 * Guards the packaging/autoloading invariants that the rest of the plugin
 * silently depends on: every shipped app/ file must be syntactically valid
 * and live in the WpabProductBay\ namespace, the PSR-4 map in composer.json
 * must actually resolve, and uninstall.php must exist and guard itself the
 * way WordPress requires.
 *
 * @package WpabProductBay
 */

namespace WpabProductBay\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Structural/packaging invariants for the app/ directory and composer.json.
 */
class PluginStructureTest extends TestCase {

	/**
	 * Absolute path to the plugin root (two levels up from tests/Unit).
	 *
	 * @var string
	 */
	private static $root;

	/**
	 * Set up the plugin root path once for the whole test case.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		self::$root = \dirname( __DIR__, 2 );
	}

	/**
	 * Recursively list every *.php file under app/.
	 *
	 * @return string[] Absolute paths.
	 */
	private static function app_php_files() {
		$files    = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( self::$root . '/app', \FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	/**
	 * Every PHP file under app/ must be syntactically valid, i.e. it would
	 * survive being loaded on a production server. A single stray parse
	 * error in a rarely-hit file otherwise ships silently until a customer's
	 * site white-screens on it.
	 */
	public function test_every_app_php_file_has_valid_syntax() {
		$files = self::app_php_files();
		$this->assertNotEmpty( $files, 'Found no PHP files under app/ — is the path right?' );

		foreach ( $files as $file ) {
			$output   = array();
			$exit_code = 0;
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Test-only syntax check, not shipped code.
			\exec( \escapeshellarg( PHP_BINARY ) . ' -l ' . \escapeshellarg( $file ) . ' 2>&1', $output, $exit_code );

			$this->assertSame(
				0,
				$exit_code,
				'Syntax error in ' . \str_replace( self::$root . '/', '', $file ) . ":\n" . \implode( "\n", $output )
			);
		}
	}

	/**
	 * Every PHP file under app/ (other than the "Silence is golden"
	 * directory-listing stubs WordPress convention ships, which
	 * intentionally declare no namespace) must live under the
	 * WpabProductBay\ namespace. Anything that doesn't either escaped the
	 * PSR-4 autoload map or is about to collide with another plugin's
	 * globals.
	 */
	public function test_every_app_php_file_declares_the_plugin_namespace() {
		$files = self::app_php_files();
		$this->assertNotEmpty( $files, 'Found no PHP files under app/ — is the path right?' );

		foreach ( $files as $file ) {
			if ( 'index.php' === \basename( $file ) ) {
				// Directory-listing silence stub: no classes, no namespace, by design.
				continue;
			}

			$contents = \file_get_contents( $file );
			$this->assertSame(
				1,
				\preg_match( '/^namespace\s+WpabProductBay(\\\\[A-Za-z0-9_\\\\]+)?;/m', $contents ),
				\str_replace( self::$root . '/', '', $file ) . ' does not declare a namespace under WpabProductBay\\.'
			);
		}
	}

	/**
	 * The PSR-4 map declared in composer.json ("WpabProductBay\" => "app/")
	 * must actually resolve real classes, not just look correct on paper.
	 * A sample across every top-level app/ subdirectory catches a mismatched
	 * folder/namespace pair without hardcoding a class-by-class list.
	 *
	 * This resolves paths by hand instead of calling class_exists()/
	 * spl_autoload — every app/ file starts with `if ( ! defined( 'ABSPATH' )
	 * ) { exit; }`, and this suite never boots WordPress, so autoloading one
	 * of these classes would execute that guard and silently kill the whole
	 * PHPUnit process (exit code 0, no output). Checking the file the PSR-4
	 * map would load, and that it declares the expected class, verifies the
	 * same invariant without ever including the file.
	 */
	public function test_psr4_autoload_map_resolves_a_sample_of_classes() {
		$composer_json = \json_decode( \file_get_contents( self::$root . '/composer.json' ), true );
		$psr4          = $composer_json['autoload']['psr-4'] ?? array();

		$prefix = 'WpabProductBay\\';
		$this->assertArrayHasKey( $prefix, $psr4, 'composer.json no longer declares the WpabProductBay\\ PSR-4 root.' );
		$this->assertSame( 'app/', $psr4[ $prefix ], 'composer.json PSR-4 root for WpabProductBay\\ no longer points at app/.' );

		$sample_classes = array(
			'WpabProductBay\\Core\\Plugin',
			'WpabProductBay\\Core\\Activator',
			'WpabProductBay\\Data\\TableRepository',
			'WpabProductBay\\Api\\ApiController',
			'WpabProductBay\\Admin\\Admin',
			'WpabProductBay\\Frontend\\Shortcode',
			'WpabProductBay\\Http\\Router',
			'WpabProductBay\\Blocks\\BlockManager',
		);

		foreach ( $sample_classes as $fqcn ) {
			$relative_class = \substr( $fqcn, \strlen( $prefix ) );
			$relative_path  = \str_replace( '\\', '/', $relative_class ) . '.php';
			$path           = self::$root . '/' . \rtrim( $psr4[ $prefix ], '/' ) . '/' . $relative_path;

			$this->assertFileExists( $path, $fqcn . ' does not resolve to a file via the PSR-4 map (expected ' . $relative_path . ').' );

			$short_name = \substr( $relative_class, \strrpos( $relative_class, '\\' ) + 1 );
			$contents   = \file_get_contents( $path );
			$this->assertSame(
				1,
				\preg_match( '/\bclass\s+' . \preg_quote( $short_name, '/' ) . '\b/', $contents ),
				$path . ' does not declare "class ' . $short_name . '".'
			);
		}
	}

	/**
	 * save_table() must refuse an ID that is not one of our tables, the same
	 * way delete_table() does. Without the check, POSTing a page or product
	 * ID retypes that post into a table, which delete_table() then accepts.
	 */
	public function test_save_table_refuses_ids_that_are_not_tables() {
		$contents = \file_get_contents( self::$root . '/app/Data/TableRepository.php' );

		$this->assertSame(
			1,
			\preg_match(
				'/function save_table\(.*?\$id > 0 && self::POST_TYPE !== get_post_type\( \$id \).*?wp_update_post/s',
				$contents
			),
			'save_table() must check get_post_type() before wp_update_post().'
		);
	}

	/**
	 * uninstall.php is what WordPress runs when a user deletes the plugin
	 * from wp-admin. It must exist, and — per the WordPress requirement —
	 * must refuse to run unless invoked by WordPress core itself, or any
	 * PHP file dropped into the plugin's uninstall path becomes a way to
	 * run arbitrary cleanup code by hitting the URL directly.
	 */
	public function test_uninstall_php_exists_and_guards_on_wp_uninstall_plugin_constant() {
		$path = self::$root . '/uninstall.php';
		$this->assertFileExists( $path );

		$contents = \file_get_contents( $path );
		$this->assertSame(
			1,
			\preg_match( '/defined\(\s*[\'"]WP_UNINSTALL_PLUGIN[\'"]\s*\)/', $contents ),
			'uninstall.php does not guard on defined( \'WP_UNINSTALL_PLUGIN\' ).'
		);
	}
}
