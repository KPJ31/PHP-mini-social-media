<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';

$user_id = $_SESSION['user_id'];
$search_query = isset($_GET['search']) ? trim(inputText($_GET, 'search')) : '';

$friendOffset = (pageNumber('friends_page') - 1) * 50;
$requestOffset = (pageNumber('requests_page') - 1) * 50;
$searchOffset = (pageNumber('search_page') - 1) * 50;
$friendShown = $requestShown = $searchShown = 0;
// Fetch accepted friends
$friends_stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.username, u.profile_image
    FROM users u
    JOIN friend_requests fr ON (
        (fr.sender_id = ? AND fr.receiver_id = u.id) OR 
        (fr.receiver_id = ? AND fr.sender_id = u.id)
    )
    WHERE fr.status = 'accepted' ORDER BY u.username, u.id LIMIT 51 OFFSET ?
");
$friends_stmt->bind_param("iii", $user_id, $user_id, $friendOffset);
$friends_stmt->execute();
$friends = $friends_stmt->get_result();
$friends_stmt->close();

// Search results (only if user typed something)
$search_results = null;
if ($search_query !== '') {
    $search_term = '%' . $search_query . '%';
    $stmt = $conn->prepare("SELECT id, username, profile_image FROM users WHERE username LIKE ? AND id != ? ORDER BY username, id LIMIT 51 OFFSET ?");
    $stmt->bind_param("sii", $search_term, $user_id, $searchOffset);
    $stmt->execute();
    $search_results = $stmt->get_result();
    $stmt->close();
}

// Fetch incoming friend requests
$pending_stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.username, u.profile_image
    FROM users u
    JOIN friend_requests fr ON u.id = fr.sender_id
    WHERE fr.receiver_id = ? AND fr.status = 'pending' ORDER BY u.id LIMIT 51 OFFSET ?
");
$pending_stmt->bind_param("ii", $user_id, $requestOffset);
$pending_stmt->execute();
$pending_requests = $pending_stmt->get_result();
$pending_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= csrfToken() ?>">
  <meta charset="UTF-8">
  <title>Friends - Mini Social Network</title>
<?php include 'ui_assets.php'; ?>
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container mt-4">

  <!-- Header with Search -->
  <div class="search-panel">
    <h2>Find a familiar face</h2>
    <form method="get" class="d-flex" role="search">
      <label for="friend-search" class="visually-hidden">Search by username</label><input id="friend-search" type="search" name="search" class="form-control" placeholder="Search username..." value="<?= htmlspecialchars($search_query) ?>">
      <button type="submit" class="btn btn-gold ms-2">Search</button>
    </form>
  </div>

  <!-- Friends Section -->
  <div class="card shadow-sm mb-4">
    <div class="card-header">
      <strong>Your Friends</strong>
    </div>
    <div class="card-body p-0">
      <?php if ($friends->num_rows > 0): ?>
        <div class="table-responsive" role="region" aria-label="Community members" tabindex="0"><table class="table table-hover m-0">
          <thead>
            <tr>
              <th>Profile</th>
              <th>Username</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while (($friend = $friends->fetch_assoc()) && $friendShown++ < 50): ?>
              <tr>
                <td><img loading="lazy" src="uploads/<?= htmlspecialchars(($friend['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $friend['profile_image']) ?>" class="rounded-circle" width="40" height="40" alt="Profile image"></td>
                <td><?= htmlspecialchars($friend['username']) ?></td>
                <td>
                  <a href="profile.php?user_id=<?= $friend['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                  <a href="chat.php?user_id=<?= $friend['id'] ?>" class="btn btn-sm btn-primary">Chat</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
</div>
        <?= paginationLinks($friends->num_rows > 50, 'friends_page') ?>
      <?php else: ?>
        <div class="empty-state"><i class="bx bx-group" aria-hidden="true"></i><h2>Your circle starts here.</h2><p>Search for a username above to send your first friend request.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pending Friend Requests -->
  <div class="card shadow-sm mb-4">
    <div class="card-header">
      <strong>Friend Requests</strong>
    </div>
    <div class="card-body p-0">
      <?php if ($pending_requests->num_rows > 0): ?>
        <div class="table-responsive" role="region" aria-label="Community members" tabindex="0"><table class="table table-hover m-0">
          <thead>
            <tr>
              <th>Profile</th>
              <th>Username</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while (($req = $pending_requests->fetch_assoc()) && $requestShown++ < 50): ?>
              <tr>
                <td><img loading="lazy" src="uploads/<?= htmlspecialchars(($req['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $req['profile_image']) ?>" class="rounded-circle" width="40" height="40" alt="Profile image"></td>
                <td><?= htmlspecialchars($req['username']) ?></td>
                <td>
                  <form action="accept_request.php" method="post" class="d-inline"><?= csrfField() ?><input type="hidden" name="user_id" value="<?= $req['id'] ?>"><button type="submit" class="btn btn-sm btn-primary">Accept</button></form>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
</div>
        <?= paginationLinks($pending_requests->num_rows > 50, 'requests_page') ?>
      <?php else: ?>
        <div class="empty-state"><i class="bx bx-check-circle" aria-hidden="true"></i><p>You are all caught up. New friend requests will appear here.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Search Results -->
  <?php if ($search_query !== ''): ?>
    <div class="card shadow-sm">
      <div class="card-header">
        <strong>Search Results</strong>
      </div>
      <div class="card-body p-0">
        <?php if ($search_results && $search_results->num_rows > 0): ?>
          <div class="table-responsive" role="region" aria-label="Community members" tabindex="0"><table class="table table-hover m-0">
            <thead>
              <tr>
                <th>Profile</th>
                <th>Username</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while (($user = $search_results->fetch_assoc()) && $searchShown++ < 50): ?>
                <tr>
                  <td><img loading="lazy" src="uploads/<?= htmlspecialchars(($user['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $user['profile_image']) ?>" class="rounded-circle" width="40" height="40" alt="Profile image"></td>
                  <td><?= htmlspecialchars($user['username']) ?></td>
                  <td>
                    <form action="send_request.php" method="post" class="d-inline"><?= csrfField() ?><input type="hidden" name="user_id" value="<?= $user['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-primary">Send Request</button></form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
</div>
        <?= paginationLinks($search_results->num_rows > 50, 'search_page') ?>
        <?php else: ?>
          <div class="p-3 text-muted">No users found for "<?= htmlspecialchars($search_query) ?>"</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
