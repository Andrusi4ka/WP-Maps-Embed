<?php
/**
 * Runs when the plugin is deleted under Plugins (not on deactivation or
 * update): removes the maps with their settings, and the update checker's
 * stored state.
 *
 * @package WP_Map_Embed
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete every map (any status, trash included) of the current site.
 * wp_delete_post() also removes the post meta (_wp_map_embed_settings).
 */
function wp_map_embed_uninstall_site() {
	global $wpdb;

	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'wp_map_embed' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off cleanup.
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
		switch_to_blog( $site_id );
		wp_map_embed_uninstall_site();
		restore_current_blog();
	}
} else {
	wp_map_embed_uninstall_site();
}

// Last update check (Plugin Update Checker).
delete_site_option( 'external_updates-wp-map-embed' );
