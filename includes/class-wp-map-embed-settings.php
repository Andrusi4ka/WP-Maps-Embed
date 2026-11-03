<?php
/**
 * A map's settings: defaults, sanitizing, and the map's address turned into
 * the URL of Google's embedded map.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Map_Embed_Settings {

	const POST_TYPE = 'wp_map_embed';
	const META      = '_wp_map_embed_settings';

	/** The cover's icons. */
	const ICONS = array( 'hand', 'pin', 'none' );

	/**
	 * Map types; '' keeps the embed code's own (an address: Map). Without an
	 * API key Google's embed has these two only: its satellite view has the
	 * labels (no separate hybrid), and terrain shows as the map.
	 */
	const MAP_TYPES = array( '', 'roadmap', 'satellite' );

	/** Units of the height: unit => [ min, max ]. */
	const HEIGHT_UNITS = array(
		'px' => array( 50, 2000 ),
		// Of the window's height.
		'vh' => array( 10, 100 ),
		'%'  => array( 1, 100 ),
		'vw' => array( 1, 100 ),
		'dvh' => array( 1, 100 ),
		'auto' => array( 50, 2000 ),
	);

	/** How the map's height is set. */
	const SIZE_MODES = array( 'height', 'ratio' );

	/** Proportions, width:height. */
	const RATIOS = array( '21:9', '16:9', '3:2', '4:3', '1:1', '3:4', '9:16', 'custom' );

	/**
	 * Where the stencil sits in the block when their proportions differ:
	 * position => [ x, y ], each 0 (left / top) to 1 (right / bottom).
	 */
	const SHAPE_POSITIONS = array(
		'left top'      => array( 0, 0 ),
		'center top'    => array( 0.5, 0 ),
		'right top'     => array( 1, 0 ),
		'left center'   => array( 0, 0.5 ),
		'center center' => array( 0.5, 0.5 ),
		'right center'  => array( 1, 0.5 ),
		'left bottom'   => array( 0, 1 ),
		'center bottom' => array( 0.5, 1 ),
		'right bottom'  => array( 1, 1 ),
	);

	/**
	 * Languages of the map's labels: Google's code => name in that language.
	 * '' (as copied, else the visitor's browser) and 'site' come first in the list.
	 *
	 * @return array
	 */
	public static function languages() {
		return array(
			'en' => 'English',
			'no' => 'Norsk',
			'sv' => 'Svenska',
			'da' => 'Dansk',
			'fi' => 'Suomi',
			'de' => 'Deutsch',
			'fr' => 'Français',
			'es' => 'Español',
			'it' => 'Italiano',
			'nl' => 'Nederlands',
			'pl' => 'Polski',
			'pt' => 'Português',
			'uk' => 'Українська',
		);
	}

	/**
	 * Google's code for the site's language (nb_NO: no, pt_BR: pt-BR).
	 *
	 * @return string
	 */
	public static function site_language() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$locale = str_replace( '_', '-', (string) $locale );
		if ( preg_match( '/^(nb|nn)\b/i', $locale ) ) {
			return 'no';
		}
		// Google tells these apart by region; the rest by language only.
		if ( preg_match( '/^(pt-BR|zh-CN|zh-TW|en-GB)$/i', $locale ) ) {
			return $locale;
		}
		return strtolower( strtok( $locale, '-' ) );
	}

	/**
	 * @return array key => default.
	 */
	public static function defaults() {
		return array(
			// An address, or the src of Google's "Embed a map" iframe.
			'source'          => '',
			// Used with an address only (Google's embed code has its own).
			'zoom'            => 15,
			'map_type'        => '',
			// '' as copied (else the visitor's browser), 'site' or a code of languages().
			'language'        => '',
			// height: the heights below; ratio: the proportions; fill: the free
			// height of the parent block (frontend.js), else the heights below.
			'size_mode'       => 'height',
			// '' on tablet / mobile: as on the larger device.
			'ratio'           => '16:9',
			'ratio_tablet'    => '',
			'ratio_mobile'    => '',
			'ratio_custom'    => '4x3',
			'ratio_custom_tablet' => '',
			'ratio_custom_mobile' => '',
			// In height_unit; 0 on tablet / mobile: as on the larger device.
			'height'          => 450,
			'height_unit'     => 'px',
			'height_unit_tablet' => '',
			'height_unit_mobile' => '',
			'height_tablet'   => 0,
			'height_mobile'   => 0,
			// Existing maps keep their rounded corners until explicitly switched off.
			'rounded'         => true,
			'radius'          => 5,
			// '' on tablet / mobile: as on the larger device (0 is a radius of its own).
			'radius_tablet'   => '',
			'radius_mobile'   => '',
			'border'          => false,
			'border_width'    => 1,
			'border_width_tablet' => '',
			'border_width_mobile' => '',
			'border_style'    => 'solid',
			'border_color'    => '#ffffff',
			'shadow'          => false,
			'shadow_x'        => 0,
			'shadow_y'        => 8,
			'shadow_blur'     => 24,
			'shadow_spread'   => 0,
			'shadow_color'    => 'rgba(0,0,0,0.25)',
			// The size of everything on Google's map (names, roads, its buttons),
			// %: the frame drawn larger or smaller and scaled to the block
			// (frontend.css). '' on tablet / mobile: as on the larger device.
			'scale_enabled'   => false,
			'scale'           => 100,
			'scale_tablet'    => '',
			'scale_mobile'    => '',
			// The stencil: an SVG file's shapes (WP_Map_Embed_Svg_Shape, geometry
			// only, JSON), the map seen through them; '' none. Fitted into the
			// block at its proportions, at shape_position (SHAPE_POSITIONS).
			'shape_enabled'   => false,
			'shape'           => '',
			'shape_position'  => 'center center',
			'shape_position_tablet' => '',
			'shape_position_mobile' => '',
			// The cover over the map until a click: the page scrolls past the map
			// instead of zooming it.
			'grayscale'       => true,
			// Google's frame loads only on that click.
			'defer'           => false,
			'placeholder'     => 0,
			'overlay_enabled' => true,
			'overlay_color'   => '#dfdad7',
			'overlay_opacity' => 24,
			'icon_enabled'    => true,
			'icon'            => 'hand',
			// Empty: the theme's primary colour.
			'icon_color'      => '',
			'icon_size'       => 70,
			'icon_size_tablet' => '',
			'icon_size_mobile' => '',
			'text_enabled'    => false,
			'label'           => '',
		);
	}

	/**
	 * A map's settings, defaults filled in.
	 *
	 * @param int $post_id Map ID.
	 * @return array
	 */
	public static function get( $post_id ) {
		return self::prepare( get_post_meta( $post_id, self::META, true ) );
	}

	/**
	 * Settings as stored by any version, brought up to date: what older
	 * versions saved differently is moved to today's keys (the map looks as
	 * it did), then the defaults fill in the rest. The one place that knows
	 * about older settings: everything else reads complete, current ones.
	 *
	 * @param mixed $saved Stored settings.
	 * @return array
	 */
	public static function prepare( $saved ) {
		$saved = is_array( $saved ) ? $saved : array();

		// 1.0: one "Cover the map until it is clicked" switch over the cover's
		// options. Off: none of them (their own switches came later).
		if ( array_key_exists( 'activate', $saved ) ) {
			if ( empty( $saved['activate'] ) ) {
				foreach ( self::COVER_SWITCHES as $key ) {
					$saved[ $key ] = false;
				}
			}
			unset( $saved['activate'] );
		}
		// 1.0: "Fill the parent block" was a size mode; now the Auto unit.
		if ( isset( $saved['size_mode'] ) && in_array( $saved['size_mode'], array( 'fill', 'auto' ), true ) ) {
			$saved['size_mode']          = 'height';
			$saved['height_unit']        = 'auto';
			$saved['height_unit_tablet'] = '';
			$saved['height_unit_mobile'] = '';
		}
		// 1.0: the icon "none" and a text without a switch.
		if ( isset( $saved['icon'] ) && 'none' === $saved['icon'] ) {
			if ( ! array_key_exists( 'icon_enabled', $saved ) ) {
				$saved['icon_enabled'] = false;
			}
			$saved['icon'] = 'hand';
		}
		if ( ! array_key_exists( 'text_enabled', $saved ) ) {
			$saved['text_enabled'] = isset( $saved['label'] ) && '' !== trim( (string) $saved['label'] );
		}
		// Settings saved before their switch: on where they were in use.
		if ( ! array_key_exists( 'scale_enabled', $saved ) ) {
			$saved['scale_enabled'] = false;
			foreach ( array( 'scale', 'scale_tablet', 'scale_mobile' ) as $key ) {
				if ( isset( $saved[ $key ] ) && '' !== $saved[ $key ] && 100 !== (int) $saved[ $key ] ) {
					$saved['scale_enabled'] = true;
				}
			}
		}
		if ( ! array_key_exists( 'shape_enabled', $saved ) ) {
			$saved['shape_enabled'] = ! empty( $saved['shape'] );
		}

		return array_merge( self::defaults(), $saved );
	}

	/** The cover's options: with any of them on, the map waits for a click. */
	const COVER_SWITCHES = array( 'grayscale', 'defer', 'overlay_enabled', 'icon_enabled', 'text_enabled' );

	/** Border styles, as CSS names them. */
	const BORDER_STYLES = array( 'solid', 'dashed', 'dotted', 'double' );

	/**
	 * Whether the map has a cover (waits for a click).
	 *
	 * @param array $settings Complete settings (prepare()).
	 * @return bool
	 */
	public static function has_cover( $settings ) {
		foreach ( self::COVER_SWITCHES as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Settings sent by the edit screen, made safe to store.
	 *
	 * @param array $raw Unslashed input.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$raw      = is_array( $raw ) ? $raw : array();
		$defaults = self::defaults();
		$out      = array();

		$out['source'] = self::sanitize_source( isset( $raw['source'] ) ? (string) $raw['source'] : '' );

		$out['height_unit'] = isset( $raw['height_unit'] ) && in_array( $raw['height_unit'], array_keys( self::HEIGHT_UNITS ), true ) ? $raw['height_unit'] : $defaults['height_unit'];
		$range              = self::HEIGHT_UNITS[ $out['height_unit'] ];

		$ints = array(
			'zoom'            => array( 1, 21 ),
			'height'          => $range,
			'radius'          => array( 0, 100 ),
			'border_width'    => array( 0, 50 ),
			'shadow_x'        => array( -200, 200 ),
			'shadow_y'        => array( -200, 200 ),
			'shadow_blur'     => array( 0, 200 ),
			'shadow_spread'   => array( -200, 200 ),
			'scale'           => array( 5, 150 ),
			'overlay_opacity' => array( 0, 100 ),
			'icon_size'       => array( 16, 200 ),
		);
		foreach ( $ints as $key => $limits ) {
			$value       = isset( $raw[ $key ] ) && '' !== $raw[ $key ] ? (int) $raw[ $key ] : $defaults[ $key ];
			$out[ $key ] = max( $limits[0], min( $limits[1], $value ) );
		}
		// Each device has its own unit and limits; 0 height inherits the
		// complete height of the larger device. Empty unit inherits its unit.
		foreach ( array( 'tablet', 'mobile' ) as $device ) {
			$key = 'height_unit_' . $device;
			$out[ $key ] = isset( $raw[ $key ] ) && in_array( $raw[ $key ], array_keys( self::HEIGHT_UNITS ), true ) ? $raw[ $key ] : '';
			$limits = self::HEIGHT_UNITS[ self::height_unit_for( $out, $device ) ];
			$key = 'height_' . $device;
			$value = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? (int) $raw[ $key ] : 0;
			$out[ $key ] = $value > 0 ? max( $limits[0], min( $limits[1], $value ) ) : 0;
		}

		// Tablet / mobile: '' as on the larger device.
		$per_device = array(
			'radius_tablet'    => $ints['radius'],
			'radius_mobile'    => $ints['radius'],
			'border_width_tablet' => $ints['border_width'],
			'border_width_mobile' => $ints['border_width'],
			'scale_tablet'     => $ints['scale'],
			'scale_mobile'     => $ints['scale'],
			'icon_size_tablet' => $ints['icon_size'],
			'icon_size_mobile' => $ints['icon_size'],
		);
		foreach ( $per_device as $key => $limits ) {
			$out[ $key ] = isset( $raw[ $key ] ) && '' !== trim( (string) $raw[ $key ] ) ? max( $limits[0], min( $limits[1], (int) $raw[ $key ] ) ) : '';
		}

		// Checkboxes: absent when unticked.
		foreach ( array( 'grayscale', 'defer', 'border', 'shadow', 'rounded', 'shape_enabled', 'scale_enabled', 'overlay_enabled', 'icon_enabled', 'text_enabled' ) as $key ) {
			$out[ $key ] = ! empty( $raw[ $key ] );
		}

		$out['placeholder'] = isset( $raw['placeholder'] ) ? absint( $raw['placeholder'] ) : 0;

		// The picker sends one RGBA value; keep the existing hex/opacity storage
		// so previously saved maps and older integrations retain their appearance.
		$color = self::sanitize_color( isset( $raw['overlay_color'] ) ? $raw['overlay_color'] : '' );
		if ( preg_match( '/^rgba\((\d+),(\d+),(\d+),([\d.]+)\)$/', $color, $channels ) ) {
			$color = sprintf( '#%02x%02x%02x', (int) $channels[1], (int) $channels[2], (int) $channels[3] );
			$out['overlay_opacity'] = (int) round( (float) $channels[4] * 100 );
		} elseif ( '' !== $color && ! array_key_exists( 'overlay_opacity', $raw ) ) {
			// Pickr uses hex when the chosen color is fully opaque.
			$out['overlay_opacity'] = 100;
		}
		$out['overlay_color'] = $color ? $color : $defaults['overlay_color'];
		$color                = self::sanitize_color( isset( $raw['icon_color'] ) ? $raw['icon_color'] : '' );
		$out['icon_color']    = $color ? $color : '';
		foreach ( array( 'border_color', 'shadow_color' ) as $key ) {
			$color = self::sanitize_color( isset( $raw[ $key ] ) ? $raw[ $key ] : '' );
			$out[ $key ] = '' !== $color ? $color : $defaults[ $key ];
		}
		$out['border_style'] = isset( $raw['border_style'] ) && in_array( $raw['border_style'], self::BORDER_STYLES, true ) ? $raw['border_style'] : 'solid';

		$out['map_type']  = isset( $raw['map_type'] ) && in_array( $raw['map_type'], self::MAP_TYPES, true ) ? $raw['map_type'] : $defaults['map_type'];
		$language         = isset( $raw['language'] ) ? (string) $raw['language'] : '';
		$out['language']  = '' === $language || 'site' === $language || isset( self::languages()[ $language ] ) ? $language : $defaults['language'];
		$out['size_mode'] = isset( $raw['size_mode'] ) && in_array( $raw['size_mode'], self::SIZE_MODES, true ) ? $raw['size_mode'] : $defaults['size_mode'];
		$out['ratio']     = isset( $raw['ratio'] ) && in_array( $raw['ratio'], self::RATIOS, true ) ? $raw['ratio'] : $defaults['ratio'];
		foreach ( array( 'ratio_tablet', 'ratio_mobile' ) as $key ) {
			$out[ $key ] = isset( $raw[ $key ] ) && in_array( $raw[ $key ], self::RATIOS, true ) ? $raw[ $key ] : '';
		}
		foreach ( array( 'ratio_custom', 'ratio_custom_tablet', 'ratio_custom_mobile' ) as $key ) {
			$value = self::normalize_ratio( isset( $raw[ $key ] ) ? $raw[ $key ] : '' );
			$out[ $key ] = '' !== $value ? $value : $defaults[ $key ];
		}

		// The file's text from the edit screen ({ name, svg }), or the shapes kept.
		$out['shape']          = WP_Map_Embed_Svg_Shape::sanitize( isset( $raw['shape'] ) && is_scalar( $raw['shape'] ) ? (string) $raw['shape'] : '' );
		foreach ( array( 'shape_position', 'shape_position_tablet', 'shape_position_mobile' ) as $key ) {
			$out[ $key ] = isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) && isset( self::SHAPE_POSITIONS[ $raw[ $key ] ] ) ? $raw[ $key ] : $defaults[ $key ];
		}

		$out['icon']  = isset( $raw['icon'] ) && in_array( $raw['icon'], self::ICONS, true ) ? $raw['icon'] : $defaults['icon'];
		$out['label'] = isset( $raw['label'] ) ? sanitize_text_field( $raw['label'] ) : '';

		return $out;
	}

	/** Same width x height text format and limits as WP Swiper. */
	public static function normalize_ratio( $value ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^\s*(\d+(?:[.,]\d+)?)\s*[x×х:\/*]\s*(\d+(?:[.,]\d+)?)\s*$/iu', (string) $value, $parts ) ) {
			return '';
		}
		$sides = array();
		foreach ( array( $parts[1], $parts[2] ) as $number ) {
			$side = max( 0.1, min( 100, (float) str_replace( ',', '.', $number ) ) );
			$sides[] = rtrim( rtrim( number_format( $side, 2, '.', '' ), '0' ), '.' );
		}
		return implode( 'x', $sides );
	}

	/** Resolve a device's unit, falling back through the larger devices. */
	public static function height_unit_for( $settings, $device = 'desktop' ) {
		$unit = isset( $settings['height_unit'] ) && in_array( $settings['height_unit'], array_keys( self::HEIGHT_UNITS ), true ) ? $settings['height_unit'] : 'px';
		if ( 'desktop' === $device ) {
			return $unit;
		}
		foreach ( array( 'tablet', 'mobile' ) as $screen ) {
			$key = 'height_unit_' . $screen;
			if ( isset( $settings[ $key ] ) && in_array( $settings[ $key ], array_keys( self::HEIGHT_UNITS ), true ) ) {
				$unit = $settings[ $key ];
			}
			if ( $screen === $device ) {
				break;
			}
		}
		return $unit;
	}

	/** Accept only hex or numeric rgba colors; never arbitrary CSS. */
	public static function sanitize_color( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = strtolower( trim( (string) $value ) );
		$hex = sanitize_hex_color( $value );
		if ( $hex ) {
			return $hex;
		}
		if ( preg_match( '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(0|1|0?\.\d+|1\.0+)\s*\)$/', $value, $match ) ) {
			return sprintf( 'rgba(%d,%d,%d,%s)', min( 255, (int) $match[1] ), min( 255, (int) $match[2] ), min( 255, (int) $match[3] ), rtrim( rtrim( number_format( (float) $match[4], 2, '.', '' ), '0' ), '.' ) );
		}
		return '';
	}

	/**
	 * What was typed in "Map": Google's iframe code becomes its src, a link is
	 * kept as a link, anything else is an address.
	 *
	 * @param string $source Input.
	 * @return string
	 */
	public static function sanitize_source( $source ) {
		$source = trim( $source );
		if ( preg_match( '/<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/is', $source, $match ) ) {
			$source = html_entity_decode( $match[2], ENT_QUOTES );
		}
		if ( preg_match( '#^https?://#i', $source ) ) {
			return esc_url_raw( $source );
		}
		return sanitize_text_field( $source );
	}

	/**
	 * Whether a link is Google's embedded map (what the frame may show).
	 *
	 * @param string $url Link.
	 * @return bool
	 */
	public static function is_embed_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}
		$path  = isset( $parts['path'] ) ? $parts['path'] : '';
		$query = isset( $parts['query'] ) ? $parts['query'] : '';
		if ( ! preg_match( '/(^|\.)google\.[a-z]{2,3}(\.[a-z]{2})?$/i', $parts['host'] ) || 0 !== strpos( $path, '/maps' ) ) {
			return false;
		}
		// "Share > Embed a map" (/maps/embed?pb=...) or an address one (output=embed).
		return 0 === strpos( $path, '/maps/embed' ) || false !== strpos( '&' . $query, '&output=embed' );
	}

	/**
	 * The frame's URL for a map, or '' when it has none: no address, or a link
	 * that is not an embedded map (a "Share > Send a link" one, for instance).
	 *
	 * @param array $settings Map settings.
	 * @return string
	 */
	public static function embed_url( $settings ) {
		$source = trim( (string) $settings['source'] );
		if ( '' === $source ) {
			return '';
		}
		$type     = isset( $settings['map_type'] ) ? $settings['map_type'] : '';
		$language = isset( $settings['language'] ) ? (string) $settings['language'] : '';
		if ( 'site' === $language ) {
			$language = self::site_language();
		}

		if ( preg_match( '#^https?://#i', $source ) ) {
			if ( ! self::is_embed_url( $source ) ) {
				return '';
			}
			return self::adjust_embed_url( $source, $type, $language );
		}

		// An address: Google's embed by search, no API key needed.
		$args = array(
			'q'      => rawurlencode( $source ),
			't'      => self::type_letter( $type ),
			'z'      => (int) $settings['zoom'],
			'output' => 'embed',
		);
		if ( '' !== $language ) {
			$args['hl'] = $language;
		}
		return add_query_arg( $args, 'https://maps.google.com/maps' );
	}

	/**
	 * The "t" of an address map: m(ap) or k (satellite).
	 *
	 * @param string $type Map type.
	 * @return string
	 */
	private static function type_letter( $type ) {
		return 'satellite' === $type ? 'k' : 'm';
	}

	/**
	 * Google's embed code with another map type or language. Its "pb" holds
	 * both: "!5e0" (map) / "!5e1" (satellite), then "!3m2!1s<language>!2s<region>".
	 *
	 * @param string $url      Embed URL.
	 * @param string $type     Map type ('': as copied).
	 * @param string $language Google's language code ('': as copied).
	 * @return string
	 */
	public static function adjust_embed_url( $url, $type, $language ) {
		$views = array(
			'roadmap'   => '0',
			'satellite' => '1',
		);
		if ( false === strpos( $url, '/maps/embed' ) ) {
			// A pasted address link (output=embed): its "t" holds the type.
			if ( '' !== $type ) {
				$url = self::set_param( $url, 't', self::type_letter( $type ) );
			}
		} elseif ( isset( $views[ $type ] ) ) {
			$url = preg_replace( '/!5e\d+/', '!5e' . $views[ $type ], $url, 1 );
		}
		if ( '' !== $language ) {
			$url = preg_replace( '/(!5e\d+!3m2!1s)[^!&]*/', '${1}' . $language, $url, 1 );
			// Codes without that part (an address link, output=embed) read "hl".
			$url = self::set_param( $url, 'hl', $language );
		}
		return $url;
	}

	/**
	 * One query parameter set, the rest of the URL left as it is
	 * (add_query_arg() would re-encode the "!" of Google's pb).
	 *
	 * @param string $url   URL.
	 * @param string $key   Parameter.
	 * @param string $value Value (letters, digits, "-").
	 * @return string
	 */
	private static function set_param( $url, $key, $value ) {
		$value = preg_replace( '/[^A-Za-z0-9-]/', '', $value );
		$count = 0;
		$url   = preg_replace( '/([?&]' . preg_quote( $key, '/' ) . '=)[^&#]*/', '${1}' . $value, $url, 1, $count );
		if ( ! $count ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value;
		}
		return $url;
	}

	/**
	 * Whether the map's source is an address (zoom applies) rather than a link.
	 *
	 * @param string $source Map source.
	 * @return bool
	 */
	public static function is_address( $source ) {
		$source = trim( (string) $source );
		return '' !== $source && ! preg_match( '#^https?://#i', $source );
	}
}
