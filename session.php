<?php
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_id'])) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'like_post.php') {
        failRequest(401, 'Please log in to continue.');
    }
    header('Location: login.php');
    exit();
}
?>
