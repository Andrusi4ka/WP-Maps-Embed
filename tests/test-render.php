<?php
/**
 * The shortcode and the map's markup: frame, cover, sizes and CSS variables.
 */

class Test_Render extends WP_Map_Embed_TestCase {

	public function test_shortcode_renders_a_published_map_and_enqueues_its_files() {
		$id   = $this->map( $this->settings( array( 'activate' => '1' ) ) );
		$html = do_shortcode( '[wp_map_embed id="' . $id . '"]' );

		$this->assertContains( 'wp-map-embed', $this->classes( $html ) );
		$this->assertStringContainsString( 'title="Test map"', $html );
		$this->assertTrue( wp_style_is( 'wp-map-embed', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'wp-map-embed', 'enqueued' ) );
	}

	public function test_shortcode_renders_nothing_for_other_posts() {
		$draft = $this->map( $this->settings(), array( 'post_status' => 'draft' ) );
		$page  = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( '', do_shortcode( '[wp_map_embed id="' . $draft . '"]' ) );
		$this->assertSame( '', do_shortcode( '[wp_map_embed id="' . $page . '"]' ) );
		$this->assertSame( '', do_shortcode( '[wp_map_embed id="999999"]' ) );
		$this->assertSame( '', do_shortcode( '[wp_map_embed]' ) );
	}

	public function test_no_markup_without_a_usable_map() {
		$this->assertSame( '', $this->render( array( 'source' => '' ) ) );
		$this->assertSame( '', $this->render( array( 'source' => 'https://maps.app.goo.gl/abc' ) ) );
	}

