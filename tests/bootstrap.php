<?php
/**
 * PHPUnit bootstrap: the WordPress test suite (wp-phpunit, installed by
 * Composer) with this plugin loaded as a must-use plugin.
 */

$wp_map_embed_root = dirname( __DIR__ );
$wp_map_embed_deps = $wp_map_embed_root . '/tests/vendor';

if ( ! file_exists( $wp_map_embed_deps . '/autoload.php' ) ) {
	fwrite( STDERR, "Run `composer install` first (see tests/README.md).\n" );
	exit( 1 );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $wp_map_embed_deps . '/yoast/phpunit-polyfills' );
putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

$wp_map_embed_tests_dir = $wp_map_embed_deps . '/wp-phpunit/wp-phpunit';
require_once $wp_map_embed_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () use ( $wp_map_embed_root ) {
		require $wp_map_embed_root . '/wp-map-embed.php';
	}
);

require $wp_map_embed_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/class-wp-map-embed-testcase.php';
