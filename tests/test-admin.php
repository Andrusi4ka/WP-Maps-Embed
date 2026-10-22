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
		$this->assertStringContainsString( 'wp-map-embed-admin__update', $html );
	}

	public function test_settings_box_device_switcher() {
		$this->as_role( 'editor' );
		$html = $this->settings_box( $this->map() );

		$this->assertSame( 4, substr_count( $html, 'class="wp-map-embed-admin__devices"' ) );
		// Desktop shown first, the others hidden until their icon is clicked.
		$this->assertStringContainsString( 'data-device="desktop" name="wp_map_embed[radius]"', $html );
		$this->assertMatchesRegularExpression( '/data-device="tablet" name="wp_map_embed\[radius_tablet\]"[^>]* hidden>/', $html );
		$this->assertStringContainsString( '<option value="">Inherit</option>', $html );
	}

	public function test_settings_box_shows_the_saved_map_and_its_error() {
		$this->as_role( 'editor' );
		$ok = $this->settings_box( $this->map( $this->settings() ) );
		$this->assertStringContainsString( 'wp-map-embed__frame', $ok );
		$this->assertStringContainsString( '<p class="wp-map-embed-admin__error" hidden>', $ok );

		$bad = $this->settings_box( $this->map( array( 'source' => 'https://maps.app.goo.gl/abc' ) ) );
		$this->assertStringContainsString( '<p class="wp-map-embed-admin__error">', $bad );
		$this->assertStringContainsString( '<p class="wp-map-embed-admin__empty">', $bad );
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
 * The preview's request (admin-ajax): the markup for the settings on the screen.
 */
class Test_Admin_Preview extends WP_Ajax_UnitTestCase {

	private function preview( $id, $settings ) {
		$_POST['nonce']        = wp_create_nonce( 'wp_map_embed_preview' );
		$_POST['post_id']      = $id;
		$_POST['title']        = 'Office';
		$_POST['wp_map_embed'] = $settings;
		try {
			$this->_handleAjax( 'wp_map_embed_preview' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}
		return json_decode( $this->_last_response, true );
	}

	private function map() {
		return self::factory()->post->create( array( 'post_type' => WP_Map_Embed_Settings::POST_TYPE ) );
	}

	public function test_preview_of_unsaved_settings() {
		$this->_setRole( 'editor' );
		$response = $this->preview( $this->map(), array( 'source' => 'Oslo', 'activate' => '1' ) );

		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'q=Oslo', $response['data']['html'] );
		$this->assertStringContainsString( 'title="Office"', $response['data']['html'] );
		$this->assertFalse( $response['data']['invalid'] );
	}

	public function test_preview_says_when_a_link_is_no_map() {
		$this->_setRole( 'editor' );
		$response = $this->preview( $this->map(), array( 'source' => 'https://maps.app.goo.gl/abc' ) );

		$this->assertSame( '', $response['data']['html'] );
		$this->assertTrue( $response['data']['invalid'] );
	}

	public function test_preview_needs_the_right_to_edit() {
		$this->_setRole( 'subscriber' );
		$response = $this->preview( $this->map(), array( 'source' => 'Oslo' ) );
		$this->assertFalse( $response['success'] );
	}
}
