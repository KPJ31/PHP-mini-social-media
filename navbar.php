<!-- navbar.php -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">MiniSocial</a>

    <!-- Toggler button for mobile -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Navbar items -->
    <div class="collapse navbar-collapse" id="navbarMenu">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="profile.php">My Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="friend_list.php">Friends</a></li>
        <li class="nav-item"><a class="nav-link" href="chat.php">Chat</a></li>
        <li class="nav-item"><form action="logout.php" method="post"><?= csrfField() ?><button type="submit" class="nav-link text-danger">Logout</button></form></li>
      </ul>
    </div>
  </div>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
