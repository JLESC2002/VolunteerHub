<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? "VolunteerHub Admin") ?></title>

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90' font-family='system-ui, -apple-system, sans-serif'>🌿</text></svg>">

  <link href="../includes/css/bootstrap.min.css" rel="stylesheet">
  <link href="../includes/css/all.min.css" rel="stylesheet">

  <?php
  $currentPage = basename($_SERVER['PHP_SELF']);
  if ($currentPage === 'index.php') { return; }
  ?>

  <!-- Design system -->
  <link rel="stylesheet" href="/VolunteerHub/styles/admin_layout.css">

  <?php if (!empty($pageCSS)): ?>
  <!-- Page-specific CSS -->
  <link rel="stylesheet" href="<?= htmlspecialchars($pageCSS) ?>">
  <?php endif; ?>
</head>

<body>

<!-- ── Overlay (closes sidebar on outside click) ── -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ── Sidebar ── -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <a href="/VolunteerHub/admin/admin_dashboard.php" class="brand text-decoration-none">
      <div class="brand-icon">🌿</div>
      <span class="brand-name">VolunteerHub</span>
    </a>
    <button id="closeSidebar" aria-label="Close menu">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <span class="sidebar-section-label">Management</span>

  <ul class="nav flex-column">
    <li class="nav-item">
      <a href="/VolunteerHub/admin/admin_dashboard.php"
         class="nav-link <?= ($currentPage === 'admin_dashboard.php') ? 'active' : '' ?>">
        <i class="fas fa-tachometer-alt"></i> Dashboard
      </a>
    </li>
    <li class="nav-item">
      <a href="/VolunteerHub/admin/manage_events.php"
         class="nav-link <?= ($currentPage === 'manage_events.php') ? 'active' : '' ?>">
        <i class="fas fa-calendar-alt"></i> Manage Events
      </a>
    </li>
    <li class="nav-item">
      <a href="/VolunteerHub/admin/manage_donations.php"
         class="nav-link <?= ($currentPage === 'manage_donations.php') ? 'active' : '' ?>">
        <i class="fas fa-hand-holding-heart"></i> Manage Donations
      </a>
    </li>
    <li class="nav-item">
      <a href="/VolunteerHub/admin/manage_tasks.php"
         class="nav-link <?= ($currentPage === 'manage_tasks.php') ? 'active' : '' ?>">
        <i class="fas fa-tasks"></i> Manage Tasks
      </a>
    </li>
    <!-- Add after Manage Tasks nav-item -->
    <li class="nav-item">
      <a href="/VolunteerHub/admin/admin_notifications.php"
        class="nav-link <?= ($currentPage === 'admin_notifications.php') ? 'active' : '' ?>">
        <i class="fas fa-bell"></i> Notifications
      </a>
    </li>
    <li class="nav-item">
      <a href="/VolunteerHub/admin/admin_reports.php"
        class="nav-link <?= ($currentPage === 'admin_reports.php') ? 'active' : '' ?>">
        <i class="fas fa-chart-bar"></i> Reports
      </a>
    </li>
  </ul>

  <span class="sidebar-section-label" style="margin-top:auto;">Account</span>
  <ul class="nav flex-column" style="padding-bottom:16px;">
    <li class="nav-item">
      <a href="/VolunteerHub/admin/admin_profile.php"
         class="nav-link <?= ($currentPage === 'admin_profile.php') ? 'active' : '' ?>">
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
    <a href="/VolunteerHub/admin/admin_dashboard.php" class="brand-link text-decoration-none">
      <span>VolunteerHub</span>
    </a>
    <span class="role-badge d-none d-sm-inline">Admin</span>
  </div>

  <!-- Profile dropdown -->
  <!-- ── Topbar right side ── -->
<div class="d-flex align-items-center gap-2">

  <!-- 🔔 Notification Bell -->
  <div class="dropdown" id="notifDropdownWrap">
    <button class="notif-bell-btn" id="notifBellBtn"
            data-bs-toggle="dropdown" aria-expanded="false"
            aria-label="Notifications">
      <i class="fas fa-bell"></i>
      <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
    </button>

    <div class="dropdown-menu dropdown-menu-end notif-dropdown" id="notifDropdownMenu"
         aria-labelledby="notifBellBtn">
      <div class="notif-dropdown-header">
        <span><i class="fas fa-bell me-2"></i>Notifications</span>
        <a href="/VolunteerHub/admin/admin_notifications.php" class="notif-see-all">See all</a>
      </div>
      <div id="notifList">
        <div class="notif-loading">
          <i class="fas fa-spinner fa-spin"></i> Loading…
        </div>
      </div>
    </div>
  </div>

  <!-- Profile dropdown -->
  <div class="dropdown">
    <button class="dropdown-toggle" type="button" id="userDropdown"
            data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-user-circle"></i>
      <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
      <li>
        <a class="dropdown-item" href="/VolunteerHub/admin/admin_profile.php">
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

  // Close on ESC
  document.addEventListener('keydown', e => { if (e.key === 'Escape') shut(); });
})();
/* ── Notification Bell AJAX ───────────────────────────── */
(function () {
  const bell    = document.getElementById('notifBellBtn');
  const badge   = document.getElementById('notifBadge');
  const list    = document.getElementById('notifList');
  let loaded    = false;

  function fetchNotifs() {
    if (loaded) return;
    loaded = true;
    fetch('/VolunteerHub/admin/get_notifications.php')
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
            <div class="notif-icon ${n.color}"><i class="${n.icon}"></i></div>
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

  // Load on first open of dropdown
  if (bell) {
    bell.addEventListener('click', fetchNotifs);
  }

  // Also show badge on page load (quick count)
  fetch('/VolunteerHub/admin/get_notifications.php?count_only=1')
    .then(r => r.json())
    .then(data => {
      if (data.unread > 0) {
        badge.textContent = data.unread > 9 ? '9+' : data.unread;
        badge.style.display = 'flex';
      }
    }).catch(() => {});
})();
</script>