<?php
require_once 'config.php';
require_once 'session.php';
requirePost();

$comment_id = inputId($_POST, 'id');
$post_id = inputId($_POST, 'post_id');
$user_id = $_SESSION['user_id'];

// Get comment with user_id and post_id
$stmt = $conn->prepare("
    SELECT comments.user_id, comments.post_id, posts.user_id AS post_owner_id 
    FROM comments 
    JOIN posts ON comments.post_id = posts.id 
    WHERE comments.id = ?
");
$stmt->bind_param("i", $comment_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
if (!$result) { failRequest(404, 'Comment not found.'); }
$post_id = (int) $result['post_id'];

if ($result && ($result['user_id'] == $user_id || $result['post_owner_id'] == $user_id)) {
    $delete_stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
    $delete_stmt->bind_param("i", $comment_id);
    $delete_stmt->execute();
}

header("Location: comment_post.php?post_id=" . $post_id);
exit();
