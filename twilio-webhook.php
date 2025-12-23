<?php
// twilio_inbound.php

header('Content-Type: text/xml; charset=utf-8');

$base_url     = "https://db.smartpeoplemoving.com/wp-json";
$wp_login_url = $base_url . "/jwt-auth/v1/token";
$wp_api_url   = $base_url . "/wp/v2/followup";

$wp_user = getenv('WP_USER');
$wp_pass = getenv('WP_PASS');

$from       = isset($_POST['From']) ? trim((string)$_POST['From']) : '';
$to         = isset($_POST['To']) ? trim((string)$_POST['To']) : '';
$body       = isset($_POST['Body']) ? trim((string)$_POST['Body']) : '';
$messageSid = isset($_POST['MessageSid']) ? trim((string)$_POST['MessageSid']) : '';

if ($from === '' || $body === '') {
  exit;
}

$loginResp = http_post_json($wp_login_url, [
  "username" => $wp_user,
  "password" => $wp_pass
]);

if ($loginResp['error'] || empty($loginResp['body']['token'])) {
  // Twilio expects 200, but log it
  error_log('[TWILIO_INBOUND] WP login failed: ' . json_encode($loginResp['body']));
  exit;
}

$token = $loginResp['body']['token'];

$payload = [
  "title"   => "Inbound SMS from " . $from,
  "status"  => "publish",
  "acf" => [
    "customer_phone" => $from,
    "message" => $body,
  ],
];

$createResp = http_post_json($wp_api_url, $payload, $token);

if ($createResp['error']) {
  error_log('[TWILIO_INBOUND] create failed: ' . json_encode($createResp['body']));
}

exit;



function http_post_json(string $url, array $payload, ?string $bearer = null): array {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

  $headers = ["Content-Type: application/json"];
  if ($bearer) $headers[] = "Authorization: Bearer " . $bearer;
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  return [
    "error" => ($err || $code >= 400),
    "http"  => $code,
    "body"  => json_decode((string)$resp, true),
    "raw"   => $resp,
  ];
}