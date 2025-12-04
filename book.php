<?php
$allowed_origin = 'http://localhost:3000';
$base_url       = "https://db.smartpeoplemoving.com/wp-json";
$wp_login_url   = $base_url . "/jwt-auth/v1/token";
$wp_works_url   = $base_url . "/wp/v2/works";
$wp_user = getenv('WP_USER');
$wp_pass = getenv('WP_PASS');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'smartpeoplemoving.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None' 
]);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// стартуем сессию — тут будем хранить токены
session_start();

// какой "эндпоинт" вызываем
$action = $_GET['action'] ?? null;

switch ($action) {
    case "prices":
        handle_login($wp_login_url, $wp_user, $wp_pass, $wp_works_url);
        break;

    case "create_work":
        handle_create_work($wp_works_url);
        break;

    case "get_work":
        handle_get_work($wp_works_url);
        break;

    case "update_work":
        handle_update_work($wp_works_url);
        break;

    default:
        http_response_code(400);
        echo json_encode(["error" => "Unknown action"]);
}

function handle_login($login_url, $wp_user, $wp_pass, $works_url)
{
    $body = json_decode(file_get_contents("php://input"), true) ?? [];

    $payload = [
        "username" => $wp_user,
        "password" => $wp_pass
    ];

    $resp = wp_post_json($login_url, $payload);

    if ($resp['error']) {
        http_response_code(401);
        echo json_encode(["error" => "Login failed", "details" => $resp['body']]);
        return;
    }

    $data = $resp['body'];

    // tokens from WP
    $token  = $data['token']  ?? null;

    if (!$token) {
        http_response_code(401);
        echo json_encode(["error" => "Token not returned"]);
        return;
    }

    $_SESSION['jwt_token'] = $token;

    $result = [
        "prices" => $data["prices"] ?? null,
    ];

    $workId = $_GET["id"] ?? null;
    if ($workId) {
        $url = $works_url . "/" . $workId . '?_fields=acf,id,author,date';
        $resp = wp_get_json($url, $token);
        $result['work'] = $resp['body'] ?? null;
    }

    http_response_code(200);
    echo json_encode($result);
}

function handle_create_work(string $works_url)
{
    if (empty($_SESSION["jwt_token"])) {
        http_response_code(401);
        echo json_encode(["error" => "Not authenticated"]);
        return;
    }

    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    $resp = wp_post_json($works_url, $data, $_SESSION["jwt_token"]);

    if ($resp["error"]) {
        http_response_code(400);
        echo json_encode(["error" => "Create failed", "details" => $resp["body"]]);
        return;
    }

    echo json_encode($resp["body"]);
}

function handle_get_work(string $works_url)
{
    if (empty($_SESSION["jwt_token"])) {
        http_response_code(401);
        echo json_encode(["error" => "Not authenticated (no token in session)", "session" => $_SESSION]);
        return;
    }

    $id = $_GET["id"] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing id"]);
        return;
    }

    $url = $works_url . "/" . $id;

    $resp = wp_get_json($url, $_SESSION["jwt_token"]);

    if ($resp["error"]) {
        http_response_code(400);
        echo json_encode(["error" => "Fetch failed", "details" => $resp["body"]]);
        return;
    }

    echo json_encode($resp["body"]);
}

function handle_update_work(string $works_url)
{
    if (empty($_SESSION["jwt_token"])) {
        http_response_code(401);
        echo json_encode(["error" => "Not authenticated"]);
        return;
    }

    $id = $_GET["id"] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing id"]);
        return;
    }

    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    $url = $works_url . "/" . $id;

    $resp = wp_post_json($url, $data, $_SESSION["jwt_token"]);

    if ($resp["error"]) {
        http_response_code(400);
        echo json_encode(["error" => "Update failed", "details" => $resp["body"]]);
        return;
    }

    echo json_encode($resp["body"]);
}

function wp_post_json($url, $payload, $token = null)
{
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $headers = ["Content-Type: application/json"];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        "error" => ($err || $code >= 400),
        "body"  => json_decode($response, true),
        "http"  => $code
    ];
}

function wp_get_json($url, $token)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $token"
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);

    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        "error" => ($err || $code >= 400),
        "body"  => json_decode($response, true),
        "http"  => $code
    ];
}