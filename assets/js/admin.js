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

		function field( key ) {
			return $box.find( '[name="wp_map_embed[' + key + ']"]' );
		}

		function checked( key ) {
			return field( key ).is( ':checked' );
		}

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
			var activate = checked( 'activate' );
			var icon     = $box.find( '[name="wp_map_embed[icon]"]:checked' ).val();
			var mode     = $box.find( '[name="wp_map_embed[size_mode]"]:checked' ).val();

			setOff( 'ratio', 'ratio' !== mode );
			setOff( 'height', 'ratio' === mode );
			$box.find( '[data-field="size_mode"] [data-mode="fill"]' ).prop( 'hidden', 'fill' !== mode );

			$box.find( '.wp-map-embed-admin__row[data-depends="activate"]' ).each( function () {
				setOff( $( this ).data( 'field' ), ! activate );
			} );
			setOff( 'placeholder', ! activate || ! checked( 'defer' ) );
			setOff( 'icon_color', ! activate || 'none' === icon );
			setOff( 'icon_size', ! activate || 'none' === icon );
			setOff( 'zoom', ! isAddress() );

			// With a stencil its shapes are the outline: no corner radius.
			var shape = '' !== ( field( 'shape' ).val() || '' );
			setOff( 'shape_position', ! shape );
			setOff( 'radius', shape );
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
		$( '#title' ).on( 'change', changed );

		// A slider and its number field move together.
		$box.on( 'input change', '.wp-map-embed-admin__range input', function () {
			$( this ).siblings( 'input' ).val( this.value );
		} );

		$box.find( '.wp-map-embed-admin__color' ).wpColorPicker( {
			change: function ( event, ui ) {
				// The picker fires before the field holds the new value.
				$( event.target ).val( ui.color.toString() );
				changed();
			},
			clear: changed,
		} );

		/*
		 * Per-device values (as in WP Swiper): one device's field at a time, by
		 * the switcher at the label. An empty tablet / mobile field shows the
		 * value it inherits; a dot on the icon marks a value of the device's own.
		 */
		function syncResponsive( $row ) {
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

		$box
			.on( 'click', '.wp-map-embed-admin__device', function () {
				setDevice( $( this ).data( 'device' ) );
				$( this ).closest( '.wp-map-embed-admin__row' ).find( '.wp-map-embed-admin__device-input:not([hidden])' ).trigger( 'focus' );
			} )
			.on( 'click', '.wp-map-embed-preview__device-btn', function () {
				setDevice( $( this ).data( 'device' ) );
			} )
			.on( 'input change', '.wp-map-embed-admin__device-input', function () {
				syncResponsive( $( this ).closest( '.wp-map-embed-admin__row' ) );
			} )
			// The height's unit: its limits for every device's field.
			.on( 'change', '[name="wp_map_embed[height_unit]"]', function () {
				var $option = $( this ).find( ':selected' );
				$( this ).siblings( 'input' ).attr( {
					min: $option.data( 'min' ),
					max: $option.data( 'max' ),
				} );
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
			if ( ! map || ! map.querySelector( '.wp-map-embed__cover' ) ) {
				return;
			}
			if ( map.hasAttribute( 'data-defer' ) ) {
				map.querySelector( '.wp-map-embed__frame' ).removeAttribute( 'src' );
			}
			map.classList.remove( 'is-active' );
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
		// The device kept from the last visit, and the preview drawn at once.
		setDevice( currentDevice );
		refresh();
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
