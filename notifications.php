<?php
require_once 'session.php';
require_once 'function.php';
$scope = notificationScope();
$viewer = (int)$_SESSION['user_id'];
$filter = inputText($_GET, 'filter') === 'unread' ? 'unread' : 'all';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = inputText($_POST, 'action');
    $id = inputId($_POST, 'id');
    if ($action === 'read_all') {
        dbRun('UPDATE notifications SET is_read=1 WHERE user_id=? AND ' . $scope, 'i', [$viewer]);
    } elseif ($action === 'read') {
        $notice = dbRun('SELECT id FROM notifications WHERE id=? AND user_id=? AND ' . $scope, 'ii', [$id, $viewer])->get_result()->fetch_assoc();
        if (!$notice) {
            failRequest(404, 'Notification not found.');
        }
        dbRun('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?', 'ii', [$id, $viewer]);
    } else {
        failRequest(422, 'Choose a valid notification action.');
    }
    header('Location: notifications.php?' . http_build_query(['filter' => $filter, 'page' => pageNumber()]));
    exit;
}
$offset = (pageNumber() - 1) * 20;
$rows = dbRun('SELECT * FROM notifications WHERE user_id=? AND ' . $scope . ($filter === 'unread' ? ' AND is_read=0' : '') . ' ORDER BY id DESC LIMIT 21 OFFSET ?', 'ii', [$viewer, $offset])->get_result()->fetch_all(MYSQLI_ASSOC);
$more = count($rows) > 20;
$rows = array_slice($rows, 0, 20);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
    <title>Notifications &middot; MiniSocial</title><?php include 'ui_assets.php'; ?>
</head>

<body class="<?= isStaff() ? 'admin-page' : 'bg-light' ?>">
    <?php include 'navbar.php'; ?><div class="container">
        <section class="card notification-inbox">
            <div class="notification-toolbar">
                <nav aria-label="Notification filter"><a href="notifications.php" <?= $filter === 'all' ? 'aria-current="page"' : '' ?>>All activity</a><a href="notifications.php?filter=unread" <?= $filter === 'unread' ? 'aria-current="page"' : '' ?>>Unread</a></nav>
                <form method="post"><?= csrfField() ?><button class="btn btn-outline-primary btn-sm" name="action" value="read_all">Mark all as read</button></form>
            </div>
            <?php if (!$rows): ?><div class="empty-state"><i class="bx bx-bell" aria-hidden="true"></i>
                    <h2>You're all caught up</h2>
                    <p><?= $filter === 'unread' ? 'There are no unread notifications.' : 'New activity will appear here as it happens.' ?></p>
                </div><?php else: ?><ul class="notification-list">
                    <?php foreach ($rows as $notice): $link = notificationLink($notice); ?><li class="notification-item <?= !$notice['is_read'] ? 'is-unread' : '' ?>"><span class="notification-symbol" aria-hidden="true"><i class="bx <?= ['like' => 'bx-heart', 'comment' => 'bx-comment-detail', 'message' => 'bx-envelope', 'friend_request' => 'bx-user-plus', 'friend_accepted' => 'bx-user-check', 'friend_post' => 'bx-image', 'moderation' => 'bx-shield-quarter', 'staff_activity' => 'bx-briefcase'][$notice['kind']] ?? 'bx-bell' ?>"></i></span>
                            <div class="notification-copy">
                                <p><?= htmlspecialchars($notice['title']) ?></p><time datetime="<?= date('c', strtotime($notice['created_at'])) ?>"><?= date('M j, Y g:i A', strtotime($notice['created_at'])) ?></time>
                                <div class="notification-links"><?php if ($link): ?><a href="<?= htmlspecialchars($link) ?>">View details</a><?php else: ?><span class="text-muted">This content is no longer available.</span><?php endif; ?><?php if (!$notice['is_read']): ?><form method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= $notice['id'] ?>"><button class="notification-read" name="action" value="read">Mark as read</button></form><?php else: ?><span class="text-muted">Read</span><?php endif; ?></div>
                            </div><?php if (!$notice['is_read']): ?><span class="unread-dot" role="img" aria-label="Unread"></span><?php endif; ?>
                        </li><?php endforeach; ?></ul><?php endif; ?><div class="px-3"><?= paginationLinks($more) ?></div>
        </section>
    </div><?php include 'footer.php'; ?></body>

</html>