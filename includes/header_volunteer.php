<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? "VolunteerHub") ?></title>

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90' font-family='system-ui, -apple-system, sans-serif'>🌿</text></svg>">

  <link href="../../includes/css/bootstrap.min.css" rel="stylesheet">
  <link href="../../includes/css/all.min.css" rel="stylesheet">

  <?php
  $currentPage = basename($_SERVER['PHP_SELF']);
  if ($currentPage === 'index.php') { return; }
  ?>

  <!-- Design system -->
  <link rel="stylesheet" href="/VolunteerHub/styles/volunteer_layout.css">

  <?php if (!empty($pageCSS)): ?>
  <!-- Page-specific CSS -->
  <link rel="stylesheet" href="<?= htmlspecialchars($pageCSS) ?>">
  <?php endif; ?>
</head>

<body>

<!-- ── Overlay ── -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ── Sidebar ── -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <a href="/VolunteerHub/volunteer/volunteer_dashboard.php" class="brand text-decoration-none">
      <div class="brand-icon">🌿</div>
      <span class="brand-name">VolunteerHub</span>
    </a>
    <button id="closeSidebar" aria-label="Close menu">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <span class="sidebar-section-label">Explore</span>

  <ul class="nav flex-column">
    <li class="nav-item">
      <a href="/VolunteerHub/volunteer/volunteer_dashboard.php"
         class="nav-link <?= ($currentPage === 'volunteer_dashboard.php') ? 'active' : '' ?>">
        <i class="fas fa-home"></i> Home
      </a>
    </li>
    <li class="nav-item">
      <a href="/VolunteerHub/volunteer/organizations.php"
         class="nav-link <?= ($currentPage === 'organizations.php') ? 'active' : '' ?>">
        <i class="fas fa-building"></i> Organizations
      </a>
    </li>
    <li class="nav-item">
      <a href="/VolunteerHub/volunteer/list_events.php"
         class="nav-link <?= ($currentPage === 'list_events.php') ? 'active' : '' ?>">
        <i class="fas fa-calendar-alt"></i> Events
      </a>
    </li>
    <li class="nav-item">
      <a href="/VolunteerHub/volunteer/my_tasks.php"
         class="nav-link <?= ($currentPage === 'my_tasks.php') ? 'active' : '' ?>">
        <i class="fas fa-tasks"></i> My Tasks
      </a>
    </li>
    <li class="nav-item">
      <a href="/VolunteerHub/volunteer/application_history.php"
         class="nav-link <?= ($currentPage === 'application_history.php') ? 'active' : '' ?>">
        <i class="fas fa-clipboard-list"></i> Application History
      </a>
    </li>
    <li class="nav-item">
      <a href="/VolunteerHub/volunteer/notifications.php"
         class="nav-link <?= ($currentPage === 'notifications.php') ? 'active' : '' ?>">
        <i class="fas fa-bell"></i> Notifications
      </a>
    </li>
  </ul>

  <span class="sidebar-section-label" style="margin-top:auto;">Account</span>
  <ul class="nav flex-column" style="padding-bottom:16px;">
    <li class="nav-item">
      <a href="/VolunteerHub/volunteer/volunteers_profile.php"
         class="nav-link <?= ($currentPage === 'volunteers_profile.php') ? 'active' : '' ?>">
        <i class="fas fa-user-circle"></i> My Profile
      </a>
    </li>
    <li class="nav-item">
      <a href="/VolunteerHub/logout.php" class="nav-link" style="color:#ef4444;">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </li>
  </ul>
</nav>

<!-- ── Topbar ── -->
<header class="topbar">
  <div class="d-flex align-items-center gap-3">
    <button id="toggleSidebar" aria-label="Open menu">
      <i class="fas fa-bars"></i>
    </button>
    <a href="/VolunteerHub/volunteer/volunteer_dashboard.php" class="brand-link text-decoration-none">
      <span>VolunteerHub</span>
    </a>
    <span class="role-badge d-none d-sm-inline">Volunteer</span>
  </div>

  <!-- Profile dropdown -->
