<?php
require_once 'config.php';
require_once 'function.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Handle form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(inputText($_POST, 'username'));
    $email = trim(inputText($_POST, 'email'));
    $password = inputText($_POST, 'password');
    $confirm_password = inputText($_POST, 'confirm_password');

    // Validate fields
    if ($username === '' || $email === '' || $password === '' || $confirm_password === '') {
        $errors[] = "All fields are required.";
    } elseif (strlen($password) < 8) {
        $errors[] = 'Use a password with at least 8 characters.';
    } elseif (str_contains($password, "\0")) {
        $errors[] = 'Password contains an invalid character.';
    } elseif (strlen($username) > 50 || strlen($email) > 100 || strlen($password) > 72) {
        $errors[] = 'Username, email, or password is too long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    } else {
        // Check if email or username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Email or username already taken.";
        } else {
            // Hash password and insert
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $insert->bind_param("sss", $username, $email, $hashed_password);

            $conn->begin_transaction();
            try {
                $registered = $insert->execute();
                $registeredId=$insert->insert_id;
                notifyUser($registeredId,$registeredId,'account','Welcome to MiniSocial. Your notifications will appear here.');
                notifyStaff('users.view','A new member joined the community.',$registeredId,'registered:'.$registeredId,$registeredId);
                $conn->commit();
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                if ($e->getCode() !== 1062) { throw $e; }
                $registered = false;
            }
            if ($registered) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $insert->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = false;
                $_SESSION['auth_version'] = 0;
                header("Location: index.php");
                exit();
            } else {
                $errors[] = "Registration failed. Try again.";
            }
        }

        $stmt->close();
    }
}
?>

<!-- HTML Registration Form -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= csrfToken() ?>">
<title>Register - Mini Social Network</title>
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
<span class="eyebrow">Join the community</span>
<h1>Create your account</h1>
<p class="auth-intro">Start with a few details. Make it your own.</p>
<?php if (!empty($errors)): ?><div class="alert alert-danger" role="alert"><?= implode('<br>', $errors) ?></div><?php endif; ?>
<form action="" method="POST" data-loading-form><?= csrfField() ?>
<div class="mb-3"><label for="username">Username</label><input id="username" type="text" name="username" class="form-control" autocomplete="username" required value="<?= htmlspecialchars(inputText($_POST, 'username')) ?>"></div>
<div class="mb-3"><label for="email">Email address</label><input id="email" type="email" name="email" class="form-control" autocomplete="email" required value="<?= htmlspecialchars(inputText($_POST, 'email')) ?>"></div>
<div class="mb-3"><label for="password">Password</label><div class="password-wrap"><input id="password" type="password" name="password" minlength="8" aria-describedby="password-help" class="form-control" autocomplete="new-password" required><button type="button" class="icon-button" data-password-toggle="password" aria-label="Show password" aria-controls="password" aria-pressed="false"><i class="bx bx-show" aria-hidden="true"></i></button></div></div>
<div class="mb-3"><label for="confirm_password">Confirm password</label><div class="password-wrap"><input id="confirm_password" type="password" name="confirm_password" class="form-control" autocomplete="new-password" required><button type="button" class="icon-button" data-password-toggle="confirm_password" aria-label="Show password" aria-controls="confirm_password" aria-pressed="false"><i class="bx bx-show" aria-hidden="true"></i></button></div></div>

<p id="password-help" class="form-text">Use at least 8 characters. Passwords may contain up to 72 bytes.</p>
<button type="submit" class="btn btn-gold w-100">Create account<i class="bx bx-right-arrow-alt" aria-hidden="true"></i></button>
</form>
<p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
</section>
</main>
</body>
</html>
