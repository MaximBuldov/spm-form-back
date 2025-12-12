<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib/wp.php';

\Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET'));

$endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET');

$payload    = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event      = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $endpoint_secret
    );
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    echo 'Invalid signature';
    exit;
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    echo 'Invalid payload';
    exit;
}

if ($event->type === 'payment_intent.succeeded') {
    $paymentIntent = $event->data->object;

    $workId = isset($paymentIntent->metadata->work_id)
        ? (int)$paymentIntent->metadata->work_id
        : 0;

    if ($workId > 0) {
        $chargeId = null;

        if ($paymentIntent->charges
            && isset($paymentIntent->charges->data[0])
            && isset($paymentIntent->charges->data[0]->id)
        ) {
            $chargeId = $paymentIntent->charges->data[0]->id;
        } else {
            if (!empty($paymentIntent->latest_charge)) {
                $chargeId = $paymentIntent->latest_charge;
            }
        }

        $base_url     = 'https://db.smartpeoplemoving.com/wp-json';
        $wp_login_url = $base_url . '/jwt-auth/v1/token';
        $wp_works_url = $base_url . '/wp/v2/works';

        $wp_user = getenv('WP_USER');
        $wp_pass = getenv('WP_PASS');

        $loginPayload = [
            'username' => $wp_user,
            'password' => $wp_pass,
        ];
        $loginResp = wp_post_json($wp_login_url, $loginPayload);

        if (!$loginResp['error'] && !empty($loginResp['body']['token'])) {
            $token = $loginResp['body']['token'];

            $updatePayload = [
                'acf' => [
                    'paid'      => true,
                    'charge_id' => $chargeId,
                    'revision_author' => '1',
                ],
            ];

            $updateUrl  = $wp_works_url . '/' . $workId;
            $updateResp = wp_post_json($updateUrl, $updatePayload, $token);
        }
    }
}
http_response_code(200);
echo 'OK';