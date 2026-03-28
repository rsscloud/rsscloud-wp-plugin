<?php
/**
 * Tests for data storage functions.
 *
 * @package Rsscloud
 */

class DataStorageTest extends WP_UnitTestCase {

	public function test_get_hub_notifications_returns_false_when_not_set() {
		$this->assertFalse( rsscloud_get_hub_notifications() );
	}

	public function test_update_hub_notifications_stores_data() {
		$data = array(
			'http://example.com/feed' => array(
				'http://example.com/rpc' => array(
					'protocol'      => 'http-post',
					'status'        => 'active',
					'failure_count' => 0,
				),
			),
		);

		$this->assertTrue( rsscloud_update_hub_notifications( $data ) );
		$this->assertSame( $data, rsscloud_get_hub_notifications() );
	}

	public function test_update_hub_notifications_casts_to_array() {
		rsscloud_update_hub_notifications( 'not-an-array' );
		$this->assertSame( array( 'not-an-array' ), rsscloud_get_hub_notifications() );
	}

	public function test_update_hub_notifications_overwrites_previous() {
		$first  = array( 'first' => 'value' );
		$second = array( 'second' => 'value' );

		rsscloud_update_hub_notifications( $first );
		rsscloud_update_hub_notifications( $second );

		$this->assertSame( $second, rsscloud_get_hub_notifications() );
	}

	/**
	 * This test must run after tests that write data.
	 * It verifies that WP_UnitTestCase database isolation is working
	 * (each test gets a clean slate via transaction rollback).
	 */
	public function test_database_isolation_between_tests() {
		$this->assertFalse( rsscloud_get_hub_notifications() );
	}
}
