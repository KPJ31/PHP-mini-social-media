<?php
require_once 'config.php';
require_once 'session.php';
requirePost();
header('Content-Type: application/json');
$postId = inputId($_POST, 'post_id');
$userId = (int) $_SESSION['user_id'];
$conn->begin_transaction();
try {
    $post = $conn->prepare('SELECT id,user_id FROM posts WHERE id = ? FOR UPDATE');
    $post->bind_param('i', $postId);
    $post->execute();
    if (!($likedPost = $post->get_result()->fetch_assoc())) {
        $conn->rollback();
        failRequest(404, 'Post not found.');
    }
    $delete = $conn->prepare('DELETE FROM likes WHERE user_id = ? AND post_id = ?');
    $delete->bind_param('ii', $userId, $postId);
    $delete->execute();
    if (!$delete->affected_rows) {
        $insert = $conn->prepare('INSERT INTO likes (user_id, post_id) VALUES (?, ?)');
        $insert->bind_param('ii', $userId, $postId);
        $insert->execute();
        if ((int)$likedPost['user_id']!==$userId) { notifyUser((int)$likedPost['user_id'],$userId,'like',$_SESSION['username'].' liked your post.',$postId,'like:'.$userId.':'.$postId); }
    }
    $count = $conn->prepare('SELECT COUNT(*) AS total FROM likes WHERE post_id = ?');
    $count->bind_param('i', $postId);
    $count->execute();
    $likes = (int) $count->get_result()->fetch_assoc()['total'];
    $update = $conn->prepare('UPDATE posts SET likes = ? WHERE id = ?');
    $update->bind_param('ii', $likes, $postId);
    $update->execute();
    $conn->commit();
    echo json_encode(['success' => true, 'likes' => $likes]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log($e->getMessage());
    failRequest(500, 'Unable to update the like. Please try again.');
}
