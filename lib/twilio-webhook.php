<?php
defined('ABSPATH') || exit;

function spm_handle_twilio_webhook(WP_REST_Request $request): WP_REST_Response {
    $cfg  = spm_config();
    $from = trim((string)($request->get_param('From') ?? ''));
    $body = trim((string)($request->get_param('Body') ?? ''));

    if ($from === '' || $body === '') {
        return new WP_REST_Response('', 200);
    }

    $phone = preg_replace('/^\+1/', '', $from);

    $loginResp = spm_post_json($cfg['wp_login_url'], [
        'username' => $cfg['wp_user'],
        'password' => $cfg['wp_pass'],
    ]);

    if ($loginResp['error'] || empty($loginResp['body']['token'])) {
        error_log('[SPM_TWILIO] WP login failed: ' . wp_json_encode($loginResp['body']));
        return new WP_REST_Response('', 200);
    }

    $token      = $loginResp['body']['token'];
    $createResp = spm_post_json($cfg['wp_followup_url'], [
        'status' => 'publish',
        'acf'    => [
            'customer_phone' => $phone,
            'message'        => $body,
        ],
    ], $token);

    if ($createResp['error']) {
        error_log('[SPM_TWILIO] create failed: ' . wp_json_encode($createResp['body']));
    }

    // Twilio expects TwiML response, but we're just logging — return empty TwiML
    return new WP_REST_Response(
        new SimpleXMLElement('<Response/>'),
        200,
        ['Content-Type' => 'text/xml']
    );
}
