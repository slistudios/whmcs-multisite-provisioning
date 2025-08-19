# Stripe Billing Multisite Provisioning

This fork replaces the original WHMCS integration with Stripe Billing.

- The WordPress plugin (`stripe-mrp.php`) accepts JSON requests from a trusted endpoint.
- A simple webhook listener (`stripe/webhook.php`) translates Stripe subscription events into provisioning actions and forwards them to the plugin.

## Usage
1. Deploy the plugin to your WordPress Multisite network and network activate it.
2. Expose an endpoint such as `https://example.com/?stripe=1` for the plugin to receive provisioning calls.
3. Configure Stripe to send subscription events to `stripe/webhook.php` and set the following environment variables:
   - `WP_PROVISIONING_ENDPOINT` – the WordPress URL that receives provisioning calls.
   - `WP_PROVISIONING_USER` and `WP_PROVISIONING_PASSWORD` – the WordPress network credentials used to authenticate the request.
4. Add required metadata (`domain`, `email`, optional `title`, `user_name`, `password`, etc.) to the Stripe subscription so the listener can forward it to WordPress.
5. The listener forwards create and cancel subscription events to WordPress to create or remove sites.

This project is published for developers who wish to adapt and maintain their own Stripe-based provisioning workflows.
