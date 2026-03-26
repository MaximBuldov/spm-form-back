<?php
defined('ABSPATH') || exit;

function spm_handle_prices(WP_REST_Request $request): WP_REST_Response {
    spm_session_start();
    $cfg  = spm_config();
    $body = $request->get_json_params() ?? [];

    $resp = spm_post_json($cfg['wp_login_url'], [
        'username' => $cfg['wp_user'],
        'password' => $cfg['wp_pass'],
    ]);

    if ($resp['error']) {
        return new WP_REST_Response(['error' => 'Login failed', 'details' => $resp['body']], 401);
    }

    $token = $resp['body']['token'] ?? null;
    if (!$token) {
        return new WP_REST_Response(['error' => 'Token not returned'], 401);
    }

    spm_session_set('jwt_token', $token);

    $result = ['prices' => $resp['body']['prices'] ?? null];

    $workId = $body['work']  ?? null;
    $phone  = $body['token'] ?? null;

    if ($workId && $phone) {
        $url      = $cfg['wp_works_url'] . '/' . $workId . '?_fields=acf.customer_info,acf.date,acf.state,id,author,date,acf.watched,acf.paid,acf.deposit';
        $workResp = spm_get_json($url, $token);
        $work     = $workResp['body'] ?? null;

        if ($work && isset($work['acf']['customer_info'])) {
            $watched     = $work['acf']['watched'] ?? false;
            $phoneFromWp = $work['acf']['customer_info']['customer_phone'] ?? '';

            if ($phone === $phoneFromWp) {
                if (!$watched) {
                    spm_post_json($cfg['wp_works_url'] . '/' . $workId, ['acf' => ['watched' => true]], $token);
                    $work['acf']['watched'] = true;
                }
                $result['work'] = $work;
            }
        }
    }

    return new WP_REST_Response($result, 200);
}
