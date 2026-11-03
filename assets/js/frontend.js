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
			// The block, whether the cover is in it or in its stencil.
			activate( cover.closest( '.wp-map-embed' ) );
		}
	} );

	/* ------------------------------------------------------------------ *
	 * Auto height: fill the parent block
	 *
	 * height: 100% cannot reach through the page builder's wrappers (column >
	 * inner > wrapper > element), so the free height is measured: with the
	 * map collapsed, the column keeps the height its row gives it (the
	 * neighbour column, a full-height row...). The map is then made as tall
	 * as that, less whatever else the column holds (its padding, a heading).
	 * Only on the devices set to Auto (--wme-auto-active, frontend.css).
	 * ------------------------------------------------------------------ */

	// Below this much free height (a column alone in its row, columns stacked
	// on phones) the map keeps its saved height: the parent has none of its own.
	var FILL_MIN = 120;

	function fillBox( map ) {
		return map.closest( '.wpb_column, .vc_column_container' ) || map.parentElement;
	}

	function autoHeight( map ) {
		if ( getComputedStyle( map ).getPropertyValue( '--wme-auto-active' ).trim() !== '1' ) {
			map.style.height = '';
			return;
		}
		var box = fillBox( map );
		map.style.height = '0px';
		var boxStyle = getComputedStyle( box );
		var available = box.clientHeight - ( parseFloat( boxStyle.paddingTop ) || 0 ) - ( parseFloat( boxStyle.paddingBottom ) || 0 );
		var branch = map;
		while ( branch && branch !== box ) {
			var style = getComputedStyle( branch );
			available -= ( parseFloat( style.marginTop ) || 0 ) + ( parseFloat( style.marginBottom ) || 0 );
			var parent = branch.parentElement;
			if ( ! parent ) { break; }
			var parentStyle = getComputedStyle( parent );
			var horizontal = parentStyle.display.indexOf( 'flex' ) !== -1 && parentStyle.flexDirection.indexOf( 'row' ) === 0;
			var grid = parentStyle.display.indexOf( 'grid' ) !== -1;
			var siblings = 0;
			if ( ! horizontal && ! grid ) {
				[].forEach.call( parent.children, function ( child ) {
					if ( child === branch ) { return; }
					var css = getComputedStyle( child );
					if ( css.display === 'none' || css.position === 'absolute' || css.position === 'fixed' ) { return; }
					available -= child.getBoundingClientRect().height + ( parseFloat( css.marginTop ) || 0 ) + ( parseFloat( css.marginBottom ) || 0 );
					siblings++;
				} );
				if ( parentStyle.display.indexOf( 'flex' ) !== -1 ) {
					available -= siblings * ( parseFloat( parentStyle.rowGap ) || 0 );
				}
			}
			if ( parent !== box ) {
				available -= ( parseFloat( parentStyle.paddingTop ) || 0 ) + ( parseFloat( parentStyle.paddingBottom ) || 0 ) +
					( parseFloat( parentStyle.borderTopWidth ) || 0 ) + ( parseFloat( parentStyle.borderBottomWidth ) || 0 );
			}
			branch = parent;
		}
		// Without a parent height of its own, the saved height.
		map.style.height = available >= FILL_MIN ? Math.floor( available ) + 'px' : '';
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
				autoHeight( map );
			} );
		}

		autoHeight( map );
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
			observer.observe( box );
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
		[].forEach.call( document.querySelectorAll( '.wp-map-embed--auto' ), function ( map ) {
			// In the edit screen's preview its page is the parent (as tall as the screen).
			if ( ! map.hasAttribute( 'data-filled' ) ) {
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
