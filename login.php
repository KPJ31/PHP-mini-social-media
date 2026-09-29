<?php
require_once 'config.php';
require_once 'function.php';
require_once 'auth_throttle.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    recordLoginAttempt();
    $email = trim(inputText($_POST, 'email'));
    $password = inputText($_POST, 'password');

    if ($email === '' || $password === '') {
        $errors[] = "Email and password are required.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, is_admin, is_suspended, auth_version FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $username, $hashed_password, $is_admin, $is_suspended, $auth_version);
            $stmt->fetch();

            if (!$is_suspended && password_verify($password, $hashed_password)) {
                // Set session
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = (bool) $is_admin;
                $_SESSION['auth_version'] = (int) $auth_version;
                header('Location: ' . ($is_admin ? 'admin.php' : 'index.php'));
                exit();
            } else {
                $errors[] = "Invalid email or password.";
            }
        } else {
            $errors[] = "Invalid email or password.";
        }

        $stmt->close();
    }
}
?>

<!-- HTML Login Form -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= csrfToken() ?>">
<title>Login - Mini Social Network</title>
<?php include 'ui_assets.php'; ?>
</head>
<body class="auth-page">
<main class="auth-shell" id="main-content">
<section class="auth-visual" aria-label="Welcome to MiniSocial">
<a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">m<span>.</span></span>MiniSocial<span class="brand-dot">.</span></a>
<h2>Little moments.<br><span>Real connections.</span></h2>
<p>Share your day, find your people, and make room for a good conversation.</p>
<div class="auth-art" aria-hidden="true"><div class="auth-art-row"><span class="avatar-initial">m.</span><div class="auth-art-lines"><span></span><span></span></div></div><div class="auth-art-lines"><span></span><span></span></div><div class="auth-art-note"><i class="bx bx-heart"></i> A little closer, every day.</div></div>
<p class="auth-tagline">YOUR PEOPLE. YOUR STORIES. YOUR SPACE.</p>
</section>
<section class="auth-form">
<span class="eyebrow">Your community awaits</span>
<h1>Welcome back</h1>
<p class="auth-intro">Log in to catch up with your community.</p>
<?php if (!empty($errors)): ?><div class="alert alert-danger" role="alert"><?= implode('<br>', $errors) ?></div><?php endif; ?>
<form action="" method="POST" data-loading-form><?= csrfField() ?>
<div class="mb-3"><label for="email">Email address</label><input id="email" type="email" name="email" class="form-control" autocomplete="email" required value="<?= htmlspecialchars(inputText($_POST, 'email')) ?>"></div>
<div class="mb-3"><label for="password">Password</label><div class="password-wrap"><input id="password" type="password" name="password" class="form-control" autocomplete="current-password" required><button type="button" class="icon-button" data-password-toggle="password" aria-label="Show password" aria-controls="password" aria-pressed="false"><i class="bx bx-show" aria-hidden="true"></i></button></div></div>

<button type="submit" class="btn btn-gold w-100">Log in<i class="bx bx-right-arrow-alt" aria-hidden="true"></i></button>
</form>
<p class="auth-switch">New to MiniSocial? <a href="register.php">Create an account</a></p>
</section>
</main>
</body>
</html>
