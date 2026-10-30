<?php
/**
 * The edit screen: its fields, saving, the preview's request and the list's column.
 */

class Test_Admin extends WP_Map_Embed_TestCase {

	public function tear_down() {
		unset( $_POST['wp_map_embed'], $_POST[ WP_Map_Embed_Admin::NONCE . '_nonce' ], $_POST['nonce'], $_POST['post_id'], $_POST['title'], $_REQUEST['nonce'] );
		parent::tear_down();
	}

	private function as_role( $role ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	private function settings_box( $id ) {
		ob_start();
		WP_Map_Embed_Admin::render_settings_box( get_post( $id ) );
		return ob_get_clean();
	}

	public function test_post_type_and_menu() {
		$type = get_post_type_object( WP_Map_Embed_Settings::POST_TYPE );
		$this->assertNotNull( $type );
		$this->assertFalse( $type->public );
		$this->assertTrue( $type->show_ui );
		$this->assertSame( 'WP Maps Embed', $type->labels->menu_name );
		$this->assertStringEndsWith( 'assets/images/menu-icon.png', $type->menu_icon );
	}

	public function test_settings_box_has_every_field_once() {
		$this->as_role( 'editor' );
		$html = $this->settings_box( $this->map() );

		$keys = array_diff( array_keys( WP_Map_Embed_Settings::defaults() ), array() );
		foreach ( $keys as $key ) {
			$this->assertGreaterThanOrEqual( 1, substr_count( $html, 'name="wp_map_embed[' . $key . ']"' ), $key );
		}
		// One input per device of the per-device values.
		foreach ( array( 'height', 'ratio', 'radius', 'icon_size' ) as $key ) {
			$this->assertStringContainsString( 'data-field="' . $key . '"', $html );
			$this->assertSame( 1, substr_count( $html, 'name="wp_map_embed[' . $key . '_tablet]"' ), $key );
		}
		$this->assertStringContainsString( 'name="' . WP_Map_Embed_Admin::NONCE . '_nonce"', $html );
		$this->assertStringContainsString( 'wp-map-embed-preview__update', $html );
	}

	public function test_settings_box_device_switcher() {
		$this->as_role( 'editor' );
		$html = $this->settings_box( $this->map() );

		$this->assertSame( 4, substr_count( $html, 'class="wp-map-embed-admin__devices"' ) );
		// The preview's own, with names.
		$this->assertSame( 3, substr_count( $html, 'class="wp-map-embed-preview__device-btn"' ) );
		// Desktop shown first, the others hidden until their icon is clicked.
		$this->assertStringContainsString( 'data-device="desktop" name="wp_map_embed[radius]"', $html );
		$this->assertMatchesRegularExpression( '/data-device="tablet" name="wp_map_embed\[radius_tablet\]"[^>]* hidden>/', $html );
		$this->assertStringContainsString( '<option value="">Inherit</option>', $html );
	}

	public function test_settings_box_shows_the_saved_maps_error() {
		$this->as_role( 'editor' );
		$ok = $this->settings_box( $this->map( $this->settings() ) );
		$this->assertStringContainsString( '<p class="wp-map-embed-admin__error" hidden>', $ok );

		$bad = $this->settings_box( $this->map( array( 'source' => 'https://maps.app.goo.gl/abc' ) ) );
		$this->assertStringContainsString( '<p class="wp-map-embed-admin__error">', $bad );
	}

	public function test_settings_box_has_the_preview_in_its_devices() {
		$this->as_role( 'editor' );
		$id   = $this->map();
		$html = $this->settings_box( $id );

		// The frame admin.js posts the form into, and what it needs for that.
		$this->assertStringContainsString( '<iframe class="wp-map-embed-preview__frame" name="wp-map-embed-preview-' . $id . '"', $html );
		$this->assertStringContainsString( 'data-action="' . WP_Map_Embed_Preview::ACTION . '"', $html );
		$this->assertStringContainsString( 'data-nonce="' . wp_create_nonce( WP_Map_Embed_Preview::ACTION . '_' . $id ) . '"', $html );
		$this->assertStringContainsString( 'data-sizes="' . esc_attr( wp_json_encode( WP_Map_Embed_Preview::SIZES ) ) . '"', $html );
		// A drawing per device, each file there.
		foreach ( WP_Map_Embed_Preview::DEVICES as $device => $drawing ) {
			$this->assertStringContainsString( 'class="wp-map-embed-preview__device" data-device="' . $device . '"', $html );
			$this->assertFileExists( WP_MAP_EMBED_DIR . $drawing['src'] );
		}
		// The turn button with the plugin's icon, the notes of the turned devices.
		$this->assertStringContainsString( 'class="wp-map-embed-preview__rotate-icon"', $html );
		$this->assertStringContainsString( 'data-device="mobile" hidden', $html );
	}

	public function test_save_sanitizes_the_settings() {
		$this->as_role( 'editor' );
		$id = $this->map();

		$_POST[ WP_Map_Embed_Admin::NONCE . '_nonce' ] = wp_create_nonce( WP_Map_Embed_Admin::NONCE );
		$_POST['wp_map_embed']                          = array(
			'source'   => '<iframe src="' . self::PB . '"></iframe>',
			'activate' => '1',
			'height'   => '9999',
			'icon'     => 'pin',
		);
		WP_Map_Embed_Admin::save( $id );

		$saved = WP_Map_Embed_Settings::get( $id );
		$this->assertSame( self::PB, $saved['source'] );
		$this->assertTrue( $saved['activate'] );
		$this->assertFalse( $saved['grayscale'] );
		$this->assertSame( 2000, $saved['height'] );
		$this->assertSame( 'pin', $saved['icon'] );
	}

	public function test_save_needs_the_nonce_and_the_right() {
		$id = $this->map( array( 'source' => 'Oslo' ) );
		$_POST['wp_map_embed'] = array( 'source' => 'Bergen' );

		$this->as_role( 'editor' );
		$_POST[ WP_Map_Embed_Admin::NONCE . '_nonce' ] = 'wrong';
		WP_Map_Embed_Admin::save( $id );
		$this->assertSame( 'Oslo', WP_Map_Embed_Settings::get( $id )['source'] );

		$this->as_role( 'subscriber' );
		$_POST[ WP_Map_Embed_Admin::NONCE . '_nonce' ] = wp_create_nonce( WP_Map_Embed_Admin::NONCE );
		WP_Map_Embed_Admin::save( $id );
		$this->assertSame( 'Oslo', WP_Map_Embed_Settings::get( $id )['source'] );
	}

	public function test_shortcode_box_and_list_column() {
		$id = $this->map();
		ob_start();
		WP_Map_Embed_Admin::render_shortcode_box( get_post( $id ) );
		$this->assertStringContainsString( 'value="[wp_map_embed id=&quot;' . $id . '&quot;]"', ob_get_clean() );

		$columns = WP_Map_Embed_Admin::columns( array( 'cb' => '', 'title' => 'Title', 'date' => 'Date' ) );
		$this->assertSame( array( 'cb', 'title', 'wp_map_embed_shortcode', 'date' ), array_keys( $columns ) );
	}

	public function test_wpbakery_element() {
		$this->map( array(), array( 'post_title' => 'Office' ) );
		$element = WP_Map_Embed_WPBakery::settings();

		$this->assertSame( 'WP Maps Embed', $element['name'] );
		$this->assertSame( WP_Map_Embed_Render::SHORTCODE, $element['base'] );
		$this->assertStringEndsWith( 'assets/images/wpbakery-icon.png', $element['icon'] );
		$this->assertContains( 'Office', array_map( 'strval', array_map( function ( $label ) {
			return preg_replace( '/ \(#\d+\)$/', '', $label );
		}, array_keys( $element['params'][0]['value'] ) ) ) );
	}
}

/**
 * The preview's frame (WP_Map_Embed_Preview): the map for the settings on the
 * screen, saved or not, on a page of its own.
 */
class Test_Admin_Preview extends WP_Map_Embed_TestCase {

