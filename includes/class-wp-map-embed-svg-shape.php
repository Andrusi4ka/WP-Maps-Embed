<?php
/**
 * The map's stencil: the shapes of an SVG file a designer drew, the map
 * showing through all of them (WP_Map_Embed_Render::shape()).
 *
 * A copy of WP Swiper's WP_Swiper_Svg_Shape (its Art "SVG shape"), names
 * aside: a fix in one belongs in the other. "Windows", "main" and their
 * order come from there; the map shows through every window alike.
 *
 * Only the geometry is kept: the file is read here, in the admin's own
 * request, and what is stored (and shown) are its shapes as plain paths of
 * lines and cubic curves, in numbers from 0 to 1 of the drawing. Nothing of
 * the file itself reaches a page: no scripts, links, styles or images.
 *
 * Which shapes: the drawing's top-level objects (inside a single layer or
 * group that holds everything, the objects in it), each a window; a group
 * is one window of all its shapes. Shapes with no fill (lines, outlines),
 * hidden ones and text are left out. Windows keep the file's stacking order
 * (the later on top). The main window, where the current photo shows, is the
 * one named "main" (its layer's or object's name in the drawing program),
 * else the largest; then those named "2", "3"..., then the others, larger
 * first.
 *
 * @package WP_Map_Embed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Map_Embed_Svg_Shape {

	// The largest file read, bytes.
	const MAX_BYTES = 1048576;
	// The most windows (more in the file: left out, counted in 'over').
	const MAX_SHAPES = 100;
	// The longest path kept for a window, characters.
	const MAX_PATH = 60000;
	// Points per curve when measuring (bounds, area).
	const SAMPLES = 12;

	// Not drawn, or not shapes: their content is left out.
	const SKIP = array( 'defs', 'clippath', 'mask', 'symbol', 'pattern', 'marker', 'metadata', 'title', 'desc', 'style', 'script', 'text', 'image', 'foreignobject', 'use', 'lineargradient', 'radialgradient', 'filter', 'line' );
	// Containers whose content is drawn.
	const GROUPS = array( 'g', 'a', 'switch', 'svg' );
	// Shapes with an inside.
	const SHAPES = array( 'path', 'rect', 'circle', 'ellipse', 'polygon', 'polyline' );

	/**
	 * The value kept for the setting: the shapes as JSON ('' for none). From
	 * the editor: { "name": file name, "svg": the file's text } (read in the
	 * browser) or the file's text; from a slider saved before: the shapes
	 * kept, checked again.
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public static function sanitize( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( '<' === $value[0] ) {
			$data = self::parse( $value );
			return $data ? self::encode( $data ) : '';
		}
		$input = json_decode( $value, true );
		if ( ! is_array( $input ) ) {
			return '';
		}
		if ( isset( $input['svg'] ) ) {
			$data = self::parse( (string) $input['svg'], isset( $input['name'] ) ? (string) $input['name'] : '' );
			return $data ? self::encode( $data ) : '';
		}
		$data = self::check( $input );
		return $data ? self::encode( $data ) : '';
	}

	/**
	 * The shapes kept (sanitize()), checked; null for none.
	 *
	 * @param string $value Value kept.
	 * @return array|null { name, w, h, shapes: [ { id, d, box, area, rule, z } ], skipped }
	 */
	public static function decode( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || '{' !== $value[0] ) {
			return null;
		}
		$input = json_decode( $value, true );
		return is_array( $input ) && ! isset( $input['svg'] ) ? self::check( $input ) : null;
	}

	private static function encode( $data ) {
		return (string) wp_json_encode(
			array(
				'v'       => 1,
				'name'    => $data['name'],
				'w'       => $data['w'],
				'h'       => $data['h'],
				'skipped' => $data['skipped'],
				'over'    => $data['over'],
				'shapes'  => $data['shapes'],
			)
		);
	}

	/**
	 * Shapes kept before, checked as they were made: plain paths (M, L, C, Z
	 * and numbers), boxes of four numbers.
	 *
	 * @param array $input Decoded JSON.
	 * @return array|null
	 */
	private static function check( $input ) {
		if ( empty( $input['shapes'] ) || ! is_array( $input['shapes'] ) ) {
			return null;
		}
		$w = isset( $input['w'] ) ? (float) $input['w'] : 0;
		$h = isset( $input['h'] ) ? (float) $input['h'] : 0;
		if ( $w <= 0 || $h <= 0 ) {
			return null;
		}
		$shapes = array();
		foreach ( array_slice( $input['shapes'], 0, self::MAX_SHAPES ) as $shape ) {
			if ( ! is_array( $shape ) || ! isset( $shape['d'], $shape['box'] ) || ! is_array( $shape['box'] ) || 4 !== count( $shape['box'] ) ) {
				return null;
			}
			$d = (string) $shape['d'];
			if ( strlen( $d ) > self::MAX_PATH || ! preg_match( '/^M(?: (?:[MLCZ]|-?\d+(?:\.\d+)?))+$/', $d ) ) {
				return null;
			}
			$shapes[] = array(
				'id'   => isset( $shape['id'] ) ? self::clean_id( $shape['id'] ) : '',
				'd'    => $d,
				'box'  => array_map( array( __CLASS__, 'round' ), array_map( 'floatval', array_values( $shape['box'] ) ) ),
				'area' => isset( $shape['area'] ) ? self::round( (float) $shape['area'] ) : 0,
				'rule' => isset( $shape['rule'] ) && 'evenodd' === $shape['rule'] ? 'evenodd' : 'nonzero',
				'z'    => isset( $shape['z'] ) ? max( 0, min( 99, (int) $shape['z'] ) ) : count( $shapes ),
			);
		}
		return array(
			'name'    => isset( $input['name'] ) ? self::clean_name( $input['name'] ) : '',
			'w'       => self::round( $w ),
			'h'       => self::round( $h ),
			'skipped' => isset( $input['skipped'] ) ? max( 0, (int) $input['skipped'] ) : 0,
			// Windows over MAX_SHAPES, left out.
			'over'    => ( isset( $input['over'] ) ? max( 0, (int) $input['over'] ) : 0 ) + max( 0, count( $input['shapes'] ) - self::MAX_SHAPES ),
			'shapes'  => $shapes,
		);
	}

	/**
	 * An SVG file's windows (see the class).
	 *
	 * @param string $svg  The file's text.
	 * @param string $name The file's name.
	 * @return array|null As decode(); null when it is no SVG or has no shapes.
	 */
	public static function parse( $svg, $name = '' ) {
		$svg = (string) $svg;
		// No entities (they could expand without end); a doctype alone is fine.
		// UTF-8 only (an editor's export): in another encoding (UTF-16) the
		// check for entities could be got round.
		if ( '' === $svg || strlen( $svg ) > self::MAX_BYTES || preg_match( '/<!ENTITY/i', $svg )
			|| false !== strpos( $svg, "\0" ) || ! self::is_utf8( $svg ) ) {
			return null;
		}
		$doc      = new DOMDocument();
		$internal = libxml_use_internal_errors( true );
		// Nothing is fetched from elsewhere (PHP 8 loads no external entities anyway).
		$loader = null;
		if ( PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) ) {
			$loader = libxml_disable_entity_loader( true ); // phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions -- PHP 7.
		}
		$loaded = $doc->loadXML( $svg, LIBXML_NONET );
		if ( null !== $loader ) {
			libxml_disable_entity_loader( $loader ); // phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions -- PHP 7.
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $internal );
		$root = $loaded ? $doc->documentElement : null;
		if ( ! $root || 'svg' !== strtolower( $root->localName ) ) {
			return null;
		}
		// And once read: no entities declared, whatever the file looked like.
		if ( $doc->doctype && ( $doc->doctype->entities->length || '' !== trim( (string) $doc->doctype->internalSubset ) ) ) {
			return null;
		}

		// The drawing's area: its viewBox, else its width and height.
		$view = self::numbers( $root->getAttribute( 'viewBox' ) );
		if ( 4 === count( $view ) && $view[2] > 0 && $view[3] > 0 ) {
			list( $vx, $vy, $vw, $vh ) = $view;
		} else {
			$vx = 0;
			$vy = 0;
			$vw = (float) $root->getAttribute( 'width' );
			$vh = (float) $root->getAttribute( 'height' );
		}

		$classes = self::stylesheet( $doc );

		// Inside a single layer or group that holds everything: its objects.
		$matrix    = array( 1, 0, 0, 1, 0, 0 );
		$inherited = self::style( $root, $classes, array() );
		$container = $root;
		for ( $depth = 0; $depth < 20; $depth++ ) {
			$children = self::drawn_children( $container, $classes, $inherited );
			if ( 1 !== count( $children ) || ! in_array( strtolower( $children[0]->localName ), self::GROUPS, true ) ) {
				break;
			}
			$container = $children[0];
			$matrix    = self::multiply( $matrix, self::transform( $container->getAttribute( 'transform' ) ) );
			$inherited = self::style( $container, $classes, $inherited );
		}

		$windows = array();
		$skipped = 0;
		$over    = 0;
		foreach ( self::drawn_children( $container, $classes, $inherited, $skipped ) as $z => $child ) {
			$parts = array();
			$rule  = null;
			self::collect( $child, $matrix, $inherited, $classes, $parts, $rule, $skipped );
			if ( ! $parts ) {
				continue;
			}
			// More than MAX_SHAPES: left out, and said so (summary()).
			if ( count( $windows ) >= self::MAX_SHAPES ) {
				++$over;
				continue;
			}
			$label     = $child->getAttribute( 'data-name' );
			$label     = '' !== $label ? $label : $child->getAttribute( 'id' );
			$windows[] = array(
				'id'    => self::clean_id( $label ),
				'parts' => $parts,
				'rule'  => $rule ? $rule : 'nonzero',
				'z'     => count( $windows ),
			);
		}
		if ( ! $windows ) {
			return null;
		}

		// Measured and written out in numbers of the drawing (0–1).
		$all = array();
		foreach ( $windows as $i => $window ) {
			$points = self::sample( $window['parts'] );
			$box    = self::bounds( $points );
			if ( ! $box || ( $box[2] <= 0 && $box[3] <= 0 ) ) {
				unset( $windows[ $i ] );
				continue;
			}
			if ( ! $vw || ! $vh ) {
				$all = array_merge( $all, array( array( $box[0], $box[1] ), array( $box[0] + $box[2], $box[1] + $box[3] ) ) );
			}
			$windows[ $i ]['box']  = $box;
			$windows[ $i ]['area'] = self::area( $window['parts'] );
		}
		if ( ! $windows ) {
			return null;
		}
		// No size of its own: the shapes' bounds.
		if ( ! $vw || ! $vh ) {
			$bounds = self::bounds( $all );
			list( $vx, $vy, $vw, $vh ) = $bounds;
			if ( $vw <= 0 || $vh <= 0 ) {
				return null;
			}
		}

		$shapes = array();
		foreach ( $windows as $window ) {
			$d = self::path( $window['parts'], $vx, $vy, $vw, $vh );
			if ( strlen( $d ) > self::MAX_PATH ) {
				continue;
			}
			$shapes[] = array(
				'id'   => $window['id'],
				'd'    => $d,
				'box'  => array(
					self::round( ( $window['box'][0] - $vx ) / $vw ),
					self::round( ( $window['box'][1] - $vy ) / $vh ),
					self::round( $window['box'][2] / $vw ),
					self::round( $window['box'][3] / $vh ),
				),
				'area' => self::round( $window['area'] / ( $vw * $vh ) ),
				'rule' => $window['rule'],
				'z'    => $window['z'],
			);
		}
		if ( ! $shapes ) {
			return null;
		}

		return array(
			'name'    => self::clean_name( $name ),
			'w'       => self::round( $vw ),
			'h'       => self::round( $vh ),
			'skipped' => $skipped,
			'over'    => $over,
			'shapes'  => self::ordered( $shapes ),
		);
	}

	/**
	 * The windows in the order photos fill them: "main" (else the largest),
	 * then those named with a number, by it, then the others, larger first.
	 * Their stacking ("z") stays the file's.
	 *
	 * @param array $shapes Shapes.
	 * @return array
	 */
	public static function ordered( $shapes ) {
		$main = null;
		foreach ( $shapes as $i => $shape ) {
			if ( 'main' === strtolower( $shape['id'] ) ) {
				$main = $i;
				break;
			}
		}
		if ( null === $main ) {
			$largest = -1;
			foreach ( $shapes as $i => $shape ) {
				if ( $shape['area'] > $largest ) {
					$largest = $shape['area'];
					$main    = $i;
				}
			}
		}
		$first = $shapes[ $main ];
		unset( $shapes[ $main ] );
		$rest = array_values( $shapes );
		usort(
			$rest,
			function ( $a, $b ) {
				$na = preg_match( '/^\d+$/', $a['id'] ) ? (int) $a['id'] : null;
				$nb = preg_match( '/^\d+$/', $b['id'] ) ? (int) $b['id'] : null;
				if ( null !== $na || null !== $nb ) {
					if ( null === $na ) {
						return 1;
					}
					if ( null === $nb ) {
						return -1;
					}
					if ( $na !== $nb ) {
						return $na < $nb ? -1 : 1;
					}
				}
				if ( $a['area'] !== $b['area'] ) {
					return $a['area'] > $b['area'] ? -1 : 1;
				}
				return $a['z'] - $b['z'];
			}
		);
		return array_merge( array( $first ), $rest );
	}

	/**
	 * A line for the editor: the file and how many windows it gave.
	 *
	 * @param array|null $data decode().
	 * @return string
	 */
	public static function summary( $data ) {
		if ( ! $data ) {
			return __( 'No shape yet: upload an SVG file.', 'wp-map-embed' );
		}
		$count = count( $data['shapes'] );
		/* translators: %d: number of shapes. */
		$text = sprintf( _n( '%d shape', '%d shapes', $count, 'wp-map-embed' ), $count );
		if ( $data['skipped'] ) {
			/* translators: %d: number of objects left out. */
			$text .= ', ' . sprintf( _n( '%d object left out (no fill, hidden or text)', '%d objects left out (no fill, hidden or text)', $data['skipped'], 'wp-map-embed' ), $data['skipped'] );
		}
		if ( ! empty( $data['over'] ) ) {
			/* translators: 1: number of shapes left out, 2: the most shapes kept. */
			$text .= ', ' . sprintf( _n( '%1$d more left out: at most %2$d shapes', '%1$d more left out: at most %2$d shapes', $data['over'], 'wp-map-embed' ), $data['over'], self::MAX_SHAPES );
		}
		return '' !== $data['name'] ? $data['name'] . ': ' . $text : $text;
	}

	/**
	 * The windows for a page: in numbers (0–1) of the drawing's area, or with
	 * `$crop` of the shapes' bounds (the empty margins of the file left out),
	 * and the area's proportions (width / height).
	 *
	 * @param array $data decode().
	 * @param bool  $crop The shapes' bounds only.
	 * @return array { ratio, shapes: [ { d, box, rule, z } ] }
	 */
	public static function frame( $data, $crop ) {
		$x0 = 0;
		$y0 = 0;
		$fw = 1;
		$fh = 1;
		if ( $crop ) {
			$x1 = INF;
			$y1 = INF;
			$x2 = -INF;
			$y2 = -INF;
			foreach ( $data['shapes'] as $shape ) {
				$x1 = min( $x1, $shape['box'][0] );
				$y1 = min( $y1, $shape['box'][1] );
				$x2 = max( $x2, $shape['box'][0] + $shape['box'][2] );
				$y2 = max( $y2, $shape['box'][1] + $shape['box'][3] );
			}
			if ( $x2 > $x1 && $y2 > $y1 ) {
				$x0 = $x1;
				$y0 = $y1;
				$fw = $x2 - $x1;
				$fh = $y2 - $y1;
			}
		}
		$shapes = array();
		foreach ( $data['shapes'] as $shape ) {
			$xy       = 0;
			$d        = preg_replace_callback(
				'/-?\d+(?:\.\d+)?/',
				function ( $match ) use ( &$xy, $x0, $y0, $fw, $fh ) {
					$value = (float) $match[0];
					$value = 0 === $xy++ % 2 ? ( $value - $x0 ) / $fw : ( $value - $y0 ) / $fh;
					return self::number( $value );
				},
				$shape['d']
			);
			$shapes[] = array(
				'd'    => $d,
				'box'  => array(
					self::round( ( $shape['box'][0] - $x0 ) / $fw ),
					self::round( ( $shape['box'][1] - $y0 ) / $fh ),
					self::round( $shape['box'][2] / $fw ),
					self::round( $shape['box'][3] / $fh ),
				),
				'rule' => $shape['rule'],
				'z'    => $shape['z'],
			);
		}
		return array(
			'ratio'  => self::round( ( $fw * $data['w'] ) / ( $fh * $data['h'] ) ),
			'shapes' => $shapes,
		);
	}

	/*
	 * Reading the file.
	 */

	/**
	 * The element children of a node that draw something, in order (hidden
	 * ones and those of SKIP left out, the shapes among them counted in
	 * `$skipped`).
	 */
	private static function drawn_children( $node, $classes, $inherited, &$skipped = null ) {
		$list = array();
		foreach ( $node->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			$tag = strtolower( $child->localName );
			if ( in_array( $tag, self::SKIP, true ) ) {
				if ( null !== $skipped && in_array( $tag, array( 'text', 'image', 'use', 'line' ), true ) ) {
					++$skipped;
				}
				continue;
			}
			if ( ! in_array( $tag, self::GROUPS, true ) && ! in_array( $tag, self::SHAPES, true ) ) {
				continue;
			}
			$style = self::style( $child, $classes, $inherited );
			if ( ! self::visible( $style ) ) {
				if ( null !== $skipped ) {
					++$skipped;
				}
				continue;
			}
			$list[] = $child;
		}
		return $list;
	}

	/**
	 * A window's shapes: the element's, or all those in a group, as parts
	 * (subpaths: a start point and segments) in the drawing's numbers.
	 */
	private static function collect( $node, $matrix, $inherited, $classes, &$parts, &$rule, &$skipped ) {
		$style = self::style( $node, $classes, $inherited );
		if ( ! self::visible( $style ) ) {
			++$skipped;
			return;
		}
		$matrix = self::multiply( $matrix, self::transform( $node->getAttribute( 'transform' ) ) );
		$tag    = strtolower( $node->localName );
		if ( in_array( $tag, self::GROUPS, true ) ) {
			foreach ( self::drawn_children( $node, $classes, $style, $skipped ) as $child ) {
				self::collect( $child, $matrix, $style, $classes, $parts, $rule, $skipped );
			}
			return;
		}
		// An outline or a line: no inside to show a photo in.
		if ( 'none' === $style['fill'] ) {
			++$skipped;
			return;
		}
		$shape = self::shape( $node, $tag );
		if ( ! $shape ) {
			return;
		}
		foreach ( $shape as $part ) {
			$parts[] = self::apply( $part, $matrix );
		}
		$rule = 'evenodd' === $style['rule'] ? 'evenodd' : ( $rule ? $rule : 'nonzero' );
	}

	// Whether it is drawn (not hidden, not see-through).
	private static function visible( $style ) {
		return 'none' !== $style['display'] && 'hidden' !== $style['visibility'] && 'collapse' !== $style['visibility'] && $style['opacity'] > 0;
	}

	/**
	 * What of an element's style matters here, as it gets it: its
	 * attributes, then the stylesheet's rules for its classes, then its own
	 * style. Fill, fill-rule and visibility pass on to what it holds.
	 */
	private static function style( $node, $classes, $inherited ) {
		$style = array(
			'fill'       => isset( $inherited['fill'] ) ? $inherited['fill'] : '',
			'rule'       => isset( $inherited['rule'] ) ? $inherited['rule'] : 'nonzero',
			'visibility' => isset( $inherited['visibility'] ) ? $inherited['visibility'] : 'visible',
			'display'    => '',
			'opacity'    => 1,
		);
		$declared = array();
		foreach ( array( 'fill', 'fill-rule', 'clip-rule', 'visibility', 'display', 'opacity', 'fill-opacity' ) as $attribute ) {
			if ( $node->hasAttribute( $attribute ) ) {
				$declared[ $attribute ] = $node->getAttribute( $attribute );
			}
		}
		foreach ( preg_split( '/\s+/', trim( $node->getAttribute( 'class' ) ) ) as $class ) {
			if ( '' !== $class && isset( $classes[ $class ] ) ) {
				$declared = array_merge( $declared, $classes[ $class ] );
			}
		}
		$declared = array_merge( $declared, self::declarations( $node->getAttribute( 'style' ) ) );

		foreach ( $declared as $property => $value ) {
			$value = strtolower( trim( $value ) );
			if ( 'inherit' === $value ) {
				continue;
			}
			if ( 'fill' === $property ) {
				$style['fill'] = 'none' === $value || 'transparent' === $value ? 'none' : 'paint';
			} elseif ( 'fill-rule' === $property ) {
				$style['rule'] = 'evenodd' === $value ? 'evenodd' : 'nonzero';
			} elseif ( 'visibility' === $property || 'display' === $property ) {
				$style[ $property ] = $value;
			} elseif ( 'opacity' === $property || 'fill-opacity' === $property ) {
				$style['opacity'] = min( $style['opacity'], (float) $value );
			}
		}
		return $style;
	}

	/**
	 * The file's stylesheets: for each class, the declarations its rules set
	 * (later rules over earlier ones). Only plain class selectors (".st0"),
	 * as drawing programs write them.
	 */
	private static function stylesheet( $doc ) {
		$classes = array();
		foreach ( $doc->getElementsByTagName( 'style' ) as $node ) {
			$css = preg_replace( '#/\*.*?\*/#s', '', $node->textContent );
			if ( ! preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $rules as $rule ) {
				$declarations = self::declarations( $rule[2] );
				foreach ( explode( ',', $rule[1] ) as $selector ) {
					$selector = trim( $selector );
					if ( preg_match( '/^\.([\w-]+)$/', $selector, $match ) ) {
						$classes[ $match[1] ] = array_merge( isset( $classes[ $match[1] ] ) ? $classes[ $match[1] ] : array(), $declarations );
					}
				}
			}
		}
		return $classes;
	}

	private static function declarations( $css ) {
		$out = array();
		foreach ( explode( ';', (string) $css ) as $declaration ) {
			$pair = explode( ':', $declaration, 2 );
			if ( 2 === count( $pair ) ) {
				$out[ strtolower( trim( $pair[0] ) ) ] = trim( $pair[1] );
			}
		}
		return $out;
	}

	/*
	 * Shapes as parts: [ start x, start y, segments, closed ], each segment
	 * [ 'L', x, y ] or [ 'C', x1, y1, x2, y2, x, y ].
	 */

	private static function shape( $node, $tag ) {
		$num = function ( $name ) use ( $node ) {
			return (float) $node->getAttribute( $name );
		};
		switch ( $tag ) {
			case 'path':
				return self::parse_path( $node->getAttribute( 'd' ) );
			case 'rect':
				return self::rect( $num( 'x' ), $num( 'y' ), $num( 'width' ), $num( 'height' ), $node->hasAttribute( 'rx' ) ? $num( 'rx' ) : null, $node->hasAttribute( 'ry' ) ? $num( 'ry' ) : null );
			case 'circle':
				return self::ellipse( $num( 'cx' ), $num( 'cy' ), $num( 'r' ), $num( 'r' ) );
			case 'ellipse':
				return self::ellipse( $num( 'cx' ), $num( 'cy' ), $num( 'rx' ), $num( 'ry' ) );
			case 'polygon':
			case 'polyline':
				$points = self::numbers( $node->getAttribute( 'points' ) );
				if ( count( $points ) < 6 ) {
					return null;
				}
				$segments = array();
				for ( $i = 2; $i + 1 < count( $points ); $i += 2 ) {
					$segments[] = array( 'L', $points[ $i ], $points[ $i + 1 ] );
				}
				return array( array( $points[0], $points[1], $segments, true ) );
		}
		return null;
	}

	private static function rect( $x, $y, $w, $h, $rx, $ry ) {
		if ( $w <= 0 || $h <= 0 ) {
			return null;
		}
		// One radius set: the other the same; none above half the side.
		$rx = null === $rx ? ( null === $ry ? 0 : $ry ) : $rx;
		$ry = null === $ry ? $rx : $ry;
		$rx = max( 0, min( $rx, $w / 2 ) );
		$ry = max( 0, min( $ry, $h / 2 ) );
		if ( ! $rx || ! $ry ) {
			return array( array( $x, $y, array( array( 'L', $x + $w, $y ), array( 'L', $x + $w, $y + $h ), array( 'L', $x, $y + $h ) ), true ) );
		}
		$k  = 0.5522847498;
		$kx = $rx * $k;
		$ky = $ry * $k;
		$r  = $x + $w;
		$b  = $y + $h;
		return array(
			array(
				$x + $rx,
				$y,
				array(
					array( 'L', $r - $rx, $y ),
					array( 'C', $r - $rx + $kx, $y, $r, $y + $ry - $ky, $r, $y + $ry ),
					array( 'L', $r, $b - $ry ),
					array( 'C', $r, $b - $ry + $ky, $r - $rx + $kx, $b, $r - $rx, $b ),
					array( 'L', $x + $rx, $b ),
					array( 'C', $x + $rx - $kx, $b, $x, $b - $ry + $ky, $x, $b - $ry ),
					array( 'L', $x, $y + $ry ),
					array( 'C', $x, $y + $ry - $ky, $x + $rx - $kx, $y, $x + $rx, $y ),
				),
				true,
			),
		);
	}

	private static function ellipse( $cx, $cy, $rx, $ry ) {
		if ( $rx <= 0 || $ry <= 0 ) {
			return null;
		}
		$kx = $rx * 0.5522847498;
		$ky = $ry * 0.5522847498;
		return array(
			array(
				$cx + $rx,
				$cy,
				array(
					array( 'C', $cx + $rx, $cy + $ky, $cx + $kx, $cy + $ry, $cx, $cy + $ry ),
					array( 'C', $cx - $kx, $cy + $ry, $cx - $rx, $cy + $ky, $cx - $rx, $cy ),
					array( 'C', $cx - $rx, $cy - $ky, $cx - $kx, $cy - $ry, $cx, $cy - $ry ),
					array( 'C', $cx + $kx, $cy - $ry, $cx + $rx, $cy - $ky, $cx + $rx, $cy ),
				),
				true,
			),
		);
	}

	/**
	 * A path's data as parts: every command (relative ones too, H, V, S, Q, T
	 * and arcs) turned into lines and cubic curves. Data that stops making
	 * sense ends the path there, as browsers do.
	 *
	 * @param string $d Path data.
	 * @return array|null
	 */
	public static function parse_path( $d ) {
		$d     = (string) $d;
		$len   = strlen( $d );
		$pos   = 0;
		$args  = array( 'M' => 2, 'L' => 2, 'H' => 1, 'V' => 1, 'C' => 6, 'S' => 4, 'Q' => 4, 'T' => 2, 'A' => 7, 'Z' => 0 );
		$parts = array();
		$part  = null;
		$x     = 0;
		$y     = 0;
		$sx    = 0;
		$sy    = 0;
		// The last curve's second control point (S) and quadratic one (T).
		$cx  = null;
		$cy  = null;
		$qx  = null;
		$qy  = null;
		$cmd = null;

		$number = function () use ( $d, &$pos ) {
			if ( preg_match( '/\G[\s,]*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $d, $match, 0, $pos ) ) {
				$pos += strlen( $match[0] );
				return (float) $match[1];
			}
			return null;
		};
		$flag = function () use ( $d, &$pos ) {
			if ( preg_match( '/\G[\s,]*([01])/', $d, $match, 0, $pos ) ) {
				$pos += strlen( $match[0] );
				return (int) $match[1];
			}
			return null;
		};
		$finish = function () use ( &$part, &$parts ) {
			if ( $part && $part[2] ) {
				$parts[] = $part;
			}
			$part = null;
		};

		while ( $pos < $len ) {
			if ( preg_match( '/\G[\s,]*([MmLlHhVvCcSsQqTtAaZz])/', $d, $match, 0, $pos ) ) {
				$pos += strlen( $match[0] );
				$cmd  = $match[1];
			} elseif ( null === $cmd || preg_match( '/\G[\s,]*$/', $d, $match, 0, $pos ) ) {
				break;
			} elseif ( 'Z' === strtoupper( $cmd ) ) {
				// Numbers after a Z: not a path any more.
				break;
			}
			$upper    = strtoupper( $cmd );
			$relative = $cmd !== $upper;

			if ( 'Z' === $upper ) {
				if ( $part ) {
					$part[3] = true;
					$finish();
				}
				$x  = $sx;
				$y  = $sy;
				$cx = null;
				$qx = null;
				continue;
			}

			// The command's numbers (an arc's flags are single digits).
			$values = array();
			for ( $i = 0; $i < $args[ $upper ]; $i++ ) {
				$value = ( 'A' === $upper && ( 3 === $i || 4 === $i ) ) ? $flag() : $number();
				if ( null === $value ) {
					break 2;
				}
				$values[] = $value;
			}
			$ox = $relative ? $x : 0;
			$oy = $relative ? $y : 0;

			switch ( $upper ) {
				case 'M':
					$finish();
					$x    = $ox + $values[0];
					$y    = $oy + $values[1];
					$sx   = $x;
					$sy   = $y;
					$part = array( $x, $y, array(), false );
					// Pairs after it are lines.
					$cmd = $relative ? 'l' : 'L';
					$cx  = null;
					$qx  = null;
					continue 2;
				case 'L':
					$x = $ox + $values[0];
					$y = $oy + $values[1];
					$segment = array( 'L', $x, $y );
					break;
				case 'H':
					$x = $ox + $values[0];
					$segment = array( 'L', $x, $y );
					break;
				case 'V':
					$y = $oy + $values[0];
					$segment = array( 'L', $x, $y );
					break;
				case 'C':
					$segment = array( 'C', $ox + $values[0], $oy + $values[1], $ox + $values[2], $oy + $values[3], $ox + $values[4], $oy + $values[5] );
					break;
				case 'S':
					$x1      = null === $cx ? $x : 2 * $x - $cx;
					$y1      = null === $cx ? $y : 2 * $y - $cy;
					$segment = array( 'C', $x1, $y1, $ox + $values[0], $oy + $values[1], $ox + $values[2], $oy + $values[3] );
					break;
				case 'Q':
				case 'T':
					if ( 'Q' === $upper ) {
						$px = $ox + $values[0];
						$py = $oy + $values[1];
						$ex = $ox + $values[2];
						$ey = $oy + $values[3];
					} else {
						$px = null === $qx ? $x : 2 * $x - $qx;
						$py = null === $qx ? $y : 2 * $y - $qy;
						$ex = $ox + $values[0];
						$ey = $oy + $values[1];
					}
					$segment = array( 'C', $x + 2 / 3 * ( $px - $x ), $y + 2 / 3 * ( $py - $y ), $ex + 2 / 3 * ( $px - $ex ), $ey + 2 / 3 * ( $py - $ey ), $ex, $ey );
					break;
				case 'A':
					$segment = null;
					$curves  = self::arc( $x, $y, $values[0], $values[1], $values[2], $values[3], $values[4], $ox + $values[5], $oy + $values[6] );
					break;
			}

			if ( ! $part ) {
				// Drawing on after a Z: from where it closed.
				$part = array( $x, $y, array(), false );
			}
			if ( 'A' === $upper ) {
				foreach ( $curves as $curve ) {
					$part[2][] = $curve;
				}
				$x  = $ox + $values[5];
				$y  = $oy + $values[6];
				$cx = null;
				$qx = null;
				continue;
			}
			$part[2][] = $segment;
			if ( 'C' === $segment[0] ) {
				$x = $segment[5];
				$y = $segment[6];
			}
			$cx = in_array( $upper, array( 'C', 'S' ), true ) ? $segment[3] : null;
			$cy = in_array( $upper, array( 'C', 'S' ), true ) ? $segment[4] : null;
			$qx = in_array( $upper, array( 'Q', 'T' ), true ) ? $px : null;
			$qy = in_array( $upper, array( 'Q', 'T' ), true ) ? $py : null;
		}
		$finish();
		return $parts ? $parts : null;
	}

	/**
	 * An elliptical arc as cubic curves (SVG's arc rules: radii too small
	 * grow, zero radii are a line), a quarter turn at most each.
	 */
	private static function arc( $x1, $y1, $rx, $ry, $angle, $large, $sweep, $x2, $y2 ) {
		if ( $x1 === $x2 && $y1 === $y2 ) {
			return array();
		}
		$rx = abs( $rx );
		$ry = abs( $ry );
		if ( ! $rx || ! $ry ) {
			return array( array( 'L', $x2, $y2 ) );
		}
		$phi = deg2rad( fmod( $angle, 360 ) );
		$cos = cos( $phi );
		$sin = sin( $phi );
		$dx  = ( $x1 - $x2 ) / 2;
		$dy  = ( $y1 - $y2 ) / 2;
		$px  = $cos * $dx + $sin * $dy;
		$py  = -$sin * $dx + $cos * $dy;
		$l   = ( $px * $px ) / ( $rx * $rx ) + ( $py * $py ) / ( $ry * $ry );
		if ( $l > 1 ) {
			$rx *= sqrt( $l );
			$ry *= sqrt( $l );
		}
		$num  = $rx * $rx * $ry * $ry - $rx * $rx * $py * $py - $ry * $ry * $px * $px;
		$den  = $rx * $rx * $py * $py + $ry * $ry * $px * $px;
		$root = $den ? sqrt( max( 0, $num / $den ) ) : 0;
		if ( (bool) $large === (bool) $sweep ) {
			$root = -$root;
		}
		$cxp = $root * $rx * $py / $ry;
		$cyp = -$root * $ry * $px / $rx;
		$cx  = $cos * $cxp - $sin * $cyp + ( $x1 + $x2 ) / 2;
		$cy  = $sin * $cxp + $cos * $cyp + ( $y1 + $y2 ) / 2;

		$vector = function ( $ux, $uy, $vx, $vy ) {
			$a = atan2( $uy, $ux );
			$b = atan2( $vy, $vx );
			return $b - $a;
		};
		$theta = $vector( 1, 0, ( $px - $cxp ) / $rx, ( $py - $cyp ) / $ry );
		$delta = $vector( ( $px - $cxp ) / $rx, ( $py - $cyp ) / $ry, ( -$px - $cxp ) / $rx, ( -$py - $cyp ) / $ry );
		$delta = fmod( $delta, 2 * M_PI );
		if ( $sweep && $delta < 0 ) {
			$delta += 2 * M_PI;
		} elseif ( ! $sweep && $delta > 0 ) {
			$delta -= 2 * M_PI;
		}

		$count  = max( 1, (int) ceil( abs( $delta ) / ( M_PI / 2 ) - 1e-9 ) );
		$step   = $delta / $count;
		$k      = 4 / 3 * tan( $step / 4 );
		$curves = array();
		$point  = function ( $t ) use ( $cx, $cy, $rx, $ry, $cos, $sin ) {
			$ex = $rx * cos( $t );
			$ey = $ry * sin( $t );
			return array( $cx + $cos * $ex - $sin * $ey, $cy + $sin * $ex + $cos * $ey );
		};
		$tangent = function ( $t ) use ( $rx, $ry, $cos, $sin ) {
			$ex = -$rx * sin( $t );
			$ey = $ry * cos( $t );
			return array( $cos * $ex - $sin * $ey, $sin * $ex + $cos * $ey );
		};
		for ( $i = 0; $i < $count; $i++ ) {
			$t1 = $theta + $i * $step;
			$t2 = $t1 + $step;
			$p1 = $point( $t1 );
			$p2 = $point( $t2 );
			$d1 = $tangent( $t1 );
			$d2 = $tangent( $t2 );
			$end = $i === $count - 1 ? array( $x2, $y2 ) : $p2;
			$curves[] = array( 'C', $p1[0] + $k * $d1[0], $p1[1] + $k * $d1[1], $p2[0] - $k * $d2[0], $p2[1] - $k * $d2[1], $end[0], $end[1] );
		}
		return $curves;
	}

	/*
	 * Transforms: [ a, b, c, d, e, f ] as SVG's matrix().
	 */

	public static function transform( $text ) {
		$matrix = array( 1, 0, 0, 1, 0, 0 );
		if ( ! preg_match_all( '/(matrix|translate|scale|rotate|skewX|skewY)\s*\(([^)]*)\)/i', (string) $text, $items, PREG_SET_ORDER ) ) {
			return $matrix;
		}
		foreach ( $items as $item ) {
			$n = self::numbers( $item[2] );
			switch ( strtolower( $item[1] ) ) {
				case 'matrix':
					$next = 6 === count( $n ) ? $n : array( 1, 0, 0, 1, 0, 0 );
					break;
				case 'translate':
					$next = array( 1, 0, 0, 1, isset( $n[0] ) ? $n[0] : 0, isset( $n[1] ) ? $n[1] : 0 );
					break;
				case 'scale':
					$sx   = isset( $n[0] ) ? $n[0] : 1;
					$next = array( $sx, 0, 0, isset( $n[1] ) ? $n[1] : $sx, 0, 0 );
					break;
				case 'rotate':
					$a    = deg2rad( isset( $n[0] ) ? $n[0] : 0 );
					$next = array( cos( $a ), sin( $a ), -sin( $a ), cos( $a ), 0, 0 );
					if ( 3 === count( $n ) ) {
						$next = self::multiply( self::multiply( array( 1, 0, 0, 1, $n[1], $n[2] ), $next ), array( 1, 0, 0, 1, -$n[1], -$n[2] ) );
					}
					break;
				case 'skewx':
					$next = array( 1, 0, tan( deg2rad( isset( $n[0] ) ? $n[0] : 0 ) ), 1, 0, 0 );
					break;
				default:
					$next = array( 1, tan( deg2rad( isset( $n[0] ) ? $n[0] : 0 ) ), 0, 1, 0, 0 );
			}
			$matrix = self::multiply( $matrix, $next );
		}
		return $matrix;
	}

	private static function multiply( $m, $n ) {
		return array(
			$m[0] * $n[0] + $m[2] * $n[1],
			$m[1] * $n[0] + $m[3] * $n[1],
			$m[0] * $n[2] + $m[2] * $n[3],
			$m[1] * $n[2] + $m[3] * $n[3],
			$m[0] * $n[4] + $m[2] * $n[5] + $m[4],
			$m[1] * $n[4] + $m[3] * $n[5] + $m[5],
		);
	}

	private static function apply( $part, $m ) {
		$point = function ( $x, $y ) use ( $m ) {
			return array( $m[0] * $x + $m[2] * $y + $m[4], $m[1] * $x + $m[3] * $y + $m[5] );
		};
		$start    = $point( $part[0], $part[1] );
		$segments = array();
		foreach ( $part[2] as $segment ) {
			if ( 'L' === $segment[0] ) {
				$p          = $point( $segment[1], $segment[2] );
				$segments[] = array( 'L', $p[0], $p[1] );
			} else {
				$a          = $point( $segment[1], $segment[2] );
				$b          = $point( $segment[3], $segment[4] );
				$c          = $point( $segment[5], $segment[6] );
				$segments[] = array( 'C', $a[0], $a[1], $b[0], $b[1], $c[0], $c[1] );
			}
		}
		return array( $start[0], $start[1], $segments, $part[3] );
	}

	/*
	 * Measuring and writing out.
	 */

	// Points along the parts (each closed one as a polygon), for bounds and area.
	private static function sample( $parts ) {
		$points = array();
		foreach ( $parts as $part ) {
			foreach ( self::polygon( $part ) as $point ) {
				$points[] = $point;
			}
		}
		return $points;
	}

	private static function polygon( $part ) {
		$x      = $part[0];
		$y      = $part[1];
		$points = array( array( $x, $y ) );
		foreach ( $part[2] as $s ) {
			if ( 'L' === $s[0] ) {
				$points[] = array( $s[1], $s[2] );
			} else {
				for ( $i = 1; $i <= self::SAMPLES; $i++ ) {
					$t        = $i / self::SAMPLES;
					$u        = 1 - $t;
					$points[] = array(
						$u * $u * $u * $x + 3 * $u * $u * $t * $s[1] + 3 * $u * $t * $t * $s[3] + $t * $t * $t * $s[5],
						$u * $u * $u * $y + 3 * $u * $u * $t * $s[2] + 3 * $u * $t * $t * $s[4] + $t * $t * $t * $s[6],
					);
				}
			}
			$last = end( $points );
			$x    = $last[0];
			$y    = $last[1];
		}
		return $points;
	}

	private static function bounds( $points ) {
		if ( ! $points ) {
			return null;
		}
		$xs = array_column( $points, 0 );
		$ys = array_column( $points, 1 );
		return array( min( $xs ), min( $ys ), max( $xs ) - min( $xs ), max( $ys ) - min( $ys ) );
	}

	// The area inside, about (subpaths turning the other way, holes, take theirs off).
	private static function area( $parts ) {
		$sum = 0;
		foreach ( $parts as $part ) {
			$points = self::polygon( $part );
			$count  = count( $points );
			for ( $i = 0; $i < $count; $i++ ) {
				$a    = $points[ $i ];
				$b    = $points[ ( $i + 1 ) % $count ];
				$sum += $a[0] * $b[1] - $b[0] * $a[1];
			}
		}
		return abs( $sum ) / 2;
	}

	// The parts as path data in numbers of the drawing's area (0–1).
	private static function path( $parts, $vx, $vy, $vw, $vh ) {
		$p   = function ( $x, $y ) use ( $vx, $vy, $vw, $vh ) {
			return self::number( ( $x - $vx ) / $vw ) . ' ' . self::number( ( $y - $vy ) / $vh );
		};
		$out = array();
		foreach ( $parts as $part ) {
			$out[] = 'M ' . $p( $part[0], $part[1] );
			foreach ( $part[2] as $s ) {
				$out[] = 'L' === $s[0] ? 'L ' . $p( $s[1], $s[2] ) : 'C ' . $p( $s[1], $s[2] ) . ' ' . $p( $s[3], $s[4] ) . ' ' . $p( $s[5], $s[6] );
			}
			// Filled either way: each part closed.
			$out[] = 'Z';
		}
		return implode( ' ', $out );
	}

	public static function numbers( $text ) {
		preg_match_all( '/[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', (string) $text, $match );
		return array_map( 'floatval', $match[0] );
	}

	// Five decimals: a hundred-thousandth of the drawing, no trailing zeros.
	private static function number( $value ) {
		$text = rtrim( rtrim( sprintf( '%.5F', $value ), '0' ), '.' );
		return '-0' === $text || '' === $text ? '0' : $text;
	}

	private static function round( $value ) {
		return round( (float) $value, 5 );
	}

	// A layer's or object's name: plain text, short.
	// Whether text is valid UTF-8 (without mbstring too).
	private static function is_utf8( $text ) {
		return function_exists( 'mb_check_encoding' ) ? mb_check_encoding( $text, 'UTF-8' ) : (bool) preg_match( '//u', $text );
	}

	private static function clean_id( $id ) {
		return substr( sanitize_text_field( (string) $id ), 0, 64 );
	}

	private static function clean_name( $name ) {
		return substr( sanitize_file_name( wp_basename( (string) $name ) ), 0, 120 );
	}
}
