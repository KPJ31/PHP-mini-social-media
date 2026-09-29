<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';

$user_id = $_SESSION['user_id'] ?? null;
$post_id = inputId($_POST, 'post_id') ?: inputId($_GET, 'post_id');

// Get post details
$post_stmt = $conn->prepare("
    SELECT posts.*, users.username, users.profile_image 
    FROM posts 
    JOIN users ON posts.user_id = users.id 
    WHERE posts.id = ?
");
$post_stmt->bind_param("i", $post_id);
$post_stmt->execute();
$post = $post_stmt->get_result()->fetch_assoc();
$post_stmt->close();

if (!$post) { failRequest(404, 'Post not found.'); }

// Handle new comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $comment = trim(inputText($_POST, 'comment'));
    if ($comment === '' || strlen($comment) > 65535) { failRequest(422, 'Enter a comment within 65535 bytes.'); }
    if ($comment !== '') {
        $stmt = $conn->prepare("INSERT INTO comments (user_id, post_id, comment, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $user_id, $post_id, $comment);
        $stmt->execute();
    }
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') { echo 'success'; exit; }
    header("Location: comment_post.php?post_id=" . $post_id);
    exit();
}

$post_owner_id = $post['user_id'] ?? 0;

$offset = (pageNumber() - 1) * 50;
$shown = 0;
// Get comments
$comments_stmt = $conn->prepare("
    SELECT comments.*, users.username, users.profile_image 
    FROM comments 
    JOIN users ON comments.user_id = users.id 
    WHERE post_id = ? 
    ORDER BY comments.id ASC LIMIT 51 OFFSET ?
");
$comments_stmt->bind_param("ii", $post_id, $offset);
$comments_stmt->execute();
$comments = $comments_stmt->get_result();
$comments_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= csrfToken() ?>">
  <meta charset="UTF-8">
  <title>Comments - Mini Social Network</title>
<?php include 'ui_assets.php'; ?>
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-md-8">

      <!-- Post Display -->
      <div class="card mb-4 shadow-sm">
        <div class="card-header d-flex align-items-center">
          <img loading="lazy" src="uploads/<?= htmlspecialchars(($post['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $post['profile_image']); ?>" class="rounded-circle me-2" width="40" height="40" alt="Profile">
          <strong><?= htmlspecialchars($post['username']); ?></strong>
          <span class="ms-auto small text-muted"><?= date('F j, Y h:i A', strtotime($post['created_at'])); ?></span>
        </div>
        <div class="card-body">
          <p><?= nl2br(htmlspecialchars($post['content'] ?? '')); ?></p>
          <?php if ($post['image']): ?>
            <img loading="lazy" src="uploads/<?= htmlspecialchars($post['image']); ?>" class="img-fluid rounded" alt="Profile image">
          <?php endif; ?>
        </div>
      </div>

      <!-- Comment Form -->
      <form action="" method="POST" class="card form-panel mb-4" data-loading-form><?= csrfField() ?>
        <div class="mb-3">
          <label for="comment" class="form-label">Add a comment</label>
          <textarea name="comment" id="comment" class="form-control" rows="3" required></textarea>
        </div>
        <button type="submit" class="btn btn-gold">Post Comment</button>
        <a href="index.php" class="btn btn-secondary">Back</a>
      </form>

      <!-- Comment List -->
      <div class="card form-panel">
        <h2 class="mb-3">Comments</h2>
        <?php if ($comments->num_rows > 0): ?>
          <?php while (($comment = $comments->fetch_assoc()) && $shown++ < 50): ?>
            <div class="d-flex mb-3">
              <img loading="lazy" src="uploads/<?= htmlspecialchars(($comment['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $comment['profile_image']); ?>" class="rounded-circle me-2" width="40" height="40" alt="Profile">
              <div class="flex-grow-1">
                <strong><?= htmlspecialchars($comment['username']); ?></strong><br>
                <span><?= nl2br(htmlspecialchars($comment['comment'])); ?></span><br>
                <small class="text-muted"><?= date('F j, Y h:i A', strtotime($comment['created_at'])); ?></small>
              </div>
              <?php if ($comment['user_id'] == $user_id || $post_owner_id == $user_id): ?>
                <div class="ms-2">
                  <form action="delete_comment.php" method="post" class="d-inline" data-confirm="Delete this comment?"><?= csrfField() ?><input type="hidden" name="id" value="<?= $comment['id'] ?>"><input type="hidden" name="post_id" value="<?= $post_id ?>"><button type="submit" class="btn btn-sm btn-outline-danger">Delete</button></form>
                </div>
              <?php endif; ?>
            </div>
          <?php endwhile; ?>
          <?= paginationLinks($comments->num_rows > 50) ?>
        <?php else: ?>
          <p class="text-muted">No comments yet.</p>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
