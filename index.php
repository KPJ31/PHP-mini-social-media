<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';

// Fetch all posts with user info
$stmt = $conn->prepare("
    SELECT posts.*, users.username, users.profile_image,
        (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS likes
    FROM posts
    JOIN users ON posts.user_id = users.id
    ORDER BY posts.created_at DESC
");
$stmt->execute();
$posts = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= csrfToken() ?>">
  <meta charset="UTF-8">
  <title>Mini Social Network</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-md-8">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Recent Posts</h3>
        <a href="add_post.php" class="btn btn-primary btn-sm">+ Create Post</a>
      </div>

      <?php if ($posts->num_rows > 0): ?>
        <?php while ($row = $posts->fetch_assoc()): ?>
          <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex align-items-center">
              <img src="uploads/<?= htmlspecialchars(($row['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $row['profile_image']) ?>" class="rounded-circle me-2" width="40" height="40" alt="Profile">
              <strong><?= htmlspecialchars($row['username']) ?></strong>
              <span class="ms-auto text-muted small"><?= date('F j, Y h:i A', strtotime($row['created_at'])) ?></span>
            </div>
            <div class="card-body">
              <p><?= nl2br(htmlspecialchars($row['content'] ?? '')) ?></p>

              <?php if (!empty($row['image'])): ?>
                <img src="uploads/<?= htmlspecialchars($row['image']) ?>" class="img-fluid rounded mb-2">
              <?php endif; ?>

              <div class="d-flex justify-content-between align-items-center">
                <button class="btn btn-outline-primary btn-sm like-button" data-post-id="<?= $row['id'] ?>">
                  <span id="like-count-<?= $row['id'] ?>"><?= $row['likes'] ?> likes</span>
                </button>
                <a href="comment_post.php?post_id=<?= $row['id'] ?>" class="btn btn-outline-secondary btn-sm">Comment</a>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="text-muted text-center">No posts available.</p>
      <?php endif; ?>

    </div>
  </div>
</div>

<script src="likePost.js"></script>

</body>
</html>
