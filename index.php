<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';

$offset = (pageNumber() - 1) * 20;
$shown = 0;
// Fetch a bounded page of posts
$stmt = $conn->prepare("
    SELECT posts.*, users.username, users.profile_image,
        (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS likes
    FROM posts
    JOIN users ON posts.user_id = users.id
    ORDER BY posts.id DESC LIMIT 21 OFFSET ?
");
$stmt->bind_param('i', $offset);
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
<?php include 'ui_assets.php'; ?>
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container mt-4">
  <div class="feed-layout">
    <section class="feed-content" aria-label="Community posts">

      <div class="welcome-panel"><span class="eyebrow">A place to belong</span><h2>Your people. Your stories.<br>Your everyday moments.</h2><p>Share something small. Start a conversation. Stay connected.</p></div>
      <div class="section-title"><h2>Recent posts</h2><span>From your community</span></div>
      <?php if ($posts->num_rows > 0): ?>
        <?php while (($row = $posts->fetch_assoc()) && $shown++ < 20): ?>
          <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex align-items-center">
              <img loading="lazy" src="uploads/<?= htmlspecialchars(($row['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $row['profile_image']) ?>" class="rounded-circle me-2" width="40" height="40" alt="Profile">
              <strong><?= htmlspecialchars($row['username']) ?></strong>
              <span class="ms-auto text-muted small"><?= date('F j, Y h:i A', strtotime($row['created_at'])) ?></span>
            </div>
            <div class="card-body">
              <p><?= nl2br(htmlspecialchars($row['content'] ?? '')) ?></p>

              <?php if (!empty($row['image'])): ?>
                <img loading="lazy" src="uploads/<?= htmlspecialchars($row['image']) ?>" class="img-fluid rounded mb-2" alt="Image shared with this post">
              <?php endif; ?>

              <div class="d-flex justify-content-between align-items-center post-actions">
                <button class="btn btn-outline-primary btn-sm like-button" data-post-id="<?= $row['id'] ?>">
                  <span id="like-count-<?= $row['id'] ?>"><?= $row['likes'] ?> likes</span>
                </button>
                <a href="comment_post.php?post_id=<?= $row['id'] ?>" class="btn btn-outline-secondary btn-sm">Comment</a>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
        <?= paginationLinks($posts->num_rows > 20) ?>
      <?php else: ?>
        <div class="empty-state"><i class="bx bx-edit" aria-hidden="true"></i><h2>The first story could be yours.</h2><p>No posts yet. Share a moment with your community.</p><a class="btn btn-outline-primary" href="add_post.php">Write your first post</a></div>
      <?php endif; ?>

    </section>
    <aside class="feed-aside" aria-label="Explore your community">
      <div class="card"><div class="card-body"><span class="icon-badge"><i class="bx bx-group" aria-hidden="true"></i></span><h2>Find your people</h2><p>A familiar face. A new connection. Your next conversation starts here.</p><a href="friend_list.php" class="btn btn-outline-primary">Explore friends <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></a></div></div>
      <div class="card"><div class="card-body"><span class="icon-badge"><i class="bx bx-message-rounded-dots" aria-hidden="true"></i></span><h2>Say a little hello</h2><p>Pick up where you left off, or simply ask how their day is going.</p><a href="chat.php" class="btn btn-outline-primary">Open messages</a></div></div>
      <p class="community-note">Make this a welcoming space. Be thoughtful, be kind, and share what matters to you.</p>
    </aside>
  </div>
</div>

<script src="likePost.js"></script>

<?php include 'footer.php'; ?>
</body>
</html>
