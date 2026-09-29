<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';

$user_id = $_SESSION['user_id'];

// Fetch current user info
$stmt = $conn->prepare("SELECT username, email, bio, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) { failRequest(404, 'User not found.'); }

$success = $error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $username = trim(inputText($_POST, 'username'));
    $email = trim(inputText($_POST, 'email'));
    $bio = inputText($_POST, 'bio');
    $new_image = $user['profile_image'];
    if ($username === '' || strlen($username) > 50 || strlen($email) > 100
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($bio) > 65535) {
        $error = 'Enter a valid username and email, and keep your bio within 65535 bytes.';
    }
    if (!$error) {
        $check = $conn->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?');
        $check->bind_param('ssi', $username, $email, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows) {
            $error = 'Email or username already taken.';
        }
    }
    if (!$error && isset($_FILES['profile_image']) && ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $new_image = uploadProfileImage($_FILES['profile_image']);
        if ($new_image === null) {
            $error = 'Upload a valid JPG, PNG, or GIF image no larger than 2MB.';
        }
    }
    if (!$error) {
        try {
            $update = $conn->prepare('UPDATE users SET username = ?, email = ?, bio = ?, profile_image = ? WHERE id = ?');
            $update->bind_param('ssssi', $username, $email, $bio, $new_image, $user_id);
            $update->execute();
            $_SESSION['username'] = $username;
            $success = 'Profile updated successfully.';
            $user = array_merge($user, compact('username', 'email', 'bio'), ['profile_image' => $new_image]);
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() !== 1062) { throw $e; }
            $error = 'Email or username already taken.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?= csrfToken() ?>">
  <meta charset="UTF-8">
  <title>Edit Profile - Mini Social Network</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5.3 CSS -->
<?php include 'ui_assets.php'; ?>
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">

      <div class="card form-panel">
        <h2>Profile details</h2>

        <?php if ($success): ?>
          <div class="alert alert-success" role="status"><?= $success ?></div>
        <?php elseif ($error): ?>
          <div class="alert alert-danger" role="alert"><?= $error ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" data-loading-form><?= csrfField() ?>
          <input type="hidden" name="update_profile" value="1">

          <div class="mb-3">
            <label class="form-label" for="username">Username</label>
            <input type="text" name="username" id="username" value="<?= htmlspecialchars($user['username']) ?>" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-control" required>
          </div>

          <div class="mb-3">
            <label for="bio">Bio</label>
            <textarea name="bio" id="bio" class="form-control" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label d-block" for="profile_image">Profile Image</label>
            <img loading="lazy" src="uploads/<?= htmlspecialchars(($user['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $user['profile_image']) ?>" width="100" height="100" class="rounded-circle mb-2 border" alt="Profile image">
            <input type="file" name="profile_image" id="profile_image" class="form-control" accept="image/jpeg,image/png,image/gif" data-preview="avatar-preview" aria-describedby="avatar-help"><p id="avatar-help" class="form-text">JPG, PNG, or GIF. Up to 2 MB.</p><img id="avatar-preview" class="upload-preview" alt="Selected profile image preview" hidden>
          </div>

          <div class="form-actions">
            <div>
              <button type="submit" class="btn btn-gold">Save Changes</button>
              <a href="profile.php" class="btn btn-secondary ms-2">Cancel</a>
            </div>
            
          </div>
          <br>
        </form>
      </div>

    </div>
  </div>
</div>

<!-- Bootstrap JS Bundle -->

<?php include 'footer.php'; ?>
</body>
</html>

