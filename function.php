<?php
require_once 'workspace.php';

/**
 * Sanitize user input to prevent XSS and other attacks
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Get user info by user ID
 */
function getUserById($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT id, username, email, profile_image FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Check if two users are friends
 */
function areFriends($user1_id, $user2_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT 1 FROM friend_requests
        WHERE ((sender_id = ? AND receiver_id = ?)
           OR (sender_id = ? AND receiver_id = ?))
        AND status = 'accepted' AND EXISTS (SELECT 1 FROM users WHERE id = friend_requests.sender_id AND is_admin=0 AND staff_role='member' AND is_suspended=0) AND EXISTS (SELECT 1 FROM users WHERE id = friend_requests.receiver_id AND is_admin=0 AND staff_role='member' AND is_suspended=0) LIMIT 1
    ");
    $stmt->bind_param("iiii", $user1_id, $user2_id, $user2_id, $user1_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

/**
 * Count likes for a post
 */
function countLikes($post_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM likes WHERE post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'] ?? 0;
}

/**
 * Check if a user liked a post
 */
function userLikedPost($user_id, $post_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT 1 FROM likes WHERE user_id = ? AND post_id = ? LIMIT 1");
    $stmt->bind_param("ii", $user_id, $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

/**
 * Count comments for a post
 */
function countComments($post_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM comments WHERE post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'] ?? 0;
}

/**
 * Format datetime nicely
 */
function formatDateTime($datetime) {
    return date("H:i d/m/Y", strtotime($datetime));
}

/**
 * Upload profile image securely, returns filename or null
 */
function uploadProfileImage($file) {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])
        || filesize($file['tmp_name']) > 2 * 1024 * 1024) {
        return null;
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $dimensions = @getimagesize($file['tmp_name']);
    if (!isset($types[$type]) || !$dimensions || $dimensions[0] * $dimensions[1] > 40000000) {
        return null;
    }
    $name = bin2hex(random_bytes(16)) . '.' . $types[$type];
    return @move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $name) ? $name : null;
}
function requireFriend(int $userId, int $friendId): void {
    if (!$friendId || !areFriends($userId, $friendId)) {
        failRequest(403, 'Choose an accepted friend to chat with.');
    }
}

function pageNumber(string $key = 'page'): int {
    return min(100000, max(1, inputId($_GET, $key)));
}
function paginationLinks(bool $more, string $key = 'page'): string {
    $page = pageNumber($key);
    if ($page === 1 && !$more) { return ''; }
    $params = array_filter($_GET, static fn ($value) => is_string($value));
    $html = '<nav class="pagination-links" aria-label="Pagination">';
    foreach (['Previous' => $page - 1, 'Next' => $page + 1] as $label => $target) {
        if (($label === 'Previous' && $page === 1) || ($label === 'Next' && !$more)) { continue; }
        $params[$key] = $target;
        $html .= '<a class="btn btn-outline-primary" href="?' . htmlspecialchars(http_build_query($params)) . '">' . $label . '</a>';
    }
    return $html . '</nav>';
}
function removeUnusedUpload(?string $name): void {
    global $conn;
    if (!$name || basename($name) !== $name || in_array($name, ['default.png', 'default.svg'], true)) { return; }
    $check = $conn->prepare('SELECT 1 FROM users WHERE profile_image = ? UNION ALL SELECT 1 FROM posts WHERE image = ? LIMIT 1');
    $check->bind_param('ss', $name, $name);
    $check->execute();
    if ($check->get_result()->num_rows) { return; }
    $path = __DIR__ . '/uploads/' . $name;
    if (is_file($path) && !@unlink($path)) { error_log('Unable to remove unreferenced upload.'); }
}
function conversationMessages(int $viewer, int $friend, int $before = 0): array {
    global $conn;
    $upper = $before ?: PHP_INT_MAX;
    $stmt = $conn->prepare('SELECT m.*, u.username FROM messages m JOIN users u ON u.id = m.sender_id
        WHERE ((m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_sender = 0)
            OR (m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_receiver = 0))
        AND m.id < ? ORDER BY m.id DESC LIMIT 101');
    $stmt->bind_param('iiiii', $viewer, $friend, $friend, $viewer, $upper);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $more = count($rows) > 100;
    $rows = array_reverse(array_slice($rows, 0, 100));
    if ($rows) {
        $min = $rows[0]['id'];
        $max = $rows[count($rows) - 1]['id'];
        $read = $conn->prepare('UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND id BETWEEN ? AND ? AND deleted_by_receiver = 0 AND is_read = 0');
        $read->bind_param('iiii', $viewer, $friend, $min, $max);
        $read->execute();
    }
    return ['rows' => $rows, 'more' => $more];
}
