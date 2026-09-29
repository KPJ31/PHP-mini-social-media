<?php
require_once 'config.php';
require_once 'session.php';

$user_id = $_SESSION['user_id'];
$search_query = isset($_GET['search']) ? trim(inputText($_GET, 'search')) : '';

// Fetch accepted friends
$friends_stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.username, u.profile_image
    FROM users u
    JOIN friend_requests fr ON (
        (fr.sender_id = ? AND fr.receiver_id = u.id) OR 
        (fr.receiver_id = ? AND fr.sender_id = u.id)
    )
    WHERE fr.status = 'accepted'
");
$friends_stmt->bind_param("ii", $user_id, $user_id);
$friends_stmt->execute();
$friends = $friends_stmt->get_result();
$friends_stmt->close();

// Search results (only if user typed something)
$search_results = null;
if ($search_query !== '') {
    $search_term = '%' . $search_query . '%';
    $stmt = $conn->prepare("SELECT id, username, profile_image FROM users WHERE username LIKE ? AND id != ?");
    $stmt->bind_param("si", $search_term, $user_id);
    $stmt->execute();
    $search_results = $stmt->get_result();
    $stmt->close();
}

// Fetch incoming friend requests
$pending_stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.username, u.profile_image
    FROM users u
    JOIN friend_requests fr ON u.id = fr.sender_id
    WHERE fr.receiver_id = ? AND fr.status = 'pending'
");
$pending_stmt->bind_param("i", $user_id);
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container mt-4">

  <!-- Header with Search -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Friends & Requests</h4>
    <form method="get" class="d-flex" role="search">
      <input type="text" name="search" class="form-control" placeholder="Search username..." value="<?= htmlspecialchars($search_query) ?>">
      <button type="submit" class="btn btn-primary ms-2">Search</button>
    </form>
  </div>

  <!-- Friends Section -->
  <div class="card shadow-sm mb-4">
    <div class="card-header">
      <strong>Your Friends</strong>
    </div>
    <div class="card-body p-0">
      <?php if ($friends->num_rows > 0): ?>
        <table class="table table-hover m-0">
          <thead>
            <tr>
              <th>Profile</th>
              <th>Username</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($friend = $friends->fetch_assoc()): ?>
              <tr>
                <td><img src="uploads/<?= htmlspecialchars(($friend['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $friend['profile_image']) ?>" class="rounded-circle" width="40" height="40"></td>
                <td><?= htmlspecialchars($friend['username']) ?></td>
                <td>
                  <a href="profile.php?user_id=<?= $friend['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                  <a href="chat.php?user_id=<?= $friend['id'] ?>" class="btn btn-sm btn-success">Chat</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="p-3 text-muted">You have no friends yet.</div>
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
        <table class="table table-hover m-0">
          <thead>
            <tr>
              <th>Profile</th>
              <th>Username</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($req = $pending_requests->fetch_assoc()): ?>
              <tr>
                <td><img src="uploads/<?= htmlspecialchars(($req['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $req['profile_image']) ?>" class="rounded-circle" width="40" height="40"></td>
                <td><?= htmlspecialchars($req['username']) ?></td>
                <td>
                  <form action="accept_request.php" method="post" class="d-inline"><?= csrfField() ?><input type="hidden" name="user_id" value="<?= $req['id'] ?>"><button type="submit" class="btn btn-sm btn-success">Accept</button></form>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="p-3 text-muted">No pending friend requests.</div>
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
          <table class="table table-hover m-0">
            <thead>
              <tr>
                <th>Profile</th>
                <th>Username</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($user = $search_results->fetch_assoc()): ?>
                <tr>
                  <td><img src="uploads/<?= htmlspecialchars(($user['profile_image'] ?? 'default.png') === 'default.png' ? 'default.svg' : $user['profile_image']) ?>" class="rounded-circle" width="40" height="40"></td>
                  <td><?= htmlspecialchars($user['username']) ?></td>
                  <td>
                    <form action="send_request.php" method="post" class="d-inline"><?= csrfField() ?><input type="hidden" name="user_id" value="<?= $user['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-primary">Send Request</button></form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="p-3 text-muted">No users found for "<?= htmlspecialchars($search_query) ?>"</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

</body>
</html>
