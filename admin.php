<?php
require_once 'session.php';
require_once 'function.php';
if (!isStaff()) {
    failRequest(403, 'Management access required.');
}
function esc($value): string
{
    return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function accountValues(bool $requirePassword = false): array
{
    $name = trim(inputText($_POST, 'username'));
    $email = trim(inputText($_POST, 'email'));
    $bio = inputText($_POST, 'bio');
    $password = inputText($_POST, 'password');
    if ($name === '' || strlen($name) > 50 || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($bio) > 65535) {
        throw new RuntimeException('Enter a valid username and email. Bio must be within 65535 bytes.');
    }
    if (($requirePassword || $password !== '') && (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0"))) {
        throw new RuntimeException('Use a password containing 12 to 72 bytes.');
    }
    return [$name, $email, $bio, $password];
}
function staffValues(): array
{
    $role = inputText($_POST, 'staff_role');
    if (!in_array($role, ['manager', 'employee'], true)) {
        throw new RuntimeException('Choose manager or employee.');
    }
    $permissions = $_POST['permissions'] ?? [];
    if (!is_array($permissions) || array_filter($permissions, fn($key) => !is_string($key) || !isset(permissionCatalog()[$key]))) {
        throw new RuntimeException('Choose valid permissions.');
    }
    foreach ($permissions as $key) {
        if (str_ends_with($key, '.manage')) {
            $permissions[] = str_replace('.manage', '.view', $key);
        }
    }
    return [$role, json_encode(array_values(array_unique($permissions)))];
}
function accountFields(array $row, string $prefix, bool $new = false): void
{ ?>
    <label for="<?= $prefix ?>-name">Username</label><input class="form-control" id="<?= $prefix ?>-name" name="username" value="<?= esc($row['username'] ?? '') ?>" maxlength="50" required>
    <label for="<?= $prefix ?>-email">Email address</label><input class="form-control" id="<?= $prefix ?>-email" name="email" type="email" value="<?= esc($row['email'] ?? '') ?>" maxlength="100" required>
    <label for="<?= $prefix ?>-bio">Bio / team notes</label><textarea class="form-control" id="<?= $prefix ?>-bio" name="bio" rows="2"><?= esc($row['bio'] ?? '') ?></textarea>
    <label for="<?= $prefix ?>-password"><?= $new ? 'Initial password' : 'New password (optional)' ?></label><input class="form-control" id="<?= $prefix ?>-password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" <?= $new ? 'required' : '' ?>>
    <p class="form-text">Use 12-72 bytes. <?= $new ? 'Share the login details with this team member.' : 'Leave blank to keep the current password.' ?></p>
<?php }
function permissionFields(array $row, string $prefix): void
{
    $selected = json_decode($row['staff_permissions'] ?? '[]', true) ?: []; ?>
    <label for="<?= $prefix ?>-role">Team role</label><select class="form-select" name="staff_role" id="<?= $prefix ?>-role">
        <option value="employee" <?= ($row['staff_role'] ?? '') === 'employee' ? 'selected' : '' ?>>Employee</option>
        <option value="manager" <?= ($row['staff_role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
    </select>
    <fieldset class="permission-grid">
        <legend>Allowed actions</legend><?php foreach (permissionCatalog() as $key => $label): ?><label class="permission-choice"><input type="checkbox" name="permissions[]" value="<?= $key ?>" <?= in_array($key, $selected, true) ? 'checked' : '' ?>><span><?= esc($label) ?></span></label><?php endforeach; ?>
    </fieldset>
    <p class="form-text">Manage permissions also allow viewing that section. Both roles receive only the permissions selected here. Only administrators can manage the team.</p>
<?php }
$tabs = workspaceTabs();
$tab = inputText($_GET, 'tab') ?: 'overview';
if (!isset($tabs[$tab])) {
    failRequest(403, 'You do not have access to this section.');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = inputText($_POST, 'action');
    $id = inputId($_POST, 'id');
    $uploads = [];
    $permissions = [
        'create_staff' => 'team',
        'save_staff' => 'team',
        'suspend_staff' => 'team',
        'restore_staff' => 'team',
        'delete_staff' => 'team',
        'save_user' => 'users.manage',
        'suspend' => 'users.manage',
        'restore' => 'users.manage',
        'delete_user' => 'users.manage',
        'save_post' => 'posts.manage',
        'delete_post' => 'posts.manage',
        'save_comment' => 'comments.manage',
        'delete_comment' => 'comments.manage',
        'delete_message' => 'messages.manage',
        'save_self' => ''
    ];
    if (!array_key_exists($action, $permissions)) {
        failRequest(422, 'Choose a valid action.');
    }
    if ($permissions[$action] !== '') {
        requirePermission($permissions[$action]);
    }
    $conn->begin_transaction();
    try {
        $actor = dbRun('SELECT * FROM users WHERE id=? FOR UPDATE', 'i', [(int)$_SESSION['user_id']])->get_result()->fetch_assoc();
        if (!$actor || $actor['is_suspended'] || (int)$actor['auth_version'] !== (int)$_SESSION['auth_version'] || ($permissions[$action] !== '' && !canManage($permissions[$action], $actor))) {
            throw new RuntimeException('Your access changed. Sign in again.');
        }
        $owner = 0;
        $self = false;
        if ($action === 'create_staff') {
            [$name, $email, $bio, $password] = accountValues(true);
            [$role, $grants] = staffValues();
            $insert = dbRun('INSERT INTO users (username,email,bio,password,staff_role,staff_permissions) VALUES (?,?,?,?,?,?)', 'ssssss', [$name, $email, $bio, password_hash($password, PASSWORD_DEFAULT), $role, $grants]);
            $id = $insert->insert_id;
            $owner = $id;
        } elseif (in_array($action, ['save_self', 'save_user', 'suspend', 'restore', 'delete_user', 'save_staff', 'suspend_staff', 'restore_staff', 'delete_staff'], true)) {
            $self = $action === 'save_self';
            if ($self) {
                $id = (int)$_SESSION['user_id'];
            }
            $target = dbRun('SELECT * FROM users WHERE id=? FOR UPDATE', 'i', [$id])->get_result()->fetch_assoc();
            if (!$target) {
                throw new RuntimeException('Account not found.');
            }
            $teamAction = str_ends_with($action, '_staff');
            if (!$self && (!empty($target['is_admin']) || ($teamAction ? !isStaff($target) : isStaff($target)))) {
                throw new RuntimeException('This account is protected. Use the appropriate management section.');
            }
            if (in_array($action, ['save_self', 'save_user', 'save_staff'], true)) {
                [$name, $email, $bio, $password] = accountValues();
                dbRun('UPDATE users SET username=?,email=?,bio=? WHERE id=?', 'sssi', [$name, $email, $bio, $id]);
                if ($action === 'save_staff') {
                    [$role, $grants] = staffValues();
                    dbRun('UPDATE users SET staff_role=?,staff_permissions=?,auth_version=auth_version+1 WHERE id=?', 'ssi', [$role, $grants, $id]);
                }
                if ($password !== '') {
                    dbRun('UPDATE users SET password=?,auth_version=auth_version+1 WHERE id=?', 'si', [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
                $owner = $id;
            } elseif (in_array($action, ['suspend', 'restore', 'suspend_staff', 'restore_staff'], true)) {
                dbRun('UPDATE users SET is_suspended=?,auth_version=auth_version+1 WHERE id=?', 'ii', [str_starts_with($action, 'suspend') ? 1 : 0, $id]);
                $owner = $id;
            } else {
                $uploads = dbRun('SELECT profile_image AS image FROM users WHERE id=? UNION SELECT image FROM posts WHERE user_id=?', 'ii', [$id, $id])->get_result()->fetch_all(MYSQLI_ASSOC);
                dbRun('DELETE FROM likes WHERE user_id=? OR post_id IN (SELECT id FROM posts WHERE user_id=?)', 'ii', [$id, $id]);
                dbRun('DELETE FROM users WHERE id=?', 'i', [$id]);
            }
        } else {
            $table = str_contains($action, 'post') ? 'posts' : (str_contains($action, 'comment') ? 'comments' : 'messages');
            $target = dbRun("SELECT * FROM $table WHERE id=? FOR UPDATE", 'i', [$id])->get_result()->fetch_assoc();
            if (!$target) {
                throw new RuntimeException('This content no longer exists.');
            }
            $owner = (int)($target['user_id'] ?? $target['sender_id']);
            if (str_starts_with($action, 'save_')) {
                $content = trim(inputText($_POST, 'content'));
                if (($content === '' && ($table !== 'posts' || empty($target['image']))) || strlen($content) > 65535) {
                    throw new RuntimeException('Enter content within 65535 bytes.');
                }
                $column = $table === 'posts' ? 'content' : 'comment';
                dbRun("UPDATE $table SET $column=? WHERE id=?", 'si', [$content, $id]);
            } else {
                if ($table === 'posts') {
                    $uploads[] = ['image' => $target['image']];
                    dbRun('DELETE FROM likes WHERE post_id=?', 'i', [$id]);
                    dbRun('DELETE FROM comments WHERE post_id=?', 'i', [$id]);
                }
                dbRun("DELETE FROM $table WHERE id=?", 'i', [$id]);
                if ($table === 'messages' && (int)$target['receiver_id'] !== $owner) {
                    notifyUser((int)$target['receiver_id'], (int)$_SESSION['user_id'], 'moderation', 'A message in your conversation was removed by the moderation team.');
                }
            }
        }
        $audit = dbRun('INSERT INTO admin_audit (admin_id,action,target_id) VALUES (?,?,?)', 'isi', [(int)$_SESSION['user_id'], $action, $id])->insert_id;
        if ($owner) {
            $titles = [
                'create_staff' => 'Your team account is ready.',
                'save_staff' => 'Your team account or permissions were updated. Sign in again to use your current access.',
                'save_self' => 'Your account settings were updated.',
                'save_user' => 'Your account details were updated by the management team.',
                'suspend' => 'Your account was suspended.',
                'restore' => 'Your account access was restored.',
                'suspend_staff' => 'Your team account was suspended.',
                'restore_staff' => 'Your team account access was restored.',
                'save_post' => 'Your post was edited by the moderation team.',
                'delete_post' => 'Your post was removed by the moderation team.',
                'save_comment' => 'Your comment was edited by the moderation team.',
                'delete_comment' => 'Your comment was removed by the moderation team.',
                'delete_message' => 'Your message was removed by the moderation team.'
            ];
            notifyUser($owner, (int)$_SESSION['user_id'], 'moderation', $titles[$action] ?? 'Your account was updated.', $id, 'audit:' . $audit);
        }
        notifyStaff('audit.view', staffLabel() . ' performed ' . str_replace('_', ' ', $action) . ' on #' . $id . '.', $audit, 'audit:' . $audit, (int)$_SESSION['user_id']);
        $conn->commit();
        if ($self) {
            $_SESSION['username'] = $name;
            if ($password !== '') {
                $_SESSION['auth_version'] = (int)$target['auth_version'] + 1;
                session_regenerate_id(true);
            }
        }
        foreach ($uploads as $upload) {
            removeUnusedUpload($upload['image']);
        }
        $_SESSION['admin_notice'] = 'Changes saved successfully.';
        header('Location: admin.php?' . http_build_query(['tab' => $tab, 'q' => inputText($_GET, 'q'), 'page' => pageNumber()]));
        exit;
    } catch (RuntimeException $e) {
        $conn->rollback();
        if ($e instanceof mysqli_sql_exception) {
            if ($e->getCode() !== 1062) {
                throw $e;
            }
            $error = 'This username or email is already in use.';
        } else {
            $error = $e->getMessage();
        }
    }
}
$notice = $_SESSION['admin_notice'] ?? '';
unset($_SESSION['admin_notice']);
$counts = [];
foreach (['users', 'posts', 'comments', 'messages'] as $table) {
    if (isset($tabs[$table])) {
        $counts[$table] = (int)$conn->query('SELECT COUNT(*) FROM ' . $table . ($table === 'users' ? " WHERE is_admin=0 AND staff_role='member'" : ''))->fetch_row()[0];
    }
}
if (isset($tabs['team'])) {
    $counts['team'] = (int)$conn->query("SELECT COUNT(*) FROM users WHERE is_admin=1 OR staff_role IN ('manager','employee')")->fetch_row()[0];
}
$q = mb_substr(trim(inputText($_GET, 'q')), 0, 100);
$search = '%' . $q . '%';
$offset = (pageNumber() - 1) * 20;
$rows = [];
$more = false;
$queries = [
    'users' => "SELECT * FROM users WHERE is_admin=0 AND staff_role='member' AND (username LIKE ? OR email LIKE ?) ORDER BY id DESC LIMIT 21 OFFSET ?",
    'team' => "SELECT * FROM users WHERE (is_admin=1 OR staff_role IN ('manager','employee')) AND (username LIKE ? OR email LIKE ?) ORDER BY id DESC LIMIT 21 OFFSET ?",
    'posts' => 'SELECT p.*,u.username FROM posts p JOIN users u ON u.id=p.user_id WHERE p.content LIKE ? OR u.username LIKE ? ORDER BY p.id DESC LIMIT 21 OFFSET ?',
    'comments' => 'SELECT c.*,u.username FROM comments c JOIN users u ON u.id=c.user_id WHERE c.comment LIKE ? OR u.username LIKE ? ORDER BY c.id DESC LIMIT 21 OFFSET ?',
    'messages' => 'SELECT m.*,u.username,r.username AS recipient FROM messages m JOIN users u ON u.id=m.sender_id JOIN users r ON r.id=m.receiver_id WHERE m.message LIKE ? OR u.username LIKE ? ORDER BY m.id DESC LIMIT 21 OFFSET ?',
    'audit' => 'SELECT a.*,u.username FROM admin_audit a LEFT JOIN users u ON u.id=a.admin_id WHERE a.action LIKE ? OR u.username LIKE ? ORDER BY a.id DESC LIMIT 21 OFFSET ?'
];
if (isset($queries[$tab])) {
    $rows = dbRun($queries[$tab], 'ssi', [$search, $search, $offset])->get_result()->fetch_all(MYSQLI_ASSOC);
    $more = count($rows) > 20;
    $rows = array_slice($rows, 0, 20);
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
    <title>Management workspace &middot; MiniSocial</title><?php include 'ui_assets.php'; ?>
</head>

<body class="admin-page">
    <?php include 'navbar.php'; ?><div class="container">
        <?php if ($notice): ?><div class="alert alert-success" role="status"><?= esc($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger" role="alert"><?= esc($error) ?></div><?php endif; ?>
        <?php if ($tab === 'overview'): ?><div class="admin-banner">
                <div><span class="eyebrow">Your management workspace</span>
                    <h2>Keep your community moving forward.</h2>
                    <p>Manage people, review activity, and give your team the right access.</p>
                </div><span class="admin-badge"><i class="bx bx-shield-quarter" aria-hidden="true"></i><?= staffLabel() ?></span>
            </div>
            <div class="admin-stats"><?php foreach ($counts as $key => $count): ?><a class="stat-card" href="admin.php?tab=<?= $key ?>"><span><?= $tabs[$key] ?></span><strong><?= number_format($count) ?></strong><small>Open section &nearr;</small></a><?php endforeach; ?><a class="stat-card" href="notifications.php"><span>Unread notifications</span><strong><?= unreadNotifications() ?></strong><small>Open inbox &nearr;</small></a></div>
            <div class="admin-overview">
                <section class="card card-body"><span class="eyebrow">Your access</span>
                    <h2><?= staffLabel() ?> workspace</h2>
                    <p><?= !empty($currentUser['is_admin']) ? 'You control team accounts and permissions across the community.' : 'Your administrator assigns access to each management section. Contact them if you need additional permissions.' ?></p>
                    <div class="access-chips"><?php foreach (permissionCatalog() as $key => $label): if (canManage($key)): ?><span class="status-chip"><?= esc($label) ?></span><?php endif;
                                                                                                                                                                        endforeach; ?></div>
                </section>
                <section class="card card-body"><span class="eyebrow"><?= isset($tabs['team']) ? 'Team management' : 'Stay informed' ?></span>
                    <h2><?= isset($tabs['team']) ? 'The right people. The right access.' : 'All your updates in one place.' ?></h2>
                    <p><?= isset($tabs['team']) ? 'Create manager and employee accounts, choose exactly what they can view or manage, and update access whenever responsibilities change.' : 'Notifications bring together account updates and activity in the sections you are allowed to manage.' ?></p><a class="btn btn-primary" href="<?= isset($tabs['team']) ? 'admin.php?tab=team' : 'notifications.php' ?>"><?= isset($tabs['team']) ? 'Manage team' : 'View notifications' ?></a>
                </section>
            </div>
        <?php elseif ($tab === 'account'): ?><section class="card card-body workspace-account">
                <h2>My account</h2>
                <p class="text-muted">Update your details. Changing your password signs out your other sessions.</p>
                <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="save_self"><?php accountFields($currentUser, 'self'); ?><button class="btn btn-primary" type="submit">Save account</button></form>
            </section>
        <?php else: ?>
            <?php if ($tab === 'team'): ?><section class="card card-body team-create">
                    <div><span class="eyebrow">Build your team</span>
                        <h2>Managers &amp; employees</h2>
                        <p>Team members work in this workspace. Social posting and friend features belong to member accounts.</p>
                    </div>
                    <details <?= $error && inputText($_POST, 'action') === 'create_staff' ? 'open' : '' ?>>
                        <summary class="btn btn-primary">Create team account</summary>
                        <form method="post" class="team-form"><?= csrfField() ?><input type="hidden" name="action" value="create_staff">
                            <div><?php accountFields(inputText($_POST, 'action') === 'create_staff' ? $_POST : [], 'new', true); ?></div>
                            <div><?php permissionFields([], 'new'); ?></div><button class="btn btn-primary" type="submit">Create account</button>
                        </form>
                    </details>
                </section><?php endif; ?>
            <section class="card admin-list">
                <div class="admin-list-heading">
                    <div>
                        <h2><?= $tabs[$tab] ?></h2>
                        <p class="text-muted mb-0"><?= $tab === 'messages' ? 'Private conversations - restricted moderation access' : 'Search and review this section of your workspace.' ?></p>
                    </div>
                    <form method="get" class="admin-search"><input type="hidden" name="tab" value="<?= $tab ?>"><label for="admin-search" class="visually-hidden">Search <?= strtolower($tabs[$tab]) ?></label><input class="form-control" id="admin-search" name="q" value="<?= esc($q) ?>" placeholder="Search"><button class="btn btn-primary" type="submit">Search</button><?php if ($q !== ''): ?><a href="admin.php?tab=<?= $tab ?>">Clear</a><?php endif; ?></form>
                </div>
                <?php if (!$rows): ?><div class="empty-state">
                        <h3>No results found</h3>
                        <p>Try a different search, or return when there is new activity.</p>
                    </div><?php else: ?>
                    <div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable management table">
                        <table class="table admin-table">
                            <caption class="visually-hidden"><?= $tabs[$tab] ?> management</caption>
                            <thead>
                                <tr>
                                    <th scope="col">ID / Date</th>
                                    <th scope="col"><?= in_array($tab, ['users', 'team']) ? 'Account' : 'Content / Activity' ?></th>
                                    <th scope="col"><?= $tab === 'audit' ? 'Team member' : 'Actions' ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): $account = in_array($tab, ['users', 'team']);
                                    $editable = $tab === 'team' ? !$row['is_admin'] : canManage($tab . '.manage'); ?><tr>
                                        <td><strong>#<?= $row['id'] ?></strong><small><?= esc($row['created_at'] ?? $row['sent_at'] ?? '') ?></small></td>
                                        <td>
                                            <?php if ($account): ?><strong><?= esc($row['username']) ?></strong><small><?= esc($row['email']) ?></small><span class="status-chip <?= $row['is_suspended'] ? 'suspended' : '' ?>"><?= $row['is_suspended'] ? 'Suspended' : ($tab === 'team' ? staffLabel($row) : 'Active member') ?></span><?php if ($tab === 'team'): ?><div class="team-permissions"><?php if ($row['is_admin']): ?>Full access<?php else: $granted = json_decode($row['staff_permissions'] ?? '[]', true) ?: []; ?><?= count($granted) ?> permissions<?php endif; ?></div><?php endif; ?>
                                            <?php elseif ($tab === 'audit'): ?><strong><?= esc(ucwords(str_replace('_', ' ', $row['action']))) ?></strong><small>Target #<?= $row['target_id'] ?></small>
                                            <?php else: ?><strong><?= esc($row['username']) ?><?= $tab === 'messages' ? ' &rarr; ' . esc($row['recipient']) : '' ?></strong>
                                                <p class="admin-content-text"><?= nl2br(esc($row['content'] ?? $row['comment'] ?? $row['message'] ?? '')) ?></p><?php if (!empty($row['image'])): ?><a href="uploads/<?= esc($row['image']) ?>"><img class="admin-thumbnail" src="uploads/<?= esc($row['image']) ?>" alt="Post attachment" loading="lazy"></a><?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php if ($tab === 'audit'): ?><?= esc($row['username'] ?? 'Removed team member') ?><?php elseif (!$editable): ?><span class="text-muted"><?= $tab === 'team' ? 'Administrator protected' : 'View only' ?></span><?php else: ?><div class="admin-actions">
                                                <?php if ($account): ?><details>
                                                        <summary class="btn btn-outline-primary btn-sm"><?= $tab === 'team' ? 'Edit access' : 'Edit account' ?></summary>
                                                        <form method="post" class="admin-edit"><?= csrfField() ?><input type="hidden" name="action" value="<?= $tab === 'team' ? 'save_staff' : 'save_user' ?>"><input type="hidden" name="id" value="<?= $row['id'] ?>"><?php accountFields($row, 'user-' . $row['id']);
                                                                                                                                                                                                                                                                    if ($tab === 'team') {
                                                                                                                                                                                                                                                                        permissionFields($row, 'user-' . $row['id']);
                                                                                                                                                                                                                                                                    } ?><button class="btn btn-primary" type="submit">Save account</button></form>
                                                    </details>
                                                <?php elseif (in_array($tab, ['posts', 'comments'])): ?><details>
                                                        <summary class="btn btn-outline-primary btn-sm">Edit content</summary>
                                                        <form method="post" class="admin-edit"><?= csrfField() ?><input type="hidden" name="id" value="<?= $row['id'] ?>"><input type="hidden" name="action" value="<?= $tab === 'posts' ? 'save_post' : 'save_comment' ?>"><label for="content-<?= $row['id'] ?>">Content</label><textarea class="form-control" id="content-<?= $row['id'] ?>" name="content"><?= esc($row['content'] ?? $row['comment']) ?></textarea><button class="btn btn-primary mt-2" type="submit">Save content</button></form>
                                                    </details><?php endif; ?>
                                                <?php if ($account): ?><form method="post" data-confirm="Change this account's access? Existing sessions will be signed out."><?= csrfField() ?><input type="hidden" name="id" value="<?= $row['id'] ?>"><button class="btn btn-outline-secondary btn-sm" name="action" value="<?= ($row['is_suspended'] ? 'restore' : 'suspend') . ($tab === 'team' ? '_staff' : '') ?>"><?= $row['is_suspended'] ? 'Restore' : 'Suspend' ?></button></form><?php endif; ?>
                                                <form method="post" data-confirm="Permanently delete this <?= $account ? 'account and its content' : 'content' ?>? This cannot be undone."><?= csrfField() ?><input type="hidden" name="id" value="<?= $row['id'] ?>"><button class="btn btn-outline-danger btn-sm" name="action" value="<?= ['users' => 'delete_user', 'team' => 'delete_staff', 'posts' => 'delete_post', 'comments' => 'delete_comment', 'messages' => 'delete_message'][$tab] ?>">Delete</button></form>
                                            </div><?php endif; ?></td>
                                    </tr><?php endforeach; ?></tbody>
                        </table>
                    </div><?php endif; ?><div class="px-3"><?= paginationLinks($more) ?></div>
            </section><?php endif; ?>
    </div><?php include 'footer.php'; ?></body>

</html>