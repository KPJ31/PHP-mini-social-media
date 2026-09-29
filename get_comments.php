<?php
require_once 'config.php';
require_once 'session.php';

$post_id = inputId($_GET, 'post_id');

$stmt = $conn->prepare("
    SELECT c.*, u.username, u.profile_image 
    FROM comments c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.post_id = ? 
    ORDER BY c.created_at ASC
");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo '<div class="mb-2">';
    echo '<strong>' . htmlspecialchars($row['username']) . '</strong>: ';
    echo htmlspecialchars($row['comment']);
    echo '<br><small class="text-muted">' . date("H:i d/m", strtotime($row['created_at'])) . '</small>';
    echo '</div>';
}
?>
