<?php
/**
 * Shared Brain Monkey test case.
 *
 * Extend this (instead of PHPUnit\Framework\TestCase directly) for any test
 * that needs to stub or spy on WordPress functions/hooks via Brain Monkey.
 * Tests that only inspect plain files or PHP structures (no WP functions
 * involved) can extend PHPUnit\Framework\TestCase directly instead.
 *
 * @package WpabProductBay
 */

namespace WpabProductBay\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case wiring Brain Monkey's setup/teardown into PHPUnit.
 */
abstract class TestCase extends PHPUnitTestCase {

	/**
	 * Boot Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear Brain Monkey down after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
