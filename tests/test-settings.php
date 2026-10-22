<?php
/**
 * Settings: sanitizing, and the map's source turned into Google's embed URL.
 */

class Test_Settings extends WP_Map_Embed_TestCase {

	public function test_defaults_when_nothing_saved() {
		$id = $this->map();
		$this->assertSame( WP_Map_Embed_Settings::defaults(), WP_Map_Embed_Settings::get( $id ) );
	}

	public function test_iframe_code_becomes_its_link() {
		$code     = '<iframe src="' . self::PB . '" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>';
		$settings = $this->settings( array( 'source' => $code ) );
		$this->assertSame( self::PB, $settings['source'] );
	}

	public function test_whole_old_snippet_gives_its_iframe_link() {
		$code     = '<div class="map-wrapper"><div onclick="x()"><svg></svg></div><iframe src=\'' . self::PB . '\' loading="lazy"></iframe></div><style>.map-wrapper{}</style>';
		$settings = $this->settings( array( 'source' => $code ) );
		$this->assertSame( self::PB, $settings['source'] );
	}

	public function test_address_is_kept_as_text() {
		$settings = $this->settings( array( 'source' => '  Karl Johans gate 1, Oslo <b>' ) );
		$this->assertSame( 'Karl Johans gate 1, Oslo', $settings['source'] );
		$this->assertTrue( WP_Map_Embed_Settings::is_address( $settings['source'] ) );
		$this->assertFalse( WP_Map_Embed_Settings::is_address( self::PB ) );
	}

	/**
	 * @dataProvider embed_urls
	 */
	public function test_which_links_the_frame_may_show( $url, $ok ) {
		$this->assertSame( $ok, WP_Map_Embed_Settings::is_embed_url( $url ), $url );
	}

	public function embed_urls() {
		return array(
			'share > embed'      => array( self::PB, true ),
			'other google tld'   => array( 'https://www.google.no/maps/embed?pb=!1m2', true ),
			'address embed'      => array( 'https://maps.google.com/maps?q=Oslo&output=embed', true ),
			'send a link'        => array( 'https://maps.app.goo.gl/abc', false ),
			'not embeddable'     => array( 'https://www.google.com/maps/place/Oslo', false ),
			'plain http'         => array( 'http://www.google.com/maps/embed?pb=1', false ),
			'another site'       => array( 'https://evil.example/maps/embed?pb=1', false ),
			'look-alike host'    => array( 'https://evilgoogle.com/maps/embed?pb=1', false ),
			'google, not a map'  => array( 'https://www.google.com/search?output=embed', false ),
		);
	}

	public function test_no_url_without_a_usable_source() {
		$this->assertSame( '', WP_Map_Embed_Settings::embed_url( $this->settings( array( 'source' => '' ) ) ) );
		$this->assertSame( '', WP_Map_Embed_Settings::embed_url( $this->settings( array( 'source' => 'https://maps.app.goo.gl/abc' ) ) ) );
	}

	public function test_address_url() {
		$url = WP_Map_Embed_Settings::embed_url( $this->settings( array( 'source' => 'Karl Johans gate 1, Oslo', 'zoom' => '12' ) ) );
		$this->assertSame( 'https://maps.google.com/maps?q=Karl%20Johans%20gate%201%2C%20Oslo&t=m&z=12&output=embed', $url );
	}

	public function test_address_map_type_and_language() {
		$url = WP_Map_Embed_Settings::embed_url( $this->settings( array( 'map_type' => 'satellite', 'language' => 'uk' ) ) );
		$this->assertStringContainsString( '&t=k&', $url );
		$this->assertStringEndsWith( '&hl=uk', $url );
	}

	public function test_embed_code_as_copied_is_unchanged() {
		$this->assertSame( self::PB, WP_Map_Embed_Settings::embed_url( $this->settings( array( 'source' => self::PB ) ) ) );
	}

	public function test_embed_code_satellite_and_language_keep_its_pb_as_it_is() {
		$url = WP_Map_Embed_Settings::embed_url(
			$this->settings(
				array(
					'source'   => self::PB,
					'map_type' => 'satellite',
					'language' => 'en',
				)
			)
		);
		$this->assertStringContainsString( '!5e1!3m2!1sen!2sno', $url );
		$this->assertStringEndsWith( '&hl=en', $url );
		// add_query_arg() would turn "!" into "%21" and "%20" into "+".
		$this->assertStringNotContainsString( '%21', $url );
		$this->assertStringContainsString( 'Relevant%20AS', $url );
	}

