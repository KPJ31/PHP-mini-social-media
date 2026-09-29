<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';

$logged_in_user = $_SESSION['user_id'];
$friend_id = inputId($_GET, 'user_id');

$friendOffset = (pageNumber('friends_page') - 1) * 50;
$friendShown = 0;
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
    ) ORDER BY u.username, u.id LIMIT 51 OFFSET ?
");
$friends_stmt->bind_param("iiiiii", $logged_in_user, $logged_in_user, $logged_in_user, $logged_in_user, $logged_in_user, $friendOffset);
$friends_stmt->execute();
$friends = $friends_stmt->get_result();

// Fetch messages if friend is selected
$messages = [];
if ($friend_id) {
    requireFriend($logged_in_user, $friend_id);
    $chatFriend = getUserById($friend_id);
    $viewerId = (int) $logged_in_user;
    $friendId = $friend_id;
    $conversation = conversationMessages($viewerId, $friendId, inputId($_GET, 'before'));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= csrfToken() ?>">
  <meta charset="UTF-8">
  <title>Chat</title>
<?php include 'ui_assets.php'; ?>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<div class="container mt-4">
  <div class="row">
    <!-- Friend List -->
    <div class="col-md-4">
      <div class="card shadow-sm mb-3">
        <div class="card-header">
          <strong>Your conversations</strong>
        </div>
        <ul class="list-group list-group-flush">
          <?php if (!$friends->num_rows): ?><li class="list-group-item text-muted">No conversations yet. <a href="friend_list.php">Find a friend</a> to get started.</li><?php endif; ?>
          <?php while (($friend = $friends->fetch_assoc()) && $friendShown++ < 50): ?>
            <li class="list-group-item <?= ($friend_id == $friend['id']) ? 'active text-white' : '' ?>">
              <a href="chat.php?user_id=<?= $friend['id'] ?>" class="<?= ($friend_id == $friend['id']) ? 'text-white' : '' ?> text-decoration-none d-flex align-items-center">
                <img loading="lazy" src="uploads/<?= htmlspecialchars(($friend['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $friend['profile_image']) ?>" class="rounded-circle me-2" width="40" height="40" alt="Profile image">
                <?= htmlspecialchars($friend['username']) ?>
              </a>
            </li>
          <?php endwhile; ?>
        </ul>
        <div class="px-3"><?= paginationLinks($friends->num_rows > 50, 'friends_page') ?></div>
      </div>
    </div>

    <!-- Chat Area -->
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-header">
          <strong><?= $friend_id ? htmlspecialchars($chatFriend['username']) : "Your messages" ?></strong>
        </div>
        <div id="chat-box" data-history="<?= inputId($_GET, 'before') ? 'true' : 'false' ?>" class="card-body" style="height: 400px; overflow-y: auto;">
          <?php if ($friend_id): ?>
            <?php if (inputId($_GET, 'before')): ?><p><a href="chat.php?user_id=<?= $friend_id ?>">Back to latest messages</a></p><?php endif; ?>
            <?php require 'message_list.php'; ?>
          <?php else: ?><p class="text-muted">Choose a friend to start chatting.</p><?php endif; ?>
        </div>

        <?php if ($friend_id): ?>
          <form id="chatForm" action="send_message.php" method="POST" class="p-3 border-top"><?= csrfField() ?>
            <input type="hidden" name="receiver_id" value="<?= $friend_id ?>">
            <div class="input-group">
              <label class="visually-hidden" for="message">Your message</label><input id="message" type="text" name="message" class="form-control" placeholder="Type a message..." required>
              <button class="btn btn-gold" type="submit">Send</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>


<script src="js/chat.js"></script>
<?php include 'footer.php'; ?>
</body>
</html>
