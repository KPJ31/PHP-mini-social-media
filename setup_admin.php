<?php
// Run only from the terminal: php setup_admin.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/config.php';
foreach (['is_admin' => 'TINYINT(1) NOT NULL DEFAULT 0', 'is_suspended' => 'TINYINT(1) NOT NULL DEFAULT 0', 'auth_version' => 'INT NOT NULL DEFAULT 0'] as $column => $definition) {
    $exists = $conn->query("SHOW COLUMNS FROM users LIKE '$column'");
    if (!$exists->num_rows) {
        $conn->query("ALTER TABLE users ADD COLUMN $column $definition");
    }
}
$conn->query("CREATE TABLE IF NOT EXISTS admin_audit (id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT NOT NULL, action VARCHAR(50) NOT NULL, target_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
require_once __DIR__ . '/migrate_workspace.php';
$email = 'admin@mini.com';
$stmt = $conn->prepare('SELECT id, is_admin FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
if ($existing) {
    if (!$existing['is_admin']) {
        fwrite(STDERR, "This email belongs to an existing member. No account was promoted or password changed.\n");
        exit(1);
    }
    echo "Admin already configured. Password unchanged.\n";
    exit;
}
$password = 'Mini!' . bin2hex(random_bytes(9));
$hash = password_hash($password, PASSWORD_DEFAULT);
$name = 'mini_admin_' . bin2hex(random_bytes(3));
$stmt = $conn->prepare('INSERT INTO users (username,email,password,is_admin) VALUES (?,?,?,1)');
$stmt->bind_param('sss', $name, $email, $hash);
$stmt->execute();
echo "Admin created.\nEmail: $email\nPassword: $password\nSign in at login.php. Change your password from the dashboard.\n";
