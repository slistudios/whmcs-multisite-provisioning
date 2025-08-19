=== Stripe Billing Multisite Provisioning ===
Contributors: Arnold Bailey
Tags: stripe, hosting, multisite, support, billing, integration, provisioning
Requires at least: 3.0 and Multisite
Tested up to: 3.3.1
Stable tag: 1.0

This plugin allows provisioning of blogs on a WordPress multi-site installation driven by Stripe Billing events.

== Description ==

Stripe Billing Multisite Provisioning listens for HTTP requests from the included `stripe/webhook.php` script and performs site lifecycle actions such as create and terminate.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Network activate the plugin through the Network Admin's "Plugins" menu.
3. Expose an endpoint like `https://example.com/?stripe=1` and set `WP_PROVISIONING_ENDPOINT` in `stripe/webhook.php`.
4. Configure Stripe webhooks to post subscription events to the webhook script.

== Changelog ==

= 1.0 =
* Initial Stripe integration fork.
