/**
 * WP Maps Embed: a click on the cover makes the map usable (in colour,
 * dragged and zoomed), and loads a deferred map's frame first.
 */
( function () {
	'use strict';

	/**
	 * @param {HTMLElement} map The .wp-map-embed block.
	 */
	function activate( map ) {
		var frame   = map.querySelector( '.wp-map-embed__frame' );
		var focused = map.contains( document.activeElement );
		if ( frame && ! frame.getAttribute( 'src' ) && frame.getAttribute( 'data-src' ) ) {
			frame.setAttribute( 'src', frame.getAttribute( 'data-src' ) );
		}
		map.classList.add( 'is-active' );
		// The cover was focused (keyboard): carry on in the map.
		if ( frame && focused ) {
			frame.focus();
		}
	}

	// Delegated: maps added later (the edit screen's preview, AJAX content) work too.
	document.addEventListener( 'click', function ( event ) {
		var cover = event.target.closest && event.target.closest( '.wp-map-embed__cover' );
		if ( cover ) {
			event.preventDefault();
			activate( cover.parentNode );
		}
	} );

	/* ------------------------------------------------------------------ *
	 * Fill the parent block
	 *
	 * height: 100% cannot reach through the page builder's wrappers (column >
	 * inner > wrapper > element), so the free height is measured: with the
	 * map collapsed, the column keeps the height its row gives it (the
	 * neighbour column, a full-height row...). The map is then made as tall
	 * as that, less whatever else the column holds (its padding, a heading).
	 * ------------------------------------------------------------------ */

	// Below this much free height (a column alone in its row, columns stacked
	// on phones) the map keeps its Height setting.
	var FILL_MIN = 120;

	function fillBox( map ) {
		return map.closest( '.wpb_column, .vc_column_container' ) || map.parentElement;
	}

	function fillHeight( map ) {
		var box = fillBox( map );

		map.style.height = '0px';
		var collapsed = box.getBoundingClientRect().height;

		map.style.height = collapsed + 'px';
		var free = Math.floor( collapsed - ( box.getBoundingClientRect().height - collapsed ) );
		map.style.height = free >= FILL_MIN ? free + 'px' : '';
		return map.offsetHeight;
	}

	function fill( map ) {
		var queued = false;

		function refit() {
			if ( queued ) {
				return;
			}
			queued = true;
			requestAnimationFrame( function () {
				queued = false;
				fillHeight( map );
			} );
		}

		fillHeight( map );
		window.addEventListener( 'resize', refit );
		window.addEventListener( 'load', refit );

		// Neighbour columns change height as their images load or text wraps.
		// Their content is watched, not the row: once the map is the tallest
		// thing in the row, the row keeps its height and a shrinking neighbour
		// would go unnoticed. Collapsing and restoring the map in one go leaves
		// the watched sizes unchanged, so this does not feed itself.
		if ( 'ResizeObserver' in window ) {
			var box = fillBox( map );
			var row = box.parentElement || box;
			var observer = new ResizeObserver( refit );

			observer.observe( row );
			[].forEach.call( row.querySelectorAll( '.wpb_wrapper, .vc_column-inner > *' ), function ( node ) {
				if ( ! node.contains( map ) && ! map.contains( node ) ) {
					observer.observe( node );
				}
			} );
			// Outside the page builder: the other content next to the map.
			[].forEach.call( box.children, function ( node ) {
				if ( ! node.contains( map ) ) {
					observer.observe( node );
				}
			} );
		}
	}

	function init() {
		[].forEach.call( document.querySelectorAll( '.wp-map-embed--fill' ), function ( map ) {
			// The edit screen's preview has no block to fill.
			if ( ! map.closest( '.wp-map-embed-admin' ) && ! map.hasAttribute( 'data-filled' ) ) {
				map.setAttribute( 'data-filled', '' );
				fill( map );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	window.wpMapEmbed = { activate: activate, init: init };
} )();
