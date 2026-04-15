=== Plugin Name ===
Contributors: josephscott, automattic
Tags: rss
Requires at least: 2.8
Tested up to: 7.0.0
Stable tag: 0.5.1

Adds RSSCloud ( http://rsscloud.co/ ) capabilities to your RSS feed.

== Description ==

Adds RSSCloud ( http://rsscloud.co/ ) capabilities to your RSS feed.

== Changelog ==

= 0.5.1 =
* Harden plugin files against direct access and escape values in the cloud element output                                     
* Allow notifications to subscribers using non-standard ports                                                                   
* More reliable detection of failed notification requests
* Reject subscription requests with no domain when the remote address is unavailable                                          

= 0.5.0 =
* Updates to support PHP 8+
* Check for http scheme in the $notify_url, add it if missing
* Only output the cloud element when we are in a feed - the rss2_head hook also runs for exports

= 0.4.2 =
* Use wp_safe_remote_*() functions for HTTP requests
* Use openssl_random_pseudo_bytes() ( when available ) instead of mt_rand() when generating tokens

= 0.4.1 =
* Limit domain characters

= 0.4.0 =
* Add support for the domain parameter in notification requests
* Notification requests that include a domain parameter use HTTP GET that
  inlcudes a challenge field that must be returned exactly as is in the body
  of the response
* Separate out the code for scheduling notifications and sending
  notifications, making it easier to replace just one or the other
* Only update notification URL details if something in the full
  loop has changed
* Add an optional parameter to rsscloud_send_post_notifications()
  for the rss2_url that was updated
* Add do_action() calls for certain events
* Provide a failure response for update requests for any feed URL
  that isn't the feed URL for the blog
* Accept any 2xx HTTP status code for notifications
* Use RSSCLOUD_FEED_URL constant for the blog feed URL if it is defined

= 0.3.2 =
* Escape error text when a notification test has failed

= 0.3.1 =
* Require notification path to start with a slash

= 0.3 =
* Limit path characters
* Fix typo
* Small adjustment to plugin description

= 0.2 =
* Minor improvements and bug fixes

= 0.1 =
* Initial release