	public function test_extra_classes_from_the_shortcode() {
		$id   = $this->map( $this->settings() );
		$html = do_shortcode( '[wp_map_embed id="' . $id . '" class="one two"]' );
		$this->assertContains( 'one', $this->classes( $html ) );
		$this->assertContains( 'two', $this->classes( $html ) );
		// Only what a class may hold.
		$html = WP_Map_Embed_Render::markup( $this->settings(), '', 'x"><script>' );
		$this->assertContains( 'xscript', $this->classes( $html ) );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_cover_grayscale_and_label() {
		$html = $this->render(
			array(
				'activate'  => '1',
				'grayscale' => '1',
				'label'     => 'Klikk for kart',
			)
		);
		$this->assertContains( 'wp-map-embed--gray', $this->classes( $html ) );
		$this->assertNotContains( 'is-active', $this->classes( $html ) );
		$this->assertStringContainsString( '<button type="button" class="wp-map-embed__cover">', $html );
		$this->assertStringContainsString( '<span class="wp-map-embed__label">Klikk for kart</span>', $html );
	}

	public function test_cover_without_text_is_named_for_screen_readers() {
		$html = $this->render( array( 'activate' => '1' ) );
		$this->assertStringContainsString( 'class="wp-map-embed__cover" aria-label="Show the interactive map"', $html );
	}

	public function test_without_cover_the_map_is_usable_at_once() {
		$html = $this->render( array( 'grayscale' => '1', 'defer' => '1' ) );
		$this->assertContains( 'is-active', $this->classes( $html ) );
		// Grey and deferred apply only with the cover.
		$this->assertNotContains( 'wp-map-embed--gray', $this->classes( $html ) );
		$this->assertStringNotContainsString( 'wp-map-embed__cover', $html );
		$this->assertStringContainsString( '<iframe class="wp-map-embed__frame" src=', $html );
	}

	public function test_deferred_frame_has_no_src_until_the_click() {
		$html = $this->render( array( 'activate' => '1', 'defer' => '1' ) );
		$this->assertStringContainsString( ' data-defer>', $html );
		$this->assertStringContainsString( '<iframe class="wp-map-embed__frame" data-src=', $html );
		$this->assertStringNotContainsString( ' src=', $html );
	}

	public function test_placeholder_image_only_for_a_deferred_map() {
		$image = self::factory()->attachment->create_object(
			array(
				'file'           => 'shot.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$deferred = $this->render( array( 'activate' => '1', 'defer' => '1', 'placeholder' => $image ) );
		$this->assertStringContainsString( '--wme-placeholder:url(', $this->style( $deferred ) );

		$loaded = $this->render( array( 'activate' => '1', 'placeholder' => $image ) );
		$this->assertStringNotContainsString( '--wme-placeholder', $this->style( $loaded ) );
	}

	public function test_icons() {
		$this->assertStringContainsString( 'viewBox="0 0 203.079 203.079"', $this->render( array( 'activate' => '1', 'icon' => 'hand' ) ) );
		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $this->render( array( 'activate' => '1', 'icon' => 'pin' ) ) );
		$this->assertStringNotContainsString( '<svg', $this->render( array( 'activate' => '1', 'icon' => 'none' ) ) );
	}

	public function test_height_per_device() {
		$style = $this->style( $this->render( array( 'height' => '450', 'height_mobile' => '300' ) ) );
		$this->assertStringContainsString( '--wme-height:450px;', $style );
		$this->assertStringContainsString( '--wme-height-mobile:300px;', $style );
		// Unset: as on the larger screen (frontend.css falls back).
		$this->assertStringNotContainsString( '--wme-height-tablet', $style );
	}

	public function test_height_in_vh() {
		$style = $this->style( $this->render( array( 'height' => '60', 'height_tablet' => '50', 'height_unit' => 'vh' ) ) );
		$this->assertStringContainsString( '--wme-height:60vh;', $style );
		$this->assertStringContainsString( '--wme-height-tablet:50vh;', $style );
	}

	public function test_proportions_per_device() {
		$html  = $this->render( array( 'size_mode' => 'ratio', 'ratio' => '21:9', 'ratio_mobile' => '1:1' ) );
		$style = $this->style( $html );
		$this->assertContains( 'wp-map-embed--ratio', $this->classes( $html ) );
		$this->assertStringContainsString( '--wme-ratio:21 / 9;', $style );
		$this->assertStringContainsString( '--wme-ratio-mobile:1 / 1;', $style );
		$this->assertStringNotContainsString( '--wme-ratio-tablet', $style );
	}

	public function test_proportions_only_in_their_mode() {
		$html = $this->render( array( 'ratio' => '21:9' ) );
		$this->assertNotContains( 'wp-map-embed--ratio', $this->classes( $html ) );
		$this->assertStringNotContainsString( '--wme-ratio', $this->style( $html ) );
	}

	public function test_fill_the_parent_block() {
		$html = $this->render( array( 'size_mode' => 'fill' ) );
		$this->assertContains( 'wp-map-embed--fill', $this->classes( $html ) );
		// Its fallback where the block has too little room.
		$this->assertStringContainsString( '--wme-height:450px;', $this->style( $html ) );
	}

	public function test_radius_and_icon_size_per_device() {
		$style = $this->style(
			$this->render(
				array(
					'activate'         => '1',
					'radius'           => '12',
					'radius_mobile'    => '0',
					'icon_size'        => '70',
					'icon_size_tablet' => '50',
				)
			)
		);
		$this->assertStringContainsString( '--wme-radius:12px;', $style );
		$this->assertStringContainsString( '--wme-radius-mobile:0px;', $style );
		$this->assertStringNotContainsString( '--wme-radius-tablet', $style );
		$this->assertStringContainsString( '--wme-icon:70px;', $style );
		$this->assertStringContainsString( '--wme-icon-tablet:50px;', $style );
		$this->assertStringNotContainsString( '--wme-icon-mobile', $style );
	}

	public function test_cover_colour_and_icon_colour() {
		$style = $this->style( $this->render( array( 'activate' => '1', 'overlay_color' => '#ff0000', 'overlay_opacity' => '50', 'icon_color' => '#00ff00' ) ) );
		$this->assertStringContainsString( '--wme-cover:rgba(255,0,0,0.5);', $style );
		$this->assertStringContainsString( '--wme-icon-color:#00ff00;', $style );
		// No colour of its own: the theme's (frontend.css).
		$this->assertStringNotContainsString( '--wme-icon-color', $this->style( $this->render( array( 'activate' => '1' ) ) ) );
	}

	public function test_rgba() {
		$this->assertSame( 'rgba(223,218,215,0.24)', WP_Map_Embed_Render::rgba( '#dfdad7', 24 ) );
		$this->assertSame( 'rgba(255,255,255,1)', WP_Map_Embed_Render::rgba( '#fff', 100 ) );
		$this->assertSame( 'rgba(0,0,0,0)', WP_Map_Embed_Render::rgba( '#000000', -3 ) );
	}

	public function test_frame_shows_the_embed_url() {
		$this->assertSame( self::PB, $this->frame_url( $this->render( array( 'source' => self::PB ) ) ) );
	}
}
