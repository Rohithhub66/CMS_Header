<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$user = $_SESSION['user'] ?? [];
// Accept either ['first_name','last_name'] or ['name'] or fallback to username or Admin
if (isset($user['first_name']) && isset($user['last_name'])) {
    $userName = trim($user['first_name'].' '.$user['last_name']);
} elseif (isset($user['name'])) {
    $userName = $user['name'];
} elseif (isset($user['username'])) {
    $userName = $user['username'];
} else {
    $userName = 'Admin';
}
?>
<link rel="stylesheet" href="css/header.css">
<!-- Main Banner -->
<div class="banner-main">
    <span class="banner-title">
        <svg width="38" height="38" viewBox="0 0 38 38" fill="none" style="vertical-align:middle;margin-right:12px;">
          <defs>
            <linearGradient id="shield-gradient" x1="0" y1="0" x2="38" y2="38" gradientUnits="userSpaceOnUse">
              <stop stop-color="#0f6efd"/>
              <stop offset="1" stop-color="#19c37d"/>
            </linearGradient>
          </defs>
          <path d="M19 4L32 9V17C32 26.5 24.5 33.5 19 35C13.5 33.5 6 26.5 6 17V9L19 4Z" fill="url(#shield-gradient)" stroke="#fff" stroke-width="2"/>
          <path d="M19 20V27" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
          <circle cx="19" cy="16" r="2.5" fill="#fff"/>
        </svg>
        <span style="vertical-align:middle; font-weight:700; font-size:2.2rem; letter-spacing:1px; color:#0ff0fc;">
            Continuous Monitoring System
        </span>
    </span>
    <div class="banner-user">
        <div class="user-profile" tabindex="0">
            <div class="user-profile-inner">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M12 14c-5 0-7 2.5-7 4.5V21h14v-2.5c0-2-2-4.5-7-4.5z"/>
                </svg>
                <span><?= htmlspecialchars($userName) ?></span>
            </div>
            <div class="user-profile-dropdown">
                <a href="profile.php" class="profile-link">Profile</a>
                <a class="logout-link" href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</div>
<!-- Navigation Bar -->
<nav class="nav-secondary">
    <div class="nav-secondary-inner">
        <?php if (isset($page_breadcrumb) && is_array($page_breadcrumb) && count($page_breadcrumb)): ?>
            <?php foreach ($page_breadcrumb as $i => $crumb): ?>
                <?php if (!empty($crumb['href'])): ?>
                    <a href="<?= htmlspecialchars($crumb['href']) ?>"><?= htmlspecialchars($crumb['text']) ?></a>
                <?php else: ?>
                    <span><?= htmlspecialchars($crumb['text']) ?></span>
                <?php endif; ?>
                <?php if ($i < count($page_breadcrumb) - 1): ?>
                    <span class="breadcrumb-sep">&gt;</span>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <span class="dashboard-label">Dashboard</span>
        <?php endif; ?>
    </div>
</nav>
<script>
    // Show/hide dropdown on hover/focus for .user-profile
    document.addEventListener('DOMContentLoaded', function() {
        const userProfile = document.querySelector('.user-profile');
        if (!userProfile) return;
        userProfile.addEventListener('mouseenter', () => {
            userProfile.classList.add('open');
        });
        userProfile.addEventListener('mouseleave', () => {
            userProfile.classList.remove('open');
        });
        userProfile.addEventListener('focusin', () => {
            userProfile.classList.add('open');
        });
        userProfile.addEventListener('focusout', () => {
            userProfile.classList.remove('open');
        });
    });
</script>