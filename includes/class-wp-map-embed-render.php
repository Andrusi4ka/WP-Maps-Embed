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
		$url = WP_Map_Embed_Settings::embed_url( $settings );
		if ( '' === $url ) {
			return '';
		}

		$activate = ! empty( $settings['activate'] );
		$defer    = $activate && ! empty( $settings['defer'] );
		$classes  = array( 'wp-map-embed' );
		$mode     = isset( $settings['size_mode'] ) ? $settings['size_mode'] : 'height';
		if ( 'ratio' === $mode ) {
			$classes[] = 'wp-map-embed--ratio';
		} elseif ( 'fill' === $mode ) {
			// frontend.js sizes it to its parent block.
			$classes[] = 'wp-map-embed--fill';
		}
		if ( $activate && ! empty( $settings['grayscale'] ) ) {
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
			$label = trim( (string) $settings['label'] );
			$cover = sprintf(
				'<button type="button" class="wp-map-embed__cover"%1$s>%2$s%3$s</button>',
				'' === $label ? ' aria-label="' . esc_attr__( 'Show the interactive map', 'wp-map-embed' ) . '"' : '',
				self::icon( $settings['icon'] ),
				'' === $label ? '' : '<span class="wp-map-embed__label">' . esc_html( $label ) . '</span>'
			);
		}

		return sprintf(
			'<div class="%1$s" style="%2$s"%3$s>%4$s%5$s</div>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( self::style( $settings, $defer ) ),
			$defer ? ' data-defer' : '',
			$frame,
			$cover
		);
	}

	/**
	 * The map's CSS variables (frontend.css uses them).
	 *
	 * @param array $settings Map settings.
	 * @param bool  $defer    Whether the frame waits for a click.
	 * @return string
	 */
	public static function style( $settings, $defer ) {
		$unit = isset( $settings['height_unit'] ) && isset( WP_Map_Embed_Settings::HEIGHT_UNITS[ $settings['height_unit'] ] ) ? $settings['height_unit'] : 'px';
		$vars = array(
			'--wme-height' => (int) $settings['height'] . $unit,
			'--wme-radius' => (int) $settings['radius'] . 'px',
			'--wme-cover'  => self::rgba( $settings['overlay_color'], (int) $settings['overlay_opacity'] ),
			'--wme-icon'   => (int) $settings['icon_size'] . 'px',
		);
		// Unset: as on the larger device (frontend.css falls back).
		if ( (int) $settings['height_tablet'] ) {
			$vars['--wme-height-tablet'] = (int) $settings['height_tablet'] . $unit;
		}
		if ( (int) $settings['height_mobile'] ) {
			$vars['--wme-height-mobile'] = (int) $settings['height_mobile'] . $unit;
		}
		$per_device = array(
			'radius'    => '--wme-radius-',
			'icon_size' => '--wme-icon-',
		);
		foreach ( $per_device as $key => $prefix ) {
			foreach ( array( 'tablet', 'mobile' ) as $device ) {
				$value = isset( $settings[ $key . '_' . $device ] ) ? $settings[ $key . '_' . $device ] : '';
				// '' as on the larger device; 0 is a value of its own.
				if ( '' !== $value && null !== $value ) {
					$vars[ $prefix . $device ] = (int) $value . 'px';
				}
			}
		}
		if ( isset( $settings['size_mode'] ) && 'ratio' === $settings['size_mode'] ) {
			// "16:9" => "16 / 9"; unset on tablet / mobile: as on the larger device.
			$names = array(
				'ratio'        => '--wme-ratio',
				'ratio_tablet' => '--wme-ratio-tablet',
				'ratio_mobile' => '--wme-ratio-mobile',
			);
			foreach ( $names as $key => $name ) {
				if ( ! empty( $settings[ $key ] ) && in_array( $settings[ $key ], WP_Map_Embed_Settings::RATIOS, true ) ) {
					$vars[ $name ] = str_replace( ':', ' / ', $settings[ $key ] );
				}
			}
		}
		if ( $settings['icon_color'] ) {
			$vars['--wme-icon-color'] = $settings['icon_color'];
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
