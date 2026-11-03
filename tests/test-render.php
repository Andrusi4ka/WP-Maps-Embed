<?php
/**
 * The shortcode and the map's markup: frame, cover, sizes and CSS variables.
 */

class Test_Render extends WP_Map_Embed_TestCase {

	public function test_shortcode_renders_a_published_map_and_enqueues_its_files() {
		$id   = $this->map( $this->settings() );
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
				'grayscale'    => '1',
				'text_enabled' => '1',
				'label'        => 'Klikk for kart',
			)
		);
		$this->assertContains( 'wp-map-embed--gray', $this->classes( $html ) );
		$this->assertNotContains( 'is-active', $this->classes( $html ) );
		$this->assertStringContainsString( '<button type="button" class="wp-map-embed__cover">', $html );
		$this->assertStringContainsString( '<span class="wp-map-embed__label">Klikk for kart</span>', $html );

		// A text with its switch off is not shown.
		$html = $this->render( array( 'grayscale' => '1', 'label' => 'Klikk for kart' ) );
		$this->assertStringNotContainsString( 'wp-map-embed__label', $html );
	}

	public function test_cover_without_text_is_named_for_screen_readers() {
		$html = $this->render( array( 'overlay_enabled' => '1' ) );
		$this->assertStringContainsString( 'class="wp-map-embed__cover" aria-label="Show the interactive map"', $html );
	}

	public function test_any_cover_option_makes_the_map_wait_for_a_click() {
		foreach ( WP_Map_Embed_Settings::COVER_SWITCHES as $key ) {
			$html = $this->render( array( $key => '1', 'label' => 'Open' ) );
			$this->assertNotContains( 'is-active', $this->classes( $html ), $key );
			$this->assertStringContainsString( 'wp-map-embed__cover', $html, $key );
		}
	}

	public function test_without_cover_the_map_is_usable_at_once() {
		// Every option of the cover off.
		$html = $this->render();
		$this->assertContains( 'is-active', $this->classes( $html ) );
		$this->assertNotContains( 'wp-map-embed--gray', $this->classes( $html ) );
		$this->assertStringNotContainsString( 'wp-map-embed__cover', $html );
		$this->assertStringContainsString( '<iframe class="wp-map-embed__frame" src=', $html );
	}

	public function test_a_map_saved_by_1_0_without_its_cover_keeps_none() {
		// 1.0 kept one "activate" switch; its options were saved on as they were.
		$id   = $this->map( array( 'source' => 'Oslo', 'activate' => false, 'grayscale' => true, 'label' => 'Open' ) );
		$html = do_shortcode( '[wp_map_embed id="' . $id . '"]' );
		$this->assertContains( 'is-active', $this->classes( $html ) );
		$this->assertStringNotContainsString( 'wp-map-embed__cover', $html );

		// With it: the cover as it was (colour, icon, the text it had).
		$id   = $this->map( array( 'source' => 'Oslo', 'activate' => true, 'grayscale' => true, 'label' => 'Open', 'icon' => 'none' ) );
		$html = do_shortcode( '[wp_map_embed id="' . $id . '"]' );
		$this->assertContains( 'wp-map-embed--gray', $this->classes( $html ) );
		$this->assertStringContainsString( '<span class="wp-map-embed__label">Open</span>', $html );
		$this->assertStringNotContainsString( 'wp-map-embed__icon', $html );
		$this->assertStringContainsString( '--wme-cover:rgba(223,218,215,0.24);', $this->style( $html ) );
	}

	public function test_shortcode_map_saved_with_the_cover_waits_for_a_click() {
		$id   = $this->map( $this->settings( array( 'overlay_enabled' => '1' ) ) );
		$html = do_shortcode( '[wp_map_embed id="' . $id . '"]' );
		$this->assertNotContains( 'is-active', $this->classes( $html ) );
	}

	public function test_deferred_frame_has_no_src_until_the_click() {
		$html = $this->render( array( 'defer' => '1' ) );
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
		$deferred = $this->render( array( 'defer' => '1', 'placeholder' => $image ) );
		$this->assertStringContainsString( '--wme-placeholder:url(', $this->style( $deferred ) );

		$loaded = $this->render( array( 'placeholder' => $image ) );
		$this->assertStringNotContainsString( '--wme-placeholder', $this->style( $loaded ) );
	}

	public function test_icons() {
		$this->assertStringContainsString( 'viewBox="0 0 203.079 203.079"', $this->render( array( 'icon_enabled' => '1', 'icon' => 'hand' ) ) );
		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $this->render( array( 'icon_enabled' => '1', 'icon' => 'pin' ) ) );
		$this->assertStringNotContainsString( '<svg', $this->render( array( 'overlay_enabled' => '1', 'icon' => 'pin' ) ) );
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

	public function test_auto_height_fills_the_parent_block() {
		$html  = $this->render( array( 'height_unit' => 'auto' ) );
		$style = $this->style( $html );
		$this->assertContains( 'wp-map-embed--auto', $this->classes( $html ) );
		$this->assertStringContainsString( '--wme-auto:1;', $style );
		// Its fallback where the block has too little room, in px.
		$this->assertStringContainsString( '--wme-height:450px;', $style );

		// On mobile only: the larger screens keep their height.
		$html  = $this->render( array( 'height_unit' => 'vh', 'height' => '60', 'height_unit_mobile' => 'auto' ) );
		$style = $this->style( $html );
		$this->assertContains( 'wp-map-embed--auto', $this->classes( $html ) );
		$this->assertStringContainsString( '--wme-auto:0;', $style );
		$this->assertStringContainsString( '--wme-auto-tablet:0;', $style );
		$this->assertStringContainsString( '--wme-auto-mobile:1;', $style );

		$this->assertNotContains( 'wp-map-embed--auto', $this->classes( $this->render() ) );
	}

	public function test_a_map_filling_its_block_in_1_0_is_auto() {
		$id   = $this->map( array( 'source' => 'Oslo', 'size_mode' => 'fill', 'height' => 400 ) );
		$html = do_shortcode( '[wp_map_embed id="' . $id . '"]' );
		$this->assertContains( 'wp-map-embed--auto', $this->classes( $html ) );
		$this->assertStringContainsString( '--wme-height:400px;', $this->style( $html ) );
	}

	public function test_border_and_shadow_only_when_on() {
		$style = $this->style( $this->render( array( 'border_width' => '3', 'shadow_y' => '4' ) ) );
		$this->assertStringNotContainsString( '--wme-border', $style );
		$this->assertStringNotContainsString( '--wme-shadow', $style );

		$style = $this->style(
			$this->render(
				array(
					'border'              => '1',
					'border_width'        => '3',
					'border_width_mobile' => '1',
					'border_style'        => 'dashed',
					'border_color'        => 'rgba(0,0,0,0.5)',
					'shadow'              => '1',
					'shadow_y'            => '4',
				)
			)
		);
		$this->assertStringContainsString( '--wme-border-width:3px;--wme-border-style:dashed;--wme-border-color:rgba(0,0,0,0.5);', $style );
		$this->assertStringContainsString( '--wme-border-width-mobile:1px;', $style );
		$this->assertStringContainsString( '--wme-shadow:0px 4px 24px 0px rgba(0,0,0,0.25);', $style );
	}

	public function test_custom_proportions() {
		$style = $this->style( $this->render( array( 'size_mode' => 'ratio', 'ratio' => 'custom', 'ratio_custom' => '2,35x1', 'ratio_mobile' => '1:1' ) ) );
		$this->assertStringContainsString( '--wme-ratio:2.35 / 1;', $style );
		$this->assertStringContainsString( '--wme-ratio-mobile:1 / 1;', $style );
		$this->assertStringNotContainsString( '--wme-ratio-tablet', $style );
	}

	public function test_radius_and_icon_size_per_device() {
		$style = $this->style(
			$this->render(
				array(
					'rounded'          => '1',
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

		// Without rounded corners: none on any device.
		$style = $this->style( $this->render( array( 'radius' => '12', 'radius_mobile' => '4' ) ) );
		$this->assertStringContainsString( '--wme-radius:0px;', $style );
		$this->assertStringNotContainsString( '--wme-radius-mobile', $style );
	}

	public function test_cover_colour_and_icon_colour() {
		// The picker sends one colour with its opacity.
		$style = $this->style( $this->render( array( 'overlay_enabled' => '1', 'overlay_color' => 'rgba(255,0,0,0.5)', 'icon_color' => '#00ff00' ) ) );
		$this->assertStringContainsString( '--wme-cover:rgba(255,0,0,0.5);', $style );
		$this->assertStringContainsString( '--wme-icon-color:#00ff00;', $style );
		// Opaque, it sends hex.
		$this->assertStringContainsString( '--wme-cover:rgba(255,0,0,1);', $this->style( $this->render( array( 'overlay_enabled' => '1', 'overlay_color' => '#ff0000' ) ) ) );
		// No colour of its own: the theme's (frontend.css).
		$this->assertStringNotContainsString( '--wme-icon-color', $this->style( $this->render( array( 'overlay_enabled' => '1' ) ) ) );
		// Its switch off: the cover takes clicks, see-through.
		$this->assertStringContainsString( '--wme-cover:transparent;', $this->style( $this->render( array( 'grayscale' => '1', 'overlay_color' => '#ff0000' ) ) ) );
	}

	public function test_rgba() {
		$this->assertSame( 'rgba(223,218,215,0.24)', WP_Map_Embed_Render::rgba( '#dfdad7', 24 ) );
		$this->assertSame( 'rgba(255,255,255,1)', WP_Map_Embed_Render::rgba( '#fff', 100 ) );
		$this->assertSame( 'rgba(0,0,0,0)', WP_Map_Embed_Render::rgba( '#000000', -3 ) );
	}

	public function test_map_scale() {
		$this->assertSame( 100, WP_Map_Embed_Settings::defaults()['scale'] );

		// 100%: nothing written (frontend.css takes 1).
		$this->assertStringNotContainsString( '--wme-scale', $this->style( $this->render() ) );

		// A factor; tablet / mobile only with a value of their own.
		$style = $this->style( $this->render( array( 'scale_enabled' => '1', 'scale' => '80', 'scale_mobile' => '100' ) ) );
		$this->assertStringContainsString( '--wme-scale:0.8;', $style );
		$this->assertStringNotContainsString( '--wme-scale-tablet', $style );
		$this->assertStringContainsString( '--wme-scale-mobile:1;', $style );

		// Its switch off: the values kept, not used.
		$this->assertStringNotContainsString( '--wme-scale', $this->style( $this->render( array( 'scale' => '80' ) ) ) );

		// 5–150%; empty on tablet / mobile stays empty, desktop empty is 100.
		$settings = $this->settings( array( 'scale' => '2', 'scale_tablet' => '400', 'scale_mobile' => ' ' ) );
		$this->assertSame( 5, $settings['scale'] );
		$this->assertSame( 150, $settings['scale_tablet'] );
		$this->assertSame( '', $settings['scale_mobile'] );
		$this->assertSame( 100, $this->settings( array( 'scale' => '' ) )['scale'] );

		// A map saved before keeps 100%; one saved with a scale (before its switch) keeps it.
		$this->assertSame( 100, WP_Map_Embed_Settings::get( $this->map( array( 'source' => 'Oslo' ) ) )['scale'] );
		$id = $this->map( array( 'source' => 'Oslo', 'scale' => 80 ) );
		$this->assertStringContainsString( '--wme-scale:0.8;', $this->style( do_shortcode( '[wp_map_embed id="' . $id . '"]' ) ) );
	}

	public function test_frame_shows_the_embed_url() {
		$this->assertSame( self::PB, $this->frame_url( $this->render( array( 'source' => self::PB ) ) ) );
	}
}
