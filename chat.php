<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';

$logged_in_user = $_SESSION['user_id'];
$friend_id = inputId($_GET, 'user_id');

// Fetch friends list
$friends_stmt = $conn->prepare("
    SELECT u.id, u.username, u.profile_image FROM users u
    WHERE u.id != ?
    AND u.id IN (
        SELECT CASE
            WHEN sender_id = ? THEN receiver_id
            WHEN receiver_id = ? THEN sender_id
        END
        FROM friend_requests
        WHERE (sender_id = ? OR receiver_id = ?) AND status = 'accepted'
    )
");
$friends_stmt->bind_param("iiiii", $logged_in_user, $logged_in_user, $logged_in_user, $logged_in_user, $logged_in_user);
$friends_stmt->execute();
$friends = $friends_stmt->get_result();

// Fetch messages if friend is selected
$messages = [];
if ($friend_id) {
    requireFriend($logged_in_user, $friend_id);
    $msg_stmt = $conn->prepare("
        SELECT m.*, u.username FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_sender = 0) OR (m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_receiver = 0)
        ORDER BY m.sent_at ASC
    ");
    $msg_stmt->bind_param("iiii", $logged_in_user, $friend_id, $friend_id, $logged_in_user);
    $msg_stmt->execute();
    $messages = $msg_stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= csrfToken() ?>">
  <meta charset="UTF-8">
  <title>Chat</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<div class="container mt-4">
  <div class="row">
    <!-- Friend List -->
    <div class="col-md-4">
      <div class="card shadow-sm mb-3">
        <div class="card-header">
          <strong>Friends</strong>
        </div>
        <ul class="list-group list-group-flush">
          <?php while ($friend = $friends->fetch_assoc()): ?>
            <li class="list-group-item <?= ($friend_id == $friend['id']) ? 'active text-white' : '' ?>">
              <a href="chat.php?user_id=<?= $friend['id'] ?>" class="<?= ($friend_id == $friend['id']) ? 'text-white' : '' ?> text-decoration-none d-flex align-items-center">
                <img src="uploads/<?= htmlspecialchars(($friend['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $friend['profile_image']) ?>" class="rounded-circle me-2" width="40" height="40">
                <?= htmlspecialchars($friend['username']) ?>
              </a>
            </li>
          <?php endwhile; ?>
        </ul>
      </div>
    </div>

    <!-- Chat Area -->
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-header">
          <strong><?= $friend_id ? "Chat with User #$friend_id" : "Select a friend to chat" ?></strong>
        </div>
        <div id="chat-box" class="card-body" style="height: 400px; overflow-y: auto;">
          <?php if ($friend_id && $messages && $messages->num_rows > 0): ?>
            <?php while ($msg = $messages->fetch_assoc()): ?>
              <div class="mb-2">
                <strong><?= htmlspecialchars($msg['username']) ?>:</strong>
                <span><?= nl2br(htmlspecialchars($msg['message'])) ?></span>
                <div class="text-muted small"><?= date('F j, Y h:i A', strtotime($msg['sent_at'])) ?></div>
              </div>
            <?php endwhile; ?>
          <?php elseif ($friend_id): ?>
            <p class="text-muted">No messages yet.</p>
          <?php else: ?>
            <p class="text-muted">Choose a friend to start chatting.</p>
          <?php endif; ?>
        </div>

        <?php if ($friend_id): ?>
          <form id="chatForm" action="send_message.php" method="POST" class="p-3 border-top"><?= csrfField() ?>
            <input type="hidden" name="receiver_id" value="<?= $friend_id ?>">
            <div class="input-group">
              <input type="text" name="message" class="form-control" placeholder="Type a message..." required>
              <button class="btn btn-primary" type="submit">Send</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>


<script src="js/chat.js"></script>
</body>
</html>
