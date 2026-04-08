<?php
/*
Plugin Name: RSS Cloud
Plugin URI:
Description: Ping RSS Cloud servers
Version: 0.5.0
Author: Joseph Scott
Author URI: http://josephscott.org/
License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Uncomment this to not use cron to send out notifications
// define( 'RSSCLOUD_NOTIFICATIONS_INSTANT', true );

if ( ! defined( 'RSSCLOUD_USER_AGENT' ) ) {
	define( 'RSSCLOUD_USER_AGENT', 'WordPress/RSSCloud 0.5.0' );
}

if ( ! defined( 'RSSCLOUD_MAX_FAILURES' ) ) {
	define( 'RSSCLOUD_MAX_FAILURES', 5 );
}

if ( ! defined( 'RSSCLOUD_HTTP_TIMEOUT' ) ) {
	define( 'RSSCLOUD_HTTP_TIMEOUT', 3 );
}

require __DIR__ . '/data-storage.php';

if ( ! function_exists( 'rsscloud_hub_process_notification_request' ) ) {
	require __DIR__ . '/notification-request.php';
}

if ( ! function_exists( 'rsscloud_schedule_post_notifications' ) ) {
	require __DIR__ . '/schedule-post-notifications.php';
}

if ( ! function_exists( 'rsscloud_send_post_notifications' ) ) {
	require __DIR__ . '/send-post-notifications.php';
}

add_filter( 'query_vars', 'rsscloud_query_vars' );
function rsscloud_query_vars( $vars ) {
	$vars[] = 'rsscloud';
	return $vars;
}

add_action( 'parse_request', 'rsscloud_parse_request' );
function rsscloud_parse_request( $wp ) {
	if ( array_key_exists( 'rsscloud', $wp->query_vars ) ) {
		if ( 'notify' === $wp->query_vars['rsscloud'] ) {
			rsscloud_hub_process_notification_request();
		}

		exit;
	}
}

if ( ! function_exists( 'rsscloud_notify_result' ) ) {
	function rsscloud_notify_result( $success, $msg ) {
		$success = esc_attr( ent2ncr( wp_strip_all_tags( $success ) ) );
		$msg     = esc_attr( ent2ncr( wp_strip_all_tags( $msg ) ) );

		header( 'Content-Type: text/xml' );
		echo "<?xml version='1.0'?>\n";
		echo "<notifyResult success='" . esc_attr( $success ) . "' msg='" . esc_attr( $msg ) . "' />\n";
		exit;
	}
}

add_action( 'rss2_head', 'rsscloud_add_rss_cloud_element' );
function rsscloud_add_rss_cloud_element() {
	if ( ! is_feed() ) {
		return;
	}

	$cloud = parse_url( get_option( 'home' ) . '/?rsscloud=notify' );

	$cloud['port'] = (int) $cloud['port'];
	if ( empty( $cloud['port'] ) ) {
		$cloud['port'] = 80;
	}

	$cloud['path'] .= "?{$cloud['query']}";

	$cloud['host'] = strtolower( $cloud['host'] );

	echo "<cloud domain='" . esc_attr( $cloud['host'] ) . "' port='" . esc_attr( $cloud['port'] ) . "'";
	echo " path='" . esc_attr( $cloud['path'] ) . "' registerProcedure=''";
	echo " protocol='http-post' />";
	echo "\n";
}

function rsscloud_generate_challenge( $length = 30 ) {
	$chars        = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	$chars_length = strlen( $chars );

	$string = '';
	if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
		$string = bin2hex( openssl_random_pseudo_bytes( $length / 2 ) );
	} else {
		for ( $i = 0; $i < $length; $i++ ) {
			$string .= $chars[ wp_rand( 0, $chars_length - 1 ) ];
		}
	}

	return $string;
}
