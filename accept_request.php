<?php
require_once 'config.php';
require_once 'session.php';
requirePost();

$receiver_id = $_SESSION['user_id'];
$sender_id = inputId($_POST, 'user_id');

// Update request status to 'accepted'
$update = $conn->prepare("UPDATE friend_requests 
    SET status = 'accepted' 
    WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'");
$update->bind_param("ii", $sender_id, $receiver_id);
$update->execute();

header("Location: friend_list.php");
exit;
?>
