<?php
/**
 * Tests for main plugin functions.
 *
 * @package Rsscloud
 */

class RsscloudTest extends WP_UnitTestCase {

	public function test_generate_challenge_returns_string_of_expected_length() {
		$challenge = rsscloud_generate_challenge();

		$this->assertIsString( $challenge );
		$this->assertSame( 30, strlen( $challenge ) );
	}

	public function test_generate_challenge_respects_custom_length() {
		$this->assertSame( 50, strlen( rsscloud_generate_challenge( 50 ) ) );
	}

	public function test_generate_challenge_returns_unique_values() {
		$first  = rsscloud_generate_challenge();
		$second = rsscloud_generate_challenge();

		$this->assertNotSame( $first, $second );
	}

	public function test_query_vars_adds_rsscloud() {
		$vars   = array( 'existing_var' );
		$result = rsscloud_query_vars( $vars );

		$this->assertContains( 'existing_var', $result );
		$this->assertContains( 'rsscloud', $result );
	}
}
