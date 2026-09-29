<?php
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$pageInfo = [
    'admin.php' => ['Administration', 'Community dashboard', 'Manage your members and keep your community welcoming.'],
    'index.php' => ['Your community', 'Your daily catch-up', 'Fresh moments and conversations from your community.'],
    'profile.php' => ['Profile', 'Your corner of the community.', 'Posts, personality, and the things you choose to share.'],
    'friend_list.php' => ['Connections', 'Good things start with people.', 'Find friends, welcome new connections, and keep in touch.'],
    'chat.php' => ['Messages', 'Keep the conversation going.', 'A space for one-to-one conversations with your friends.'],
    'add_post.php' => ['Create a post', 'What would you like to share?', 'A thought, a photo, or a small moment from your day.'],
    'edit_profile.php' => ['Edit profile', 'Make yourself at home.', 'Let your community get to know you.'],
    'comment_post.php' => ['Conversation', 'Join the conversation.', 'Read the post and add your own perspective.'],
];
[$pageLabel, $pageTitle, $pageDescription] = $pageInfo[$currentPage] ?? $pageInfo['index.php'];
$navigation = [
    ['index.php', 'bx-home-alt', 'Home feed'],
    ['friend_list.php', 'bx-group', 'Friends'],
    ['chat.php', 'bx-message-rounded-dots', 'Messages'],
    ['profile.php', 'bx-user-circle', 'My profile'],
];
if (!empty($_SESSION['is_admin'])) { $navigation[] = ['admin.php', 'bx-shield-quarter', 'Admin dashboard']; }
?>
<a href="#main-content" class="skip-link">Skip to content</a>
<aside class="app-sidebar" id="site-navigation" aria-label="Main navigation">
    <div class="sidebar-brand-row">
        <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">m<span>.</span></span>MiniSocial<span class="brand-dot">.</span></a>
        <button type="button" class="icon-button sidebar-close" data-close-menu aria-label="Close navigation"><i class="bx bx-x" aria-hidden="true"></i></button>
    </div>
    <p class="sidebar-caption">YOUR EVERYDAY CONNECTIONS</p>
    <nav aria-label="Primary">
        <?php foreach ($navigation as [$href, $icon, $label]):
            $active = $currentPage === $href || ($href === 'profile.php' && $currentPage === 'edit_profile.php');
        ?>
        <a class="sidebar-link <?= $active ? 'active' : '' ?>" href="<?= $href ?>" <?= $active ? 'aria-current="page"' : '' ?>><i class="bx <?= $icon ?>" aria-hidden="true"></i><?= $label ?><?php if ($active): ?><span class="nav-indicator" aria-hidden="true"></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-note">
        <span class="icon-badge"><i class="bx bx-edit-alt" aria-hidden="true"></i></span>
        <h2>A moment worth sharing?</h2>
        <p>Let your friends in on your day.</p>
        <a href="add_post.php" class="btn btn-gold w-100">Create a post <i class="bx bx-plus" aria-hidden="true"></i></a>
    </div>
    <div class="sidebar-bottom">
        <span class="avatar-initial" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['username'] ?? 'M', 0, 1))) ?></span>
        <div class="sidebar-user"><strong><?= htmlspecialchars($_SESSION['username'] ?? 'Member') ?></strong><span>Your personal space</span></div>
        <form action="logout.php" method="post"><?= csrfField() ?><button type="submit" class="icon-button" aria-label="Log out" title="Log out"><i class="bx bx-log-out" aria-hidden="true"></i></button></form>
    </div>
</aside>
<button class="nav-backdrop" data-close-menu tabindex="-1" aria-label="Close navigation" hidden></button>
<header class="app-topbar">
    <div class="topbar-start"><button type="button" class="icon-button menu-toggle" aria-controls="site-navigation" aria-expanded="false" aria-label="Open navigation"><i class="bx bx-menu" aria-hidden="true"></i></button><a href="index.php" class="topbar-home">MiniSocial</a><span class="topbar-divider">/</span><span><?= $pageLabel ?></span></div>
    <a class="topbar-profile" href="profile.php" aria-label="My profile"><span class="avatar-initial small-avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['username'] ?? 'M', 0, 1))) ?></span><span><?= htmlspecialchars($_SESSION['username'] ?? 'My profile') ?></span></a>
</header>
<main class="app-main" id="main-content" tabindex="-1">
    <div class="page-heading">
        <div><span class="eyebrow"><?= $pageLabel ?></span><h1><?= $pageTitle ?></h1><p><?= $pageDescription ?></p></div>
        <?php if (in_array($currentPage, ['index.php', 'profile.php'], true)): ?><a class="btn btn-gold" href="add_post.php"><i class="bx bx-plus" aria-hidden="true"></i>Create a post</a><?php endif; ?>
    </div>
