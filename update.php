<?php
function handle_update_work(string $works_url): void {
    if (empty($_SESSION['jwt_token'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $id = $data['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => $data]);
        return;
    }

    $url  = $works_url . '/' . $id;
    $resp = wp_post_json($url, $data, $_SESSION['jwt_token']);

    if ($resp['error']) {
        http_response_code(400);
        echo json_encode(['error' => 'Update failed', 'details' => $resp['body']]);
        return;
    }

    echo json_encode($resp['body']);
}