<?php
/**
 * The stencil: WP_Map_Embed_Svg_Shape reads an SVG file's shapes (geometry
 * only), the map shows through them.
 */

class Test_Svg_Shape extends WP_Map_Embed_TestCase {

	private function svg( $body, $view = '0 0 100 100' ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . $view . '">' . $body . '</svg>';
	}

	/** Two rectangles side by side, 60 by 20 together, in a 100 by 100 drawing. */
	private function two() {
		return $this->svg( '<rect x="10" y="20" width="40" height="20"/><rect x="60" y="20" width="10" height="20"/>' );
	}

	/*
	 * Reading the file (the same as WP Swiper's).
	 */

	public function test_shapes_layers_and_transforms() {
		$data = WP_Map_Embed_Svg_Shape::parse(
			$this->svg(
				'<g id="Layer_1"><g id="pair"><rect width="10" height="10"/><rect x="20" width="10" height="10"/></g>' .
				'<rect id="moved" width="10" height="10" transform="translate(50 50) scale(2)"/></g>'
			),
			'pair.svg'
		);
		$this->assertCount( 2, $data['shapes'] );
		$this->assertSame( 'pair.svg', $data['name'] );
		$this->assertEqualsWithDelta( array( 0.5, 0.5, 0.2, 0.2 ), $data['shapes'][0]['box'], 0.0001 );
		$this->assertSame( 'pair.svg: 2 shapes', WP_Map_Embed_Svg_Shape::summary( $data ) );

		// Arcs, relative commands: a circle round 75 50.
		$circle = WP_Map_Embed_Svg_Shape::parse( $this->svg( '<path d="M50 50a10 10 0 1150 0 10 10 0 01-50 0"/>' ) );
		$this->assertEqualsWithDelta( array( 0.5, 0.25, 0.5, 0.5 ), $circle['shapes'][0]['box'], 0.001 );
	}

	public function test_what_is_left_out() {
		$data = WP_Map_Embed_Svg_Shape::parse(
			$this->svg(
				'<rect fill="none" stroke="#000" width="10" height="10"/>' .
				'<rect style="display:none" x="20" width="10" height="10"/>' .
				'<text x="0" y="50">Hi</text>' .
				'<circle cx="50" cy="50" r="10"/>'
			)
		);
		$this->assertCount( 1, $data['shapes'] );
		$this->assertSame( '1 shape, 3 objects left out (no fill, hidden or text)', WP_Map_Embed_Svg_Shape::summary( $data ) );
		$this->assertSame( 'No shape yet: upload an SVG file.', WP_Map_Embed_Svg_Shape::summary( null ) );

		$this->assertNull( WP_Map_Embed_Svg_Shape::parse( $this->svg( '<rect fill="none" width="10" height="10"/>' ) ) );
		$this->assertNull( WP_Map_Embed_Svg_Shape::parse( '<html><body>no</body></html>' ) );
		$this->assertNull( WP_Map_Embed_Svg_Shape::parse( 'not xml' ) );
	}

	public function test_only_the_geometry_is_kept() {
		$evil  = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 100 100" onload="alert(1)">'
			. '<script>alert(2)</script><a xlink:href="javascript:alert(3)"><rect id="x&quot;&gt;&lt;b&gt;" width="50" height="50" onclick="alert(4)"/></a>'
			. '<foreignObject><div>html</div></foreignObject></svg>';
		$value = WP_Map_Embed_Svg_Shape::sanitize( wp_json_encode( array( 'name' => '../../evil<b>.svg', 'svg' => $evil ) ) );
		$this->assertNotSame( '', $value );
		foreach ( array( 'alert', 'script', 'javascript', 'onclick', 'onload', '<b>', 'html' ) as $word ) {
			$this->assertStringNotContainsString( $word, $value, $word );
		}
		$this->assertStringNotContainsString( '/', WP_Map_Embed_Svg_Shape::decode( $value )['name'] );

		// Entities (which could expand without end), and too large: not read.
		$this->assertSame( '', WP_Map_Embed_Svg_Shape::sanitize( '<!DOCTYPE svg [<!ENTITY a "aaaa">]><svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>' ) );
		$this->assertSame( '', WP_Map_Embed_Svg_Shape::sanitize( $this->svg( '<rect width="10" height="10"/>' . str_repeat( ' ', WP_Map_Embed_Svg_Shape::MAX_BYTES ) ) ) );
	}

