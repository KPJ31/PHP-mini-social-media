<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';
requirePost();

$sender_id = $_SESSION['user_id'];
$receiver_id = inputId($_POST, 'receiver_id');
requireFriend($sender_id, $receiver_id);
$message = trim(inputText($_POST, 'message'));
if ($message === '' || strlen($message) > 65535) { failRequest(422, 'Enter a message within 65535 bytes.'); }

if ($message !== '') {
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, sent_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
    $stmt->execute();
    $stmt->close();
}

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') { http_response_code(204); exit; }
header("Location: chat.php?user_id=" . $receiver_id);
exit();
