# WP Maps Embed

Google Maps on a WordPress page without copying iframe code: create a map from an address or Google's embed code, choose its size and the "click to activate" cover, and insert it with a shortcode or the WPBakery element.

- **Maps kept in one place:** each map has its own settings and is placed with a shortcode or the WPBakery element; change a map once, every page with it follows.
- **Source:** an address (no API key needed) or Google's *Share > Embed a map* code, the whole iframe or just its link. Only Google's embedded maps are shown in the frame.
- **Map type and language:** map or satellite view; the names on the map in the site's language or another.
- **Size:** height per device (px or vh), proportions per device, or filling the parent block (in a WPBakery row the map is as tall as the column beside it). Corner radius per device.
- **Click to activate:** a cover over the map until it is clicked, so the page scrolls past it instead of zooming the map. Black and white until then, a hand or pin icon (its colour, its size per device), a text, the cover's colour and opacity. Works with the keyboard.
- **Load only after a click:** Google's frame is not requested until the visitor opens the map, with a placeholder image if you like.
- **Preview:** beside the settings, refreshed with its *Update preview* button.

Requires WordPress 6.0+ and PHP 7.4+.

## Install

Download `wp-map-embed.zip` from the [latest release](https://github.com/Andrusi4ka/WP-Maps-Embed/releases/latest) and upload it under **Plugins > Add New > Upload Plugin**. Newer releases then show up as regular plugin updates.

Use it with `[wp_map_embed id="123"]` (the id is shown on the map's edit screen) or the **WP Maps Embed** element in WPBakery.

## Releasing a new version

1. Raise the version in `wp-map-embed.php` (the `Version:` header and `WP_MAP_EMBED_VERSION`) and in `readme.txt` (`Stable tag:`), and add a changelog entry to `readme.txt`.
2. Commit, then tag and push:
   ```
   git tag v1.1.0
   git push origin main --tags
   ```
3. The **Release** workflow (`.github/workflows/release.yml`) runs the tests first; once they pass it builds `wp-map-embed.zip` and publishes a GitHub release for the tag. If they fail there is no release: fix, commit, then move the tag (`git tag -f v1.1.0`, `git push -f origin v1.1.0`).
4. Sites with the plugin see the update under **Plugins** (WordPress checks every 12 hours; **Dashboard > Updates > Check again** checks at once).

The tag must match the `Version:` header, without the `v`.

## Tests

PHPUnit tests with the WordPress test suite, run by GitHub Actions on every push and pull request and before every release. How to run them locally: [tests/README.md](tests/README.md).

## Structure

| Path | What it holds |
|---|---|
| `wp-map-embed.php` | Plugin header, post type, menu icon, update checker |
| `includes/class-wp-map-embed-settings.php` | Settings, sanitizing, the embed URL from an address or Google's code |
| `includes/class-wp-map-embed-admin.php` | Map edit screen, saving, the preview's request |
| `includes/class-wp-map-embed-render.php` | Shortcode and markup |
| `includes/class-wp-map-embed-wpbakery.php` | WPBakery element |
| `assets/js/admin.js`, `assets/css/admin.css` | Edit screen behaviour and styles |
| `assets/js/frontend.js`, `assets/css/frontend.css` | The cover, deferred loading, filling the parent block |
| `vendor/plugin-update-checker` | Plugin Update Checker 5 (MIT) |
| `tests/`, `composer.json`, `phpunit.xml.dist` | Tests (not in `wp-map-embed.zip`) |
| `.github/workflows` | Tests on every push; the release, once they pass |

## License

GPL-2.0-or-later. Plugin Update Checker is MIT licensed.
