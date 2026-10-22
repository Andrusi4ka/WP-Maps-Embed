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
	);

	/** How the map's height is set. */
	const SIZE_MODES = array( 'height', 'ratio', 'fill' );

	/** Proportions, width:height. */
	const RATIOS = array( '21:9', '16:9', '3:2', '4:3', '1:1', '3:4', '9:16' );

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
			// In height_unit; 0 on tablet / mobile: as on the larger device.
			'height'          => 450,
			'height_unit'     => 'px',
			'height_tablet'   => 0,
			'height_mobile'   => 0,
			'radius'          => 5,
			// '' on tablet / mobile: as on the larger device (0 is a radius of its own).
			'radius_tablet'   => '',
			'radius_mobile'   => '',
			// The cover over the map until a click: the page scrolls past the map
			// instead of zooming it.
			'activate'        => true,
			'grayscale'       => true,
			// Google's frame loads only on that click.
			'defer'           => false,
			'placeholder'     => 0,
			'overlay_color'   => '#dfdad7',
			'overlay_opacity' => 24,
			'icon'            => 'hand',
			// Empty: the theme's primary colour.
			'icon_color'      => '',
			'icon_size'       => 70,
			'icon_size_tablet' => '',
			'icon_size_mobile' => '',
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
		$saved = get_post_meta( $post_id, self::META, true );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Settings sent by the edit screen, made safe to store.
	 *
	 * @param array $raw Unslashed input.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$defaults = self::defaults();
		$out      = array();

		$out['source'] = self::sanitize_source( isset( $raw['source'] ) ? (string) $raw['source'] : '' );

		$out['height_unit'] = isset( $raw['height_unit'] ) && in_array( $raw['height_unit'], array_keys( self::HEIGHT_UNITS ), true ) ? $raw['height_unit'] : $defaults['height_unit'];
		$range              = self::HEIGHT_UNITS[ $out['height_unit'] ];

		$ints = array(
			'zoom'            => array( 1, 21 ),
			'height'          => $range,
			'height_tablet'   => array( 0, $range[1] ),
			'height_mobile'   => array( 0, $range[1] ),
			'radius'          => array( 0, 100 ),
			'overlay_opacity' => array( 0, 100 ),
			'icon_size'       => array( 16, 200 ),
		);
		foreach ( $ints as $key => $limits ) {
			$value       = isset( $raw[ $key ] ) && '' !== $raw[ $key ] ? (int) $raw[ $key ] : $defaults[ $key ];
			$out[ $key ] = max( $limits[0], min( $limits[1], $value ) );
		}
		// Tablet / mobile: at least the smallest height (0 stays: as on the larger device).
		foreach ( array( 'height_tablet', 'height_mobile' ) as $key ) {
			if ( $out[ $key ] && $out[ $key ] < $range[0] ) {
				$out[ $key ] = $range[0];
			}
		}

		// Tablet / mobile: '' as on the larger device.
		$per_device = array(
			'radius_tablet'    => $ints['radius'],
			'radius_mobile'    => $ints['radius'],
			'icon_size_tablet' => $ints['icon_size'],
			'icon_size_mobile' => $ints['icon_size'],
		);
		foreach ( $per_device as $key => $limits ) {
			$out[ $key ] = isset( $raw[ $key ] ) && '' !== trim( (string) $raw[ $key ] ) ? max( $limits[0], min( $limits[1], (int) $raw[ $key ] ) ) : '';
		}

		// Checkboxes: absent when unticked.
		foreach ( array( 'activate', 'grayscale', 'defer' ) as $key ) {
			$out[ $key ] = ! empty( $raw[ $key ] );
		}

		$out['placeholder'] = isset( $raw['placeholder'] ) ? absint( $raw['placeholder'] ) : 0;

		$color                = isset( $raw['overlay_color'] ) ? sanitize_hex_color( $raw['overlay_color'] ) : '';
		$out['overlay_color'] = $color ? $color : $defaults['overlay_color'];
		$color                = isset( $raw['icon_color'] ) ? sanitize_hex_color( $raw['icon_color'] ) : '';
		$out['icon_color']    = $color ? $color : '';

		$out['map_type']  = isset( $raw['map_type'] ) && in_array( $raw['map_type'], self::MAP_TYPES, true ) ? $raw['map_type'] : $defaults['map_type'];
		$language         = isset( $raw['language'] ) ? (string) $raw['language'] : '';
		$out['language']  = '' === $language || 'site' === $language || isset( self::languages()[ $language ] ) ? $language : $defaults['language'];
		$out['size_mode'] = isset( $raw['size_mode'] ) && in_array( $raw['size_mode'], self::SIZE_MODES, true ) ? $raw['size_mode'] : $defaults['size_mode'];
		$out['ratio']     = isset( $raw['ratio'] ) && in_array( $raw['ratio'], self::RATIOS, true ) ? $raw['ratio'] : $defaults['ratio'];
		foreach ( array( 'ratio_tablet', 'ratio_mobile' ) as $key ) {
			$out[ $key ] = isset( $raw[ $key ] ) && in_array( $raw[ $key ], self::RATIOS, true ) ? $raw[ $key ] : '';
		}

		$out['icon']  = isset( $raw['icon'] ) && in_array( $raw['icon'], self::ICONS, true ) ? $raw['icon'] : $defaults['icon'];
		$out['label'] = isset( $raw['label'] ) ? sanitize_text_field( $raw['label'] ) : '';

		return $out;
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
