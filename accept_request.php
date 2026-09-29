<?php
require_once 'config.php';
require_once 'session.php';
requirePost();

$receiver_id = $_SESSION['user_id'];
$sender_id = inputId($_POST, 'user_id');

$conn->begin_transaction();
try {
// Update request status to 'accepted'
$update = $conn->prepare("UPDATE friend_requests 
    SET status = 'accepted' 
    WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'");
$update->bind_param("ii", $sender_id, $receiver_id);
$update->execute();
if ($update->affected_rows) { notifyUser((int)$sender_id,(int)$receiver_id,'friend_accepted',$_SESSION['username'].' accepted your friend request.',(int)$receiver_id,'friend_accepted:'.$sender_id.':'.$receiver_id); }
$conn->commit();
} catch (Throwable $e) { $conn->rollback(); throw $e; }

header("Location: friend_list.php");
exit;
?>
