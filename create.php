<?php
function handle_create_work(string $works_url): void {
    if (empty($_SESSION['jwt_token'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $resp = wp_post_json($works_url, $data, $_SESSION['jwt_token']);

    if ($resp['error']) {
        http_response_code(400);
        echo json_encode(['error' => 'Create failed', 'details' => $resp['body']]);
        return;
    }

    echo json_encode($resp['body']);
}