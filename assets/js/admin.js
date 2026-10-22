/**
 * WP Map Embed edit screen: the preview follows the settings, settings that
 * do not apply are greyed out (keeping their values), the placeholder image
 * picker and the shortcode's copy.
 */
( function ( $ ) {
	'use strict';

	var l10n = window.wpMapEmbedAdmin || {};

	$( function () {
		var $box = $( '.wp-map-embed-admin' );
		if ( ! $box.length ) {
			return;
		}
		var $form  = $box.closest( 'form' );
		var $stage = $box.find( '.wp-map-embed-admin__stage' );
		var request = null;

		function field( key ) {
			return $box.find( '[name="wp_map_embed[' + key + ']"]' );
		}

		function checked( key ) {
			return field( key ).is( ':checked' );
		}

		// Greyed out, not disabled: a disabled field is not sent, and its value would be lost.
		function setOff( key, off ) {
			var $row = $box.find( '.wp-map-embed-admin__row[data-field="' + key + '"]' );
			$row.toggleClass( 'is-off', off );
			if ( off ) {
				$row.attr( 'inert', '' );
			} else {
				$row.removeAttr( 'inert' );
			}
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
		}

		// The settings as the form sends them.
		function data() {
			var out = $form.find( '[name^="wp_map_embed["]' ).serializeArray();
			out.push( { name: 'action', value: 'wp_map_embed_preview' } );
			out.push( { name: 'nonce', value: l10n.nonce } );
			out.push( { name: 'post_id', value: $( '#post_ID' ).val() } );
			out.push( { name: 'title', value: $( '#title' ).val() || '' } );
			return out;
		}

		/**
		 * The new preview in place of the old one; the same map frame is kept
		 * (Google's map is not loaded again for a colour or a size).
		 *
		 * @param {string} html Markup of WP_Map_Embed_Render::markup().
		 */
		function show( html ) {
			var $old = $stage.children( '.wp-map-embed' );
			var $new = $( $.parseHTML( html || '' ) );
			var oldFrame = $old.find( '.wp-map-embed__frame' ).get( 0 );
			var newFrame = $new.find( '.wp-map-embed__frame' ).get( 0 );

			if ( oldFrame && newFrame && oldFrame.outerHTML === newFrame.outerHTML ) {
				$old.attr( 'class', $new.attr( 'class' ) ).attr( 'style', $new.attr( 'style' ) );
				$old.children( '.wp-map-embed__cover' ).remove();
				$old.append( $new.children( '.wp-map-embed__cover' ) );
			} else {
				$stage.empty().append( $new );
			}
			$box.find( '.wp-map-embed-admin__empty' ).prop( 'hidden', !! html );
		}

		// Only on "Update preview": no request to the server for every change.
		function refresh() {
			var $button = $box.find( '.wp-map-embed-admin__update' );
			if ( request ) {
				request.abort();
			}
			$button.prop( 'disabled', true );
			request = $.post( l10n.ajaxUrl, data() )
				.done( function ( response ) {
					if ( ! response || ! response.success ) {
						return;
					}
					show( response.data.html );
					$box.find( '.wp-map-embed-admin__error' ).prop( 'hidden', ! response.data.invalid );
					$button.removeClass( 'is-stale' );
				} )
				.always( function () {
					$button.prop( 'disabled', false );
				} );
		}

		// The preview no longer shows these settings: the button says so.
		function changed() {
			dependencies();
			$box.find( '.wp-map-embed-admin__update' ).addClass( 'is-stale' );
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

		$box
			.on( 'click', '.wp-map-embed-admin__device', function () {
				var $btn   = $( this );
				var $row   = $btn.closest( '.wp-map-embed-admin__row' );
				var device = $btn.data( 'device' );

				$row.find( '.wp-map-embed-admin__device' ).removeClass( 'is-active' ).attr( 'aria-pressed', 'false' );
				$btn.addClass( 'is-active' ).attr( 'aria-pressed', 'true' );
				$row.find( '.wp-map-embed-admin__device-input' ).prop( 'hidden', true )
					.filter( '[data-device="' + device + '"]' ).prop( 'hidden', false ).trigger( 'focus' );
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

		$box.on( 'click', '.wp-map-embed-admin__update', refresh );

		// The cover back on, without the server: a deferred map unloads its frame too.
		$box.on( 'click', '.wp-map-embed-admin__reset', function () {
			var $map = $stage.children( '.wp-map-embed' );
			if ( ! $map.children( '.wp-map-embed__cover' ).length ) {
				return;
			}
			if ( $map.is( '[data-defer]' ) ) {
				$map.find( '.wp-map-embed__frame' ).removeAttr( 'src' );
			}
			$map.removeClass( 'is-active' );
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
