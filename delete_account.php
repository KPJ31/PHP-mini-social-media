<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';
requirePost();
if (!empty($_SESSION['is_admin'])) { failRequest(403, 'The administrator account cannot be deleted.'); }

if (isset($_POST['delete_account']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $files = $conn->prepare('SELECT profile_image AS image FROM users WHERE id = ? UNION SELECT image FROM posts WHERE user_id = ?');
    $files->bind_param('ii', $user_id, $user_id);
    $files->execute();
    $uploads = $files->get_result()->fetch_all(MYSQLI_ASSOC);
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
    foreach ($uploads as $upload) { removeUnusedUpload($upload['image']); }
    clearLoginSession();
    header("Location: index.php");
    exit;
} else {
    header("Location: profile.php");
    exit;
}
