<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';

$user_id = $_SESSION['user_id'];
$friend_id = inputId($_GET, 'user_id');
requireFriend($user_id, $friend_id);

$stmt = $conn->prepare("
    SELECT * FROM messages 
    WHERE 
      ((sender_id = ? AND receiver_id = ? AND deleted_by_sender = 0) OR 
       (sender_id = ? AND receiver_id = ? AND deleted_by_receiver = 0))
    ORDER BY sent_at ASC
");
$stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Mark unread messages as read
$conn->query("UPDATE messages SET is_read = 1 WHERE receiver_id = $user_id AND sender_id = $friend_id AND is_read = 0");

while ($msg = $result->fetch_assoc()) {
    $class = $msg['sender_id'] == $user_id ? 'sent text-end' : 'received';
    echo "<div class='message $class'>";
    echo "<small>" . htmlspecialchars($msg['message']) . "</small><br>";
    echo "<span class='text-muted small'>" . $msg['sent_at'] . "</span>";

    // Show delete icon for sender
    if ($msg['sender_id'] == $user_id) {
        echo '<form action="delete_message.php" method="post" class="d-inline">' . csrfField() . '<input type="hidden" name="id" value="' . (int) $msg['id'] . '"><button class="btn btn-link text-danger small" type="submit">Delete</button></form>';
    }

    echo "</div>";
}
?>
