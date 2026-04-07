<?php
/**
 * Tests for send-post-notifications functions.
 *
 * @package Rsscloud
 */

class SendPostNotificationsTest extends WP_UnitTestCase {

	private $feed_url = 'http://example.org/?feed=rss2';

	private $http_requests = array();

	public function set_up() {
		parent::set_up();
		$this->http_requests = array();
	}

	private function set_notifications( $data ) {
		rsscloud_update_hub_notifications( $data );
	}

	private function build_notifications( $overrides = array() ) {
		$notify_url = isset( $overrides['notify_url'] ) ? $overrides['notify_url'] : 'http://subscriber.example.com:80/rpc';

		return array(
			$this->feed_url => array(
				$notify_url => array(
					'protocol'      => isset( $overrides['protocol'] ) ? $overrides['protocol'] : 'http-post',
					'status'        => isset( $overrides['status'] ) ? $overrides['status'] : 'active',
					'failure_count' => isset( $overrides['failure_count'] ) ? $overrides['failure_count'] : 0,
				),
			),
		);
	}

	private function mock_http_response( $status_code = 200 ) {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $status_code ) {
				$this->http_requests[] = array(
					'url'  => $url,
					'args' => $args,
				);
				return array(
					'response' => array(
						'code'    => $status_code,
						'message' => 'OK',
					),
					'body'     => '',
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

	public function test_successful_notification_sends_post_request() {
		$this->set_notifications( $this->build_notifications() );
		$this->mock_http_response( 200 );

		rsscloud_send_post_notifications( $this->feed_url );

		$this->assertCount( 1, $this->http_requests );
		$this->assertSame( 'http://subscriber.example.com:80/rpc', $this->http_requests[0]['url'] );
		$this->assertSame( 'POST', $this->http_requests[0]['args']['method'] );
		$this->assertSame( $this->feed_url, $this->http_requests[0]['args']['body']['url'] );
	}

	public function test_successful_notification_does_not_update_storage() {
		$notifications = $this->build_notifications();
		$this->set_notifications( $notifications );
		$this->mock_http_response( 200 );

		rsscloud_send_post_notifications( $this->feed_url );

		$this->assertSame( $notifications, rsscloud_get_hub_notifications() );
	}

	public function test_http_error_increments_failure_count() {
		$this->set_notifications( $this->build_notifications() );
		$this->mock_http_error();

		rsscloud_send_post_notifications( $this->feed_url );

		$notify = rsscloud_get_hub_notifications();
		$this->assertSame( 1, $notify[ $this->feed_url ]['http://subscriber.example.com:80/rpc']['failure_count'] );
	}

	public function test_http_status_error_increments_failure_count() {
		$this->set_notifications( $this->build_notifications() );
		$this->mock_http_response( 500 );

		rsscloud_send_post_notifications( $this->feed_url );

		$notify = rsscloud_get_hub_notifications();
		$this->assertSame( 1, $notify[ $this->feed_url ]['http://subscriber.example.com:80/rpc']['failure_count'] );
	}

	public function test_exceeding_max_failures_suspends_subscription() {
		$this->set_notifications(
			$this->build_notifications( array( 'failure_count' => RSSCLOUD_MAX_FAILURES ) )
		);
		$this->mock_http_error();

		rsscloud_send_post_notifications( $this->feed_url );

		$notify = rsscloud_get_hub_notifications();
		$sub    = $notify[ $this->feed_url ]['http://subscriber.example.com:80/rpc'];
		$this->assertSame( 'suspended', $sub['status'] );
		$this->assertSame( RSSCLOUD_MAX_FAILURES + 1, $sub['failure_count'] );
	}

	public function test_successful_response_resets_failure_count() {
		$this->set_notifications(
			$this->build_notifications( array( 'failure_count' => 3 ) )
		);
		$this->mock_http_response( 200 );

		rsscloud_send_post_notifications( $this->feed_url );

		$notify = rsscloud_get_hub_notifications();
		$this->assertSame( 0, $notify[ $this->feed_url ]['http://subscriber.example.com:80/rpc']['failure_count'] );
	}

	public function test_suspended_subscription_is_skipped() {
		$this->set_notifications(
			$this->build_notifications( array( 'status' => 'suspended' ) )
		);
		$this->mock_http_response( 200 );

		rsscloud_send_post_notifications( $this->feed_url );

		$this->assertCount( 0, $this->http_requests );
	}

	public function test_non_http_post_protocol_is_skipped() {
		$this->set_notifications(
			$this->build_notifications( array( 'protocol' => 'xml-rpc' ) )
		);
		$this->mock_http_response( 200 );

		rsscloud_send_post_notifications( $this->feed_url );

		$this->assertCount( 0, $this->http_requests );
	}

	public function test_no_subscriptions_does_not_error() {
		$this->set_notifications( array() );
		$this->mock_http_response( 200 );

		// Should not produce any errors or warnings.
		rsscloud_send_post_notifications( $this->feed_url );

		$this->assertCount( 0, $this->http_requests );
	}

	public function test_uses_custom_port_from_notify_url() {
		$this->set_notifications(
			$this->build_notifications( array( 'notify_url' => 'http://subscriber.example.com:8080/rpc' ) )
		);
		$this->mock_http_response( 200 );

		rsscloud_send_post_notifications( $this->feed_url );

		$this->assertSame( 8080, $this->http_requests[0]['args']['port'] );
	}

	public function test_defaults_to_port_80_when_not_in_url() {
		$this->set_notifications(
			$this->build_notifications( array( 'notify_url' => 'http://subscriber.example.com/rpc' ) )
		);
		$this->mock_http_response( 200 );

		rsscloud_send_post_notifications( $this->feed_url );

		$this->assertSame( 80, $this->http_requests[0]['args']['port'] );
	}

	public function test_fires_feed_notifications_action() {
		$this->set_notifications( array() );
		$fired = false;
		add_action(
			'rsscloud_feed_notifications',
			function ( $url ) use ( &$fired ) {
				$fired = $url;
			}
		);

		rsscloud_send_post_notifications( $this->feed_url );

		$this->assertSame( $this->feed_url, $fired );
	}

	public function test_fires_send_notification_action_on_post() {
		$this->set_notifications( $this->build_notifications() );
		$this->mock_http_response( 200 );
		$fired = false;
		add_action(
			'rsscloud_send_notification',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		rsscloud_send_post_notifications( $this->feed_url );

		$this->assertTrue( $fired );
	}

	public function test_fires_notify_failure_action_on_error() {
		$this->set_notifications( $this->build_notifications() );
		$this->mock_http_error();
		$fired = false;
		add_action(
			'rsscloud_notify_failure',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		rsscloud_send_post_notifications( $this->feed_url );

		$this->assertTrue( $fired );
	}

	public function test_fires_suspend_action_when_max_failures_exceeded() {
		$this->set_notifications(
			$this->build_notifications( array( 'failure_count' => RSSCLOUD_MAX_FAILURES ) )
		);
		$this->mock_http_error();
		$fired = false;
		add_action(
			'rsscloud_suspend_notification_url',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		rsscloud_send_post_notifications( $this->feed_url );

		$this->assertTrue( $fired );
	}

	public function test_fires_reset_failure_count_action_on_recovery() {
		$this->set_notifications(
			$this->build_notifications( array( 'failure_count' => 2 ) )
		);
		$this->mock_http_response( 200 );
		$fired = false;
		add_action(
			'rsscloud_reset_failure_count',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		rsscloud_send_post_notifications( $this->feed_url );

		$this->assertTrue( $fired );
	}
}
