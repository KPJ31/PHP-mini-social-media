<?php
require_once 'session.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
$scope = notificationScope();
$latest = dbRun('SELECT COALESCE(MAX(id),0) FROM notifications WHERE user_id=? AND ' . $scope, 'i', [(int)$_SESSION['user_id']])->get_result()->fetch_row()[0];
echo json_encode(['unread' => unreadNotifications(), 'latest' => (int)$latest]);
