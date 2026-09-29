<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';

$sender_id = $_SESSION['user_id'];
$receiver_id = inputId($_GET, 'receiver_id');
requireFriend($sender_id, $receiver_id);

$stmt = $conn->prepare("
    SELECT m.*, u.username, u.profile_image
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE (m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_sender = 0) OR (m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_receiver = 0)
    ORDER BY m.sent_at ASC
");
$stmt->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
$stmt->execute();
$result = $stmt->get_result();
$read = $conn->prepare('UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND deleted_by_receiver = 0');
$read->bind_param('ii', $sender_id, $receiver_id);
$read->execute();

while ($row = $result->fetch_assoc()):
    $isOwnMessage = $row['sender_id'] == $sender_id;
    ?>
    <div class="d-flex mb-2 <?= $isOwnMessage ? 'justify-content-end' : 'justify-content-start' ?>">
        <div class="bg-<?= $isOwnMessage ? 'primary' : 'secondary' ?> text-white p-2 rounded" style="max-width: 70%;">
            <small><?= htmlspecialchars($row['message']) ?></small><br>
            <small class="text-light-50"><?= date('h:i A', strtotime($row['sent_at'])) ?></small>
        </div>
    </div>
<?php endwhile;

$stmt->close();
?>
