=== WP Maps Embed ===
Contributors: andriiboiko
Tags: google maps, map, embed, shortcode, wpbakery
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Google Maps on a page without copying iframe code, with a "click to activate" cover.

== Description ==

* Maps are kept under WP Maps Embed, each with its own settings, and placed with a shortcode or the "WP Maps Embed" element in WPBakery: change a map once, every page with it follows.
* A map from an address (no API key needed) or from Google's "Share > Embed a map" code — the whole iframe or just its link. A route link to copy for any button: it opens Google Maps with directions to the place.
* Map or satellite view, and the language of the map's names and labels (the site's or another).
* Size by height or by proportions, separately for desktop, tablet and mobile (an empty value inherits the larger screen's):
  * Height in px, %, vh, vw or dvh, or Auto: the map fills its parent block's free height (in a WPBakery row, as tall as the column beside it), its saved height where the parent has none of its own.
  * Proportions from 21:9 to 9:16, or Custom: width x height (4x3, 2.35x1).
* Map scale: the size of everything on Google's map (names, roads, its buttons), per device.
* Appearance: rounded corners, a border (style, colour, width per device) and a shadow. An SVG stencil: the map shows only through the shapes of an SVG file, placed in the block per device; the border then follows its paths and the shadow its contour.
* "Click to activate": a cover over the map until it is clicked, so the page scrolls past it instead of zooming the map. Each part has its own switch: black and white until then, the cover's colour, a hand or pin icon (in the theme's primary colour or your own, its size per device) and a text under it. Works with the keyboard too.
* "Load the map only after a click": Google's frame is not requested until the visitor opens the map — a faster page, and nothing sent to Google before that. A placeholder image (a screenshot of the map, for instance) can stand in for it.
* Colour fields with the theme's colours, HEX / RGB / HSL and transparency (Pickr 1.9.1, MIT).
* Preview beside the settings in a monitor, tablet or phone (turned sideways too), refreshed with its "Update" button.

== Installation ==

1. Download `wp-map-embed.zip` from the latest release on https://github.com/Andrusi4ka/WP-Maps-Embed/releases.
2. In WordPress go to Plugins > Add New > Upload Plugin and upload the zip, then activate it.
3. Go to WP Maps Embed > Add map, type the address or paste Google's embed code, choose the settings and publish.
4. Paste the shortcode shown in the map's "Shortcode" box into a page, or add the "WP Maps Embed" element in WPBakery.

== Frequently Asked Questions ==

= Which Google Maps link works? =

The embed one: in Google Maps open the place, then Share > Embed a map > Copy HTML, and paste it (the iframe or only its src link). A "Send a link" one (maps.app.goo.gl) cannot be shown in a page; type the address instead.

= What is removed when I delete the plugin? =

Deleting it under Plugins removes all maps with their settings. Deactivating or updating it keeps them.

== Changelog ==

= 1.1.0 =
* Height units per device: px, %, vh, vw, dvh, and Auto, which fills the parent block (it replaces the "Fill the parent block" size mode; maps using it switch to Auto by themselves).
* Custom proportions (width x height) per device.
* Map scale per device.
* Appearance: rounded corners on or off, a border (style, colour, width per device) and a shadow.
* SVG stencil: the map shows only through the shapes of an SVG file, placed per device; the border and shadow follow its contour.
* "Click to activate": each part has its own switch (black and white, cover colour, icon, text) instead of one switch for the whole cover. Maps saved before look as they did.
* Route link: copy a Google Maps directions link to the place for any button.
* Colour fields with the theme's colours and transparency (Pickr).
* Preview in a monitor, tablet or phone, turned sideways too; the settings in pages with a menu; a warning before leaving with unsaved changes.

= 1.0.1 =
* Renamed to WP Maps Embed: the plugin, its admin menu item and the WPBakery element. Updates now come from https://github.com/Andrusi4ka/WP-Maps-Embed (the repository's new name).

= 1.0.0 =
* First release: maps under WP Maps Embed, from an address or Google's embed code; map or satellite, language; height or proportions per device, or filling the parent block; corner radius per device; "click to activate" cover (black and white until clicked, icon, text, colours); loading the map only after a click, with a placeholder image; preview; shortcode and WPBakery element.
