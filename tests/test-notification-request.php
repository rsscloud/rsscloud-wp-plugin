<?php
/**
 * Tests for notification-request functions.
 *
 * @package Rsscloud
 */

class NotificationRequestTest extends WP_UnitTestCase {

	private $feed_url;
	private $http_requests = array();

	public function set_up() {
		parent::set_up();
		$this->http_requests = array();
		$this->feed_url      = get_bloginfo( 'rss2_url' );
		$_POST               = array();
		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';
		rsscloud_update_hub_notifications( array() );
	}

	public function tear_down() {
		$_POST = array();
		unset( $_SERVER['REMOTE_ADDR'] );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	private function mock_http_response( $status_code = 200, $body = '' ) {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $status_code, $body ) {
				$this->http_requests[] = array(
					'url'  => $url,
					'args' => $args,
				);
				return array(
					'response' => array(
						'code'    => $status_code,
						'message' => 'OK',
					),
					'body'     => $body,
				);
			},
			10,
			3
		);
	}

	private function mock_http_error() {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				$this->http_requests[] = array(
					'url'  => $url,
					'args' => $args,
				);
				return new WP_Error( 'http_request_failed', 'Connection refused' );
			},
			10,
			3
		);
	}

	/**
	 * Helper to call the function and capture the notify result.
	 *
	 * @return RsscloudNotifyResultException
	 */
	private function call_process_notification_request() {
		try {
			rsscloud_hub_process_notification_request();
			$this->fail( 'Expected RsscloudNotifyResultException to be thrown' );
		} catch ( RsscloudNotifyResultException $e ) {
			return $e;
		}
	}

	public function test_missing_url1_returns_error() {
		$_POST = array();

		$result = $this->call_process_notification_request();

		$this->assertSame( 'false', $result->success );
		$this->assertSame( 'No feed for url1.', $result->msg );
	}

	public function test_unsupported_protocol_returns_error() {
		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'xml-rpc',
			'port'     => '80',
			'path'     => '/rpc',
		);

		$fired = false;
		add_action(
			'rsscloud_protocol_not_post',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'false', $result->success );
		$this->assertStringContainsString( 'Only http-post', $result->msg );
		$this->assertTrue( $fired );
	}

	public function test_missing_path_returns_error() {
		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'http-post',
			'port'     => '80',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'false', $result->success );
		$this->assertSame( 'No path provided.', $result->msg );
	}

	public function test_successful_ip_based_registration() {
		$this->mock_http_response( 200 );

		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'http-post',
			'port'     => '80',
			'path'     => '/rpc',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'true', $result->success );
		$this->assertSame( 'Registration successful.', $result->msg );

		// Verify the notification was stored.
		$notify = rsscloud_get_hub_notifications();
		$this->assertArrayHasKey( $this->feed_url, $notify );
		$this->assertArrayHasKey( 'http://192.168.1.100:80/rpc', $notify[ $this->feed_url ] );

		$sub = $notify[ $this->feed_url ]['http://192.168.1.100:80/rpc'];
		$this->assertSame( 'http-post', $sub['protocol'] );
		$this->assertSame( 'active', $sub['status'] );
		$this->assertSame( 0, $sub['failure_count'] );
	}

	public function test_ip_based_sends_post_request() {
		$this->mock_http_response( 200 );

		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'http-post',
			'port'     => '80',
			'path'     => '/rpc',
		);

		$this->call_process_notification_request();

		$this->assertCount( 1, $this->http_requests );
		$this->assertSame( 'POST', $this->http_requests[0]['args']['method'] );
		$this->assertSame( $this->feed_url, $this->http_requests[0]['args']['body']['url'] );
	}

	public function test_domain_based_sends_get_with_challenge() {
		// Mock response that returns the challenge — we need to capture it.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				$this->http_requests[] = array(
					'url'  => $url,
					'args' => $args,
				);
				// Extract challenge from URL query string.
				$query = wp_parse_url( $url, PHP_URL_QUERY );
				parse_str( $query, $params );
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => isset( $params['challenge'] ) ? $params['challenge'] : '',
				);
			},
			10,
			3
		);

		$_POST = array(
			'url1'   => $this->feed_url,
			'port'   => '80',
			'path'   => '/rpc',
			'domain' => 'subscriber.example.com',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'true', $result->success );
		$this->assertCount( 1, $this->http_requests );
		$this->assertSame( 'GET', $this->http_requests[0]['args']['method'] );
		$this->assertStringContainsString( 'challenge=', $this->http_requests[0]['url'] );
	}

	public function test_domain_challenge_mismatch_returns_error() {
		$this->mock_http_response( 200, 'wrong-challenge' );

		$_POST = array(
			'url1'   => $this->feed_url,
			'port'   => '80',
			'path'   => '/rpc',
			'domain' => 'subscriber.example.com',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'false', $result->success );
		$this->assertStringContainsString( 'challenge', $result->msg );
	}

	public function test_http_request_failure_returns_error() {
		$this->mock_http_error();

		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'http-post',
			'port'     => '80',
			'path'     => '/rpc',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'false', $result->success );
		$this->assertStringContainsString( 'Error testing notification URL', $result->msg );
	}

	public function test_non_standard_wp_error_returns_error() {
		add_filter(
			'pre_http_request',
			function () {
				return new WP_Error( 'http_request_not_valid', 'A valid URL was not provided.' );
			},
			10,
			3
		);

		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'http-post',
			'port'     => '80',
			'path'     => '/rpc',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'false', $result->success );
		$this->assertStringContainsString( 'Error', $result->msg );
	}

	public function test_http_status_error_returns_error() {
		$this->mock_http_response( 500 );

		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'http-post',
			'port'     => '80',
			'path'     => '/rpc',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'false', $result->success );
		$this->assertStringContainsString( 'HTTP status code: 500', $result->msg );
	}

	public function test_wrong_feed_url_returns_error() {
		$this->mock_http_response( 200 );

		$_POST = array(
			'url1'     => 'http://other-site.example.com/feed',
			'protocol' => 'http-post',
			'port'     => '80',
			'path'     => '/rpc',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'false', $result->success );
		$this->assertStringContainsString( 'You can only request updates for', $result->msg );
	}

	public function test_default_port_is_80() {
		$this->mock_http_response( 200 );

		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'http-post',
			'path'     => '/rpc',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'true', $result->success );

		$notify = rsscloud_get_hub_notifications();
		$this->assertArrayHasKey( 'http://192.168.1.100:80/rpc', $notify[ $this->feed_url ] );
	}

	public function test_path_gets_leading_slash_prepended() {
		$this->mock_http_response( 200 );

		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'http-post',
			'port'     => '80',
			'path'     => 'rpc',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'true', $result->success );

		$notify = rsscloud_get_hub_notifications();
		$this->assertArrayHasKey( 'http://192.168.1.100:80/rpc', $notify[ $this->feed_url ] );
	}

	public function test_fires_add_notify_subscription_action() {
		$this->mock_http_response( 200 );
		$fired = false;
		add_action(
			'rsscloud_add_notify_subscription',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'http-post',
			'port'     => '80',
			'path'     => '/rpc',
		);

		$this->call_process_notification_request();

		$this->assertTrue( $fired );
	}

	public function test_post_data_is_unslashed() {
		$this->mock_http_response( 200 );

		// WordPress adds magic quotes to superglobals. A path containing
		// an apostrophe like /subscriber's would be slashed to /subscriber\'s.
		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'http-post',
			'port'     => '80',
			'path'     => "/subscriber's",
		);

		// Simulate WordPress magic quotes.
		$_POST = wp_slash( $_POST );

		$result = $this->call_process_notification_request();

		$this->assertSame( 'true', $result->success );

		$notify     = rsscloud_get_hub_notifications();
		$sub_urls   = array_keys( $notify[ $this->feed_url ] );
		$notify_url = $sub_urls[0];

		// After proper wp_unslash(), the backslash before the apostrophe
		// added by magic quotes should be removed.
		$this->assertStringNotContainsString( "\\'", $notify_url,
			'Path should be unslashed to remove magic quote artifacts' );
		$this->assertStringContainsString( "subscriber's", $notify_url,
			'Path should preserve the original apostrophe' );
	}

	public function test_missing_remote_addr_handles_gracefully() {
		$this->mock_http_response( 200 );

		unset( $_SERVER['REMOTE_ADDR'] );

		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'http-post',
			'port'     => '80',
			'path'     => '/rpc',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'false', $result->success,
			'Should reject registration when REMOTE_ADDR is not available' );
	}

	public function test_empty_remote_addr_does_not_create_broken_notify_url() {
		$this->mock_http_response( 200 );

		$_SERVER['REMOTE_ADDR'] = '';

		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'http-post',
			'port'     => '80',
			'path'     => '/rpc',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'false', $result->success,
			'Should reject registration when REMOTE_ADDR is empty' );
	}

	public function test_url1_is_unslashed_before_feed_comparison() {
		$this->mock_http_response( 200 );

		// Use a feed URL containing an apostrophe.
		$custom_feed = "http://example.org/?feed=rss2&author=O'Brien";
		add_filter(
			'feed_link',
			function ( $output, $feed ) use ( $custom_feed ) {
				if ( 'rss2' === $feed ) {
					return $custom_feed;
				}
				return $output;
			},
			10,
			2
		);

		$_POST = array(
			'url1'     => $custom_feed,
			'protocol' => 'http-post',
			'port'     => '80',
			'path'     => '/rpc',
		);

		// Simulate WordPress magic quotes — apostrophe gets backslash-escaped.
		$_POST = wp_slash( $_POST );

		$result = $this->call_process_notification_request();

		// Without wp_unslash, the slashed url1 won't match $rss2_url
		// and the function returns "You can only request updates for...".
		$this->assertSame( 'true', $result->success,
			'url1 should be unslashed before comparing to feed URL' );
	}

	public function test_url1_sent_to_subscriber_is_unslashed() {
		$this->mock_http_response( 200 );

		$custom_feed = "http://example.org/?feed=rss2&author=O'Brien";
		add_filter(
			'feed_link',
			function ( $output, $feed ) use ( $custom_feed ) {
				if ( 'rss2' === $feed ) {
					return $custom_feed;
				}
				return $output;
			},
			10,
			2
		);

		$_POST = array(
			'url1'     => $custom_feed,
			'protocol' => 'http-post',
			'port'     => '80',
			'path'     => '/rpc',
		);

		$_POST = wp_slash( $_POST );

		$this->call_process_notification_request();

		// The URL sent to the subscriber callback should be the clean URL,
		// not the magic-quoted version with backslashes.
		$this->assertSame( $custom_feed, $this->http_requests[0]['args']['body']['url'],
			'Subscriber should receive the unslashed feed URL' );
	}

	public function test_http_post_protocol_is_accepted() {
		$this->mock_http_response( 200 );

		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'HTTP-POST',
			'port'     => '80',
			'path'     => '/rpc',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'true', $result->success );
	}

	public function test_domain_based_builds_correct_notify_url() {
		// Mock that echoes back challenge.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				$this->http_requests[] = array(
					'url'  => $url,
					'args' => $args,
				);
				$query = wp_parse_url( $url, PHP_URL_QUERY );
				parse_str( $query, $params );
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => isset( $params['challenge'] ) ? $params['challenge'] : '',
				);
			},
			10,
			3
		);

		$_POST = array(
			'url1'   => $this->feed_url,
			'port'   => '9000',
			'path'   => '/notify',
			'domain' => 'callback.example.com',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'true', $result->success );

		$notify = rsscloud_get_hub_notifications();
		$this->assertArrayHasKey( 'http://callback.example.com:9000/notify', $notify[ $this->feed_url ] );
	}

	public function test_nonstandard_port_is_whitelisted_for_url_validation() {
		// No pre_http_request mock — wp_http_validate_url only runs when the
		// request isn't short-circuited, and that's where
		// http_allowed_safe_ports gets applied. Hook it at priority 999 so
		// we observe what the plugin handed to the validator.
		$observed_ports = array();
		add_filter(
			'http_allowed_safe_ports',
			function ( $ports ) use ( &$observed_ports ) {
				$observed_ports = $ports;
				return $ports;
			},
			999
		);

		// Use a TEST-NET-3 IP (RFC 5737) so wp_http_validate_url skips DNS
		// and reaches the port check where our filter runs.
		$_POST = array(
			'url1'   => $this->feed_url,
			'port'   => '4000',
			'path'   => '/feedupdated',
			'domain' => '203.0.113.5',
		);

		// Request will fail at the network layer (connection refused); we
		// don't care about the outcome, only that validation was reached
		// with our port whitelisted.
		$this->call_process_notification_request();

		$this->assertContains(
			4000,
			$observed_ports,
			'Plugin should add the subscriber port to http_allowed_safe_ports before sending.'
		);
	}

	public function test_port_443_uses_https_scheme_for_domain_based() {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				$this->http_requests[] = array(
					'url'  => $url,
					'args' => $args,
				);
				$query = wp_parse_url( $url, PHP_URL_QUERY );
				parse_str( $query, $params );
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => isset( $params['challenge'] ) ? $params['challenge'] : '',
				);
			},
			10,
			3
		);

		$_POST = array(
			'url1'   => $this->feed_url,
			'port'   => '443',
			'path'   => '/rpc',
			'domain' => 'subscriber.example.com',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'true', $result->success );
		$this->assertCount( 1, $this->http_requests );
		$this->assertStringStartsWith(
			'https://subscriber.example.com:443/rpc',
			$this->http_requests[0]['url'],
			'Port 443 should use https scheme and keep the :443 in the URL'
		);

		$notify = rsscloud_get_hub_notifications();
		$this->assertArrayHasKey(
			'https://subscriber.example.com:443/rpc',
			$notify[ $this->feed_url ]
		);
	}

	public function test_port_443_uses_https_scheme_for_ip_based() {
		$this->mock_http_response( 200 );

		$_POST = array(
			'url1'     => $this->feed_url,
			'protocol' => 'http-post',
			'port'     => '443',
			'path'     => '/rpc',
		);

		$result = $this->call_process_notification_request();

		$this->assertSame( 'true', $result->success );
		$this->assertStringStartsWith(
			'https://192.168.1.100:443/rpc',
			$this->http_requests[0]['url'],
			'Port 443 should use https scheme for IP-based registration too'
		);

		$notify = rsscloud_get_hub_notifications();
		$this->assertArrayHasKey( 'https://192.168.1.100:443/rpc', $notify[ $this->feed_url ] );
	}
}
