<?php
/**
 * Preview on the map edit screen, as in WP Swiper: a frame beside the
 * settings with the map as the form's values draw it, saved or not, in a
 * drawing of a monitor, tablet or phone. Nothing is saved: admin.js posts the
 * form into the frame when the screen opens and on "Update preview", and the
 * frame is a page with the map and the plugin's own files alone, so its CSS
 * takes the frame's width for the device's.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Map_Embed_Preview {

	const ACTION = 'wp_map_embed_frame';

	/**
	 * Frame sizes (px) per device, inside the plugin's breakpoints; the frame
	 * is drawn this size and scaled down to fit (admin.js). As WP Swiper's:
	 * each one the proportions of its drawing's screen (DEVICES).
	 */
	const SIZES = array(
		'desktop' => array( 1280, 721 ),
		'tablet'  => array( 820, 1101 ),
		'mobile'  => array( 390, 844 ),
	);

	/**
	 * The devices drawn round the frame (assets/images/preview/, WP Swiper's
	 * drawings): each one's size and its screen's box and corner radius, in
	 * the drawing's units (its viewBox).
	 */
	const DEVICES = array(
		'desktop' => array(
			'src'    => 'assets/images/preview/desktop.svg',
			'width'  => 1000.2,
			'height' => 793.1,
			'screen' => array( 22.89, 23.39, 954.91, 537.91 ),
			'radius' => 0,
		),
		'tablet'  => array(
			'src'    => 'assets/images/preview/tablet.svg',
			'width'  => 1379.63,
			'height' => 1789.88,
			'screen' => array( 78.02, 84.23, 1214.8, 1631.04 ),
			'radius' => 17.15,
		),
		'mobile'  => array(
			'src'    => 'assets/images/preview/phone.svg',
			'width'  => 213.04,
			'height' => 429.61,
			'screen' => array( 12.01, 10.13, 188.99, 409.35 ),
			'radius' => 24.05,
		),
	);

	public static function init() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'render_frame' ) );
	}

	/**
	 * The device whose settings a screen this wide takes, as frontend.css's
	 * breakpoints: below 768px Mobile, up to 1024px Tablet, wider Desktop.
	 *
	 * @param int $width Screen width (px).
	 * @return string desktop | tablet | mobile
	 */
	public static function device_at( $width ) {
		if ( $width <= 767 ) {
			return 'mobile';
		}
		return $width <= 1024 ? 'tablet' : 'desktop';
	}

	/**
	 * Tablet and Mobile turned sideways (the turn button): the note saying the
	 * screen then takes another device's settings (a phone 844px wide is a tablet).
	 *
	 * @return array device => note, for the devices that change.
	 */
	public static function turned_notes() {
		$names = self::device_names();
		$notes = array();
		foreach ( array( 'tablet', 'mobile' ) as $device ) {
			$width = self::SIZES[ $device ][1];
			$takes = self::device_at( $width );
			if ( $takes !== $device ) {
				$notes[ $device ] = sprintf(
					/* translators: 1: screen width in px, 2: a device's name (Tablet, Desktop). */
					__( 'Turned sideways the screen is %1$dpx wide: the map takes the %2$s settings, as it does on the site.', 'wp-map-embed' ),
					$width,
					$names[ $takes ]
				);
			}
		}
		return $notes;
	}

	/**
	 * @return array device => short name.
	 */
	public static function device_names() {
		return array(
			'desktop' => __( 'Desktop', 'wp-map-embed' ),
			'tablet'  => __( 'Tablet', 'wp-map-embed' ),
			'mobile'  => __( 'Mobile', 'wp-map-embed' ),
		);
	}

	/**
	 * DEVICES for admin.js: each one's size, screen and radius (no file).
	 *
	 * @return array
	 */
	public static function device_frames() {
		$frames = array();
		foreach ( self::DEVICES as $device => $drawing ) {
			unset( $drawing['src'] );
			$frames[ $device ] = $drawing;
		}
		return $frames;
	}

	/**
	 * The plugin's own SVG icon inline (currentColor), a dashicon without the file.
	 *
	 * @param string $file     File in assets/images/.
	 * @param string $class    Class of the svg.
	 * @param string $fallback Dashicon.
	 * @return string
	 */
	private static function svg_icon( $file, $class, $fallback ) {
		$path = WP_MAP_EMBED_DIR . 'assets/images/' . $file;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		$svg = is_readable( $path ) ? trim( preg_replace( '/<!--.*?-->/s', '', (string) file_get_contents( $path ) ) ) : '';
		return '' !== $svg
			? preg_replace( '/^<svg\b/', '<svg class="' . esc_attr( $class ) . '" aria-hidden="true" focusable="false"', $svg )
			: '<span class="dashicons ' . esc_attr( $fallback ) . '" aria-hidden="true"></span>';
	}

	/**
	 * The preview column: turn button, "Show the cover again", device
	 * switcher, "Update preview" and the frame in its device.
	 *
	 * @param WP_Post $post Map.
	 */
	public static function render_panel( $post ) {
		printf(
			'<div class="wp-map-embed-admin__preview wp-map-embed-preview" data-url="%1$s" data-action="%2$s" data-post="%3$d" data-nonce="%4$s" data-sizes="%5$s" data-devices="%6$s">',
			esc_url( admin_url( 'admin-ajax.php' ) ),
			esc_attr( self::ACTION ),
			(int) $post->ID,
			esc_attr( wp_create_nonce( self::ACTION . '_' . $post->ID ) ),
			esc_attr( wp_json_encode( self::SIZES ) ),
			esc_attr( wp_json_encode( self::device_frames() ) )
		);

		echo '<div class="wp-map-embed-preview__bar">';
		echo '<span class="wp-map-embed-preview__title">' . esc_html__( 'Preview', 'wp-map-embed' ) . '</span>';
		// Always here (the bar stays as it is), greyed for Desktop (admin.js).
		printf(
			'<button type="button" class="button wp-map-embed-preview__rotate" aria-pressed="false" title="%1$s" aria-label="%1$s" disabled>%2$s</button>',
			esc_attr__( 'Turn the tablet or phone sideways', 'wp-map-embed' ),
			self::svg_icon( 'rotate.svg', 'wp-map-embed-preview__rotate-icon', 'dashicons-image-rotate' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG file.
		);
		echo '<button type="button" class="button wp-map-embed-preview__reset" disabled title="' . esc_attr__( 'Show the cover again', 'wp-map-embed' ) . '"><span class="dashicons dashicons-hidden" aria-hidden="true"></span>' . esc_html__( 'Show the cover again', 'wp-map-embed' ) . '</button>';

		// The same device as the settings' per-device fields (admin.js).
		echo '<div class="wp-map-embed-preview__devices" role="group" aria-label="' . esc_attr__( 'Device', 'wp-map-embed' ) . '">';
		$names = self::device_names();
		foreach ( WP_Map_Embed_Admin::devices() as $device => $meta ) {
			printf(
				'<button type="button" class="wp-map-embed-preview__device-btn" data-device="%1$s" title="%2$s" aria-pressed="%3$s"><span class="dashicons %4$s" aria-hidden="true"></span>%5$s</button>',
				esc_attr( $device ),
				esc_attr( $meta[0] ),
				'desktop' === $device ? 'true' : 'false',
				esc_attr( $meta[1] ),
				esc_html( $names[ $device ] )
			);
		}
		echo '</div>';

		echo '<button type="button" class="button wp-map-embed-preview__update"><span class="dashicons dashicons-update" aria-hidden="true"></span>' . esc_html__( 'Update', 'wp-map-embed' ) . '</button>';
		echo '</div>';

		// A device turned sideways whose screen then takes another device's settings (admin.js shows it).
		foreach ( self::turned_notes() as $device => $note ) {
			printf(
				'<p class="description wp-map-embed-preview__hint wp-map-embed-preview__turned" data-device="%1$s" hidden><span class="dashicons dashicons-info-outline" aria-hidden="true"></span>%2$s</p>',
				esc_attr( $device ),
				esc_html( $note )
			);
		}

		echo '<div class="wp-map-embed-preview__stage">';
		printf(
			'<iframe class="wp-map-embed-preview__frame" name="wp-map-embed-preview-%1$d" title="%2$s" src="about:blank"></iframe>',
			(int) $post->ID,
			esc_attr__( 'Map preview', 'wp-map-embed' )
		);
		// The frame in the device's screen (admin.js fitPreview()); over it, it takes no clicks.
		foreach ( self::DEVICES as $device => $drawing ) {
			printf(
				'<img class="wp-map-embed-preview__device" data-device="%1$s" src="%2$s" alt="" hidden>',
				esc_attr( $device ),
				esc_url( WP_MAP_EMBED_URL . $drawing['src'] . '?ver=' . wp_map_embed_asset_version( $drawing['src'] ) )
			);
		}
		echo '</div>';

		echo '<p class="description">' . esc_html__( '“Parent block” shows with the Height: the preview has no column to fill. Click the map to try the cover.', 'wp-map-embed' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Whether the current request may draw this map's frame.
	 *
	 * @param int    $post_id Map ID.
	 * @param string $nonce   The panel's nonce.
	 * @return bool
	 */
	public static function allowed( $post_id, $nonce ) {
		$post = $post_id ? get_post( $post_id ) : null;
		return $post && WP_Map_Embed_Settings::POST_TYPE === $post->post_type
			&& wp_verify_nonce( $nonce, self::ACTION . '_' . $post_id )
			&& current_user_can( 'edit_post', $post_id );
	}

	/**
	 * The frame's page: the map drawn from the posted form, with the plugin's
	 * files and no theme; what the settings column shows from it (an address
	 * that is no map, the stencil read from its file) in
	 * window.wpMapEmbedPreview, which admin.js reads when the frame loads.
	 *
	 * @param array  $raw   The form's wp_map_embed[] (unslashed).
	 * @param string $title The map's title.
	 * @return string HTML document.
	 */
	public static function frame_html( $raw, $title ) {
		$settings = WP_Map_Embed_Settings::sanitize( $raw );
		$markup   = WP_Map_Embed_Render::markup( $settings, $title );
		$shape    = WP_Map_Embed_Svg_Shape::decode( $settings['shape'] );
		$info     = array(
			// Something typed that is no map: a link that cannot be embedded.
			'invalid' => '' !== $settings['source'] && '' === $markup,
			// The stencil as read: the field keeps the shapes, not the file.
			'shape'   => array(
				'value'   => $settings['shape'],
				'summary' => WP_Map_Embed_Svg_Shape::summary( $shape ),
				'thumb'   => $shape ? WP_Map_Embed_Admin::shape_thumb( $shape ) : '',
				// A file was sent, and nothing came of it.
				'failed'  => ! $shape && isset( $raw['shape'] ) && is_scalar( $raw['shape'] ) && '' !== trim( (string) $raw['shape'] ),
			),
		);
		if ( '' === $markup ) {
			$markup = '<p class="wp-map-embed-preview-empty">' . esc_html__( 'Type an address or paste Google’s embed code to see the map.', 'wp-map-embed' ) . '</p>';
		}

		// The site's files of the map, registered as on a page.
		WP_Map_Embed_Render::register_assets();

		ob_start();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $title ); ?></title>
	<?php wp_print_styles( array( 'wp-map-embed' ) ); ?>
	<style>
		html, body { margin: 0; background: #fff; }
		body { font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1d2327; }
		/* Tablet and Mobile (in the device): its scroll bar thin, see-through, no track. */
		@media (max-width: 1024px) {
			html::-webkit-scrollbar { width: 4px; height: 4px; }
			html::-webkit-scrollbar-track { background: transparent; }
			html::-webkit-scrollbar-thumb { border-radius: 4px; background: rgba(0, 0, 0, 0.35); }
		}
		.wp-map-embed-preview-page { box-sizing: border-box; padding: 24px; }
		@media (max-width: 767px) { .wp-map-embed-preview-page { padding: 12px; } }
		/* No map yet: the words in the middle of the screen. */
		.wp-map-embed-preview-empty { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; margin: 0; padding: 24px; color: #646970; text-align: center; }
	</style>
</head>
<body>
	<div class="wp-map-embed-preview-page">
		<?php echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built. ?>
	</div>
	<script>window.wpMapEmbedPreview = <?php echo wp_json_encode( $info, JSON_HEX_TAG | JSON_HEX_AMP ); ?>;</script>
	<?php wp_print_scripts( array( 'wp-map-embed' ) ); ?>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The frame (admin-ajax, posted by admin.js into the iframe).
	 */
	public static function render_frame() {
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$nonce   = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! self::allowed( $post_id, $nonce ) ) {
			wp_die( esc_html__( 'The preview is not available any more: reload the page.', 'wp-map-embed' ), '', array( 'response' => 403 ) );
		}
		$raw   = isset( $_POST['wp_map_embed'] ) ? (array) wp_unslash( $_POST['wp_map_embed'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field.
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo self::frame_html( $raw, '' !== $title ? $title : get_the_title( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- an HTML document built of escaped parts.
		exit;
	}
}
