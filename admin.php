<?php
require_once 'session.php';
require_once 'function.php';
if (empty($currentUser['is_admin'])) {
    failRequest(403, 'Administrator access required.');
}
function adminQuery(string $sql, string $types = '', array $values = []): mysqli_stmt
{
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();
    return $stmt;
}
function esc($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$tabs = ['overview' => 'Overview', 'users' => 'Accounts', 'posts' => 'Posts', 'comments' => 'Comments', 'messages' => 'Messages', 'audit' => 'Activity log'];
$tab = inputText($_GET, 'tab') ?: 'overview';
if (!isset($tabs[$tab])) {
    failRequest(404, 'Page not found.');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = inputText($_POST, 'action');
    $id = inputId($_POST, 'id');
    $uploads = [];
    $conn->begin_transaction();
    try {
        if (in_array($action, ['save_user', 'suspend', 'restore', 'delete_user'], true)) {
            $target = adminQuery('SELECT * FROM users WHERE id = ? FOR UPDATE', 'i', [$id])->get_result()->fetch_assoc();
            if (!$target) {
                throw new RuntimeException('Account not found.');
            }
            if ($target['is_admin'] && ($action !== 'save_user' || $id !== (int) $_SESSION['user_id'])) {
                throw new RuntimeException('Administrator accounts are protected.');
            }
            if ($action === 'save_user') {
                $name = trim(inputText($_POST, 'username'));
                $email = trim(inputText($_POST, 'email'));
                $bio = inputText($_POST, 'bio');
                $password = inputText($_POST, 'password');
                if ($name === '' || strlen($name) > 50 || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($bio) > 65535) {
                    throw new RuntimeException('Enter a valid name and email. Bio must be within 65535 bytes.');
                }
                if ($password !== '' && (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0"))) {
                    throw new RuntimeException('New passwords must contain 12 to 72 bytes.');
                }
                adminQuery('UPDATE users SET username=?, email=?, bio=? WHERE id=?', 'sssi', [$name, $email, $bio, $id]);
                if ($password !== '') {
                    adminQuery('UPDATE users SET password=?, auth_version=auth_version+1 WHERE id=?', 'si', [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
            } elseif ($action === 'suspend' || $action === 'restore') {
                adminQuery('UPDATE users SET is_suspended=?, auth_version=auth_version+1 WHERE id=?', 'ii', [$action === 'suspend' ? 1 : 0, $id]);
            } else {
                $uploads = adminQuery('SELECT profile_image AS image FROM users WHERE id=? UNION SELECT image FROM posts WHERE user_id=?', 'ii', [$id, $id])->get_result()->fetch_all(MYSQLI_ASSOC);
                adminQuery('DELETE FROM likes WHERE user_id=? OR post_id IN (SELECT id FROM posts WHERE user_id=?)', 'ii', [$id, $id]);
                adminQuery('DELETE FROM users WHERE id=?', 'i', [$id]);
            }
        } elseif (in_array($action, ['delete_post', 'delete_comment', 'delete_message', 'save_post', 'save_comment'], true)) {
            $table = str_contains($action, 'post') ? 'posts' : (str_contains($action, 'comment') ? 'comments' : 'messages');
            $target = adminQuery("SELECT * FROM $table WHERE id=? FOR UPDATE", 'i', [$id])->get_result()->fetch_assoc();
            if (!$target) {
                throw new RuntimeException('This content no longer exists.');
            }
            if (str_starts_with($action, 'save_')) {
                $content = trim(inputText($_POST, 'content'));
                if (($content === '' && ($table !== 'posts' || empty($target['image']))) || strlen($content) > 65535) {
                    throw new RuntimeException('Enter content within 65535 bytes.');
                }
                $column = $table === 'posts' ? 'content' : 'comment';
                adminQuery("UPDATE $table SET $column=? WHERE id=?", 'si', [$content, $id]);
            } else {
                if ($table === 'posts') {
                    $uploads[] = ['image' => $target['image']];
                    adminQuery('DELETE FROM likes WHERE post_id=?', 'i', [$id]);
                    adminQuery('DELETE FROM comments WHERE post_id=?', 'i', [$id]);
                }
                adminQuery("DELETE FROM $table WHERE id=?", 'i', [$id]);
            }
        } else {
            throw new RuntimeException('Choose a valid action.');
        }
        adminQuery('INSERT INTO admin_audit (admin_id,action,target_id) VALUES (?,?,?)', 'isi', [(int) $_SESSION['user_id'], $action, $id]);
        $conn->commit();
        if ($action === 'save_user' && $id === (int)$_SESSION['user_id']) {
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
        $returnParams = ['tab' => $tab, 'q' => inputText($_GET, 'q'), 'page' => pageNumber()];
        header('Location: admin.php?' . http_build_query($returnParams));
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
    $counts[$table] = (int)$conn->query("SELECT COUNT(*) FROM $table")->fetch_row()[0];
}
$suspended = (int)$conn->query('SELECT COUNT(*) FROM users WHERE is_suspended=1')->fetch_row()[0];
$q = mb_substr(trim(inputText($_GET, 'q')), 0, 100);
$search = '%' . $q . '%';
$offset = (pageNumber() - 1) * 20;
$rows = [];
$more = false;
if ($tab !== 'overview') {
    $queries = [
        'users' => 'SELECT id,username,email,bio,is_admin,is_suspended,created_at FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 21 OFFSET ?',
        'posts' => 'SELECT p.*,u.username FROM posts p JOIN users u ON u.id=p.user_id WHERE p.content LIKE ? OR u.username LIKE ? ORDER BY p.id DESC LIMIT 21 OFFSET ?',
        'comments' => 'SELECT c.*,u.username FROM comments c JOIN users u ON u.id=c.user_id WHERE c.comment LIKE ? OR u.username LIKE ? ORDER BY c.id DESC LIMIT 21 OFFSET ?',
        'messages' => 'SELECT m.*,u.username,r.username AS recipient FROM messages m JOIN users u ON u.id=m.sender_id JOIN users r ON r.id=m.receiver_id WHERE m.message LIKE ? OR u.username LIKE ? ORDER BY m.id DESC LIMIT 21 OFFSET ?',
        'audit' => 'SELECT a.*,u.username FROM admin_audit a LEFT JOIN users u ON u.id=a.admin_id WHERE a.action LIKE ? OR u.username LIKE ? ORDER BY a.id DESC LIMIT 21 OFFSET ?'
    ];
    $rows = adminQuery($queries[$tab], 'ssi', [$search, $search, $offset])->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <title>Admin dashboard &middot; MiniSocial</title><?php include 'ui_assets.php'; ?>
</head>

<body class="admin-page">
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="admin-banner">
            <div><span class="eyebrow">Community management</span>
                <h2>A healthy community starts here.</h2>
                <p>Review activity, support your members, and manage what gets shared.</p>
            </div><span class="admin-badge"><i class="bx bx-shield-quarter" aria-hidden="true"></i> Administrator</span>
        </div>
        <?php if ($notice): ?><div class="alert alert-success" role="status"><?= esc($notice) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= esc($error) ?></div><?php endif; ?>
        <div class="admin-stats"><?php foreach ($counts as $key => $count): ?><a class="stat-card" href="admin.php?tab=<?= $key ?>"><span><?= $tabs[$key] ?></span><strong><?= number_format($count) ?></strong><small>View <?= strtolower($tabs[$key]) ?> <span aria-hidden="true">&nearr;</span></small></a><?php endforeach; ?></div>
        <nav class="admin-tabs" aria-label="Dashboard sections"><?php foreach ($tabs as $key => $label): ?><a href="admin.php?tab=<?= $key ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>><?= $label ?></a><?php endforeach; ?></nav>
        <?php if ($tab === 'overview'): ?>
            <div class="admin-overview">
                <section class="card card-body"><span class="eyebrow">Account health</span>
                    <h2><?= number_format($counts['users'] - $suspended) ?> active accounts</h2>
                    <p><?= $suspended ?> suspended accounts. Suspension immediately ends access to the app; restoring an account lets the member sign in again.</p><a class="btn btn-primary" href="admin.php?tab=users">Manage accounts</a>
                </section>
                <section class="card card-body"><span class="eyebrow">Your administrator account</span>
                    <h2>Keep your login up to date</h2>
                    <p>Edit your email and profile, or choose a new password. Password resets sign out other sessions.</p><a class="btn btn-outline-primary" href="admin.php?tab=users&amp;q=<?= urlencode($_SESSION['username']) ?>">Edit my account</a>
                </section>
            </div>
            <section class="card card-body mt-4">
                <h2>Moderation workspace</h2>
                <p>Use Posts and Comments to review, edit, or remove community content. Messages includes private conversations for administrator moderation. Deletions are permanent. Every change is recorded in the activity log.</p>
            </section>
        <?php else: ?>
            <section class="card admin-list">
                <div class="admin-list-heading">
                    <div>
                        <h2><?= $tabs[$tab] ?></h2>
                        <p class="text-muted mb-0"><?= $tab === 'messages' ? 'Private conversations - administrator access only' : 'Search, review, and manage community activity.' ?></p>
                    </div>
                    <form method="get" class="admin-search"><input type="hidden" name="tab" value="<?= $tab ?>"><label for="admin-search" class="visually-hidden">Search <?= strtolower($tabs[$tab]) ?></label><input class="form-control" id="admin-search" name="q" value="<?= esc($q) ?>" placeholder="Search <?= strtolower($tabs[$tab]) ?>"><button class="btn btn-primary" type="submit">Search</button><?php if ($q !== ''): ?><a href="admin.php?tab=<?= $tab ?>">Clear</a><?php endif; ?></form>
                </div>
                <?php if (!$rows): ?><div class="empty-state">
                        <h3>No results found</h3>
                        <p>Try a different search or return after your community shares something.</p>
                    </div><?php else: ?>
                    <div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable management table">
                        <table class="table admin-table">
                            <caption class="visually-hidden"><?= $tabs[$tab] ?> management</caption>
                            <thead>
                                <tr>
                                    <th scope="col">ID / Date</th>
                                    <th scope="col"><?= $tab === 'users' ? 'Account' : 'Content / Activity' ?></th>
                                    <th scope="col"><?= $tab === 'audit' ? 'Administrator' : 'Actions' ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?><tr>
                                        <td><strong>#<?= $row['id'] ?></strong><small><?= esc($row['created_at'] ?? $row['sent_at'] ?? '') ?></small></td>
                                        <td>
                                            <?php if ($tab === 'users'): ?><strong><?= esc($row['username']) ?></strong><small><?= esc($row['email']) ?></small><span class="status-chip <?= $row['is_suspended'] ? 'suspended' : '' ?>"><?= $row['is_admin'] ? 'Administrator' : ($row['is_suspended'] ? 'Suspended' : 'Active') ?></span>
                                            <?php elseif ($tab === 'audit'): ?><strong><?= esc(ucwords(str_replace('_', ' ', $row['action']))) ?></strong><small>Target #<?= $row['target_id'] ?></small>
                                            <?php else: ?><strong><?= esc($row['username']) ?><?= $tab === 'messages' ? ' &rarr; ' . esc($row['recipient']) : '' ?></strong>
                                                <p class="admin-content-text"><?= nl2br(esc($row['content'] ?? $row['comment'] ?? $row['message'] ?? '')) ?></p><?php if (!empty($row['image'])): ?><a href="uploads/<?= esc($row['image']) ?>"><img class="admin-thumbnail" src="uploads/<?= esc($row['image']) ?>" alt="Post attachment" loading="lazy"></a><?php endif; ?><?php if ($tab === 'posts' || $tab === 'comments'): ?><a href="comment_post.php?post_id=<?= $tab === 'posts' ? $row['id'] : $row['post_id'] ?>">View conversation</a><?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($tab === 'audit'): ?><?= esc($row['username'] ?? 'Removed administrator') ?>
                                        <?php else: ?><div class="admin-actions">
                                                <?php if ($tab === 'users' && (!$row['is_admin'] || (int)$row['id'] === (int)$_SESSION['user_id'])): ?>
                                                    <details>
                                                        <summary class="btn btn-outline-primary btn-sm">Edit account</summary>
                                                        <form method="post" class="admin-edit"><?= csrfField() ?><input type="hidden" name="action" value="save_user"><input type="hidden" name="id" value="<?= $row['id'] ?>"><label for="name-<?= $row['id'] ?>">Username</label><input class="form-control" id="name-<?= $row['id'] ?>" name="username" value="<?= esc($row['username']) ?>" maxlength="50" required><label for="email-<?= $row['id'] ?>">Email</label><input class="form-control" type="email" id="email-<?= $row['id'] ?>" name="email" value="<?= esc($row['email']) ?>" maxlength="100" required><label for="bio-<?= $row['id'] ?>">Bio</label><textarea class="form-control" id="bio-<?= $row['id'] ?>" name="bio"><?= esc($row['bio']) ?></textarea><label for="password-<?= $row['id'] ?>">New password (optional)</label><input class="form-control" type="password" id="password-<?= $row['id'] ?>" name="password" autocomplete="new-password" minlength="12" maxlength="72">
                                                            <p class="form-text">Leave blank to keep the current password. Use 12-72 bytes.</p><button class="btn btn-primary" type="submit">Save account</button>
                                                        </form>
                                                    </details>
                                                <?php elseif ($tab === 'posts' || $tab === 'comments'): ?><details>
                                                        <summary class="btn btn-outline-primary btn-sm">Edit content</summary>
                                                        <form method="post" class="admin-edit"><?= csrfField() ?><input type="hidden" name="id" value="<?= $row['id'] ?>"><input type="hidden" name="action" value="<?= $tab === 'posts' ? 'save_post' : 'save_comment' ?>"><label for="content-<?= $row['id'] ?>">Content</label><textarea class="form-control" id="content-<?= $row['id'] ?>" name="content"><?= esc($row['content'] ?? $row['comment']) ?></textarea><button class="btn btn-primary mt-2" type="submit">Save content</button></form>
                                                    </details><?php endif; ?>
                                                <?php if ($tab === 'users' && !$row['is_admin']): ?><form method="post" data-confirm="Change this account's access? Existing sessions will be signed out."><?= csrfField() ?><input type="hidden" name="id" value="<?= $row['id'] ?>"><button class="btn btn-outline-secondary btn-sm" name="action" value="<?= $row['is_suspended'] ? 'restore' : 'suspend' ?>"><?= $row['is_suspended'] ? 'Restore' : 'Suspend' ?></button></form><?php endif; ?>
                                                <?php if ($tab !== 'users' || !$row['is_admin']): ?><form method="post" data-confirm="Permanently delete this <?= $tab === 'users' ? 'account and all its content' : 'content' ?>? This cannot be undone."><?= csrfField() ?><input type="hidden" name="id" value="<?= $row['id'] ?>"><button class="btn btn-outline-danger btn-sm" name="action" value="<?= ['users' => 'delete_user', 'posts' => 'delete_post', 'comments' => 'delete_comment', 'messages' => 'delete_message'][$tab] ?>">Delete</button></form><?php endif; ?>
                                            </div><?php endif; ?></td>
                                    </tr><?php endforeach; ?></tbody>
                        </table>
                    </div><?php endif; ?>
                <div class="px-3"><?= paginationLinks($more) ?></div>
            </section><?php endif; ?>
    </div><?php include 'footer.php'; ?>
</body>

</html>