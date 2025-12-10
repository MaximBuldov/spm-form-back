<?php
require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/wp.php';
require __DIR__ . '/lib/login.php';
require __DIR__ . '/lib/create.php';
require __DIR__ . '/lib/update.php';

session_start();

$action = $_GET['action'] ?? null;

switch ($action) {
    case 'prices':
        handle_login($wp_login_url, $wp_user, $wp_pass, $wp_works_url);
        break;

    case 'create_work':
        handle_create_work($wp_works_url);
        break;

    case 'update_work':
        handle_update_work($wp_works_url);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}