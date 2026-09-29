<?php
require_once __DIR__ . '/config.php';
$authenticated = isset($_SESSION['user_id']);
if ($authenticated) {
    $sessionUser = $conn->prepare('SELECT username FROM users WHERE id = ?');
    $sessionUser->bind_param('i', $_SESSION['user_id']);
    $sessionUser->execute();
    $currentUser = $sessionUser->get_result()->fetch_assoc();
    if (!$currentUser) {
        clearLoginSession();
        $authenticated = false;
    } else {
        $_SESSION['username'] = $currentUser['username'];
    }
}
if (!$authenticated) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'like_post.php'
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
        failRequest(401, 'Please log in to continue.');
    }
    header('Location: login.php');
    exit;
}