	public function test_the_kept_value_is_checked_again() {
		$value = WP_Map_Embed_Svg_Shape::sanitize( $this->two() );
		$this->assertSame( $value, WP_Map_Embed_Svg_Shape::sanitize( $value ) );

		$data                   = json_decode( $value, true );
		$data['shapes'][0]['d'] = 'M 0 0 L 1 1 Z"/><script>alert(1)</script>';
		$this->assertSame( '', WP_Map_Embed_Svg_Shape::sanitize( wp_json_encode( $data ) ) );
		$this->assertNull( WP_Map_Embed_Svg_Shape::decode( '' ) );
	}

	/*
	 * The setting.
	 */

	public function test_the_setting() {
		$this->assertSame( '', WP_Map_Embed_Settings::defaults()['shape'] );
		$this->assertSame( 'center center', WP_Map_Embed_Settings::defaults()['shape_position'] );

		// From the edit screen: the file's text, as { name, svg }.
		$settings = $this->settings( array( 'shape' => wp_json_encode( array( 'name' => 'two.svg', 'svg' => $this->two() ) ), 'shape_position' => 'right bottom' ) );
		$this->assertSame( 'two.svg', WP_Map_Embed_Svg_Shape::decode( $settings['shape'] )['name'] );
		$this->assertSame( 'right bottom', $settings['shape_position'] );

		// Nonsense, an array, an unknown position.
		$this->assertSame( '', $this->settings( array( 'shape' => 'nonsense' ) )['shape'] );
		$this->assertSame( '', $this->settings( array( 'shape' => array( $this->two() ) ) )['shape'] );
		$this->assertSame( 'center center', $this->settings( array( 'shape_position' => 'middle' ) )['shape_position'] );
	}

	/*
	 * The markup.
	 */

	public function test_no_stencil_without_a_shape() {
		$html = $this->render();
		$this->assertNotContains( 'wp-map-embed--shape', $this->classes( $html ) );
		$this->assertStringNotContainsString( 'clipPath', $html );
		$this->assertStringNotContainsString( '--wme-shape', $this->style( $html ) );
	}

	public function test_the_map_shows_through_the_shapes() {
		$html = $this->render( array( 'shape_enabled' => '1', 'shape' => $this->two(), 'overlay_enabled' => '1', 'shape_position' => 'left bottom' ) );

		$this->assertContains( 'wp-map-embed--shape', $this->classes( $html ) );
		// The shapes' bounds, the file's margins left out: 60 by 20.
		$this->assertStringContainsString( '--wme-shape-ratio:3;', $this->style( $html ) );
		$this->assertStringContainsString( '--wme-shape-x:0;--wme-shape-y:1;', $this->style( $html ) );

		// One clip path of both shapes, in numbers of the stencil's box.
		$this->assertSame( 1, preg_match( '/<clipPath id="(wp-map-embed-shape-[0-9a-f]{8}-\d+)" clipPathUnits="objectBoundingBox">(.*?)<\/clipPath>/', $html, $clip ) );
		$this->assertSame( 2, substr_count( $clip[2], '<path ' ) );
		$this->assertStringContainsString( '<path d="M 0 0 L 0.66667 0 L 0.66667 1 L 0 1 Z" clip-rule="nonzero"/>', $clip[2] );

		// The frame and the cover in the stencil, cut by that clip path.
		$this->assertMatchesRegularExpression( '/<div class="wp-map-embed__shape" style="-webkit-clip-path:url\(#' . $clip[1] . '\);clip-path:url\(#' . $clip[1] . '\)"><iframe [^>]*><\/iframe><button [^>]*class="wp-map-embed__cover"/', $html );
	}

