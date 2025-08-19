<?php
// Simple Stripe webhook listener to provision WordPress Multisite sites.
// Map Stripe subscription events to calls against the Stripe Multisite Provisioning plugin.

// Configure the WordPress endpoint and credentials for the provisioning plugin.
$endpoint = getenv('WP_PROVISIONING_ENDPOINT');
$user     = getenv('WP_PROVISIONING_USER');
$pass     = getenv('WP_PROVISIONING_PASSWORD');

if (!$endpoint || !$user || !$pass) {
    http_response_code(500);
    echo 'Missing WP_PROVISIONING_ENDPOINT or credentials';
    exit;
}

$payload = @file_get_contents('php://input');
$event = json_decode($payload, true);
if (!$event || empty($event['type'])) {
    http_response_code(400);
    echo 'Invalid event payload';
    exit;
}

// Translate Stripe events to plugin actions.
$body = [
    'credentials'   => ['user_login' => $user, 'user_password' => $pass],
    'action'        => '',
    'domain'        => '',
    'mapped_domain' => '',
    'title'         => '',
    'user_name'     => '',
    'email'         => '',
    'password'      => '',
];

switch ($event['type']) {
    case 'customer.subscription.created':
        $body['action']   = 'create';
        $body['domain']   = $event['data']['object']['metadata']['domain'] ?? '';
        $body['title']    = $event['data']['object']['metadata']['title'] ?? '';
        $body['user_name'] = $event['data']['object']['metadata']['user_name'] ?? '';
        $body['email']    = $event['data']['object']['metadata']['email'] ?? '';
        $body['password'] = $event['data']['object']['metadata']['password'] ?? '';
        if (empty($body['domain']) || empty($body['email'])) {
            http_response_code(400);
            echo 'Missing required metadata for create';
            exit;
        }
        break;
    case 'customer.subscription.deleted':
        $body['action'] = 'terminate';
        $body['domain'] = $event['data']['object']['metadata']['domain'] ?? '';
        if (empty($body['domain'])) {
            http_response_code(400);
            echo 'Missing domain for terminate';
            exit;
        }
        break;
    default:
        // Ignore other events
        http_response_code(200);
        echo 'Event ignored';
        exit;
}

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['stripe' => $body]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

http_response_code(200);
echo $response;
?>