	public function test_language_set_twice_is_replaced_not_added() {
		$url = WP_Map_Embed_Settings::adjust_embed_url( 'https://maps.google.com/maps?q=Oslo&output=embed&hl=de', '', 'uk' );
		$this->assertSame( 'https://maps.google.com/maps?q=Oslo&output=embed&hl=uk', $url );
	}

	public function test_site_language() {
		$cases = array(
			'nb_NO' => 'no',
			'nn_NO' => 'no',
			'en_US' => 'en',
			'en_GB' => 'en-GB',
			'pt_BR' => 'pt-BR',
			'uk'    => 'uk',
		);
		foreach ( $cases as $locale => $code ) {
			$filter = function () use ( $locale ) {
				return $locale;
			};
			add_filter( 'locale', $filter );
			add_filter( 'determine_locale', $filter );
			$this->assertSame( $code, WP_Map_Embed_Settings::site_language(), $locale );
			remove_filter( 'locale', $filter );
			remove_filter( 'determine_locale', $filter );
		}
	}

	public function test_unknown_choices_fall_back() {
		$settings = $this->settings(
			array(
				'map_type'    => 'terrain',
				'language'    => 'xx"><script>',
				'size_mode'   => 'huge',
				'ratio'       => '5:1',
				'ratio_tablet' => 'nope',
				'icon'        => 'skull',
				'height_unit' => 'em',
			)
		);
		$this->assertSame( '', $settings['map_type'] );
		$this->assertSame( '', $settings['language'] );
		$this->assertSame( 'height', $settings['size_mode'] );
		$this->assertSame( '16:9', $settings['ratio'] );
		$this->assertSame( '', $settings['ratio_tablet'] );
		$this->assertSame( 'hand', $settings['icon'] );
		$this->assertSame( 'px', $settings['height_unit'] );
	}

	public function test_numbers_are_kept_in_their_range() {
		$settings = $this->settings(
			array(
				'zoom'            => '99',
				'height'          => '10',
				'height_mobile'   => '20',
				'overlay_opacity' => '-5',
				'icon_size'       => '500',
			)
		);
		$this->assertSame( 21, $settings['zoom'] );
		$this->assertSame( 50, $settings['height'] );
		$this->assertSame( 50, $settings['height_mobile'] );
		$this->assertSame( 0, $settings['overlay_opacity'] );
		$this->assertSame( 200, $settings['icon_size'] );
		// Tablet left empty: as on the larger screen.
		$this->assertSame( 0, $settings['height_tablet'] );
	}

	public function test_height_in_vh_has_its_own_range() {
		$settings = $this->settings( array( 'height_unit' => 'vh', 'height' => '450', 'height_mobile' => '5' ) );
		$this->assertSame( 100, $settings['height'] );
		$this->assertSame( 10, $settings['height_mobile'] );
	}

	public function test_per_device_empty_inherits_and_zero_is_a_value() {
		$settings = $this->settings(
			array(
				'radius'           => '12',
				'radius_tablet'    => '',
				'radius_mobile'    => '0',
				'icon_size_tablet' => ' ',
				'icon_size_mobile' => '5',
			)
		);
		$this->assertSame( '', $settings['radius_tablet'] );
		$this->assertSame( 0, $settings['radius_mobile'] );
		$this->assertSame( '', $settings['icon_size_tablet'] );
		$this->assertSame( 16, $settings['icon_size_mobile'] );
	}

	public function test_checkboxes_absent_are_off() {
		$settings = WP_Map_Embed_Settings::sanitize( array( 'source' => 'Oslo' ) );
		$this->assertFalse( $settings['activate'] );
		$this->assertFalse( $settings['grayscale'] );
		$this->assertFalse( $settings['defer'] );
	}

	public function test_colours_and_text() {
		$settings = $this->settings(
			array(
				'overlay_color' => 'red',
				'icon_color'    => '#00ff00',
				'label'         => '<b>Click</b> to open',
			)
		);
		$this->assertSame( '#dfdad7', $settings['overlay_color'] );
		$this->assertSame( '#00ff00', $settings['icon_color'] );
		$this->assertSame( 'Click to open', $settings['label'] );
		$this->assertSame( '', $this->settings( array( 'icon_color' => 'nope' ) )['icon_color'] );
	}
}
