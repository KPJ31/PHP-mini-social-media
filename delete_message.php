<?php
require_once 'config.php';
require_once 'session.php';
requirePost();

$user_id = $_SESSION['user_id'];
$message_id = inputId($_POST, 'id');

// Get sender and receiver of message
$stmt = $conn->prepare("SELECT sender_id, receiver_id FROM messages WHERE id = ?");
$stmt->bind_param("i", $message_id);
$stmt->execute();
$result = $stmt->get_result();
$message = $result->fetch_assoc();
$stmt->close();

if (!$message || ($user_id != $message['sender_id'] && $user_id != $message['receiver_id'])) {
    failRequest(404, 'Message not found.');
}
if ($message) {
    if ($user_id == $message['sender_id']) {
        $conn->query("UPDATE messages SET deleted_by_sender = 1 WHERE id = $message_id");
    } elseif ($user_id == $message['receiver_id']) {
        $conn->query("UPDATE messages SET deleted_by_receiver = 1 WHERE id = $message_id");
    }
}

header("Location: chat.php?user_id=" . ($user_id == $message['sender_id'] ? $message['receiver_id'] : $message['sender_id']));
exit;
?>
