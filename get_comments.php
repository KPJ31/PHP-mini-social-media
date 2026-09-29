<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';

$offset = (pageNumber() - 1) * 50;
$shown = 0;
$post_id = inputId($_GET, 'post_id');

$stmt = $conn->prepare("
    SELECT c.*, u.username, u.profile_image 
    FROM comments c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.post_id = ? 
    ORDER BY c.id ASC LIMIT 51 OFFSET ?
");
$stmt->bind_param("ii", $post_id, $offset);
$stmt->execute();
$result = $stmt->get_result();

while (($row = $result->fetch_assoc()) && $shown++ < 50) {
    echo '<div class="mb-2">';
    echo '<strong>' . htmlspecialchars($row['username']) . '</strong>: ';
    echo htmlspecialchars($row['comment']);
    echo '<br><small class="text-muted">' . date("H:i d/m", strtotime($row['created_at'])) . '</small>';
    echo '</div>';
}
if ($result->num_rows > 50 || pageNumber() > 1) {
    echo '<a href="comment_post.php?post_id=' . $post_id . '">View the full conversation</a>';
}

