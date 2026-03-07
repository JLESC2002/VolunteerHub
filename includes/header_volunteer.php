<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? "VolunteerHub" ?></title>

<link href="../includes/css/bootstrap.min.css" rel="stylesheet">
<link href="../includes/css/all.min.css" rel="stylesheet">

<?php
$currentPage = basename($_SERVER['PHP_SELF']);
if ($currentPage === 'index.php') {
    return; // ✅ Skip rendering header if on index page
}
?>

  <!-- Custom CSS -->
  <link rel="stylesheet" href="/VolunteerHub/styles/volunteer_layout.css">
</head>

<body>

<!-- 🌐 Sidebar (Hidden by default) -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-header d-flex justify-content-between align-items-center px-3 py-2">
    <a href="/VolunteerHub/volunteer/volunteer_dashboard.php" class="fw-bold text-primary fs-5 text-decoration-none">
      VolunteerHub
    </a>
    <button class="btn btn-sm btn-outline-secondary" id="closeSidebar">
      <i class="fas fa-times"></i>
    </button>
  </div>
  <ul class="nav flex-column mt-3">
    <li class="nav-item">
      <a href="/VolunteerHub/volunteer/organizations.php" class="nav-link"><i class="fas fa-calendar-alt me-2"></i> List of NGOs </a>
    </li>
    <li class="nav-item">
      <a href="/VolunteerHub/volunteer/list_events.php" class="nav-link"><i class="fas fa-hand-holding-heart me-2"></i> Events </a>
    </li>
    <li class="nav-item">
      <a href="/VolunteerHub/volunteer/my_tasks.php" class="nav-link"><i class="fas fa-tasks me-2"></i> My Tasks</a>
    </li>
    <li class="nav-item">
      <a href="/VolunteerHub/volunteer/notifications.php" class="nav-link"><i class="fas fa-tasks me-2"></i> Notification</a>
    </li>
  </ul>
</nav>

<!-- 🌟 Topbar (Sticky) -->
<header class="topbar d-flex justify-content-between align-items-center px-4 py-2 shadow-sm">
  <div class="d-flex align-items-center gap-3">
    <!--  Floating Hamburger -->
    <button class="btn btn-light" id="toggleSidebar">
      <i class="fas fa-bars"></i>
    </button>
    <a href="/VolunteerHub/volunteer/volunteer_dashboard.php" class="text-decoration-none text-primary fw-bold fs-5">
      VolunteerHub
    </a>
  </div>

  <!-- 👤 Profile Dropdown -->
  <div class="dropdown">
    <button class="btn btn-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
      <li><a class="dropdown-item" href="/VolunteerHub/volunteer/volunteers_profile.php">View Profile</a></li>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item text-danger" href="/VolunteerHub/logout.php">Logout</a></li>
    </ul>
  </div>
</header>

<!-- 🧱 Main Content -->
<main class="page-content p-4">
