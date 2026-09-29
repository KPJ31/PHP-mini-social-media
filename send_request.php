<?php
require_once 'config.php';
require_once 'session.php';
requirePost();

$sender_id = $_SESSION['user_id'];
$receiver_id = inputId($_POST, 'user_id');

if (!$receiver_id || $sender_id == $receiver_id) {
    failRequest(422, 'Choose another user.');
}
// Lock the user pair in a consistent order so reciprocal requests cannot duplicate.
$conn->begin_transaction();
try {
    $users = $conn->prepare('SELECT id FROM users WHERE id IN (?, ?) ORDER BY id FOR UPDATE');
    $users->bind_param('ii', $sender_id, $receiver_id);
    $users->execute();
    if ($users->get_result()->num_rows !== 2) {
        $conn->rollback();
        failRequest(404, 'User not found.');
    }
    $check = $conn->prepare('SELECT id FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) FOR UPDATE');
    $check->bind_param('iiii', $sender_id, $receiver_id, $receiver_id, $sender_id);
    $check->execute();
    if (!$check->get_result()->num_rows) {
        $stmt = $conn->prepare("INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param('ii', $sender_id, $receiver_id);
        $stmt->execute();
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}

header("Location: friend_list.php");
exit;
