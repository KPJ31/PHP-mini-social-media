<?php
require_once 'config.php';
require_once 'function.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(inputText($_POST, 'email'));
    $password = inputText($_POST, 'password');

    if ($email === '' || $password === '') {
        $errors[] = "Email and password are required.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $username, $hashed_password);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                // Set session
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                header("Location: index.php");
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= csrfToken() ?>">
    <meta charset="UTF-8">
    <title>Login - Mini Social Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 shadow p-4 bg-white rounded">
            <h2 class="text-center mb-4">Login</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?= implode("<br>", $errors); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST"><?= csrfField() ?>
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Login</button>
                <div class="text-center mt-3">
                    Don't have an account? <a href="register.php">Register here</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
