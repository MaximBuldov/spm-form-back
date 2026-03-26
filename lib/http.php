<?php
defined('ABSPATH') || exit;

function spm_post_json(string $url, array $payload, ?string $token = null): array {
    $args = [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode($payload),
        'timeout' => 15,
    ];
    if ($token) {
        $args['headers']['Authorization'] = "Bearer $token";
    }

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        return ['error' => true, 'body' => null, 'http' => 0];
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    return ['error' => ($code >= 400), 'body' => $body, 'http' => $code];
}

function spm_get_json(string $url, string $token): array {
    $response = wp_remote_get($url, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => "Bearer $token",
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        return ['error' => true, 'body' => null, 'http' => 0];
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    return ['error' => ($code >= 400), 'body' => $body, 'http' => $code];
}
