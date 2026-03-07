<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? "VolunteerHub Admin") ?></title>

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
</script>