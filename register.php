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

            try {
                $registered = $insert->execute();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() !== 1062) { throw $e; }
                $registered = false;
            }
            if ($registered) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $insert->insert_id;
                $_SESSION['username'] = $username;
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= csrfToken() ?>">
    <meta charset="UTF-8">
    <title>Register - Mini Social Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 shadow p-4 bg-white rounded">
            <h2 class="text-center mb-4">Create an Account</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?= implode("<br>", $errors); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST"><?= csrfField() ?>
                <div class="mb-3">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Register</button>
                <div class="text-center mt-3">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
