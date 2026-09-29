<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';
requirePost();

if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id'])) {
    header("Location: index.php");
    exit;
}

$post_id = inputId($_POST, 'post_id');
$user_id = $_SESSION['user_id'];

// Verify post ownership
$stmt = $conn->prepare("SELECT id, image FROM posts WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $post_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: index.php");
    exit;
}

$post = $result->fetch_assoc();
$stmt->close();

// Begin transaction
$conn->begin_transaction();

try {
    // Delete comments related to the post
    $stmt = $conn->prepare("DELETE FROM comments WHERE post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $stmt->close();

    // Delete likes related to the post
    $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $stmt->close();

    // Delete the post itself
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();


} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage());
    failRequest(500, 'Unable to delete the post.');
}
removeUnusedUpload($post['image']);
header("Location: profile.php");
exit;

