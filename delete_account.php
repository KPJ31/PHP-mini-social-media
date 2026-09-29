<?php
require_once 'config.php';
require_once 'session.php';
requirePost();

if (isset($_POST['delete_account']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $conn->begin_transaction();
    try {
    $cleanup = $conn->prepare('DELETE FROM likes WHERE user_id = ? OR post_id IN (SELECT id FROM posts WHERE user_id = ?)');
    $cleanup->bind_param('ii', $user_id, $user_id);
    $cleanup->execute();
    // Delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
    $_SESSION = [];
    session_destroy();
    header("Location: index.php");
    exit;
} else {
    header("Location: profile.php");
    exit;
}
