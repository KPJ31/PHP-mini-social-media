<?php
// Shared request and session helpers; this file does not connect to the database.
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data: blob:; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
set_exception_handler(function (Throwable $error): never {
    error_log((string) $error);
    failRequest(500, 'Something went wrong. Please try again.');
});
if (session_status() === PHP_SESSION_NONE) {
    session_start(['use_strict_mode' => true, 'use_only_cookies' => true, 'cookie_httponly' => true, 'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
}
function inputText(array $source, string $key): string {
    $value = isset($source[$key]) && is_string($source[$key]) ? $source[$key] : '';
    if (!mb_check_encoding($value, 'UTF-8')) {
        failRequest(422, 'Please use valid UTF-8 text.');
    }
    return $value;
}
function inputId(array $source, string $key): int {
    $value = filter_var(inputText($source, $key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $value === false ? 0 : $value;
}
function csrfToken(): string {
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}
function failRequest(int $status, string $message): never {
    http_response_code($status);
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'like_post.php') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
    } else {
        echo htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    exit;
}
function clearLoginSession(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $cookie['path'],
            'domain' => $cookie['domain'], 'secure' => $cookie['secure'],
            'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}
function requirePost(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        failRequest(405, 'This action requires a POST request.');
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals(csrfToken(), inputText($_POST, 'csrf_token'))) {
        failRequest(403, 'Your form has expired. Reload the page and try again.');
    }
}
