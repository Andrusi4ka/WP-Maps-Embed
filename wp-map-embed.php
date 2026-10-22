<?php
/**
 * Plugin Name:       WP Maps Embed
 * Plugin URI:        https://github.com/Andrusi4ka/WP-Maps-Embed
 * Description:       Google Maps on a page without copying iframe code: create a map from an address or Google's embed code, choose its height and the "click to activate" cover, and insert it with a shortcode or the WPBakery element.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Andrii Boiko
 * Author URI:        https://github.com/Andrusi4ka
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-map-embed
 * Domain Path:       /languages
 * Update URI:        https://github.com/Andrusi4ka/WP-Maps-Embed
 *
 * @package WP_Map_Embed
 */

// "Update URI": updates come from this plugin's GitHub releases
// (wp_map_embed_updates()), never from a wordpress.org plugin of the same folder name.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_MAP_EMBED_VERSION', '1.0.1' );
define( 'WP_MAP_EMBED_REPOSITORY', 'https://github.com/Andrusi4ka/WP-Maps-Embed/' );
define( 'WP_MAP_EMBED_FILE', __FILE__ );
define( 'WP_MAP_EMBED_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_MAP_EMBED_URL', plugin_dir_url( __FILE__ ) );

/**
 * Version string for a plugin asset: the plugin version plus the file's
 * modification time, so browsers fetch it again whenever it changes.
 *
 * @param string $path Path relative to the plugin folder.
 * @return string
 */
function wp_map_embed_asset_version( $path ) {
	$time = @filemtime( WP_MAP_EMBED_DIR . $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file just keeps the plain version.
	return $time ? WP_MAP_EMBED_VERSION . '.' . $time : WP_MAP_EMBED_VERSION;
}

require_once WP_MAP_EMBED_DIR . 'includes/class-wp-map-embed-settings.php';
require_once WP_MAP_EMBED_DIR . 'includes/class-wp-map-embed-render.php';
require_once WP_MAP_EMBED_DIR . 'includes/class-wp-map-embed-admin.php';
require_once WP_MAP_EMBED_DIR . 'includes/class-wp-map-embed-wpbakery.php';

/**
 * Register the map post type.
 */
function wp_map_embed_register_post_type() {
	register_post_type(
		WP_Map_Embed_Settings::POST_TYPE,
		array(
			'labels'          => array(
				'name'               => __( 'Maps', 'wp-map-embed' ),
				'singular_name'      => __( 'Map', 'wp-map-embed' ),
				'menu_name'          => __( 'WP Maps Embed', 'wp-map-embed' ),
				'add_new'            => __( 'Add map', 'wp-map-embed' ),
				'add_new_item'       => __( 'Add new map', 'wp-map-embed' ),
				'edit_item'          => __( 'Edit map', 'wp-map-embed' ),
				'new_item'           => __( 'New map', 'wp-map-embed' ),
				'all_items'          => __( 'All maps', 'wp-map-embed' ),
				'search_items'       => __( 'Search maps', 'wp-map-embed' ),
				'not_found'          => __( 'No maps found.', 'wp-map-embed' ),
				'not_found_in_trash' => __( 'No maps found in Trash.', 'wp-map-embed' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_position'   => 26,
			// 40px for sharp screens, shown at 20px (wp_map_embed_menu_icon_style()).
			'menu_icon'       => WP_MAP_EMBED_URL . 'assets/images/menu-icon.png',
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'rewrite'         => false,
		)
	);
}
add_action( 'init', 'wp_map_embed_register_post_type' );

/**
 * The menu icon is an image (in colour, not repainted like the dashicons):
 * shown at the dashicons' 20px and not faded as WordPress fades menu images.
 */
function wp_map_embed_menu_icon_style() {
	echo '<style>#adminmenu .menu-icon-' . esc_attr( WP_Map_Embed_Settings::POST_TYPE ) . ' .wp-menu-image img{width:20px;height:20px;padding-top:7px;opacity:1}</style>';
}
add_action( 'admin_head', 'wp_map_embed_menu_icon_style' );

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'wp-map-embed', false, dirname( plugin_basename( WP_MAP_EMBED_FILE ) ) . '/languages' );
	}
);

/**
 * Updates from GitHub: a new release (tag "vX.Y.Z" with its wp-map-embed.zip)
 * shows up under Plugins like any other update.
 */
function wp_map_embed_updates() {
	$library = WP_MAP_EMBED_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
	// Only where WordPress looks for updates: the admin and its cron.
	if ( ( ! is_admin() && ! wp_doing_cron() ) || ! file_exists( $library ) ) {
		return;
	}
	require_once $library;

	$checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( WP_MAP_EMBED_REPOSITORY, WP_MAP_EMBED_FILE, 'wp-map-embed' );
	// The release's zip, which holds just the plugin (not the repository's dev files).
	$checker->getVcsApi()->enableReleaseAssets( '/wp-map-embed\.zip($|[?&#])/i' );
}
add_action( 'plugins_loaded', 'wp_map_embed_updates' );

WP_Map_Embed_Render::init();
WP_Map_Embed_Admin::init();
WP_Map_Embed_WPBakery::init();
