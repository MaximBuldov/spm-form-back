<?php
function handle_login(string $login_url, string $wp_user, string $wp_pass, string $works_url): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $payload = [
        'username' => $wp_user,
        'password' => $wp_pass,
    ];

    $resp = wp_post_json($login_url, $payload);

    if ($resp['error']) {
        http_response_code(401);
        echo json_encode(['error' => 'Login failed', 'details' => $resp, 'url' => $login_url]);
        return;
    }

    $data  = $resp['body'];
    $token = $data['token'] ?? null;

    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Token not returned']);
        return;
    }

    $_SESSION['jwt_token'] = $token;

    $result = [
        'prices' => $data['prices'] ?? null,
    ];

    $workId = $body['work']  ?? null;
    $phone  = $body['token'] ?? null;

    if ($workId && $phone) {
        $url  = $works_url . '/' . $workId . '?_fields=acf.customer_info,acf.date,acf.state,id,author,date,acf.watched,acf.paid,acf.deposit';
        $resp = wp_get_json($url, $token);

        $work = $resp['body'] ?? null;

        if ($work && isset($work['acf']['customer_info'])) {
            $watched      = $work['acf']['watched'] ?? false;
            $phoneFromWp  = $work['acf']['customer_info']['customer_phone'] ?? '';

            if ($phone === $phoneFromWp) {
                if (!$watched) {
                    $updatePayload = [
                        'acf' => [
                            'watched' => true,
                        ],
                    ];
                    wp_post_json($works_url . '/' . $workId, $updatePayload, $token);
                    $work['acf']['watched'] = true;
                }

                $result['work'] = $work;
            }
        }
    }

    http_response_code(200);
    echo json_encode($result);
}