	public function test_clip_ids_differ_by_shape() {
		$id = function ( $shape ) {
			$html = $this->render( array( 'shape_enabled' => '1', 'shape' => $shape ) );
			preg_match( '/<clipPath id="wp-map-embed-shape-([0-9a-f]{8})-\d+"/', $html, $match );
			return $match[1];
		};
		$other = WP_Map_Embed_Svg_Shape::sanitize( $this->svg( '<circle cx="5" cy="5" r="5"/>' ) );
		// Another request starts counting from 1 again: the name still tells them apart.
		$this->assertNotSame( $id( $this->two() ), $id( $other ) );
		$this->assertSame( $id( $this->two() ), $id( $this->two() ) );
	}

	public function test_long_names_in_other_letters_are_cut_whole() {
		// Each Cyrillic letter is two bytes: cut by bytes, one would be split
		// and the JSON (the setting) not encode at all.
		$layer = str_repeat( 'Карта', 20 );
		$value = WP_Map_Embed_Svg_Shape::sanitize( wp_json_encode( array( 'name' => str_repeat( 'мапа', 40 ) . '.svg', 'svg' => $this->svg( '<rect id="' . $layer . '" width="10" height="10"/><rect x="20" width="5" height="5"/>' ) ) ) );
		$data  = WP_Map_Embed_Svg_Shape::decode( $value );
		$this->assertNotNull( $data );
		$this->assertSame( 120, mb_strlen( $data['name'] ) );
		$this->assertSame( mb_substr( $layer, 0, 64 ), $data['shapes'][0]['id'] );
	}

	public function test_the_stencil_switch_off_keeps_the_file_draws_none() {
		$settings = $this->settings( array( 'shape' => $this->two() ) );
		$this->assertNotSame( '', $settings['shape'] );
		$this->assertStringNotContainsString( 'clipPath', WP_Map_Embed_Render::markup( $settings, 'Test map' ) );
		// Saved before the switch: on, with its shape.
		$this->assertTrue( WP_Map_Embed_Settings::prepare( array( 'shape' => $settings['shape'] ) )['shape_enabled'] );
	}

	public function test_each_stencil_its_own_clip_path() {
		$one = $this->render( array( 'shape_enabled' => '1', 'shape' => $this->two() ) );
		$two = $this->render( array( 'shape_enabled' => '1', 'shape' => $this->two() ) );
		preg_match( '/clipPath id="([^"]+)"/', $one, $a );
		preg_match( '/clipPath id="([^"]+)"/', $two, $b );
		$this->assertNotSame( $a[1], $b[1] );
	}

	public function test_a_kept_value_with_a_bad_path_draws_no_stencil() {
		$data                   = json_decode( WP_Map_Embed_Svg_Shape::sanitize( $this->two() ), true );
		$data['shapes'][0]['d'] = 'M 0 0 L 1 1 Z" onclick="alert(1)';
		$html                   = WP_Map_Embed_Render::markup( array( 'shape_enabled' => true, 'shape' => wp_json_encode( $data ) ) + $this->settings(), 'Test map' );
		$this->assertStringNotContainsString( 'clipPath', $html );
		$this->assertStringNotContainsString( 'alert', $html );
	}

	/*
	 * The edit screen.
	 */

	public function test_the_field_and_saving() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = $this->map();

		ob_start();
		WP_Map_Embed_Admin::render_settings_box( get_post( $id ) );
		$empty = ob_get_clean();
		$this->assertStringContainsString( 'name="wp_map_embed[shape]" value=""', $empty );
		$this->assertStringContainsString( 'No shape yet: upload an SVG file.', $empty );
		$this->assertSame( 9, substr_count( $empty, 'name="wp_map_embed[shape_position]"' ) );
		$this->assertStringContainsString( 'value="center center" aria-label="Center" checked', $empty );

