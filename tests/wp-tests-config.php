<?php
/**
 * WordPress test suite configuration. Everything comes from the environment
 * (see tests/README.md), so the same file serves a local run and CI. The
 * tests drop and recreate every table of this database: never point it at
 * a site's own.
 */

$env = function ( $name, $default ) {
	$value = getenv( $name );
	return false === $value ? $default : $value;
};

// The WordPress core the tests run on (`wp core download --path=...`).
define( 'ABSPATH', rtrim( str_replace( '\\', '/', $env( 'WP_CORE_DIR', '/tmp/wordpress' ) ), '/' ) . '/' );

define( 'DB_NAME', $env( 'WP_TESTS_DB_NAME', 'wp_map_embed_tests' ) );
define( 'DB_USER', $env( 'WP_TESTS_DB_USER', 'root' ) );
define( 'DB_PASSWORD', $env( 'WP_TESTS_DB_PASSWORD', '' ) );
define( 'DB_HOST', $env( 'WP_TESTS_DB_HOST', 'localhost' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'WP Map Embed Tests' );
define( 'WP_PHP_BINARY', PHP_BINARY );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
