<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/config.php';
foreach (['staff_role' => "VARCHAR(16) NOT NULL DEFAULT 'member'", 'staff_permissions' => 'TEXT NULL'] as $column => $definition) {
    if (!$conn->query("SHOW COLUMNS FROM users LIKE '$column'")->num_rows) {
        $conn->query("ALTER TABLE users ADD COLUMN $column $definition");
    }
}
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 actor_id INT NULL,
 kind VARCHAR(40) NOT NULL,
 title VARCHAR(255) NOT NULL,
 target_id INT NOT NULL DEFAULT 0,
 permission_key VARCHAR(40) NOT NULL DEFAULT '',
 event_key VARCHAR(120) NOT NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY notification_event (user_id,event_key),
 INDEX notification_inbox (user_id,is_read,id),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
