<?php
/**
 * WPBakery Page Builder element that wraps the [wp_map_embed] shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Map_Embed_WPBakery {

	public static function init() {
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
	}

	public static function map() {
		if ( function_exists( 'vc_lean_map' ) ) {
			// Lean map: settings (and the maps query) load only when the editor needs them.
			vc_lean_map( WP_Map_Embed_Render::SHORTCODE, array( __CLASS__, 'settings' ) );
		}
	}

	/**
	 * The "Add element" tab: with the Woodmart theme its own "Theme elements",
	 * named as the theme names it; otherwise WPBakery's "Content".
	 *
	 * @return string
	 */
	private static function category() {
		if ( function_exists( 'woodmart_get_tab_title_category_for_wpb' ) ) {
			return woodmart_get_tab_title_category_for_wpb( esc_html__( 'Theme elements', 'woodmart' ) ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- the theme's own tab name.
		}
		return __( 'Content', 'wp-map-embed' );
	}

	/**
	 * @return array Element definition.
	 */
	public static function settings() {
		return array(
			'name'        => __( 'WP Maps Build', 'wp-map-embed' ),
			'base'        => WP_Map_Embed_Render::SHORTCODE,
			// 64px, shown at WPBakery's 32px.
			'icon'        => WP_MAP_EMBED_URL . 'assets/images/wpbakery-icon.png',
			'category'    => self::category(),
			// Last in the "Add element" list (WPBakery sorts by weight, default 0).
			'weight'      => -100,
			'description' => __( 'Google map created under WP Maps Build', 'wp-map-embed' ),
			'params'      => array(
				array(
					'type'        => 'dropdown',
					'heading'     => __( 'Map', 'wp-map-embed' ),
					'param_name'  => 'id',
					'value'       => self::map_options(),
					'admin_label' => true,
					'description' => sprintf(
						/* translators: %s: link to the maps screen */
						__( 'Create and edit maps under %s.', 'wp-map-embed' ),
						'<a href="' . esc_url( admin_url( 'edit.php?post_type=' . WP_Map_Embed_Settings::POST_TYPE ) ) . '" target="_blank">' . esc_html__( 'WP Maps Build', 'wp-map-embed' ) . '</a>'
					),
				),
			),
		);
	}

	/**
	 * @return array WPBakery dropdown values: label => value.
	 */
	private static function map_options() {
		$options = array( __( '— Select map —', 'wp-map-embed' ) => '' );

		$maps = get_posts(
			array(
				'post_type'      => WP_Map_Embed_Settings::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $maps as $map ) {
			$title = $map->post_title ? $map->post_title : __( '(no title)', 'wp-map-embed' );
			$options[ sprintf( '%s (#%d)', $title, $map->ID ) ] = (string) $map->ID;
		}

		return $options;
	}
}
