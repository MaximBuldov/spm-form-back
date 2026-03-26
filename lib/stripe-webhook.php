<?php
defined('ABSPATH') || exit;

function spm_handle_stripe_webhook(WP_REST_Request $request): WP_REST_Response {
    $cfg             = spm_config();
    $payload         = $request->get_body();
    $sig_header      = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $endpoint_secret = $cfg['stripe_webhook_secret'];

    \Stripe\Stripe::setApiKey($cfg['stripe_secret']);

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        return new WP_REST_Response('Invalid signature', 400);
    } catch (\UnexpectedValueException $e) {
        return new WP_REST_Response('Invalid payload', 400);
    }

    if ($event->type === 'payment_intent.succeeded') {
        $paymentIntent = $event->data->object;
        $workId        = isset($paymentIntent->metadata->work_id) ? (int)$paymentIntent->metadata->work_id : 0;

        if ($workId > 0) {
            $chargeId = null;

            if (!empty($paymentIntent->latest_charge)) {
                $chargeId = $paymentIntent->latest_charge;
            } elseif (isset($paymentIntent->charges->data[0]->id)) {
                $chargeId = $paymentIntent->charges->data[0]->id;
            }

            $loginResp = spm_post_json($cfg['wp_login_url'], [
                'username' => $cfg['wp_user'],
                'password' => $cfg['wp_pass'],
            ]);

            if (!$loginResp['error'] && !empty($loginResp['body']['token'])) {
                $token = $loginResp['body']['token'];
                spm_post_json($cfg['wp_works_url'] . '/' . $workId, [
                    'acf' => [
                        'paid'      => true,
                        'charge_id' => $chargeId,
                        'state'     => 'confirmed',
                    ],
                ], $token);
            }
        }
    }

    return new WP_REST_Response('OK', 200);
}
