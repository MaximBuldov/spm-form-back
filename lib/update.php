<?php
defined('ABSPATH') || exit;

function spm_handle_update_work(WP_REST_Request $request): WP_REST_Response {
    spm_session_start();
    $token = spm_session_get('jwt_token');

    if (!$token) {
        return new WP_REST_Response(['error' => 'Not authenticated'], 401);
    }

    $cfg  = spm_config();
    $data = $request->get_json_params() ?? [];
    $id   = $data['id'] ?? null;

    if (!$id) {
        return new WP_REST_Response(['error' => $data], 400);
    }

    $resp = spm_post_json($cfg['wp_works_url'] . '/' . $id, $data, $token);

    if ($resp['error']) {
        return new WP_REST_Response(['error' => 'Update failed', 'details' => $resp['body']], 400);
    }

    return new WP_REST_Response($resp['body'], 200);
}
