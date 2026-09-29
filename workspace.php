<?php
require_once __DIR__ . '/config.php';
function dbRun(string $sql, string $types = '', array $values = []): mysqli_stmt {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$values); }
    $stmt->execute(); return $stmt;
}
function isStaff(?array $user = null): bool {
    $user ??= $GLOBALS['currentUser'] ?? [];
    return !empty($user['is_admin']) || in_array($user['staff_role'] ?? '', ['manager','employee'], true);
}
function permissionCatalog(): array {
    return ['users.view'=>'View member accounts','users.manage'=>'Edit, suspend and delete members',
        'posts.view'=>'View posts','posts.manage'=>'Edit and delete posts',
        'comments.view'=>'View comments','comments.manage'=>'Edit and delete comments',
        'messages.view'=>'View private messages','messages.manage'=>'Delete private messages',
        'audit.view'=>'View management activity'];
}
function canManage(string $permission, ?array $user = null): bool {
    $user ??= $GLOBALS['currentUser'] ?? [];
    if (!empty($user['is_admin'])) { return true; }
    if (!isStaff($user) || !isset(permissionCatalog()[$permission])) { return false; }
    $permissions = json_decode($user['staff_permissions'] ?? '[]', true);
    return is_array($permissions) && in_array($permission, $permissions, true);
}
function requirePermission(string $permission): void {
    if (!canManage($permission)) { failRequest(403, 'You do not have permission for this action.'); }
}
function staffLabel(?array $user = null): string {
    $user ??= $GLOBALS['currentUser'] ?? [];
    return !empty($user['is_admin']) ? 'Administrator' : ucfirst($user['staff_role'] ?? 'member');
}
function workspaceTabs(): array {
    $tabs=['overview'=>'Overview'];
    foreach (['users'=>'Members','posts'=>'Posts','comments'=>'Comments','messages'=>'Messages','audit'=>'Activity log'] as $key=>$label) {
        if (canManage($key.'.view')) { $tabs[$key]=$label; }
    }
    if (!empty($GLOBALS['currentUser']['is_admin'])) { $tabs['team']='Team & permissions'; }
    $tabs['account']='My account';
    return $tabs;
}
function notifyUser(int $recipient, ?int $actor, string $kind, string $title, int $target = 0, string $event = '', string $permission = ''): void {
    if ($recipient <= 0) { return; }
    $event = $event ?: bin2hex(random_bytes(16));
    // INSERT SELECT safely skips a recipient deleted concurrently; duplicate events are no-ops.
    dbRun('INSERT INTO notifications (user_id,actor_id,kind,title,target_id,permission_key,event_key)
        SELECT id, ?, ?, ?, ?, ?, ? FROM users WHERE id=?
        ON DUPLICATE KEY UPDATE event_key=VALUES(event_key)', 'ississi', [$actor,$kind,mb_substr($title,0,255),$target,$permission,$event,$recipient]);
}
function notifyStaff(string $permission, string $title, int $target, string $event, ?int $actor = null): void {
    $staff = dbRun("SELECT id,is_admin,staff_role,staff_permissions FROM users WHERE is_suspended=0 AND (is_admin=1 OR staff_role IN ('manager','employee'))")->get_result();
    while ($person = $staff->fetch_assoc()) {
        if ((int)$person['id'] !== $actor && canManage($permission,$person)) {
            notifyUser((int)$person['id'],$actor,'staff_activity',$title,$target,$event,$permission);
        }
    }
}
function notificationScope(): string {
    $allowed=["''"];
    foreach (array_merge(array_keys(permissionCatalog()), ['team']) as $permission) {
        if (canManage($permission)) { $allowed[]="'".$permission."'"; }
    }
    return 'permission_key IN ('.implode(',',$allowed).')';
}
function unreadNotifications(): int {
    return (int) dbRun('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0 AND '.notificationScope(),'i',[(int)$_SESSION['user_id']])->get_result()->fetch_row()[0];
}
function notificationLink(array $notice): ?string {
    $id=(int)$notice['target_id'];
    if ($notice['permission_key'] !== '') {
        if (!canManage($notice['permission_key'])) { return null; }
        return 'admin.php?tab='.($notice['permission_key']==='team'?'team':explode('.',$notice['permission_key'])[0]);
    }
    if (isStaff()) { return 'admin.php?tab=account'; }
    if (in_array($notice['kind'],['like','comment','friend_post'],true)) {
        return dbRun('SELECT id FROM posts WHERE id=?','i',[$id])->get_result()->num_rows ? 'comment_post.php?post_id='.$id : null;
    }
    if (in_array($notice['kind'],['friend_request','friend_accepted'],true)) { return 'friend_list.php'; }
    if ($notice['kind']==='message') {
        $actor=(int)$notice['actor_id'];
        return dbRun("SELECT id FROM friend_requests WHERE status='accepted' AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))",'iiii',[$actor,(int)$_SESSION['user_id'],(int)$_SESSION['user_id'],$actor])->get_result()->num_rows ? 'chat.php?user_id='.$actor : null;
    }
    return 'profile.php';
}
