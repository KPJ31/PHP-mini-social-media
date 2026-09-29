<?php
require_once __DIR__ . '/workspace.php';
$authenticated = isset($_SESSION['user_id']);
if ($authenticated) {
    $sessionUser = $conn->prepare('SELECT id, username, email, bio, is_admin, is_suspended, auth_version, staff_role, staff_permissions FROM users WHERE id = ?');
    $sessionUser->bind_param('i', $_SESSION['user_id']);
    $sessionUser->execute();
    $currentUser = $sessionUser->get_result()->fetch_assoc();
    if (!$currentUser || $currentUser['is_suspended'] || (!isset($_SESSION['auth_version']) || (int) $_SESSION['auth_version'] !== (int) $currentUser['auth_version'])) {
        clearLoginSession();
        $authenticated = false;
    } else {
        $_SESSION['username'] = $currentUser['username'];
        $_SESSION['auth_version'] = (int) $currentUser['auth_version'];
        $_SESSION['is_admin'] = (bool) $currentUser['is_admin'];
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

// Management identities cannot use social endpoints, even through direct requests.
if (isStaff()) {
    $route = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $allowed = ['admin.php','notifications.php','notification_count.php','logout.php'];
    if (!in_array($route,$allowed,true)) {
        if ($route === 'index.php' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') { header('Location: admin.php'); exit; }
        failRequest(403, 'Management accounts use the management workspace.');
    }
}