<div class="d-flex align-items-center gap-2">

  <!-- 🔔 Notification Bell -->
  <div class="dropdown" id="volNotifDropdownWrap">
    <button class="notif-bell-btn" id="volNotifBellBtn"
            data-bs-toggle="dropdown" aria-expanded="false"
            aria-label="Notifications">
      <i class="fas fa-bell"></i>
      <span class="notif-badge" id="volNotifBadge" style="display:none;">0</span>
    </button>

    <div class="dropdown-menu dropdown-menu-end notif-dropdown" id="volNotifDropdownMenu"
         aria-labelledby="volNotifBellBtn">
      <div class="notif-dropdown-header">
        <span><i class="fas fa-bell me-2"></i>Notifications</span>
        <a href="/VolunteerHub/volunteer/notifications.php" class="notif-see-all">See all</a>
      </div>
      <div id="volNotifList">
        <div class="notif-loading">
          <i class="fas fa-spinner fa-spin"></i> Loading…
        </div>
      </div>
    </div>
  </div>

  <!-- Profile dropdown (unchanged) -->
  <div class="dropdown">
    <button class="dropdown-toggle" type="button" id="userDropdown"
            data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-user-circle"></i>
      <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Volunteer') ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
      <li>
        <a class="dropdown-item" href="/VolunteerHub/volunteer/volunteers_profile.php">
          <i class="fas fa-user me-2 text-muted" style="width:16px;"></i> View Profile
        </a>
      </li>
      <li><hr class="dropdown-divider"></li>
      <li>
        <a class="dropdown-item text-danger" href="/VolunteerHub/logout.php">
          <i class="fas fa-sign-out-alt me-2" style="width:16px;"></i> Logout
        </a>
      </li>
    </ul>
  </div>

</div>
<script>
/* ── Volunteer Notification Bell AJAX ─────────────────── */
(function () {
  const bell   = document.getElementById('volNotifBellBtn');
  const badge  = document.getElementById('volNotifBadge');
  const list   = document.getElementById('volNotifList');
  let loaded   = false;

  const colorMap = {
    green:  'ni-green',
    amber:  'ni-amber',
    blue:   'ni-blue',
    red:    'ni-red',
    purple: 'ni-purple',
  };

  function fetchNotifs() {
    if (loaded) return;
    loaded = true;
    fetch('/VolunteerHub/volunteer/get_notifications_volunteer.php')
      .then(r => r.json())
      .then(data => {
        if (!data.items || data.items.length === 0) {
          list.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash me-1"></i>No notifications yet.</div>';
          return;
        }
        if (data.unread > 0) {
          badge.textContent = data.unread > 9 ? '9+' : data.unread;
          badge.style.display = 'flex';
        }
        list.innerHTML = data.items.map(n => `
          <a class="notif-item ${n.unread ? 'unread' : ''}" href="${n.link}">
            <div class="notif-icon ${colorMap[n.color] || 'ni-blue'}">
              <i class="${n.icon}"></i>
            </div>
            <div class="notif-body">
              <div class="notif-msg">${n.message}</div>
              <div class="notif-time">${n.time}</div>
            </div>
          </a>`).join('');
      })
      .catch(() => {
        list.innerHTML = '<div class="notif-empty">Could not load notifications.</div>';
      });
  }

  if (bell) bell.addEventListener('click', fetchNotifs);

  // Badge count on page load
  fetch('/VolunteerHub/volunteer/get_notifications_volunteer.php?count_only=1')
    .then(r => r.json())
    .then(data => {
      if (data.unread > 0) {
        badge.textContent = data.unread > 9 ? '9+' : data.unread;
        badge.style.display = 'flex';
      }
    }).catch(() => {});
})();
</script>
</header>

<!-- ── Main content ── -->
<main class="page-content">

<script>
(function () {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const toggle   = document.getElementById('toggleSidebar');
  const close    = document.getElementById('closeSidebar');

  function open()  { sidebar.classList.add('active');  overlay.classList.add('active'); }
  function shut()  { sidebar.classList.remove('active'); overlay.classList.remove('active'); }

  if (toggle)  toggle.addEventListener('click', open);
  if (close)   close.addEventListener('click', shut);
  if (overlay) overlay.addEventListener('click', shut);

  document.addEventListener('keydown', e => { if (e.key === 'Escape') shut(); });
})();
</script>