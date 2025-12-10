<?php
require __DIR__ . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET'));
$allowed_origin = 'http://localhost:3000';
// $allowed_origin = 'https://smartpeoplemoving.com';

$base_url     = 'https://db.smartpeoplemoving.com/wp-json';
$wp_login_url = $base_url . '/jwt-auth/v1/token';
$wp_works_url = $base_url . '/wp/v2/works';

$wp_user = getenv('WP_USER');
$wp_pass = getenv('WP_PASS');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => 'smartpeoplemoving.com',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'None',
]);