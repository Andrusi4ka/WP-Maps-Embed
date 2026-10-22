=== WP Maps Embed ===
Contributors: andriiboiko
Tags: google maps, map, embed, shortcode, wpbakery
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Google Maps on a page without copying iframe code, with a "click to activate" cover.

== Description ==

* Maps are kept under WP Maps Embed, each with its own settings, and placed with a shortcode or the "WP Maps Embed" element in WPBakery: change a map once, every page with it follows.
* A map from an address (no API key needed) or from Google's "Share > Embed a map" code — the whole iframe or just its link.
* Map or satellite view, and the language of the map's names and labels (the site's or another).
* Size by height per device (desktop, tablet, mobile; in px or vh), by proportions per device (21:9 to 9:16), or filling the parent block: in a WPBakery row the map is as tall as the column beside it. Corner radius per device.
* "Click to activate": a cover over the map until it is clicked, so the page scrolls past it instead of zooming the map. Black and white until then if you like, with a hand or pin icon (in the theme's primary colour or your own, its size per device), a text under it, and the cover's own colour and opacity. Works with the keyboard too.
* "Load the map only after a click": Google's frame is not requested until the visitor opens the map — a faster page, and nothing sent to Google before that. A placeholder image (a screenshot of the map, for instance) can stand in for it.
* Preview beside the settings, refreshed with its "Update preview" button.

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

= 1.0.1 =
* Renamed to WP Maps Embed: the plugin, its admin menu item and the WPBakery element. Updates now come from https://github.com/Andrusi4ka/WP-Maps-Embed (the repository's new name).

= 1.0.0 =
* First release: maps under WP Maps Embed, from an address or Google's embed code; map or satellite, language; height or proportions per device, or filling the parent block; corner radius per device; "click to activate" cover (black and white until clicked, icon, text, colours); loading the map only after a click, with a placeholder image; preview; shortcode and WPBakery element.
