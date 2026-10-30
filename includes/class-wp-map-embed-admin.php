<?php
/**
 * Map edit screen: the settings beside a preview, and the shortcode box.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Map_Embed_Admin {

	const NONCE = 'wp_map_embed_save';

	public static function init() {
		add_action( 'add_meta_boxes_' . WP_Map_Embed_Settings::POST_TYPE, array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . WP_Map_Embed_Settings::POST_TYPE, array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		add_filter( 'manage_' . WP_Map_Embed_Settings::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . WP_Map_Embed_Settings::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function add_meta_boxes() {
		add_meta_box( 'wp-map-embed-settings', __( 'Map settings', 'wp-map-embed' ), array( __CLASS__, 'render_settings_box' ), null, 'normal', 'high' );
		add_meta_box( 'wp-map-embed-shortcode', __( 'Shortcode', 'wp-map-embed' ), array( __CLASS__, 'render_shortcode_box' ), null, 'side', 'high' );
	}

	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || WP_Map_Embed_Settings::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'wp-map-embed-admin', WP_MAP_EMBED_URL . 'assets/css/admin.css', array(), wp_map_embed_asset_version( 'assets/css/admin.css' ) );

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_style( 'wp-color-picker' );
			// The preview is a frame of its own (WP_Map_Embed_Preview) with the site's files.
			wp_enqueue_script( 'wp-map-embed-admin', WP_MAP_EMBED_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), wp_map_embed_asset_version( 'assets/js/admin.js' ), true );
			wp_localize_script(
				'wp-map-embed-admin',
				'wpMapEmbedAdmin',
				array(
					'frameTitle'  => __( 'Choose the placeholder image', 'wp-map-embed' ),
					'frameButton' => __( 'Use this image', 'wp-map-embed' ),
					'copied'      => __( 'Copied!', 'wp-map-embed' ),
					'shapeMax'    => WP_Map_Embed_Svg_Shape::MAX_BYTES,
					'shapeUpload' => __( 'Upload SVG', 'wp-map-embed' ),
					'shapeReplace' => __( 'Replace SVG', 'wp-map-embed' ),
					/* translators: %s: file name. */
					'shapeRead'   => __( '%s: reading…', 'wp-map-embed' ),
					'shapeNone'   => WP_Map_Embed_Svg_Shape::summary( null ),
					'shapeTooBig' => __( 'The file is over 1 MB.', 'wp-map-embed' ),
					'shapeNotSvg' => __( 'That is not an SVG file.', 'wp-map-embed' ),
					'shapeFailed' => __( 'No filled shapes found in that file.', 'wp-map-embed' ),
				)
			);
		}
	}

	public static function render_settings_box( $post ) {
		$values = WP_Map_Embed_Settings::get( $post->ID );
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );

		// As in WP Swiper: the menu on the left, the chosen page beside it
		// (admin.js), the preview on the right.
		echo '<div class="wp-map-embed-admin">';
		echo '<nav class="wp-map-embed-admin__menu" aria-label="' . esc_attr__( 'Map settings', 'wp-map-embed' ) . '">';
		foreach ( self::pages() as $page => $meta ) {
			printf(
				'<button type="button" class="wp-map-embed-admin__menu-item" data-page="%1$s" aria-controls="wp-map-embed-page-%1$s" aria-current="%2$s"><span class="dashicons %3$s" aria-hidden="true"></span><span class="wp-map-embed-admin__menu-title">%4$s</span></button>',
				esc_attr( $page ),
				'map' === $page ? 'true' : 'false',
				esc_attr( $meta[1] ),
				esc_html( $meta[0] )
			);
		}
		echo '</nav>';
		echo '<div class="wp-map-embed-admin__fields">';

		// Map.
		self::page_start( 'map' );
		self::row_start( 'source', __( 'Address or embed code', 'wp-map-embed' ) );
		printf(
			'<textarea id="wp-map-embed-source" name="wp_map_embed[source]" rows="3" class="large-text" placeholder="%1$s">%2$s</textarea>',
			esc_attr__( 'Street 1, City', 'wp-map-embed' ),
			esc_textarea( $values['source'] )
		);
		echo '<p class="description">' . wp_kses(
			__( 'Type an address or a place name, or paste the code from Google Maps: <em>Share → Embed a map → Copy HTML</em> (the whole <code>&lt;iframe&gt;</code> or just its link).', 'wp-map-embed' ),
			array(
				'em'   => array(),
				'code' => array(),
			)
		) . '</p>';
		// Shown for a saved link that is no map; after a change, by "Update preview".
		$invalid = '' !== $values['source'] && '' === WP_Map_Embed_Settings::embed_url( $values );
		echo '<p class="wp-map-embed-admin__error"' . ( $invalid ? '' : ' hidden' ) . '>' . esc_html__( 'This link cannot be shown on a page. In Google Maps use Share → Embed a map, or type the address instead.', 'wp-map-embed' ) . '</p>';
		self::row_end();

		self::row_start( 'zoom', __( 'Zoom', 'wp-map-embed' ), __( 'For an address. Google’s embed code keeps the zoom it was copied with.', 'wp-map-embed' ) );
		self::range( 'zoom', $values['zoom'], 1, 21, '' );
		self::row_end();

		self::row_start( 'map_type', __( 'Map type', 'wp-map-embed' ), __( 'Satellite comes with street and place names. “As copied”: the view of Google’s embed code; for an address, Map.', 'wp-map-embed' ) );
		self::segmented(
			'map_type',
			$values['map_type'],
			array(
				''          => __( 'As copied', 'wp-map-embed' ),
				'roadmap'   => __( 'Map', 'wp-map-embed' ),
				'satellite' => __( 'Satellite', 'wp-map-embed' ),
			)
		);
		self::row_end();

		self::row_start( 'language', __( 'Language', 'wp-map-embed' ), __( 'Of the names and labels on the map. “As copied”: the language of Google’s embed code; for an address, the visitor’s browser.', 'wp-map-embed' ) );
		echo '<select name="wp_map_embed[language]">';
		$languages = array_merge(
			array(
				''     => __( 'As copied', 'wp-map-embed' ),
				/* translators: %s: language code, e.g. "no". */
				'site' => sprintf( __( 'Site language (%s)', 'wp-map-embed' ), WP_Map_Embed_Settings::site_language() ),
			),
			WP_Map_Embed_Settings::languages()
		);
		foreach ( $languages as $code => $name ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $code ), selected( $values['language'], $code, false ), esc_html( $name ) );
		}
		echo '</select>';
		self::row_end();
		echo '</fieldset>';

		// Size.
		self::page_start( 'size' );
		self::row_start( 'size_mode', __( 'Size by', 'wp-map-embed' ) );
		self::segmented(
			'size_mode',
			$values['size_mode'],
			array(
				'height' => __( 'Height', 'wp-map-embed' ),
				'ratio'  => __( 'Proportions', 'wp-map-embed' ),
				'fill'   => __( 'Parent block', 'wp-map-embed' ),
			)
		);
		echo '<p class="description" data-mode="fill">' . esc_html__( 'The map takes the free height of its column: in a WPBakery row, as tall as the column beside it (less the column’s padding and other content). Where that is under 120px — a column alone in its row, columns stacked on phones — it takes the Height below.', 'wp-map-embed' ) . '</p>';
		self::row_end();

		self::row_start( 'height', __( 'Height', 'wp-map-embed' ), __( 'px, or vh: % of the window’s height. Empty on tablet or mobile: as on the larger screen.', 'wp-map-embed' ), '', true );
		echo '<div class="wp-map-embed-admin__responsive">';
		$range = WP_Map_Embed_Settings::HEIGHT_UNITS[ $values['height_unit'] ];
		foreach ( array( 'height', 'height_tablet', 'height_mobile' ) as $i => $key ) {
			$device = self::DEVICE_KEYS[ $i ];
			printf(
				// Empty tablet / mobile: admin.js shows the inherited height as the
				// placeholder. Not "required" on desktop: hidden, it could not say so.
				'<input type="number" class="wp-map-embed-admin__device-input" data-device="%1$s" name="wp_map_embed[%2$s]" value="%3$s" min="%4$d" max="%5$d" step="1" aria-label="%6$s"%7$s>',
				esc_attr( $device ),
				esc_attr( $key ),
				esc_attr( $values[ $key ] ? $values[ $key ] : '' ),
				(int) $range[0],
				(int) $range[1],
				esc_attr( __( 'Height', 'wp-map-embed' ) . ' — ' . self::devices()[ $device ][0] ),
				'desktop' === $device ? '' : ' hidden'
			);
		}
		// The unit, shared by all devices; admin.js sets the fields' limits for it.
		echo '<select name="wp_map_embed[height_unit]" aria-label="' . esc_attr__( 'Unit', 'wp-map-embed' ) . '">';
		foreach ( WP_Map_Embed_Settings::HEIGHT_UNITS as $unit => $limits ) {
			printf(
				'<option value="%1$s" data-min="%2$d" data-max="%3$d"%4$s>%1$s</option>',
				esc_attr( $unit ),
				(int) $limits[0],
				(int) $limits[1],
				selected( $values['height_unit'], $unit, false )
			);
		}
		echo '</select></div>';
		self::row_end();

		self::row_start( 'ratio', __( 'Proportions', 'wp-map-embed' ), __( 'Width : height.', 'wp-map-embed' ), '', true );
		echo '<div class="wp-map-embed-admin__responsive">';
		foreach ( array( 'ratio', 'ratio_tablet', 'ratio_mobile' ) as $i => $key ) {
			$device = self::DEVICE_KEYS[ $i ];
			printf(
				'<select class="wp-map-embed-admin__device-input" data-device="%1$s" name="wp_map_embed[%2$s]" aria-label="%3$s"%4$s>',
				esc_attr( $device ),
				esc_attr( $key ),
				esc_attr( __( 'Proportions', 'wp-map-embed' ) . ' — ' . self::devices()[ $device ][0] ),
				'desktop' === $device ? '' : ' hidden'
			);
			if ( 'desktop' !== $device ) {
				// Tablet and mobile start with an empty choice: as on the larger screen.
				echo '<option value="">' . esc_html__( 'Inherit', 'wp-map-embed' ) . '</option>';
			}
			foreach ( WP_Map_Embed_Settings::RATIOS as $ratio ) {
				printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $ratio ), selected( $values[ $key ], $ratio, false ) );
			}
			echo '</select>';
		}
		echo '</div>';
		self::row_end();

		self::responsive_px( 'radius', __( 'Corner radius', 'wp-map-embed' ), $values, 0, 100 );
		self::responsive_px(
			'scale',
			__( 'Map scale', 'wp-map-embed' ),
			$values,
			60,
			150,
			'',
			'%',
			__( 'The size of everything on the map: names, roads, Google’s buttons. Under 100%: smaller, more of the area in view; over 100%: larger, a little blurred. The block keeps its size.', 'wp-map-embed' )
		);
		echo '</fieldset>';

		// Stencil.
		self::page_start( 'shape' );
		self::row_start( 'shape', __( 'SVG stencil', 'wp-map-embed' ), __( 'The map shows only through the shapes of an SVG file (all of them together), the page round them. Filled shapes count; outlines, lines and text are left out. Keep Google’s logo and the bottom edge of the map in the shape: Google’s terms do not allow hiding them.', 'wp-map-embed' ) );
		self::shape_box( $values['shape'] );
		self::row_end();

		self::row_start( 'shape_position', __( 'Shape position', 'wp-map-embed' ), __( 'The shape keeps its proportions and fits the block; where their proportions differ, this is where it sits.', 'wp-map-embed' ), 'shape' );
		self::positions( 'shape_position', $values['shape_position'] );
		self::row_end();
		echo '</fieldset>';

		// Cover.
		self::page_start( 'cover' );
		self::row_start( 'activate', '' );
		self::checkbox( 'activate', $values['activate'], __( 'Cover the map until it is clicked', 'wp-map-embed' ), __( 'Visitors scroll past the map instead of zooming it by accident; a click makes it usable.', 'wp-map-embed' ) );
		self::row_end();

		self::row_start( 'grayscale', '', '', 'activate' );
		self::checkbox( 'grayscale', $values['grayscale'], __( 'Black and white until clicked', 'wp-map-embed' ) );
		self::row_end();

		self::row_start( 'defer', '', '', 'activate' );
		self::checkbox( 'defer', $values['defer'], __( 'Load the map only after a click', 'wp-map-embed' ), __( 'The page loads faster and Google gets nothing from visitors who do not open the map. Until the click the block shows its colour or the placeholder image.', 'wp-map-embed' ) );
		self::row_end();

		self::row_start( 'placeholder', __( 'Placeholder image', 'wp-map-embed' ), __( 'Shown until the click, for instance a screenshot of the map.', 'wp-map-embed' ), 'defer' );
		$image = $values['placeholder'] ? wp_get_attachment_image_url( $values['placeholder'], 'medium' ) : '';
		printf(
			'<div class="wp-map-embed-admin__image"><input type="hidden" name="wp_map_embed[placeholder]" value="%1$s"><span class="wp-map-embed-admin__thumb"%2$s>%3$s</span><button type="button" class="button wp-map-embed-admin__choose">%4$s</button> <button type="button" class="button-link wp-map-embed-admin__remove"%5$s>%6$s</button></div>',
			esc_attr( $image ? $values['placeholder'] : 0 ),
			$image ? '' : ' hidden',
			$image ? '<img src="' . esc_url( $image ) . '" alt="">' : '',
			esc_html__( 'Choose image', 'wp-map-embed' ),
			$image ? '' : ' hidden',
			esc_html__( 'Remove', 'wp-map-embed' )
		);
		self::row_end();

		self::row_start( 'overlay_color', __( 'Cover colour', 'wp-map-embed' ), '', 'activate' );
		self::color( 'overlay_color', $values['overlay_color'], '#dfdad7' );
		self::row_end();

		self::row_start( 'overlay_opacity', __( 'Cover opacity', 'wp-map-embed' ), '', 'activate' );
		self::range( 'overlay_opacity', $values['overlay_opacity'], 0, 100, '%' );
		self::row_end();

		self::row_start( 'icon', __( 'Icon', 'wp-map-embed' ), '', 'activate' );
		echo '<div class="wp-map-embed-admin__icons" role="radiogroup">';
		$icons = array(
			'hand' => __( 'Hand', 'wp-map-embed' ),
			'pin'  => __( 'Pin', 'wp-map-embed' ),
			'none' => __( 'None', 'wp-map-embed' ),
		);
		foreach ( $icons as $icon => $label ) {
			printf(
				'<label class="wp-map-embed-admin__icon"><input type="radio" name="wp_map_embed[icon]" value="%1$s"%2$s><span>%3$s%4$s</span></label>',
				esc_attr( $icon ),
				checked( $values['icon'], $icon, false ),
				WP_Map_Embed_Render::icon( $icon ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from fixed paths, escaped there.
				esc_html( $label )
			);
		}
		echo '</div>';
		self::row_end();

		self::row_start( 'icon_color', __( 'Icon colour', 'wp-map-embed' ), __( 'Empty: the theme’s primary colour.', 'wp-map-embed' ), 'icon' );
		self::color( 'icon_color', $values['icon_color'], '' );
		self::row_end();

		self::responsive_px( 'icon_size', __( 'Icon size', 'wp-map-embed' ), $values, 16, 200, 'icon' );

		self::row_start( 'label', __( 'Text', 'wp-map-embed' ), __( 'Under the icon, for example “Click to open the map”. Empty: no text.', 'wp-map-embed' ), 'activate' );
		printf( '<input type="text" class="regular-text" name="wp_map_embed[label]" value="%s">', esc_attr( $values['label'] ) );
		self::row_end();
		echo '</fieldset>';

		echo '</div>';

		// Beside the fields: the map as these settings draw it, in a monitor,
		// tablet or phone (admin.js posts the form into it).
		WP_Map_Embed_Preview::render_panel( $post );

		echo '</div>';
	}

	/**
	 * The pages of the settings, in the menu's order.
	 *
	 * @return array page => [ title, dashicon ].
	 */
	public static function pages() {
		return array(
			'map'   => array( __( 'Map', 'wp-map-embed' ), 'dashicons-location-alt' ),
			'size'  => array( __( 'Size', 'wp-map-embed' ), 'dashicons-editor-expand' ),
			'shape' => array( __( 'Shape', 'wp-map-embed' ), 'dashicons-star-filled' ),
			'cover' => array( __( 'Click to activate', 'wp-map-embed' ), 'dashicons-visibility' ),
		);
	}

	/**
	 * A page of the settings: its fields' box, the first one shown until
	 * admin.js shows the one chosen last. Closed with </fieldset>.
	 *
	 * @param string $page Page (pages()).
	 */
	private static function page_start( $page ) {
		printf(
			'<fieldset class="wp-map-embed-admin__group wp-map-embed-admin__page" id="wp-map-embed-page-%1$s" data-page="%1$s"%2$s><legend>%3$s</legend>',
			esc_attr( $page ),
			'map' === $page ? '' : ' hidden',
			esc_html( self::pages()[ $page ][0] )
		);
	}

	/** The devices in the order of a per-device value's keys (value, _tablet, _mobile). */
	const DEVICE_KEYS = array( 'desktop', 'tablet', 'mobile' );

	/**
	 * The devices of the per-device values.
	 *
	 * @return array device => [ title, dashicon ].
	 */
	public static function devices() {
		return array(
			'desktop' => array( __( 'Desktop (1025px and wider)', 'wp-map-embed' ), 'dashicons-desktop' ),
			'tablet'  => array( __( 'Tablet (768–1024px)', 'wp-map-embed' ), 'dashicons-tablet' ),
			'mobile'  => array( __( 'Mobile (below 768px)', 'wp-map-embed' ), 'dashicons-smartphone' ),
		);
	}

	/**
	 * @param string $key     Field key.
	 * @param string $label   Label ('' for a checkbox row).
	 * @param string $help    Description under the field.
	 * @param string $depends The field this one only applies with (admin.js greys it).
	 * @param bool   $devices A value per device: the desktop / tablet / mobile
	 *                        switcher by the label shows one device's field at a time.
	 */
	private static function row_start( $key, $label, $help = '', $depends = '', $devices = false ) {
		printf(
			'<div class="wp-map-embed-admin__row%1$s" data-field="%2$s"%3$s>',
			$devices ? ' wp-map-embed-admin__row--responsive' : '',
			esc_attr( $key ),
			$depends ? ' data-depends="' . esc_attr( $depends ) . '"' : ''
		);
		if ( $devices ) {
			// On the left the label and its help icon; on the right the device
			// icons over the field, at its right end.
			echo '<div class="wp-map-embed-admin__head"><span class="wp-map-embed-admin__label">' . esc_html( $label ) . '</span>';
			if ( '' !== $help ) {
				self::help_tip( $help );
				$help = '';
			}
			echo '</div><div class="wp-map-embed-admin__control">';
			echo '<span class="wp-map-embed-admin__devices" role="group" aria-label="' . esc_attr__( 'Device', 'wp-map-embed' ) . '">';
			foreach ( self::devices() as $device => $meta ) {
				printf(
					'<button type="button" class="wp-map-embed-admin__device%1$s" data-device="%2$s" aria-pressed="%3$s" title="%4$s"><span class="dashicons %5$s" aria-hidden="true"></span></button>',
					'desktop' === $device ? ' is-active' : '',
					esc_attr( $device ),
					'desktop' === $device ? 'true' : 'false',
					esc_attr( $meta[0] ),
					esc_attr( $meta[1] )
				);
			}
			// The control stays open for the field (row_end() closes it).
			echo '</span>';
		} else {
			if ( '' !== $label ) {
				echo '<span class="wp-map-embed-admin__label">' . esc_html( $label ) . '</span>';
			}
			echo '<div class="wp-map-embed-admin__control">';
		}
		if ( '' !== $help ) {
			// Printed after the control by row_end().
			self::$help = $help;
		}
	}

	/**
	 * A row of a number per device (key, key_tablet, key_mobile): an empty
	 * tablet / mobile field is as on the larger screen, 0 a value of its own.
	 *
	 * @param string $key     Field key (the desktop value).
	 * @param string $label   Label.
	 * @param array  $values  Map settings.
	 * @param int    $min     Least value.
	 * @param int    $max     Greatest value.
	 * @param string $depends The field this one only applies with (admin.js greys it).
	 * @param string $unit    Unit shown after the field.
	 * @param string $help    Help before the words on tablet and mobile.
	 */
	private static function responsive_px( $key, $label, $values, $min, $max, $depends = '', $unit = 'px', $help = '' ) {
		$inherit = __( 'Empty on tablet or mobile: as on the larger screen.', 'wp-map-embed' );
		self::row_start( $key, $label, '' !== $help ? $help . ' ' . $inherit : $inherit, $depends, true );
		echo '<div class="wp-map-embed-admin__responsive">';
		foreach ( array( $key, $key . '_tablet', $key . '_mobile' ) as $i => $name ) {
			$device = self::DEVICE_KEYS[ $i ];
			printf(
				'<input type="number" class="wp-map-embed-admin__device-input" data-device="%1$s" name="wp_map_embed[%2$s]" value="%3$s" min="%4$d" max="%5$d" step="1" aria-label="%6$s"%7$s>',
				esc_attr( $device ),
				esc_attr( $name ),
				esc_attr( (string) $values[ $name ] ),
				(int) $min,
				(int) $max,
				esc_attr( $label . ' — ' . self::devices()[ $device ][0] ),
				'desktop' === $device ? '' : ' hidden'
			);
		}
		echo '<span class="wp-map-embed-admin__unit">' . esc_html( $unit ) . '</span></div>';
		self::row_end();
	}

	/**
	 * Help text behind an "i" icon, shown on hover and keyboard focus (as in WP Swiper).
	 *
	 * @param string $text Help text.
	 */
	private static function help_tip( $text ) {
		printf(
			'<span class="wp-map-embed-admin__tip" tabindex="0" role="img" aria-label="%1$s" data-tip="%1$s"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span></span>',
			esc_attr( $text )
		);
	}

	/** Description of the open row. */
	private static $help = '';

	private static function row_end() {
		if ( '' !== self::$help ) {
			echo '<p class="description">' . esc_html( self::$help ) . '</p>';
			self::$help = '';
		}
		echo '</div></div>';
	}

	/**
	 * A switch (as WP Swiper's: a checkbox drawn as one, admin.css), its help
	 * behind an "i" icon beside it, not in the label (a click on the icon must
	 * not toggle it).
	 *
	 * @param string $key     Field key.
	 * @param bool   $checked On.
	 * @param string $label   Label.
	 * @param string $help    Help text ('' none).
	 */
	private static function checkbox( $key, $checked, $label, $help = '' ) {
		echo '<div class="wp-map-embed-admin__switch">';
		printf(
			'<label><input type="checkbox" name="wp_map_embed[%1$s]" value="1"%2$s> %3$s</label>',
			esc_attr( $key ),
			checked( $checked, true, false ),
			esc_html( $label )
		);
		if ( '' !== $help ) {
			self::help_tip( $help );
		}
		echo '</div>';
	}

	/**
	 * Buttons of which one is chosen (radio buttons).
	 *
	 * @param string $key     Field key.
	 * @param string $value   Chosen value.
	 * @param array  $options value => label.
	 */
	private static function segmented( $key, $value, $options ) {
		echo '<div class="wp-map-embed-admin__segmented" role="radiogroup">';
		foreach ( $options as $option => $label ) {
			printf(
				'<label data-value="%2$s"><input type="radio" name="wp_map_embed[%1$s]" value="%2$s"%3$s><span>%4$s</span></label>',
				esc_attr( $key ),
				esc_attr( $option ),
				checked( (string) $value, (string) $option, false ),
				esc_html( $label )
			);
		}
		echo '</div>';
	}

	private static function range( $key, $value, $min, $max, $unit ) {
		printf(
			'<div class="wp-map-embed-admin__range"><input type="range" min="%2$d" max="%3$d" value="%4$d" aria-hidden="true" tabindex="-1"><input type="number" name="wp_map_embed[%1$s]" min="%2$d" max="%3$d" value="%4$d">%5$s</div>',
			esc_attr( $key ),
			(int) $min,
			(int) $max,
			(int) $value,
			'' === $unit ? '' : '<span class="wp-map-embed-admin__unit">' . esc_html( $unit ) . '</span>'
		);
	}

	private static function color( $key, $value, $default ) {
		printf(
			'<input type="text" class="wp-map-embed-admin__color" name="wp_map_embed[%1$s]" value="%2$s" data-default-color="%3$s">',
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $default )
		);
	}

	/**
	 * The stencil's field: the shapes kept (geometry only, JSON) in a hidden
	 * input; the button reads a file into it as { name, svg } (admin.js),
	 * read on the server when the preview updates or the map is saved. The
	 * file is not uploaded to the media library.
	 *
	 * @param string $value The shapes kept.
	 */
	private static function shape_box( $value ) {
		$shape = WP_Map_Embed_Svg_Shape::decode( $value );
		printf(
			'<input type="hidden" name="wp_map_embed[shape]" value="%1$s"><div class="wp-map-embed-admin__shape"><span class="wp-map-embed-admin__shape-thumb"%2$s>%3$s</span><span class="wp-map-embed-admin__shape-summary">%4$s</span><span class="wp-map-embed-admin__shape-actions"><label class="button wp-map-embed-admin__shape-choose"><span>%5$s</span><input type="file" accept=".svg,image/svg+xml" class="screen-reader-text"></label> <button type="button" class="button-link wp-map-embed-admin__shape-remove"%6$s>%7$s</button></span></div>',
			esc_attr( $shape ? $value : '' ),
			$shape ? '' : ' hidden',
			$shape ? self::shape_thumb( $shape ) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from checked numbers, escaped there.
			esc_html( WP_Map_Embed_Svg_Shape::summary( $shape ) ),
			$shape ? esc_html__( 'Replace SVG', 'wp-map-embed' ) : esc_html__( 'Upload SVG', 'wp-map-embed' ),
			$shape ? '' : ' hidden',
			esc_html__( 'Remove', 'wp-map-embed' )
		);
	}

	/**
	 * The stencil in small: its shapes as drawn, the file's margins left out.
	 *
	 * @param array $data WP_Map_Embed_Svg_Shape::decode().
	 * @return string SVG markup.
	 */
	public static function shape_thumb( $data ) {
		$frame = WP_Map_Embed_Svg_Shape::frame( $data, true );
		$paths = '';
		foreach ( $frame['shapes'] as $shape ) {
			$paths .= sprintf( '<path d="%1$s" fill-rule="%2$s"/>', esc_attr( $shape['d'] ), esc_attr( $shape['rule'] ) );
		}
		// Numbers of the shapes' box (0–1 each way), drawn at its proportions.
		return sprintf(
			'<svg viewBox="0 0 1 1" preserveAspectRatio="none" style="aspect-ratio:%1$s" aria-hidden="true" focusable="false">%2$s</svg>',
			esc_attr( (string) max( 0.1, (float) $frame['ratio'] ) ),
			$paths
		);
	}

	/**
	 * Nine positions in a 3 × 3 grid (radio buttons).
	 *
	 * @param string $key   Field key.
	 * @param string $value Chosen position (WP_Map_Embed_Settings::SHAPE_POSITIONS).
	 */
	private static function positions( $key, $value ) {
		$names = array(
			'left top'      => __( 'Top left', 'wp-map-embed' ),
			'center top'    => __( 'Top', 'wp-map-embed' ),
			'right top'     => __( 'Top right', 'wp-map-embed' ),
			'left center'   => __( 'Left', 'wp-map-embed' ),
			'center center' => __( 'Center', 'wp-map-embed' ),
			'right center'  => __( 'Right', 'wp-map-embed' ),
			'left bottom'   => __( 'Bottom left', 'wp-map-embed' ),
			'center bottom' => __( 'Bottom', 'wp-map-embed' ),
			'right bottom'  => __( 'Bottom right', 'wp-map-embed' ),
		);
		echo '<div class="wp-map-embed-admin__positions" role="radiogroup">';
		foreach ( $names as $position => $name ) {
			printf(
				'<label title="%3$s"><input type="radio" name="wp_map_embed[%1$s]" value="%2$s" aria-label="%3$s"%4$s><span aria-hidden="true"></span></label>',
				esc_attr( $key ),
				esc_attr( $position ),
				esc_attr( $name ),
				checked( $value, $position, false )
			);
		}
		echo '</div>';
	}

	public static function render_shortcode_box( $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Save the map to get its shortcode.', 'wp-map-embed' ) . '</p>';
			return;
		}
		self::render_shortcode_input( $post->ID );
		echo '<p class="description">' . esc_html__( 'Paste it into any page, or use the “WP Maps Embed” element in WPBakery.', 'wp-map-embed' ) . '</p>';
	}

	private static function render_shortcode_input( $post_id ) {
		printf(
			'<input type="text" class="wp-map-embed-shortcode" readonly value="%s" onclick="this.select()">',
			esc_attr( sprintf( '[%s id="%d"]', WP_Map_Embed_Render::SHORTCODE, $post_id ) )
		);
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$settings = isset( $_POST['wp_map_embed'] ) ? (array) wp_unslash( $_POST['wp_map_embed'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field.
		// Slashed: update_post_meta() unslashes, and the stencil's JSON (or a
		// text with a backslash) must keep its backslashes.
		update_post_meta( $post_id, WP_Map_Embed_Settings::META, wp_slash( WP_Map_Embed_Settings::sanitize( $settings ) ) );
	}

	public static function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['wp_map_embed_shortcode'] = __( 'Shortcode', 'wp-map-embed' );
			}
		}
		return $out;
	}

	public static function column_content( $column, $post_id ) {
		if ( 'wp_map_embed_shortcode' === $column ) {
			self::render_shortcode_input( $post_id );
		}
	}
}
