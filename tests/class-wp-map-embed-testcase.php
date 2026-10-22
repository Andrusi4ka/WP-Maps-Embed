<?php
/**
 * Helpers shared by the tests: maps, their settings and the markup they render.
 */

abstract class WP_Map_Embed_TestCase extends WP_UnitTestCase {

	/** Google's "Share > Embed a map" link (the src of its iframe). */
	const PB = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d432.6!2d5.74!3d58.94!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x463a%3A0xec2e!2sRelevant%20AS!5e0!3m2!1sno!2sno!4v1792653263590!5m2!1sno!2sno';

	/**
	 * A published map.
	 *
	 * @param array $settings Saved settings (over the defaults when read).
	 * @param array $post     Post fields.
	 * @return int Map ID.
	 */
	protected function map( $settings = array(), $post = array() ) {
		$id = self::factory()->post->create(
			$post + array(
				'post_type'   => WP_Map_Embed_Settings::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Test map',
			)
		);
		update_post_meta( $id, WP_Map_Embed_Settings::META, $settings );
		return $id;
	}

	/**
	 * Settings as the edit screen would send them, sanitized.
	 *
	 * @param array $raw Input.
	 * @return array
	 */
	protected function settings( $raw = array() ) {
		return WP_Map_Embed_Settings::sanitize( $raw + array( 'source' => 'Oslo' ) );
	}

	/**
	 * The markup of settings.
	 *
	 * @param array $raw Input (sanitized first).
	 * @return string
	 */
	protected function render( $raw = array() ) {
		return WP_Map_Embed_Render::markup( $this->settings( $raw ), 'Test map' );
	}

	/**
	 * The classes of the map's block.
	 *
	 * @param string $html Markup.
	 * @return string[]
	 */
	protected function classes( $html ) {
		$this->assertSame( 1, preg_match( '/<div class="(wp-map-embed[^"]*)"/', $html, $match ), 'No map block in the markup.' );
		return explode( ' ', $match[1] );
	}

	/**
	 * The inline style (CSS variables) of the map's block.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	protected function style( $html ) {
		preg_match( '/<div class="wp-map-embed[^"]*" style="([^"]*)"/', $html, $match );
		return isset( $match[1] ) ? html_entity_decode( $match[1] ) : '';
	}

	/**
	 * The frame's URL (src, or data-src of a deferred map).
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	protected function frame_url( $html ) {
		$this->assertSame( 1, preg_match( '/<iframe[^>]* (?:data-)?src="([^"]*)"/', $html, $match ), 'No frame in the markup.' );
		return html_entity_decode( $match[1] );
	}
}
