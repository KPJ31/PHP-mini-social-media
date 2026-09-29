<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';

$logged_in_user = $_SESSION['user_id'];
$view_user_id = isset($_GET['user_id']) ? inputId($_GET, 'user_id') : $logged_in_user;

// Fetch user details
$stmt = $conn->prepare("SELECT username, email, bio, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) { failRequest(404, 'User not found.'); }
$stmt->close();

// Fetch user's posts
$post_stmt = $conn->prepare("
    SELECT posts.*, users.username, users.profile_image 
    FROM posts 
    JOIN users ON posts.user_id = users.id 
    WHERE posts.user_id = ? 
    ORDER BY posts.created_at DESC
");
$post_stmt->bind_param("i", $view_user_id);
$post_stmt->execute();
$posts = $post_stmt->get_result();
$post_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= csrfToken() ?>">
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($user['username']) ?> - Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container mt-4">
  <div class="row">
    <div class="col-md-4 text-center">
      <div class="card shadow p-4">
        <img src="uploads/<?= htmlspecialchars(($user['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $user['profile_image']) ?>" class="rounded-circle mb-3" width="150" height="150" alt="Profile">
        <h4><?= htmlspecialchars($user['username']) ?></h4>
        <p class="text-muted"><?= htmlspecialchars($user['email']) ?></p>
        <?php if (!empty($user['bio'])): ?>
          <p><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
        <?php endif; ?>
        <?php if ($view_user_id == $logged_in_user): ?>
          <a href="edit_profile.php" class="btn btn-outline-primary mb-2">Edit Profile</a>
          <a href="add_post.php" class="btn btn-primary mb-2">Create Post</a>
          <form action="delete_account.php" method="post" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');"><?= csrfField() ?>
            <button type="submit" name="delete_account" class="btn btn-danger w-100">Delete Account</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-md-8">
      <h4 class="mb-3">Posts by <?= htmlspecialchars($user['username']) ?></h4>
      <?php if ($posts->num_rows > 0): ?>
        <?php while ($post = $posts->fetch_assoc()): ?>
          <div class="card mb-3 shadow-sm">
            <div class="card-header d-flex align-items-center">
              <img src="uploads/<?= htmlspecialchars(($post['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $post['profile_image']) ?>" class="rounded-circle me-2" width="40" height="40" alt="Profile">
              <strong><?= htmlspecialchars($post['username']) ?></strong>
              <span class="ms-auto text-muted small"><?= date('F j, Y h:i A', strtotime($post['created_at'])) ?></span>
            </div>
            <div class="card-body">
              <p><?= nl2br(htmlspecialchars($post['content'] ?? '')) ?></p>
              <?php if (!empty($post['image'])): ?>
                <img src="uploads/<?= htmlspecialchars($post['image']) ?>" class="img-fluid rounded">
              <?php endif; ?>
              <div class="mt-2 d-flex justify-content-between">
                <a href="comment_post.php?post_id=<?= $post['id'] ?>" class="btn btn-sm btn-outline-secondary">Comment</a>
                <?php if ($logged_in_user == $post['user_id']): ?>
                  <form action="delete_post.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this post?')"><?= csrfField() ?><input type="hidden" name="post_id" value="<?= $post['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger">Delete</button></form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="text-muted">No posts available.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>
