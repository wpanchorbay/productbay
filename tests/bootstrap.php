<?php
/**
 * PHPUnit bootstrap file for ProductBay.
 *
 * This is a Brain Monkey level test suite: it mocks WordPress functions and
 * hooks in-process rather than booting WordPress core, a database, or Docker.
 * That means these tests can verify PHP-level logic (structure, string
 * parsing, class wiring) quickly, but they CANNOT verify behaviour that
 * depends on WordPress core itself actually running (real hooks firing,
 * real DB queries, real HTTP requests). Anything needing that belongs in a
 * separate wp-env/PHPUnit-integration suite, which is out of scope here.
 *
 * @package WpabProductBay
 */

// The Composer autoloader also provides the WpabProductBay\ PSR-4 map used
// by the tests themselves (e.g. PluginStructureTest resolving classes).
require_once __DIR__ . '/../vendor/autoload.php';

// composer.json's autoload-dev is intentionally left untouched (out of this
// agent's owned keys), so the shared test-support class is loaded directly.
require_once __DIR__ . '/TestCase.php';
