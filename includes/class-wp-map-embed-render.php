<?php
/**
 * The [wp_map_embed id=""] shortcode: the map's frame under its "click to
 * activate" cover (frontend.js takes the cover away).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Map_Embed_Render {

	const SHORTCODE = 'wp_map_embed';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Registered on every page, enqueued only by a map.
	 */
	public static function register_assets() {
		wp_register_style( 'wp-map-embed', WP_MAP_EMBED_URL . 'assets/css/frontend.css', array(), wp_map_embed_asset_version( 'assets/css/frontend.css' ) );
		wp_register_script( 'wp-map-embed', WP_MAP_EMBED_URL . 'assets/js/frontend.js', array(), wp_map_embed_asset_version( 'assets/js/frontend.js' ), true );
	}

	/**
	 * @param array $atts Shortcode attributes: id, class.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'class' => '',
			),
			$atts,
			self::SHORTCODE
		);

		$post = get_post( absint( $atts['id'] ) );
		if ( ! $post || WP_Map_Embed_Settings::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}

		$html = self::markup( WP_Map_Embed_Settings::get( $post->ID ), $post->post_title, $atts['class'] );
		if ( '' !== $html ) {
			if ( ! wp_style_is( 'wp-map-embed', 'registered' ) ) {
				self::register_assets();
			}
			wp_enqueue_style( 'wp-map-embed' );
			wp_enqueue_script( 'wp-map-embed' );
		}
		return $html;
	}

	/**
	 * A map's markup (also the edit screen's preview).
	 *
	 * @param array  $settings Map settings.
	 * @param string $title    The map's name, the frame's title for screen readers.
	 * @param string $class    Extra classes.
	 * @return string '' without a usable map.
	 */
	public static function markup( $settings, $title = '', $class = '' ) {
		// Whatever version saved them (or a caller's own array): complete.
		$settings = WP_Map_Embed_Settings::prepare( $settings );
		$url      = WP_Map_Embed_Settings::embed_url( $settings );
		if ( '' === $url ) {
			return '';
		}

		// The cover is there while any of its options is on.
		$activate = WP_Map_Embed_Settings::has_cover( $settings );
		$defer    = ! empty( $settings['defer'] );
		$classes  = array( 'wp-map-embed' );
		if ( 'ratio' === $settings['size_mode'] ) {
			$classes[] = 'wp-map-embed--ratio';
		} elseif ( self::uses_auto( $settings ) ) {
			// frontend.js fits it to its parent on the devices set to Auto.
			$classes[] = 'wp-map-embed--auto';
		}
		if ( ! empty( $settings['grayscale'] ) ) {
			$classes[] = 'wp-map-embed--gray';
		}
		if ( ! $activate ) {
			$classes[] = 'is-active';
		}
		foreach ( preg_split( '/\s+/', (string) $class, -1, PREG_SPLIT_NO_EMPTY ) as $extra ) {
			$classes[] = sanitize_html_class( $extra );
		}

		$title = '' !== trim( (string) $title ) ? $title : __( 'Map', 'wp-map-embed' );
		$frame = sprintf(
			'<iframe class="wp-map-embed__frame" %1$s="%2$s" title="%3$s" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>',
			// Deferred: no frame request until the click (frontend.js sets the src).
			$defer ? 'data-src' : 'src',
			esc_url( $url ),
			esc_attr( $title )
		);

		$cover = '';
		if ( $activate ) {
			$label = ! empty( $settings['text_enabled'] ) ? trim( (string) $settings['label'] ) : '';
			$cover = sprintf(
				'<button type="button" class="wp-map-embed__cover"%1$s>%2$s%3$s</button>',
				'' === $label ? ' aria-label="' . esc_attr__( 'Show the interactive map', 'wp-map-embed' ) . '"' : '',
				! empty( $settings['icon_enabled'] ) ? self::icon( $settings['icon'] ) : '',
				'' === $label ? '' : '<span class="wp-map-embed__label">' . esc_html( $label ) . '</span>'
			);
		}

		$inside = $frame . $cover;
		$style  = self::style( $settings, $defer );
		$shape  = self::shape( $settings );
		if ( $shape ) {
			$classes[] = 'wp-map-embed--shape';
			$style    .= $shape['style'];
			// The clipped map and SVG stroke share one stage. Its drop-shadow
			// follows the visible contour, as in WP Swiper's Art shapes.
			$inside = sprintf(
				'<div class="wp-map-embed__shape-stage"><div class="wp-map-embed__shape" style="%1$s">%2$s%3$s</div></div>%4$s',
				esc_attr( '-webkit-clip-path:url(#' . $shape['id'] . ');clip-path:url(#' . $shape['id'] . ')' ),
				$inside,
				$shape['border'],
				$shape['clip']
			);
		}

		return sprintf(
			'<div class="%1$s" style="%2$s"%3$s>%4$s</div>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $style ),
			$defer ? ' data-defer' : '',
			$inside
		);
	}

	/**
	 * Whether any device takes its height from the parent (the Auto unit).
	 *
	 * @param array $settings Complete settings.
	 * @return bool
	 */
	private static function uses_auto( $settings ) {
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			if ( 'auto' === WP_Map_Embed_Settings::height_unit_for( $settings, $device ) ) {
				return true;
			}
		}
		return false;
	}

	/** Stencils rendered in this request (their clip paths' ids). */
	private static $shapes = 0;

	/**
	 * The stencil: the SVG file's shapes, with its margins left out, as one
	 * clip path in numbers of the stencil's own box (objectBoundingBox), so
	 * it scales with the box and no script is needed. frontend.css fits the
	 * box into the block at the shapes' proportions (--wme-shape-ratio) and
	 * places it (--wme-shape-x / -y). The shapes together are the stencil:
	 * the map shows through all of them.
	 *
	 * @param array $settings Map settings.
	 * @return array|null { id, clip: SVG markup, style: CSS variables }; null without a shape.
	 */
	public static function shape( $settings ) {
		$settings = WP_Map_Embed_Settings::prepare( $settings );
		if ( empty( $settings['shape_enabled'] ) ) {
			return null;
		}
		$data = WP_Map_Embed_Svg_Shape::decode( $settings['shape'] );
		if ( ! $data ) {
			return null;
		}
		$frame = WP_Map_Embed_Svg_Shape::frame( $data, true );
		$ratio = (float) $frame['ratio'];
		if ( $ratio <= 0 ) {
			return null;
		}

		$paths = '';
		foreach ( $frame['shapes'] as $shape ) {
			$paths .= sprintf( '<path d="%1$s" clip-rule="%2$s"/>', esc_attr( $shape['d'] ), esc_attr( $shape['rule'] ) );
		}
		// Named after the shapes, then counted: maps drawn in separate requests
		// and put on one page (WPBakery's front-end editor, cached fragments)
		// would otherwise both be "-1", the second cut by the first's shapes.
		// Same name, same shapes: then either clip path draws them alike.
		$id = 'wp-map-embed-shape-' . substr( md5( $paths ), 0, 8 ) . '-' . ( ++self::$shapes );
		$border = '';
		if ( ! empty( $settings['border'] ) ) {
			$strokes = '';
			foreach ( $frame['shapes'] as $shape ) {
				$strokes .= '<path d="' . esc_attr( $shape['d'] ) . '" vector-effect="non-scaling-stroke"/>';
			}
			$border_style = in_array( $settings['border_style'], WP_Map_Embed_Settings::BORDER_STYLES, true ) ? $settings['border_style'] : 'solid';
			// Double borders leave a transparent gap rather than painting over the map.
			$mask = '';
			if ( 'double' === $border_style ) {
				$mask = '<defs><mask id="' . esc_attr( $id . '-border-gap' ) . '" maskUnits="userSpaceOnUse" x="0" y="0" width="1" height="1" style="mask-type:luminance"><rect width="1" height="1" fill="white"/><g class="wp-map-embed__border-gap">' . $strokes . '</g><g class="wp-map-embed__border-inner">' . $strokes . '</g></mask></defs>';
			}
			$border = '<svg class="wp-map-embed__shape-border wp-map-embed__shape-border--' . esc_attr( $border_style ) . '" viewBox="0 0 1 1" preserveAspectRatio="none" aria-hidden="true" focusable="false">' . $mask . '<g class="wp-map-embed__border-line"' . ( $mask ? ' mask="url(#' . esc_attr( $id . '-border-gap' ) . ')"' : '' ) . '>' . $strokes . '</g></svg>';
		}
		$position = is_string( $settings['shape_position'] ) && isset( WP_Map_Embed_Settings::SHAPE_POSITIONS[ $settings['shape_position'] ] )
			? WP_Map_Embed_Settings::SHAPE_POSITIONS[ $settings['shape_position'] ]
			: WP_Map_Embed_Settings::SHAPE_POSITIONS['center center'];

		$position_style = '--wme-shape-x:' . self::number( $position[0] ) . ';--wme-shape-y:' . self::number( $position[1] ) . ';';
		foreach ( array( 'tablet', 'mobile' ) as $device ) {
			$key = 'shape_position_' . $device;
			if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && isset( WP_Map_Embed_Settings::SHAPE_POSITIONS[ $settings[ $key ] ] ) ) {
				$position = WP_Map_Embed_Settings::SHAPE_POSITIONS[ $settings[ $key ] ];
			}
			$position_style .= '--wme-shape-x-' . $device . ':' . self::number( $position[0] ) . ';--wme-shape-y-' . $device . ':' . self::number( $position[1] ) . ';';
		}

		return array(
			'id'    => $id,
			'border' => $border,
			'clip'  => sprintf(
				'<svg class="wp-map-embed__clip" width="0" height="0" aria-hidden="true" focusable="false"><clipPath id="%1$s" clipPathUnits="objectBoundingBox">%2$s</clipPath></svg>',
				esc_attr( $id ),
				$paths
			),
			'style' => '--wme-shape-ratio:' . self::number( $ratio ) . ';' . $position_style,
		);
	}

	/**
	 * A number for CSS: a dot for decimals whatever the locale, no trailing zeros.
	 *
	 * @param float $value Number.
	 * @return string
	 */
	private static function number( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 5, '.', '' ), '0' ), '.' );
	}

	/**
	 * The map's CSS variables (frontend.css uses them).
	 *
	 * @param array $settings Map settings.
	 * @param bool  $defer    Whether the frame waits for a click.
	 * @return string
	 */
	public static function style( $settings, $defer ) {
		$settings      = WP_Map_Embed_Settings::prepare( $settings );
		$scale_enabled = ! empty( $settings['scale_enabled'] );
		$unit          = WP_Map_Embed_Settings::height_unit_for( $settings );
		$vars = array(
			'--wme-height' => (int) $settings['height'] . ( 'auto' === $unit ? 'px' : $unit ),
			'--wme-auto' => 'auto' === $unit ? '1' : '0',
			'--wme-radius' => ! empty( $settings['rounded'] ) ? (int) $settings['radius'] . 'px' : '0px',
			'--wme-cover'  => ! empty( $settings['overlay_enabled'] ) ? self::rgba( $settings['overlay_color'], (int) $settings['overlay_opacity'] ) : 'transparent',
			'--wme-icon'   => (int) $settings['icon_size'] . 'px',
		);
		if ( ! empty( $settings['border'] ) ) {
			$vars['--wme-border-width'] = (int) $settings['border_width'] . 'px';
			$vars['--wme-border-style'] = in_array( $settings['border_style'], WP_Map_Embed_Settings::BORDER_STYLES, true ) ? $settings['border_style'] : 'solid';
			$vars['--wme-border-color'] = WP_Map_Embed_Settings::sanitize_color( $settings['border_color'] );
			foreach ( array( 'tablet', 'mobile' ) as $device ) {
				if ( '' !== $settings[ 'border_width_' . $device ] ) {
					$vars[ '--wme-border-width-' . $device ] = (int) $settings[ 'border_width_' . $device ] . 'px';
				}
			}
		}
		if ( ! empty( $settings['shadow'] ) ) {
			$vars['--wme-shadow'] = sprintf( '%dpx %dpx %dpx %dpx %s', (int) $settings['shadow_x'], (int) $settings['shadow_y'], (int) $settings['shadow_blur'], (int) $settings['shadow_spread'], WP_Map_Embed_Settings::sanitize_color( $settings['shadow_color'] ) );
			$vars['--wme-shape-shadow'] = sprintf( 'drop-shadow(%dpx %dpx %dpx %s)', (int) $settings['shadow_x'], (int) $settings['shadow_y'], (int) $settings['shadow_blur'], WP_Map_Embed_Settings::sanitize_color( $settings['shadow_color'] ) );
		}
		// Unset: as on the larger device (frontend.css falls back).
		foreach ( array( 'tablet', 'mobile' ) as $device ) {
			$device_unit = WP_Map_Embed_Settings::height_unit_for( $settings, $device );
			$vars[ '--wme-auto-' . $device ] = 'auto' === $device_unit ? '1' : '0';
			if ( (int) $settings[ 'height_' . $device ] ) {
				$vars[ '--wme-height-' . $device ] = (int) $settings[ 'height_' . $device ] . ( 'auto' === $device_unit ? 'px' : $device_unit );
			}
		}
		$per_device = array(
			'radius'    => '--wme-radius-',
			'icon_size' => '--wme-icon-',
		);
		foreach ( $per_device as $key => $prefix ) {
			if ( 'radius' === $key && empty( $settings['rounded'] ) ) {
				continue;
			}
			foreach ( array( 'tablet', 'mobile' ) as $device ) {
				$value = isset( $settings[ $key . '_' . $device ] ) ? $settings[ $key . '_' . $device ] : '';
				// '' as on the larger device; 0 is a value of its own.
				if ( '' !== $value && null !== $value ) {
					$vars[ $prefix . $device ] = (int) $value . 'px';
				}
			}
		}
		// The map's scale, as a factor; 100% (none) not written. Tablet and
		// mobile only with a value of their own (else as on the larger device).
		$scale = isset( $settings['scale'] ) ? (int) $settings['scale'] : 100;
		if ( $scale_enabled && 100 !== $scale ) {
			$vars['--wme-scale'] = self::number( $scale / 100 );
		}
		foreach ( array( 'tablet', 'mobile' ) as $device ) {
			$value = isset( $settings[ 'scale_' . $device ] ) ? $settings[ 'scale_' . $device ] : '';
			if ( $scale_enabled && '' !== $value && null !== $value ) {
				$vars[ '--wme-scale-' . $device ] = self::number( (int) $value / 100 );
			}
		}
		if ( isset( $settings['size_mode'] ) && 'ratio' === $settings['size_mode'] ) {
			// "16:9" => "16 / 9"; unset on tablet / mobile: as on the larger device.
			$names = array(
				'ratio'        => '--wme-ratio',
				'ratio_tablet' => '--wme-ratio-tablet',
				'ratio_mobile' => '--wme-ratio-mobile',
			);
			$ratio = '16:9';
			$custom = '4x3';
			foreach ( $names as $key => $name ) {
				$custom_key = str_replace( 'ratio', 'ratio_custom', $key );
				$own_custom = WP_Map_Embed_Settings::normalize_ratio( $settings[ $custom_key ] );
				if ( '' !== $own_custom ) {
					$custom = $own_custom;
				}
				$own_ratio = ! empty( $settings[ $key ] ) && in_array( $settings[ $key ], WP_Map_Embed_Settings::RATIOS, true );
				if ( $own_ratio ) {
					$ratio = $settings[ $key ];
				}
				if ( 'ratio' === $key || $own_ratio || ( 'custom' === $ratio && '' !== $own_custom ) ) {
					$vars[ $name ] = 'custom' === $ratio ? str_replace( 'x', ' / ', $custom ) : str_replace( ':', ' / ', $ratio );
				}
			}
		}
		// Checked here too, as the other colours: markup() takes any caller's array.
		$icon_color = WP_Map_Embed_Settings::sanitize_color( $settings['icon_color'] );
		if ( '' !== $icon_color ) {
			$vars['--wme-icon-color'] = $icon_color;
		}
		if ( $defer && $settings['placeholder'] ) {
			$image = wp_get_attachment_image_url( (int) $settings['placeholder'], 'large' );
			if ( $image ) {
				$vars['--wme-placeholder'] = 'url("' . esc_url_raw( $image ) . '")';
			}
		}

		$style = '';
		foreach ( $vars as $name => $value ) {
			$style .= $name . ':' . $value . ';';
		}
		return $style;
	}

	/**
	 * @param string $hex     #rrggbb.
	 * @param int    $opacity 0–100.
	 * @return string rgba().
	 */
	public static function rgba( $hex, $opacity ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			$hex = 'dfdad7';
		}
		return sprintf(
			'rgba(%d,%d,%d,%s)',
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
			rtrim( rtrim( number_format( max( 0, min( 100, $opacity ) ) / 100, 2, '.', '' ), '0' ), '.' )
		);
	}

	/**
	 * The cover's icon.
	 *
	 * @param string $icon hand, pin or none.
	 * @return string SVG.
	 */
	public static function icon( $icon ) {
		$paths = self::icons();
		if ( ! isset( $paths[ $icon ] ) ) {
			return '';
		}
		return '<svg class="wp-map-embed__icon" xmlns="http://www.w3.org/2000/svg" viewBox="' . esc_attr( $paths[ $icon ][0] ) . '" aria-hidden="true" focusable="false"><path d="' . esc_attr( $paths[ $icon ][1] ) . '"/></svg>';
	}

	/**
	 * @return array icon => [ viewBox, path ].
	 */
	public static function icons() {
		return array(
			// A pointing hand: "tap here".
			'hand' => array(
				'0 0 203.079 203.079',
				'M192.231,104.082V102c0-12.407-10.094-22.5-22.5-22.5c-2.802,0-5.484,0.519-7.961,1.459C159.665,70.722,150.583,63,139.731,63c-2.947,0-5.76,0.575-8.341,1.61C128.667,55.162,119.624,48,109.231,48c-2.798,0-5.496,0.541-8,1.516V22.5c0-12.407-10.094-22.5-22.5-22.5s-22.5,10.093-22.5,22.5v66.259c-3.938-5.029-8.673-9.412-14.169-11.671c-6.133-2.52-12.587-2.219-18.667,0.872c-11.182,5.686-15.792,19.389-10.277,30.548l27.95,56.563c0.79,1.552,19.731,38.008,54.023,38.008h40c31.54,0,57.199-25.794,57.199-57.506l-0.031-41.491H192.231z M135.092,188.079h-40c-24.702,0-40.091-28.738-40.646-29.796l-27.88-56.42c-1.924-3.893-0.33-8.519,3.629-10.532c2.182-1.11,4.081-1.223,6.158-0.372c8.281,3.395,16.41,19.756,19.586,29.265l2.41,7.259l12.883-4.559V22.5c0-4.136,3.364-7.5,7.5-7.5s7.5,3.364,7.5,7.5V109h15V71c0-4.187,3.748-8,7.864-8c4.262,0,8,3.505,8,7.5v41h15v-26c0-4.136,3.364-7.5,7.5-7.5s7.5,3.364,7.5,7.5v33h15V102c0-4.136,3.364-7.5,7.5-7.5s7.5,3.364,7.5,7.5v10.727h0.035l0.025,32.852C177.291,169.014,158.36,188.079,135.092,188.079z',
			),
			// A map pin.
			'pin'  => array(
				'0 0 24 24',
				'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z',
			),
		);
	}
}
