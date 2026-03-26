<?php
/**
 * Plugin Name: SPM Form Backend
 * Description: Handles booking form backend: Stripe payments, WP works API, Twilio webhooks.
 * Version: 1.0.0
 * Author: Maksim Buldau
 * License: GPL2
 */

defined('ABSPATH') || exit;

define('SPM_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once SPM_PLUGIN_DIR . 'vendor/autoload.php';
require_once SPM_PLUGIN_DIR . 'lib/http.php';
require_once SPM_PLUGIN_DIR . 'lib/wp-api.php';
require_once SPM_PLUGIN_DIR . 'lib/login.php';
require_once SPM_PLUGIN_DIR . 'lib/create.php';
require_once SPM_PLUGIN_DIR . 'lib/update.php';
require_once SPM_PLUGIN_DIR . 'lib/intent.php';
require_once SPM_PLUGIN_DIR . 'lib/stripe-webhook.php';
require_once SPM_PLUGIN_DIR . 'lib/twilio-webhook.php';

// ─── REST API routes ──────────────────────────────────────────────────────────
add_action('rest_api_init', function () {

    register_rest_route('spm/v1', '/prices', [
        'methods'             => 'POST',
        'callback'            => 'spm_handle_prices',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('spm/v1', '/create_work', [
        'methods'             => 'POST',
        'callback'            => 'spm_handle_create_work',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('spm/v1', '/update_work', [
        'methods'             => 'POST',
        'callback'            => 'spm_handle_update_work',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('spm/v1', '/create_intent', [
        'methods'             => 'POST',
        'callback'            => 'spm_handle_create_intent',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('spm/v1', '/stripe_webhook', [
        'methods'             => 'POST',
        'callback'            => 'spm_handle_stripe_webhook',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('spm/v1', '/twilio_webhook', [
        'methods'             => 'POST',
        'callback'            => 'spm_handle_twilio_webhook',
        'permission_callback' => '__return_true',
    ]);
});

// ─── CORS ────────────────────────────────────────────────────────────────────
add_action('rest_api_init', function () {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function ($value) {
        $raw     = defined('SPM_ALLOWED_ORIGINS') ? SPM_ALLOWED_ORIGINS : '';
        $allowed = array_filter(array_map('trim', explode(',', $raw)));
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowed, true)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
        }
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        return $value;
    });
}, 15);

// ─── Config ───────────────────────────────────────────────────────────────────
function spm_config(): array {
    $base = rtrim(
        defined('SPM_WP_BASE_URL') ? SPM_WP_BASE_URL : 'https://db.smartpeoplemoving.com/wp-json',
        '/'
    );

    return [
        'stripe_secret'         => defined('SPM_STRIPE_SECRET')         ? SPM_STRIPE_SECRET         : '',
        'stripe_webhook_secret' => defined('SPM_STRIPE_WEBHOOK_SECRET') ? SPM_STRIPE_WEBHOOK_SECRET : '',
        'wp_user'               => defined('SPM_WP_USER')               ? SPM_WP_USER               : '',
        'wp_pass'               => defined('SPM_WP_PASS')               ? SPM_WP_PASS               : '',
        'wp_login_url'          => $base . '/jwt-auth/v1/token',
        'wp_works_url'          => $base . '/wp/v2/works',
        'wp_followup_url'       => $base . '/wp/v2/followup',
    ];
}