		// Saved from the file; a name in non-Latin letters (\u escapes in the
		// JSON) and a text with a backslash keep them (update_post_meta()
		// unslashes). Slashed, as WordPress hands $_POST over.
		$_POST[ WP_Map_Embed_Admin::NONCE . '_nonce' ] = wp_create_nonce( WP_Map_Embed_Admin::NONCE );
		$_POST['wp_map_embed']                          = wp_slash(
			array(
				'source' => 'Oslo',
				'shape'  => wp_json_encode( array( 'name' => 'карта.svg', 'svg' => $this->two() ) ),
				'label'  => 'A \\ backslash',
			)
		);
		WP_Map_Embed_Admin::save( $id );
		$saved = WP_Map_Embed_Settings::get( $id );
		$data  = WP_Map_Embed_Svg_Shape::decode( $saved['shape'] );
		$this->assertNotNull( $data );
		$this->assertSame( 'карта.svg', $data['name'] );
		$this->assertSame( 'A \\ backslash', $saved['label'] );

		ob_start();
		WP_Map_Embed_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'карта.svg: 2 shapes', $html );
		$this->assertStringContainsString( 'Replace SVG', $html );
		$this->assertMatchesRegularExpression( '/<span class="wp-map-embed-admin__shape-thumb"><svg viewBox="0 0 1 1"[^>]*aspect-ratio:3/', $html );
	}

	/*
	 * The preview's frame: the file read on the server, what it gave sent
	 * back to the settings (window.wpMapEmbedPreview).
	 */

	public function test_the_file_read_in_the_preview_and_kept_as_shapes() {
		$svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="5"/></svg>';
		$html  = WP_Map_Embed_Preview::frame_html( array( 'source' => 'Oslo', 'shape_enabled' => '1', 'shape' => wp_json_encode( array( 'name' => 'dot.svg', 'svg' => $svg ) ) ), 'Office' );
		$shape = Test_Admin_Preview::info( $html )['shape'];

		$this->assertSame( 'dot.svg: 1 shape', $shape['summary'] );
		$this->assertFalse( $shape['failed'] );
		$this->assertStringStartsWith( '<svg viewBox="0 0 1 1"', $shape['thumb'] );
		// The field gets the shapes, not the file.
		$this->assertStringNotContainsString( '<svg', $shape['value'] );
		$this->assertNotNull( WP_Map_Embed_Svg_Shape::decode( $shape['value'] ) );
		$this->assertStringContainsString( 'clipPathUnits="objectBoundingBox"', $html );
	}

	public function test_a_file_with_no_shapes_says_so() {
		$html = WP_Map_Embed_Preview::frame_html( array( 'source' => 'Oslo', 'shape' => wp_json_encode( array( 'name' => 'x.svg', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg"><text>Hi</text></svg>' ) ) ), 'Office' );
		$this->assertTrue( Test_Admin_Preview::info( $html )['shape']['failed'] );
		$this->assertSame( '', Test_Admin_Preview::info( $html )['shape']['value'] );

		$html = WP_Map_Embed_Preview::frame_html( array( 'source' => 'Oslo' ), 'Office' );
		$this->assertFalse( Test_Admin_Preview::info( $html )['shape']['failed'] );
	}

	public function test_the_frames_info_cannot_end_its_script() {
		// The thumbnail's SVG markup is in the JSON: its < and > hex-escaped,
		// so nothing in it can close the script.
		$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';
		$html = WP_Map_Embed_Preview::frame_html( array( 'source' => 'Oslo', 'shape' => wp_json_encode( array( 'name' => '</script>.svg', 'svg' => $svg ) ) ), 'Office' );
		$this->assertSame( 1, preg_match( '/<script>window\.wpMapEmbedPreview = ([^<>]*);<\/script>/', $html, $match ) );
		$this->assertStringContainsString( '\u003Csvg', $match[1] );
	}
}
