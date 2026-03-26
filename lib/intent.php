<?php
defined('ABSPATH') || exit;

function spm_handle_create_intent(WP_REST_Request $request): WP_REST_Response {
    spm_session_start();
    $token = spm_session_get('jwt_token');

    if (!$token) {
        return new WP_REST_Response(['error' => 'Not authenticated'], 401);
    }

    $cfg    = spm_config();
    $body   = $request->get_json_params() ?? [];
    $workId = $body['work']  ?? null;
    $phone  = $body['token'] ?? null;

    if (!$workId || !$phone) {
        return new WP_REST_Response(['error' => 'work and phone are required'], 400);
    }

    $url  = $cfg['wp_works_url'] . '/' . (int)$workId . '?_fields=acf.customer_info,acf.deposit,acf.paid,id';
    $resp = spm_get_json($url, $token);

    if ($resp['error'] || empty($resp['body']['id'])) {
        return new WP_REST_Response(['error' => 'Work not found', 'details' => $resp['body'] ?? null], 404);
    }

    $work        = $resp['body'];
    $phoneFromWp = $work['acf']['customer_info']['customer_phone'] ?? '';

    $normalize = static function (string $p): string {
        return preg_replace('/\D+/', '', $p) ?: '';
    };

    if ($normalize((string)$phone) !== $normalize((string)$phoneFromWp)) {
        return new WP_REST_Response(['error' => 'Phone does not match'], 403);
    }

    if ((bool)($work['acf']['paid'] ?? false)) {
        return new WP_REST_Response(['error' => 'Already paid'], 400);
    }

    $deposit = $work['acf']['deposit'] ?? null;
    $amount  = (int) round(((float)$deposit) * 100);

    if ($amount <= 0) {
        return new WP_REST_Response(['error' => 'Invalid deposit amount', 'deposit' => $deposit], 400);
    }

    \Stripe\Stripe::setApiKey($cfg['stripe_secret']);

    try {
        $intent = \Stripe\PaymentIntent::create([
            'amount'   => $amount,
            'currency' => 'usd',
            'metadata' => [
                'work_id' => (string)$workId,
                'phone'   => (string)$phone,
            ],
        ]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return new WP_REST_Response(['error' => 'Stripe error', 'message' => $e->getMessage()], 500);
    }

    return new WP_REST_Response([
        'clientSecret' => $intent->client_secret,
        'amount'       => $intent->amount,
        'currency'     => $intent->currency,
        'workId'       => $workId,
    ], 200);
}
