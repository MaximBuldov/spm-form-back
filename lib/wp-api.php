<?php
defined('ABSPATH') || exit;

/**
 * Stores JWT token in a short-lived transient keyed to a session token
 * stored in a cookie. Replaces PHP native sessions.
 */

define('SPM_SESSION_COOKIE', 'spm_session');
define('SPM_SESSION_TTL', 3600); // 1 hour

function spm_session_start(): void {
    if (!isset($_COOKIE[SPM_SESSION_COOKIE])) {
        $id = wp_generate_uuid4();
        setcookie(SPM_SESSION_COOKIE, $id, [
            'expires'  => 0,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'None',
        ]);
        $_COOKIE[SPM_SESSION_COOKIE] = $id;
    }
}

function spm_session_id(): string {
    return $_COOKIE[SPM_SESSION_COOKIE] ?? '';
}

function spm_session_set(string $key, $value): void {
    $id = spm_session_id();
    if (!$id) return;
    set_transient("spm_sess_{$id}_{$key}", $value, SPM_SESSION_TTL);
}

function spm_session_get(string $key) {
    $id = spm_session_id();
    if (!$id) return null;
    return get_transient("spm_sess_{$id}_{$key}");
}