	private function map_id() {
		return self::factory()->post->create( array( 'post_type' => WP_Map_Embed_Settings::POST_TYPE ) );
	}

	/**
	 * What the frame's page tells the settings (window.wpMapEmbedPreview).
	 *
	 * @param string $html The frame's page.
	 * @return array
	 */
	public static function info( $html ) {
		preg_match( '/window\.wpMapEmbedPreview = (.*?);<\/script>/', $html, $match );
		return isset( $match[1] ) ? json_decode( $match[1], true ) : array();
	}

	public function test_frame_of_unsaved_settings() {
		$html = WP_Map_Embed_Preview::frame_html( array( 'source' => 'Oslo', 'activate' => '1' ), 'Office' );

		$this->assertStringStartsWith( '<!DOCTYPE html>', ltrim( $html ) );
		$this->assertStringContainsString( 'q=Oslo', $html );
		$this->assertStringContainsString( 'title="Office"', $html );
		$this->assertStringContainsString( '<div class="wp-map-embed-preview-page">', $html );
		$this->assertFalse( self::info( $html )['invalid'] );
	}

	public function test_frame_says_when_a_link_is_no_map() {
		$html = WP_Map_Embed_Preview::frame_html( array( 'source' => 'https://maps.app.goo.gl/abc' ), 'Office' );
		$this->assertStringNotContainsString( 'wp-map-embed__frame', $html );
		$this->assertStringContainsString( 'wp-map-embed-preview-empty', $html );
		$this->assertTrue( self::info( $html )['invalid'] );

		// Nothing typed yet: the words, no error.
		$html = WP_Map_Embed_Preview::frame_html( array(), 'Office' );
		$this->assertStringContainsString( 'wp-map-embed-preview-empty', $html );
		$this->assertFalse( self::info( $html )['invalid'] );
	}

