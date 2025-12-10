<?php
function handle_create_intent(string $works_url): void {
    if (empty($_SESSION['jwt_token'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        return;
    }

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $workId = $body['work']  ?? null;
    $phone  = $body['token'] ?? null;

    if (!$workId || !$phone) {
        http_response_code(400);
        echo json_encode(['error' => 'work and phone are required']);
        return;
    }

    $url = $works_url . '/' . (int)$workId . '?_fields=acf.customer_info,acf.deposit,acf.paid,id';
    $resp = wp_get_json($url, $_SESSION['jwt_token']);

    if ($resp['error'] || empty($resp['body']['id'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Work not found', 'details' => $resp['body'] ?? null]);
        return;
    }

    $work = $resp['body'];

    $phoneFromWp = $work['acf']['customer_info']['customer_phone'] ?? '';

    $normalize = static function (string $p): string {
        $clean = preg_replace('/\D+/', '', $p);
        return $clean ?: '';
    };

    if ($normalize((string)$phone) !== $normalize((string)$phoneFromWp)) {
        http_response_code(403);
        echo json_encode(['error' => 'Phone does not match']);
        return;
    }

    $paid = (bool)($work['acf']['paid'] ?? false);
    if ($paid) {
        http_response_code(400);
        echo json_encode(['error' => 'Already paid']);
        return;
    }

    $deposit = $work['acf']['deposit'] ?? null;
    $amount  = (int) round(((float)$deposit) * 100);

    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid deposit amount', 'deposit' => $deposit]);
        return;
    }

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
        http_response_code(500);
        echo json_encode([
            'error'   => 'Stripe error',
            'message' => $e->getMessage(),
        ]);
        return;
    }

    echo json_encode([
        'clientSecret' => $intent->client_secret,
        'amount'       => $intent->amount,
        'currency'     => $intent->currency,
        'workId'       => $workId,
    ]);
}