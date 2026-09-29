<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success = "";

// Handle post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim(inputText($_POST, 'content'));
    $image = '';

    // Validate post content
    if ($content === '') {
        $errors[] = "Post content cannot be empty.";
    }

    if (strlen($content) > 65535) {
        $errors[] = 'Post content is too long.';
    }
    if (!$errors && isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $image = uploadProfileImage($_FILES['image']);
        if ($image === null) {
            $errors[] = 'Upload a valid JPG, PNG, or GIF image no larger than 2MB.';
        }
    }

    // Insert into database
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, content, image, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $user_id, $content, $image);
        if ($stmt->execute()) {
            $success = "Post created successfully!";
            header("Location: index.php");
            exit();
        } else {
            $errors[] = "Error saving post.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= csrfToken() ?>">
  <meta charset="UTF-8">
  <title>Create Post - Mini Social Network</title>
<?php include 'ui_assets.php'; ?>
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-lg-8 content-card form-panel">
      <h2 class="mb-4">Your next story</h2>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert"><?= implode("<br>", $errors); ?></div>
      <?php elseif (!empty($success)): ?>
        <div class="alert alert-success" role="status"><?= $success; ?></div>
      <?php endif; ?>

      <form action="" method="POST" enctype="multipart/form-data" data-loading-form><?= csrfField() ?>
        <div class="mb-3">
          <label for="content" class="form-label">What's on your mind?</label>
          <textarea name="content" id="content" class="form-control" rows="4" required></textarea>
        </div>

        <div class="mb-3">
          <label for="image" class="form-label">Upload Image (optional)</label>
          <input type="file" name="image" id="image" data-preview="post-preview" aria-describedby="image-help" class="form-control" accept="image/jpeg,image/png,image/gif"><p id="image-help" class="form-text">JPG, PNG, or GIF. Up to 2 MB.</p><img id="post-preview" class="upload-preview" alt="Selected post image preview" hidden>
        </div>

        <div class="form-actions"><button type="submit" class="btn btn-gold">Publish post <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></button>
        <a href="index.php" class="btn btn-secondary">Cancel</a></div>
      </form>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
