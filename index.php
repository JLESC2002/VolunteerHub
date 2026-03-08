<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VolunteerHub</title>
  
  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90' font-family='system-ui, -apple-system, sans-serif'>🌿</text></svg>">
  
  <link rel="stylesheet" href="includes/styles/index_styles.css">
  <link rel="stylesheet" href="includes/css/all.min.css">
  <style>
    /* Logout success notification */
    .logout-notification {
      position: fixed;
      top: 20px;
      right: 20px;
      background: #f0fdf4;
      color: #166534;
      border: 1px solid #bbf7d0;
      border-radius: 12px;
      padding: 16px 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      display: flex;
      align-items: center;
      gap: 12px;
      z-index: 9999;
      animation: notificationSlideIn 0.4s cubic-bezier(.22,1,.36,1) both;
      max-width: 360px;
    }

    @keyframes notificationSlideIn {
      from {
        opacity: 0;
        transform: translateX(400px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes notificationSlideOut {
      from {
        opacity: 1;
        transform: translateX(0);
      }
      to {
        opacity: 0;
        transform: translateX(400px);
      }
    }

    .logout-notification.fadeOut {
      animation: notificationSlideOut 0.4s cubic-bezier(.22,1,.36,1) both;
    }

    .logout-notification i {
      font-size: 1.2rem;
      flex-shrink: 0;
    }

    .notification-content {
      flex: 1;
    }

    .notification-title {
      font-weight: 600;
      margin-bottom: 2px;
    }

    .notification-message {
      font-size: 0.9rem;
      opacity: 0.9;
    }

    .notification-close {
      background: none;
      border: none;
      color: inherit;
      cursor: pointer;
      font-size: 1.2rem;
      padding: 0;
      flex-shrink: 0;
      opacity: 0.7;
      transition: opacity 0.2s;
    }

    .notification-close:hover {
      opacity: 1;
    }

    @media (max-width: 480px) {
      .logout-notification {
        left: 12px;
        right: 12px;
        max-width: none;
      }
    }
  </style>
</head>
<body>

  <?php if (isset($_GET['logged_out']) && $_GET['logged_out'] === '1'): ?>
  <div class="logout-notification" id="logoutNotif">
    <i class="fas fa-check-circle"></i>
    <div class="notification-content">
      <div class="notification-title">See you soon!</div>
      <div class="notification-message">You've been logged out successfully.</div>
    </div>
    <button class="notification-close" onclick="closeNotification()">
      <i class="fas fa-times"></i>
    </button>
  </div>
  <?php endif; ?>

  <div class="auth-container">
    <h1 class="section-title">Welcome to VolunteerHub</h1>
    <p class="subtitle">Empowering communities through volunteer connections.</p>

    <div class="button-group">
      <a href="volunteer/pages/volunteer_login.php" class="btn-primary">Login as Volunteer</a>
      <a href="admin/pages/admin_login.php" class="btn-primary">Login as Admin</a>
      <a href="register.php" class="btn-secondary">Register</a>
    </div>

    <p class="footer-text">© 2025 VolunteerHub. All rights reserved.</p>
  </div>

  <script>
    function closeNotification() {
      const notif = document.getElementById('logoutNotif');
      if (notif) {
        notif.classList.add('fadeOut');
        setTimeout(() => notif.remove(), 400);
      }
    }

    // Auto-close notification after 4 seconds
    if (document.getElementById('logoutNotif')) {
      setTimeout(closeNotification, 4000);
    }
  </script>

</body>
</html>
