/**
 * WP Maps Embed edit screen: the preview in a monitor, tablet or phone
 * (WP_Map_Embed_Preview) follows the settings on "Update preview", one device
 * for the fields and the preview, settings that do not apply greyed out
 * (keeping their values), the stencil's file, the placeholder image picker
 * and the shortcode's copy.
 */
( function ( $ ) {
	'use strict';

	var l10n = window.wpMapEmbedAdmin || {};

	$( function () {
		var $box = $( '.wp-map-embed-admin' );
		if ( ! $box.length ) {
			return;
		}
		var $form    = $box.closest( 'form' );
		var $preview = $box.find( '.wp-map-embed-preview' );
		var $stage   = $preview.find( '.wp-map-embed-preview__stage' );
		var $update  = $preview.find( '.wp-map-embed-preview__update' );
		var previewFrame = $preview.find( '.wp-map-embed-preview__frame' )[ 0 ];
		var resetMap = null;
		var resetObserver = null;

		function syncResetButton() {
			$preview.find( '.wp-map-embed-preview__reset' ).prop( 'disabled',
				! resetMap || ! resetMap.classList.contains( 'is-active' ) || ! resetMap.querySelector( '.wp-map-embed__cover' ) );
		}

		function watchPreviewMap() {
			if ( resetObserver ) { resetObserver.disconnect(); }
			resetMap = null;
			try {
				resetMap = previewFrame.contentDocument.querySelector( '.wp-map-embed' );
			} catch ( e ) {}
			if ( resetMap ) {
				resetObserver = new MutationObserver( syncResetButton );
				resetObserver.observe( resetMap, { attributes: true, attributeFilter: [ 'class' ] } );
			}
			syncResetButton();
		}

		function field( key ) {
			return $box.find( '[name="wp_map_embed[' + key + ']"]' );
		}

		function checked( key ) {
			return field( key ).is( ':checked' );
		}

		// A derived Maps URL for copying, never a separately saved setting.
		function routeUrl( source ) {
			source = $.trim( source || '' );
			if ( /<iframe\b/i.test( source ) ) {
				var doc = new DOMParser().parseFromString( source, 'text/html' );
				var iframe = doc.querySelector( 'iframe' );
				source = iframe ? $.trim( iframe.getAttribute( 'src' ) || '' ) : '';
				if ( ! /^https:\/\//i.test( source ) ) { return ''; }
			}
			if ( ! source || /[<>]/.test( source ) ) {
				return '';
			}
			var destination = source;
			var placeId = '';
			if ( /^https?:\/\//i.test( source ) ) {
				var url;
				try { url = new URL( source ); } catch ( e ) { return ''; }
				if ( url.protocol !== 'https:' || ! /(^|\.)google\.[a-z]{2,3}(\.[a-z]{2})?$/i.test( url.hostname ) ||
					url.pathname.indexOf( '/maps' ) !== 0 ||
					( url.pathname.indexOf( '/maps/embed' ) !== 0 && url.searchParams.get( 'output' ) !== 'embed' ) ) {
					return '';
				}
				destination = url.searchParams.get( 'destination' ) || url.searchParams.get( 'q' ) || '';
				placeId = url.searchParams.get( 'destination_place_id' ) || '';
				if ( destination.indexOf( 'place_id:' ) === 0 ) {
					placeId = destination.slice( 9 );
					destination = placeId;
				}
				if ( ! destination ) {
					// Share > Embed uses an undocumented pb payload. Prefer the
					// selected place's coordinates, never the viewport's center.
					var pb = url.searchParams.get( 'pb' ) || '';
					var point = /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/.exec( pb );
					var name = /!2s([^!]+)/.exec( pb );
					if ( point && Math.abs( Number( point[ 1 ] ) ) <= 90 && Math.abs( Number( point[ 2 ] ) ) <= 180 ) {
						destination = point[ 1 ] + ',' + point[ 2 ];
					} else if ( name ) {
						destination = name[ 1 ];
					}
				}
			}
			if ( ! $.trim( destination ) ) { return ''; }
			var route = new URL( 'https://www.google.com/maps/dir/' );
			route.searchParams.set( 'api', '1' );
			route.searchParams.set( 'destination', destination );
			if ( placeId ) { route.searchParams.set( 'destination_place_id', placeId ); }
			return route.href.length <= 2048 ? route.href : '';
		}

		function updateRoute() {
			var route = routeUrl( field( 'source' ).val() );
			$box.find( '.wp-map-embed-admin__route' ).val( route ).prop( 'disabled', ! route );
			$box.find( '.wp-map-embed-admin__route-copy' ).prop( 'disabled', ! route );
			$box.find( '.wp-map-embed-admin__route-status' ).text( '' );
		}

		function fallbackCopy( text ) {
			var focused = document.activeElement;
			var input = document.createElement( 'textarea' );
			input.value = text;
			input.readOnly = true;
			input.style.cssText = 'position:fixed;left:-9999px;top:0;';
			document.body.appendChild( input );
			var copied = false;
			try {
				input.select();
				copied = document.execCommand( 'copy' );
			} catch ( e ) {}
			input.remove();
			if ( focused ) { focused.focus( { preventScroll: true } ); }
			return copied;
		}

		$box.on( 'click', '.wp-map-embed-admin__route-copy', function () {
			var value = $box.find( '.wp-map-embed-admin__route' ).val();
			if ( ! value ) { return; }
			function report( copied ) {
				if ( value !== $box.find( '.wp-map-embed-admin__route' ).val() ) { return; }
				$box.find( '.wp-map-embed-admin__route-status' ).text( copied ? ( l10n.copied || 'Copied!' ) :
					( l10n.copyFailed || 'Could not copy. Select the link and copy it manually.' ) );
			}
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( value ).then( function () { report( true ); }, function () { report( fallbackCopy( value ) ); } );
			} else {
				report( fallbackCopy( value ) );
			}
		} );
		$box.on( 'input change', '[name="wp_map_embed[source]"]', updateRoute );
		$box.on( 'click', '.wp-map-embed-admin__route', function () { this.select(); } );
		updateRoute();

		// As in WP Swiper: a field that does not apply now is hidden, and shows
		// (under its switch, say) once it does. Hidden, not disabled: a
		// disabled field is not sent, and its value would be lost.
		function setOff( key, off ) {
			$box.find( '.wp-map-embed-admin__row[data-field="' + key + '"]' ).prop( 'hidden', off );
		}

		// An address (zoom applies) rather than a link or Google's code: as
		// WP_Map_Embed_Settings::is_address() reads it.
		function isAddress() {
			var source = $.trim( field( 'source' ).val() || '' );
			return '' !== source && ! /^https?:\/\//i.test( source ) && ! /<iframe\b/i.test( source );
		}

		function dependencies() {
			var icon     = $box.find( '[name="wp_map_embed[icon]"]:checked' ).val();
			var mode     = $box.find( '[name="wp_map_embed[size_mode]"]:checked' ).val();

			setOff( 'ratio', 'ratio' !== mode );
			var customRatio = $box.find( '[data-field="ratio"] select' ).filter( function () { return $( this ).val() === 'custom'; } ).length > 0;
			setOff( 'ratio_custom', mode !== 'ratio' || ! customRatio );
			setOff( 'height', 'ratio' === mode );

			setOff( 'placeholder', ! checked( 'defer' ) );
			setOff( 'overlay_color', ! checked( 'overlay_enabled' ) );
			setOff( 'icon', ! checked( 'icon_enabled' ) );
			setOff( 'label', ! checked( 'text_enabled' ) );
			setOff( 'icon_color', ! checked( 'icon_enabled' ) || 'none' === icon );
			setOff( 'icon_size', ! checked( 'icon_enabled' ) || 'none' === icon );
			setOff( 'zoom', ! isAddress() );

			// SVG outlines use a stroke and drop-shadow, as in WP Swiper.
			var shape = '' !== ( field( 'shape' ).val() || '' );
			var shaped = checked( 'shape_enabled' ) && shape;
			setOff( 'shape', ! checked( 'shape_enabled' ) );
			setOff( 'shape_position', ! checked( 'shape_enabled' ) || ! shape );
			setOff( 'rounded', shaped );
			setOff( 'radius', shaped || ! checked( 'rounded' ) );
			setOff( 'scale', ! checked( 'scale_enabled' ) );
			[ 'border_width', 'border_style', 'border_color' ].forEach( function ( key ) { setOff( key, ! checked( 'border' ) ); } );
			[ 'shadow_x', 'shadow_y', 'shadow_blur', 'shadow_spread', 'shadow_color' ].forEach( function ( key ) { setOff( key, ! checked( 'shadow' ) ); } );
			setOff( 'shadow_spread', shaped || ! checked( 'shadow' ) );
			$box.find( '.wp-map-embed-admin__block' ).each( function () {
				$( this ).prop( 'hidden', ! $( this ).find( '.wp-map-embed-admin__row:not([hidden])' ).length );
			} );
		}

		/*
		 * Preview (WP_Map_Embed_Preview, as in WP Swiper): the form's settings,
		 * saved or not, posted into the frame when the screen opens and on
		 * "Update preview" only (not on every change). The frame is a page of its
		 * own at the device's size, so the map shows that device's settings.
		 */
		function refresh() {
			if ( ! previewFrame ) {
				return;
			}
			if ( resetObserver ) { resetObserver.disconnect(); }
			resetMap = null;
			syncResetButton();
			var $post = $( '<form method="post" hidden></form>' ).attr( {
				action: $preview.data( 'url' ),
				target: previewFrame.name,
			} );
			function add( name, value ) {
				$( '<input type="hidden">' ).attr( 'name', name ).val( value ).appendTo( $post );
			}
			add( 'action', $preview.data( 'action' ) );
			add( 'post_id', $preview.data( 'post' ) );
			add( '_wpnonce', $preview.data( 'nonce' ) );
			add( 'title', $( '#title' ).val() || '' );
			$form.find( '[name^="wp_map_embed["]' ).serializeArray().forEach( function ( item ) {
				add( item.name, item.value );
			} );
			$post.appendTo( document.body );
			$post[ 0 ].submit();
			$post.remove();
			$update.removeClass( 'is-stale' );
		}

		// What the frame's page says (window.wpMapEmbedPreview): a link that is
		// no map, and the stencil as the server read it.
		$( previewFrame ).on( 'load', function () {
			watchPreviewMap();
			var info = null;
			try {
				info = previewFrame.contentWindow.wpMapEmbedPreview;
			} catch ( e ) {}
			if ( ! info ) {
				return;
			}
			$box.find( '.wp-map-embed-admin__error' ).prop( 'hidden', ! info.invalid );
			markError();
			if ( info.shape ) {
				shapeRead( info.shape );
			}
		} );

		/*
		 * The menu (as in WP Swiper): one page of the settings in view beside
		 * it, the one chosen last kept for the next visit (this browser only).
		 */
		var PAGE_KEY = 'wpMapEmbedPage';
		var $menu = $box.find( '.wp-map-embed-admin__menu-item' );

		function showPage( page ) {
			if ( ! $menu.filter( '[data-page="' + page + '"]' ).length ) {
				return;
			}
			$menu.each( function () {
				$( this ).attr( 'aria-current', $( this ).data( 'page' ) === page ? 'true' : 'false' );
			} );
			$box.find( '.wp-map-embed-admin__page' ).each( function () {
				this.hidden = $( this ).data( 'page' ) !== page;
			} );
		}

		$menu.on( 'click', function () {
			var page = $( this ).data( 'page' );
			showPage( page );
			try {
				window.localStorage.setItem( PAGE_KEY, page );
			} catch ( e ) {}
		} );

		try {
			showPage( window.localStorage.getItem( PAGE_KEY ) );
		} catch ( e ) {}

		// A link that is no map: a mark on Map in the menu, seen from the other pages too.
		function markError() {
			$menu.filter( '[data-page="map"]' ).toggleClass( 'has-error', ! $box.find( '.wp-map-embed-admin__error' ).prop( 'hidden' ) );
		}
		markError();

		// The preview no longer shows these settings: the button says so.
		function changed() {
			dependencies();
			$update.addClass( 'is-stale' );
		}

		$box.on( 'input change', 'input, textarea, select', changed );
		$( '#title' ).on( 'input change', changed );

		// A slider and its number field move together.
		$box.on( 'input change', '.wp-map-embed-admin__range input', function () {
			$( this ).siblings( 'input' ).val( this.value );
		} );

		var COLOR_PICK = '.wp-map-embed-color-pick';
		var colorI18n = $.extend( {}, l10n.colorPicker || {} );
		// The palette's colors and names (TinyMCE's map: "RRGGBB", name, …).
		var colorSwatches = [];
		var colorNames = [];
		( $.isArray( l10n.textColors ) ? l10n.textColors : [] ).forEach( function ( item, i, map ) {
			if ( i % 2 === 0 && /^[0-9a-f]{6}$/i.test( item ) ) {
				colorSwatches.push( '#' + item.toLowerCase() );
				colorNames.push( String( map[ i + 1 ] || '' ) );
			}
		} );

		// "#rrggbb" / "rgba(r,g,b,a)" as { hex, alpha }; null for anything else.
		function parseColor( value ) {
			value = String( value || '' ).replace( /\s+/g, '' ).toLowerCase();
			var m = /^rgba\((\d{1,3}),(\d{1,3}),(\d{1,3}),(\d*\.?\d+)\)$/.exec( value );
			if ( m ) {
				return {
					hex: '#' + [ m[ 1 ], m[ 2 ], m[ 3 ] ].map( function ( channel ) {
						return ( '0' + Math.min( 255, parseInt( channel, 10 ) ).toString( 16 ) ).slice( -2 );
					} ).join( '' ),
					alpha: Math.round( Math.min( 1, parseFloat( m[ 4 ] ) ) * 100 )
				};
			}
			if ( /^#[0-9a-f]{3}$/.test( value ) ) {
				value = '#' + value[ 1 ] + value[ 1 ] + value[ 2 ] + value[ 2 ] + value[ 3 ] + value[ 3 ];
			}
			return /^#[0-9a-f]{6}$/.test( value ) ? { hex: value, alpha: 100 } : null;
		}

		function colorValue( hex, alpha ) {
			if ( alpha >= 100 ) {
				return hex;
			}
			return 'rgba(' + [ 1, 3, 5 ].map( function ( i ) {
				return parseInt( hex.substr( i, 2 ), 16 );
			} ).join( ',' ) + ',' + ( alpha / 100 ) + ')';
		}

		// What Default goes back to.
		function pickDefault( $pick ) {
			return parseColor( $pick.attr( 'data-default' ) ) || { hex: '#000000', alpha: 100 };
		}

		// The swatch says what is chosen (over its checkerboard, admin.css).
		function paintColor( $pick ) {
			$pick.find( '.wp-map-embed-color-pick__chip' ).css( 'box-shadow', 'inset 0 0 0 60px ' + colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) ) );
		}

		// Into Pickr (no event of its own), in the format it shows.
		function pickrShow( $pick ) {
			var pickr = $pick.data( 'pickr' );
			if ( ! pickr || ! $pick.data( 'pickrReady' ) ) {
				return;
			}
			var shown = pickr.getColorRepresentation() || 'HEXA';
			// Its format buttons say "change" too: not a new color.
			$pick.data( 'pickrSync', true );
			pickr.setColor( colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) ), true );
			pickr.setColorRepresentation( shown );
			$pick.data( 'pickrSync', false );
			// What Cancel goes back to.
			pickr.applyColor( true );
		}

		function setColor( $pick, hex, alpha ) {
			$pick.data( { hex: hex, alpha: alpha } );
			paintColor( $pick );
		}

		// A setting's field: what is chosen into its input (the preview and
		// the "unsaved" warning take it as any other change).
		function colorPicked( $pick ) {
			var $input = $pick.find( '.wp-map-embed-color-pick__value' );
			var value = colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) );
			if ( $input.length && $input.val() !== value ) {
				$input.val( value ).trigger( 'change' );
			}
		}

		// From Pickr: the color it shows, as the field keeps it. Only a new
		// one counts (Pickr says "change" for a format button too).
		function fromPickr( $pick, color ) {
			if ( ! color || $pick.data( 'pickrSync' ) ) {
				return;
			}
			var rgba = color.toRGBA();
			var hex = '#' + [ 0, 1, 2 ].map( function ( i ) {
				return ( '0' + Math.max( 0, Math.min( 255, Math.round( rgba[ i ] ) ) ).toString( 16 ) ).slice( -2 );
			} ).join( '' );
			var alpha = $pick.attr( 'data-alpha' ) === '1' ? Math.max( 0, Math.min( 100, Math.round( rgba[ 3 ] * 100 ) ) ) : 100;
			if ( hex === $pick.data( 'hex' ) && alpha === $pick.data( 'alpha' ) ) {
				return;
			}
			setColor( $pick, hex, alpha );
			colorPicked( $pick );
		}

		function closeColorPickers() {
			$( COLOR_PICK ).each( function () {
				var pickr = $( this ).data( 'pickr' );
				if ( pickr ) {
					pickr.hide();
				}
			} );
		}

		// Pickr the first time a field opens (one app each, in the body).
		function makePickr( $pick ) {
			var button = $pick.find( '.wp-map-embed-color-pick__swatch' )[ 0 ];
			var alpha = $pick.attr( 'data-alpha' ) === '1';
			var pickr = window.Pickr.create( {
				el: button,
				useAsButton: true,
				theme: 'classic',
				appClass: 'wp-map-embed-pcr',
				position: 'bottom-start',
				default: colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) ),
				defaultRepresentation: 'HEXA',
				lockOpacity: ! alpha,
				swatches: colorSwatches,
				i18n: $.extend( {}, colorI18n, { 'btn:toggle': button.getAttribute( 'aria-label' ) } ),
				components: {
					preview: false,
					opacity: alpha,
					hue: true,
					interaction: {
						hex: true,
						rgba: true,
						hsla: true,
						input: true,
						cancel: true
					}
				}
			} );
			$pick.data( 'pickr', pickr );
			pickr.on( 'init', function ( instance ) {
				var $app = $( instance.getRoot().app );
				// The palette's names on hover.
				$app.find( '.pcr-swatches > button' ).each( function ( i ) {
					if ( colorNames[ i ] ) {
						this.setAttribute( 'title', colorNames[ i ] );
						this.setAttribute( 'aria-label', colorNames[ i ] );
					}
				} );
				// Default, after Cancel: red, as Pickr's own Clear (classic theme).
				$( '<input type="button" class="pcr-clear wp-map-embed-pcr__default">' )
					.val( colorI18n[ 'default' ] || 'Default' )
					.attr( 'aria-label', colorI18n.defaultLabel || '' )
					.appendTo( $app.find( '.pcr-interaction' ) )
					.on( 'click', function () {
						var color = pickDefault( $pick );
						setColor( $pick, color.hex, color.alpha );
						colorPicked( $pick );
						if ( ! $pick.attr( 'data-default' ) ) {
							$pick.find( '.wp-map-embed-color-pick__value' ).val( '' ).trigger( 'change' );
						}
						pickrShow( $pick );
					} );
				$pick.data( 'pickrReady', true );
				instance.show();
			} );
			pickr.on( 'show', function () {
				$pick.data( 'originalValue', $pick.find( '.wp-map-embed-color-pick__value' ).val() );
				pickrShow( $pick );
				button.setAttribute( 'aria-expanded', 'true' );
			} );
			pickr.on( 'hide', function () {
				button.setAttribute( 'aria-expanded', 'false' );
			} );
			pickr.on( 'change', function ( color ) {
				fromPickr( $pick, color );
			} );
			pickr.on( 'cancel', function ( instance ) {
				fromPickr( $pick, instance.getColor() );
				$pick.find( '.wp-map-embed-color-pick__value' ).val( $pick.data( 'originalValue' ) ).trigger( 'change' );
				instance.hide();
			} );
		}

		$( document ).on( 'click', '.wp-map-embed-color-pick__swatch', function () {
			var $pick = $( this ).closest( COLOR_PICK );
			if ( ! $pick.data( 'pickr' ) && window.Pickr ) {
				makePickr( $pick );
			}
		} );
		// The fields show their saved color.
		$( COLOR_PICK ).each( function () {
			var $pick = $( this );
			var color = parseColor( $pick.find( '.wp-map-embed-color-pick__value' ).val() ) || pickDefault( $pick );
			$pick.find( '.wp-map-embed-color-pick__swatch' ).attr( 'aria-expanded', 'false' );
			setColor( $pick, color.hex, color.alpha );
		} );

		$box.on( 'click', '.wp-map-embed-admin__menu-item', closeColorPickers );
		$( document ).on( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) { closeColorPickers(); }
		} );

		/*
		 * Per-device values (as in WP Swiper): one device's field at a time, by
		 * the switcher at the label. An empty tablet / mobile field shows the
		 * value it inherits; a dot on the icon marks a value of the device's own.
		 */
		function syncResponsive( $row ) {
			if ( $row.attr( 'data-field' ) === 'shape_position' ) {
				$row.find( '.wp-map-embed-admin__device-input' ).each( function () {
					var device = $( this ).data( 'device' );
					var value = $( this ).find( 'input:checked' ).val() || '';
					$row.find( '.wp-map-embed-admin__device[data-device="' + device + '"]' ).toggleClass( 'has-value', device !== 'desktop' && value !== '' );
				} );
				return;
			}
			if ( $row.attr( 'data-field' ) === 'height' ) {
				var inheritedNumber = '';
				var inheritedUnit = 'px';
				[ 'desktop', 'tablet', 'mobile' ].forEach( function ( device ) {
					var $input = $row.find( 'input[data-device="' + device + '"]' );
					var $unit = $row.find( 'select[data-device="' + device + '"]' );
					var ownNumber = $input.val() !== '' && Number( $input.val() ) > 0;
					var ownUnit = $unit.val() !== '';
					var unit = $unit.val() || inheritedUnit;
					var $option = $unit.find( 'option' ).filter( function () { return this.value === unit; } );
					$input.attr( { min: $option.data( 'min' ), max: $option.data( 'max' ) } );
					$input.toggleClass( 'is-height-auto', unit === 'auto' );
					if ( unit === 'auto' ) { $input.removeAttr( 'min max' ); }
					if ( device !== 'desktop' ) {
						$input.attr( 'placeholder', inheritedNumber );
						var $inherit = $unit.find( 'option[value=""]' );
						if ( ! $inherit.data( 'label' ) ) { $inherit.data( 'label', $inherit.text() ); }
						$inherit.text( $inherit.data( 'label' ) + ' (' + inheritedUnit + ')' );
						$row.find( '.wp-map-embed-admin__device[data-device="' + device + '"]' ).toggleClass( 'has-value', ownNumber || ownUnit );
					}
					if ( ownNumber ) { inheritedNumber = $input.val(); }
					inheritedUnit = unit;
				} );
				return;
			}
			var inherited = '';
			$row.find( '.wp-map-embed-admin__device-input' ).each( function () {
				var $input = $( this );
				var device = $input.data( 'device' );
				var own    = '' !== $input.val();

				if ( 'desktop' !== device ) {
					// Numbers only: a select's empty choice says "Inherit".
					if ( ! $input.is( 'select' ) ) {
						$input.attr( 'placeholder', inherited );
					}
					$row.find( '.wp-map-embed-admin__device[data-device="' + device + '"]' ).toggleClass( 'has-value', own );
				}
				if ( own ) {
					inherited = $input.val();
				} else if ( 'desktop' === device ) {
					inherited = $input.attr( 'placeholder' ) || '';
				}
			} );
		}

		$box.find( '.wp-map-embed-admin__row--responsive' ).each( function () {
			syncResponsive( $( this ) );
		} );

		/*
		 * One device for the whole screen (as in WP Swiper): a device chosen at
		 * any field or in the preview shows every field's value for it and the
		 * preview at its size. Kept for the next visit (this browser only).
		 */
		var DEVICE_KEY = 'wpMapEmbedDevice';
		var DEVICES = [ 'desktop', 'tablet', 'mobile' ];
		var currentDevice = 'desktop';
		try {
			var kept = window.localStorage.getItem( DEVICE_KEY );
			if ( DEVICES.indexOf( kept ) !== -1 ) {
				currentDevice = kept;
			}
		} catch ( e ) {}

		function setDevice( device ) {
			currentDevice = device;
			$box.find( '.wp-map-embed-admin__device' ).each( function () {
				var on = $( this ).data( 'device' ) === device;
				$( this ).toggleClass( 'is-active', on ).attr( 'aria-pressed', on ? 'true' : 'false' );
			} );
			$box.find( '.wp-map-embed-admin__device-input' ).each( function () {
				this.hidden = $( this ).data( 'device' ) !== device;
			} );
			$preview.find( '.wp-map-embed-preview__device-btn' ).each( function () {
				$( this ).attr( 'aria-pressed', $( this ).data( 'device' ) === device ? 'true' : 'false' );
			} );
			fitPreview();
			try {
				window.localStorage.setItem( DEVICE_KEY, device );
			} catch ( e ) {}
		}

		function focusDeviceField( $row ) {
			var $controls = $row.find( '.wp-map-embed-admin__device-input:visible' );
			var $input = $controls.filter( 'input' ).first();
			if ( ! $input.length ) { $input = $controls.find( 'input:checked' ).first(); }
			( $input.length ? $input : $controls.first() ).trigger( 'focus' );
		}

		$box
			.on( 'click', '.wp-map-embed-admin__device', function () {
				setDevice( $( this ).data( 'device' ) );
				focusDeviceField( $( this ).closest( '.wp-map-embed-admin__row' ) );
			} )
			.on( 'click', '.wp-map-embed-preview__device-btn', function () {
				setDevice( $( this ).data( 'device' ) );
				focusDeviceField( $box.find( '[data-field="height"]' ) );
			} )
			.on( 'input change', '.wp-map-embed-admin__device-input', function () {
				syncResponsive( $( this ).closest( '.wp-map-embed-admin__row' ) );
			} );

		$update.on( 'click', refresh );

		/*
		 * The frame at the size of the device chosen (WP_Map_Embed_Preview::SIZES),
		 * in the device drawn for it (::DEVICES): the frame in its screen with its
		 * rounded corners, the drawing over it letting the clicks through, both
		 * scaled down to the column as one. As WP Swiper's preview.
		 *
		 * Turned sideways (Tablet, Mobile; the button by the title): the frame's
		 * sides swapped, the drawing a quarter turn clockwise round it. The map
		 * then takes the settings of the device its width falls in, as on the
		 * site (the note under the bar says which). Kept per device.
		 */
		var sizes = $preview.data( 'sizes' ) || {};
		var drawings = $preview.data( 'devices' ) || {};
		var $drawings = $preview.find( '.wp-map-embed-preview__device' );
		var $turn = $preview.find( '.wp-map-embed-preview__rotate' );
		var TURN_KEY = 'wpMapEmbedTurned';
		var turned = {};
		try {
			turned = JSON.parse( window.localStorage.getItem( TURN_KEY ) ) || {};
		} catch ( e ) {}

		function canTurn() {
			return 'desktop' !== currentDevice && !! drawings[ currentDevice ];
		}

		function isTurned() {
			return canTurn() && !! turned[ currentDevice ];
		}

		function fitPreview() {
			if ( ! previewFrame || ! sizes[ currentDevice ] ) {
				return;
			}
			var turn = isTurned();
			var size = turn ? [ sizes[ currentDevice ][ 1 ], sizes[ currentDevice ][ 0 ] ] : sizes[ currentDevice ];
			var device = drawings[ currentDevice ];
			var image = $drawings.filter( '[data-device="' + currentDevice + '"]' )[ 0 ];
			var inDevice = !! ( device && image );
			// px of the frame per unit of the drawing (its screen as wide as the
			// upright frame); the whole device's size, and the screen's place in it.
			var unit = inDevice ? sizes[ currentDevice ][ 0 ] / device.screen[ 2 ] : 1;
			var upright = inDevice ? [ device.width * unit, device.height * unit ] : size;
			var outer = turn ? [ upright[ 1 ], upright[ 0 ] ] : upright;
			var offset = [ 0, 0 ];
			if ( inDevice ) {
				// A quarter turn clockwise takes a point ( x, y ) of the drawing to ( height - y, x ).
				offset = turn ?
					[ ( device.height - device.screen[ 1 ] - device.screen[ 3 ] ) * unit, device.screen[ 0 ] * unit ] :
					[ device.screen[ 0 ] * unit, device.screen[ 1 ] * unit ];
			}
			var width = $stage.width();
			// The whole device in view: no taller than the window leaves room for.
			var height = Math.max( 300, window.innerHeight - 180 );
			var scale = Math.min( 1, width / outer[ 0 ], height / outer[ 1 ] );
			var left = Math.max( 0, ( width - outer[ 0 ] * scale ) / 2 );

			previewFrame.style.width = size[ 0 ] + 'px';
			previewFrame.style.height = size[ 1 ] + 'px';
			previewFrame.style.transform = 'scale(' + scale + ')';
			previewFrame.style.left = ( left + offset[ 0 ] * scale ) + 'px';
			previewFrame.style.top = ( offset[ 1 ] * scale ) + 'px';
			previewFrame.style.borderRadius = inDevice ? ( device.radius * unit ) + 'px' : '';
			$preview.toggleClass( 'wp-map-embed-preview--device', inDevice );
			$drawings.each( function () {
				this.hidden = this !== image || ! inDevice;
			} );
			if ( inDevice ) {
				// Turned: drawn upright, then turned round its top left corner,
				// which goes to the right of its box.
				image.style.left = ( left + ( turn ? outer[ 0 ] * scale : 0 ) ) + 'px';
				image.style.width = ( upright[ 0 ] * scale ) + 'px';
				image.style.height = ( upright[ 1 ] * scale ) + 'px';
				image.style.transform = turn ? 'rotate(90deg)' : '';
			}
			$stage.css( 'height', Math.ceil( outer[ 1 ] * scale ) + 'px' );
			// The button (Tablet, Mobile) and the note of the device turned.
			$turn.prop( 'disabled', ! canTurn() ).attr( 'aria-pressed', turn ? 'true' : 'false' );
			$preview.find( '.wp-map-embed-preview__turned' ).each( function () {
				this.hidden = ! turn || $( this ).data( 'device' ) !== currentDevice;
			} );
		}

		/*
		 * The turn itself, seen: the device as it was (its box on screen) turned
		 * a quarter round into its new place and size (the stage drawn turned
		 * back, then let go). Not with reduced motion.
		 */
		function shownBox() {
			var image = $drawings.filter( function () {
				return ! this.hidden;
			} )[ 0 ];
			return ( image || previewFrame ).getBoundingClientRect();
		}

		$turn.on( 'click', function () {
			if ( ! canTurn() ) {
				return;
			}
			var from = shownBox();
			turned[ currentDevice ] = ! turned[ currentDevice ];
			try {
				window.localStorage.setItem( TURN_KEY, JSON.stringify( turned ) );
			} catch ( e ) {}
			var stage = $stage[ 0 ];
			stage.style.transition = 'none';
			stage.style.transform = '';
			fitPreview();
			if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
				return;
			}
			var to = shownBox();
			var origin = stage.getBoundingClientRect();
			// Round the new box's middle: back the other way, its sides as they were, where it was.
			var middle = [ to.left + to.width / 2 - origin.left, to.top + to.height / 2 - origin.top ];
			var shift = [ from.left + from.width / 2 - ( to.left + to.width / 2 ), from.top + from.height / 2 - ( to.top + to.height / 2 ) ];
			var grow = to.width ? from.height / to.width : 1;
			$stage.addClass( 'is-turning' );
			stage.style.transformOrigin = middle[ 0 ] + 'px ' + middle[ 1 ] + 'px';
			stage.style.transform = 'translate(' + shift[ 0 ] + 'px, ' + shift[ 1 ] + 'px) rotate(' + ( turned[ currentDevice ] ? -90 : 90 ) + 'deg) scale(' + grow + ')';
			// Drawn so, then let go.
			void stage.offsetWidth;
			stage.style.transition = 'transform 0.5s ease';
			stage.style.transform = '';
			var finished = false;
			function done() {
				if ( finished ) {
					return;
				}
				finished = true;
				$stage.removeClass( 'is-turning' );
				stage.style.transition = '';
				stage.style.transformOrigin = '';
			}
			$stage.one( 'transitionend', done );
			setTimeout( done, 700 );
		} );

		$( window ).on( 'resize', fitPreview );
		if ( 'ResizeObserver' in window && $stage.length ) {
			new ResizeObserver( fitPreview ).observe( $stage[ 0 ] );
		}

		/*
		 * The stencil: the file chosen is read here, in the browser, and goes
		 * into the field as { name, svg }; the server keeps only its shapes'
		 * geometry (WP_Map_Embed_Svg_Shape) when the preview updates (at once)
		 * or the map is saved. Not uploaded to the media library.
		 */
		var $shape = $box.find( '.wp-map-embed-admin__shape' );

		function shapeState( summary, thumb, loaded ) {
			$shape.find( '.wp-map-embed-admin__shape-summary' ).text( summary );
			$shape.find( '.wp-map-embed-admin__shape-thumb' ).html( thumb || '' ).prop( 'hidden', ! thumb );
			$shape.find( '.wp-map-embed-admin__shape-remove' ).prop( 'hidden', ! loaded );
			$shape.find( '.wp-map-embed-admin__shape-choose > span' ).text( loaded ? l10n.shapeReplace : l10n.shapeUpload );
		}

		// The shapes the server read: the field keeps them, not the file.
		function shapeRead( shape ) {
			field( 'shape' ).val( shape.value || '' );
			shapeState( shape.failed ? l10n.shapeFailed : shape.summary, shape.thumb, !! shape.value );
			dependencies();
		}

		$shape.on( 'change', 'input[type="file"]', function () {
			var file = this.files && this.files[ 0 ];
			// The same file chosen again is a change too.
			this.value = '';
			if ( ! file ) {
				return;
			}
			if ( file.size > l10n.shapeMax ) {
				$shape.find( '.wp-map-embed-admin__shape-summary' ).text( l10n.shapeTooBig );
				return;
			}
			var reader = new FileReader();
			reader.onload = function () {
				var text = String( reader.result || '' );
				if ( ! /<svg[\s>]/i.test( text ) ) {
					$shape.find( '.wp-map-embed-admin__shape-summary' ).text( l10n.shapeNotSvg );
					return;
				}
				field( 'shape' ).val( JSON.stringify( { name: file.name, svg: text } ) );
				shapeState( l10n.shapeRead.replace( '%s', file.name ), '', true );
				changed();
				refresh();
			};
			reader.readAsText( file );
		} );

		$shape.on( 'click', '.wp-map-embed-admin__shape-remove', function () {
			field( 'shape' ).val( '' );
			shapeState( l10n.shapeNone, '', false );
			changed();
			refresh();
		} );

		// The cover back on in the frame, without the server: a deferred map
		// unloads Google's frame too.
		$preview.on( 'click', '.wp-map-embed-preview__reset', function () {
			var map = null;
			try {
				map = previewFrame.contentDocument.querySelector( '.wp-map-embed' );
			} catch ( e ) {}
			// The cover is in the block, or in its stencil.
			if ( ! map || ! map.classList.contains( 'is-active' ) || ! map.querySelector( '.wp-map-embed__cover' ) ) {
				return;
			}
			if ( map.hasAttribute( 'data-defer' ) ) {
				map.querySelector( '.wp-map-embed__frame' ).removeAttribute( 'src' );
			}
			map.classList.remove( 'is-active' );
			syncResetButton();
		} );

		// Placeholder image.
		var frame = null;
		var $image = $box.find( '.wp-map-embed-admin__image' );
		$image.on( 'click', '.wp-map-embed-admin__choose', function () {
			if ( ! frame ) {
				frame = wp.media( {
					title: l10n.frameTitle,
					button: { text: l10n.frameButton },
					library: { type: 'image' },
					multiple: false,
				} );
				frame.on( 'select', function () {
					var item  = frame.state().get( 'selection' ).first().toJSON();
					var sizes = item.sizes || {};
					var url   = ( sizes.medium || sizes.full || item ).url;
					$image.find( 'input' ).val( item.id ).trigger( 'change' );
					$image.find( '.wp-map-embed-admin__thumb' ).html( $( '<img alt="">' ).attr( 'src', url ) ).prop( 'hidden', false );
					$image.find( '.wp-map-embed-admin__remove' ).prop( 'hidden', false );
				} );
			}
			frame.open();
		} );
		$image.on( 'click', '.wp-map-embed-admin__remove', function () {
			$image.find( 'input' ).val( 0 ).trigger( 'change' );
			$image.find( '.wp-map-embed-admin__thumb' ).empty().prop( 'hidden', true );
			$( this ).prop( 'hidden', true );
		} );

		dependencies();
		syncResetButton();
		// The device kept from the last visit, and the preview drawn at once.
		setDevice( currentDevice );
		refresh();

		// As in WP Swiper: compare the title and saved settings with the
		// initial state, so reverting an edit removes the warning again.
		// Preview refreshes and device/page switches never mark data as saved.
		if ( $form.length ) {
			function formState() {
				return JSON.stringify( $form.serializeArray().filter( function ( item ) {
					return item.name === 'post_title' || /^wp_map_embed\[/.test( item.name );
				} ) );
			}
			var savedState = formState();
			var saving = false;
			var savingTimer;

			$form.on( 'submit.wp-map-embed', function () {
				saving = true;
				clearTimeout( savingTimer );
				// A prevented/failed submission must not disable the warning forever.
				savingTimer = setTimeout( function () {
					saving = false;
				}, 3000 );
			} );

			$( window ).on( 'beforeunload.wp-map-embed', function ( event ) {
				if ( saving || formState() === savedState ) {
					return;
				}
				// Modern browsers display their own confirmation text.
				var message = l10n.unsaved || 'The changes you made will be lost if you leave this page.';
				event.preventDefault();
				event.originalEvent.returnValue = message;
				return message;
			} );
		}
	} );

	// The shortcode fields (edit screen and the maps list): copy on click.
	$( document ).on( 'click', '.wp-map-embed-shortcode', function () {
		var input = this;
		input.select();
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( input.value ).then( function () {
				var $note = $( '<span class="wp-map-embed-copied"></span>' ).text( l10n.copied || '' );
				$( input ).after( $note );
				setTimeout( function () {
					$note.remove();
				}, 1500 );
			} );
		}
	} );
} )( jQuery );
