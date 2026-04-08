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

	public function test_add_rss_cloud_element_returns_early_when_not_feed() {
		ob_start();
		rsscloud_add_rss_cloud_element();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	public function test_add_rss_cloud_element_outputs_cloud_tag_on_feed() {
		$this->go_to( get_feed_link( 'rss2' ) );

		ob_start();
		rsscloud_add_rss_cloud_element();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<cloud ', $output );
		$this->assertStringContainsString( "protocol='http-post'", $output );
		$this->assertStringContainsString( 'rsscloud=notify', $output );
	}

	public function test_add_rss_cloud_element_uses_correct_domain() {
		$this->go_to( get_feed_link( 'rss2' ) );

		ob_start();
		rsscloud_add_rss_cloud_element();
		$output = ob_get_clean();

		$home = wp_parse_url( get_option( 'home' ) );
		$this->assertStringContainsString( "domain='" . $home['host'] . "'", $output );
	}

	public function test_add_rss_cloud_element_uses_port_from_home_url() {
		$this->go_to( get_feed_link( 'rss2' ) );

		ob_start();
		rsscloud_add_rss_cloud_element();
		$output = ob_get_clean();

		$home = wp_parse_url( get_option( 'home' ) );
		$port = ! empty( $home['port'] ) ? (int) $home['port'] : 80;
		$this->assertStringContainsString( "port='" . $port . "'", $output );
	}

	public function test_parse_request_does_nothing_without_rsscloud_var() {
		$wp             = new stdClass();
		$wp->query_vars = array( 'p' => '1' );

		// Should return without calling exit or any notification processing.
		rsscloud_parse_request( $wp );

		// If we reach this point, the function returned normally.
		$this->assertTrue( true );
	}
}
