<?php
// Use a project-specific session cookie name so localhost apps do not overwrite each other.
if (session_status() === PHP_SESSION_NONE) {
    $projectKey = substr(md5(__DIR__), 0, 10);
    session_name('TOUR_' . strtoupper($projectKey));
    // Use root path so sessions work regardless of folder name (e.g. /registration).
    // Session name is already project-specific, so app isolation is preserved.
    $cookiePath = '/';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token(): string
{
    return $_SESSION['csrf_token'] ?? '';
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_or_die(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Invalid CSRF token. Please refresh and try again.');
    }
}
?>
