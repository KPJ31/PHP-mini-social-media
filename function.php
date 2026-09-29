<?php
require_once 'config.php';

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
        AND status = 'accepted' LIMIT 1
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
    if (!isset($types[$type]) || @getimagesize($file['tmp_name']) === false) {
        return null;
    }
    $name = bin2hex(random_bytes(16)) . '.' . $types[$type];
    return move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $name) ? $name : null;
}
function requireFriend(int $userId, int $friendId): void {
    if (!$friendId || !areFriends($userId, $friendId)) {
        failRequest(403, 'Choose an accepted friend to chat with.');
    }
}
