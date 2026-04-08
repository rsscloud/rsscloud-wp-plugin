<?php
/**
 * Tests for schedule-post-notifications functions.
 *
 * @package Rsscloud
 */

class SchedulePostNotificationsTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		// Clear any scheduled events from prior tests.
		wp_clear_scheduled_hook( 'rsscloud_send_post_notifications_action' );
	}

	public function test_schedules_cron_event_by_default() {
		rsscloud_schedule_post_notifications();

		$next = wp_next_scheduled( 'rsscloud_send_post_notifications_action' );
		$this->assertNotFalse( $next, 'Expected a cron event to be scheduled.' );
	}

	public function test_instant_mode_calls_send_directly() {
		define( 'RSSCLOUD_NOTIFICATIONS_INSTANT', true );

		// Set up a subscriber keyed to the actual feed URL the function will resolve.
		$feed_url   = get_bloginfo( 'rss2_url' );
		$notify_url = 'http://subscriber.example.com/rpc';
		rsscloud_update_hub_notifications(
			array(
				$feed_url => array(
					$notify_url => array(
						'protocol'      => 'http-post',
						'status'        => 'active',
						'failure_count' => 0,
					),
				),
			)
		);

		$sent = false;
		add_filter(
			'pre_http_request',
			function () use ( &$sent ) {
				$sent = true;
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => '',
				);
			},
			10,
			3
		);

		rsscloud_schedule_post_notifications();

		$this->assertTrue( $sent, 'Expected notifications to be sent immediately in instant mode.' );
	}

	public function test_cron_action_hook_is_registered() {
		$this->assertSame(
			10,
			has_action( 'rsscloud_send_post_notifications_action', 'rsscloud_send_post_notifications' )
		);
	}

	public function test_publish_post_hook_is_registered() {
		$this->assertSame(
			10,
			has_action( 'publish_post', 'rsscloud_schedule_post_notifications' )
		);
	}
}