	public function test_frame_needs_the_nonce_and_the_right_to_edit() {
		$id    = $this->map_id();
		$nonce = function () use ( $id ) {
			return wp_create_nonce( WP_Map_Embed_Preview::ACTION . '_' . $id );
		};

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertTrue( WP_Map_Embed_Preview::allowed( $id, $nonce() ) );
		$this->assertFalse( WP_Map_Embed_Preview::allowed( $id, 'wrong' ) );
		// Another post type, no post.
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertFalse( WP_Map_Embed_Preview::allowed( $page, wp_create_nonce( WP_Map_Embed_Preview::ACTION . '_' . $page ) ) );
		$this->assertFalse( WP_Map_Embed_Preview::allowed( 0, $nonce() ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertFalse( WP_Map_Embed_Preview::allowed( $id, $nonce() ) );
	}

	public function test_devices_and_their_screens() {
		foreach ( WP_Map_Embed_Preview::SIZES as $device => $size ) {
			// Each frame inside its device's breakpoints, at its screen's proportions.
			$this->assertSame( $device, WP_Map_Embed_Preview::device_at( $size[0] ) );
			$screen = WP_Map_Embed_Preview::DEVICES[ $device ]['screen'];
			$this->assertEqualsWithDelta( $size[0] / $size[1], $screen[2] / $screen[3], 0.01, $device );
		}
		// Turned sideways, a tablet is a desktop and a phone a tablet.
		$notes = WP_Map_Embed_Preview::turned_notes();
		$this->assertStringContainsString( '1101px wide: the map takes the Desktop settings', $notes['tablet'] );
		$this->assertStringContainsString( '844px wide: the map takes the Tablet settings', $notes['mobile'] );
		$this->assertArrayNotHasKey( 'src', WP_Map_Embed_Preview::device_frames()['mobile'] );
	}
}
