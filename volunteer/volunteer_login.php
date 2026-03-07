<?php
/* ══════════════════════════════════════════════════════════════
   PHP LOGIN LOGIC AT TOP — allows proper header handling
   ══════════════════════════════════════════════════════════════ */
session_start();

$login_error = null;
$show_confirmation = false;
$redirect_url = null;
$last_email = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = $_POST["email"]    ?? '';
    $password = $_POST["password"] ?? '';
    $role     = $_POST["role"]     ?? 'Volunteer';

    $flask_url = "http://localhost:5000/login";
    $data      = json_encode(["email" => $email, "password" => $password, "role" => $role]);

    $ch = curl_init($flask_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $result      = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error  = curl_error($ch);
    curl_close($ch);

    if ($curl_error || $http_status === 0) {
        $login_error = 'Unable to reach the authentication server. Please try again later.';
    } elseif ($http_status === 200) {
        $response_data = json_decode($result, true);

        if ($response_data["status"] === "success") {
            setcookie("session_token", $response_data['session_token'], time() + 86400, "/", "", false, true);
            $redirect_url = $response_data["redirect"] ?? 'volunteer_dashboard.php';
            $show_confirmation = true;
        } else {
            $login_error = $response_data["message"] ?? 'Incorrect email or password.';
        }
    } elseif ($http_status === 401) {
        $login_error = 'Incorrect email or password. Please try again.';
    } else {
        $login_error = 'An unexpected error occurred. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Login — VolunteerHub</title>
    <link rel="stylesheet" href="../styles/login_styles.css">
    <link rel="stylesheet" href="../includes/css/all.min.css">
    <style>
      /* ── Improved alert messages ────────────────────────── */
      .vh-alert {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 13px 16px;
        border-radius: 12px;
        font-size: .875rem;
        font-weight: 500;
        margin-bottom: 20px;
        animation: alertIn .35s cubic-bezier(.22,1,.36,1) both;
      }
      @keyframes alertIn {
        from { opacity:0; transform:translateY(-8px); }
        to   { opacity:1; transform:translateY(0); }
      }
      .vh-alert i { font-size: 1rem; flex-shrink: 0; }
      .vh-alert-success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
      .vh-alert-error   { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
      .vh-alert-warning { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
      .vh-alert-info    { background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; }

      /* ── Forgot password link ───────────────────────────── */
      .forgot-link {
        text-align: right;
        margin-top: 6px;
        margin-bottom: 2px;
      }
      .forgot-link a {
        font-size: .8rem;
        color: var(--green-mid);
        text-decoration: none;
        font-weight: 500;
        transition: color .2s;
      }
      .forgot-link a:hover { color: var(--green-dark); text-decoration: underline; }

      /* ── Loading state ──────────────────────────────────── */
      .btn-login { position: relative; }
      .btn-login.loading { opacity: .75; pointer-events: none; }
      .btn-login.loading::before {
        content: '';
        position: absolute;
        left: 16px; top: 50%; transform: translateY(-50%);
        width: 16px; height: 16px;
        border: 2px solid rgba(255,255,255,.4);
        border-top-color: #fff;
        border-radius: 50%;
        animation: spin .7s linear infinite;
      }
      @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

      /* ── Register link ──────────────────────────────────── */
      .footer-links { display: flex; justify-content: space-between; margin-top: 20px; }
      .footer-links a { font-size: .85rem; color: var(--green-mid); font-weight: 500; text-decoration: none; }
      .footer-links a:hover { color: var(--green-dark); text-decoration: underline; }

      /* ── Login Confirmation Modal ───────────────────────── */
      .login-modal {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(3px);
        background: rgba(0, 0, 0, 0.45);
        animation: backdropFadeIn 0.3s ease both;
      }

      @keyframes backdropFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }

      .login-modal-content {
        background: white;
        border-radius: 16px;
        padding: 40px 36px;
        max-width: 380px;
        width: 100%;
        margin: 16px;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        animation: modalSlideIn 0.35s cubic-bezier(.22, 1, .36, 1) both;
      }

      @keyframes modalSlideIn {
        from {
          opacity: 0;
          transform: translateY(-12px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .login-icon {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        background: linear-gradient(135deg, #2d8653, #1a5c3a);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 24px;
        font-size: 32px;
        color: white;
      }

      .login-modal h2 {
        font-size: 1.3rem;
        font-weight: 700;
        color: #1a1f2e;
        margin-bottom: 8px;
      }

      .login-modal p {
        font-size: 0.95rem;
        color: #64748b;
        margin-bottom: 28px;
        line-height: 1.5;
      }

      .login-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
      }

      .login-btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        flex: 1;
      }

      .btn-cancel {
        background: #f1f5f9;
        color: #64748b;
      }

      .btn-cancel:hover {
        background: #e2e8f0;
        color: #475569;
      }

      .btn-continue {
        background: linear-gradient(135deg, #2d8653, #1a5c3a);
        color: white;
      }

      .btn-continue:hover {
        background: linear-gradient(135deg, #1f5a3c, #0f4c2a);
        box-shadow: 0 8px 16px rgba(45, 134, 83, 0.3);
      }

      .btn-continue.loading {
        opacity: 0.7;
        pointer-events: none;
      }
    </style>
</head>
<body>

<?php if ($show_confirmation): ?>
  <!-- Login Confirmation Modal -->
  <div class="login-modal">
    <div class="login-modal-content">
      <div class="login-icon">
        <i class="fas fa-check-circle"></i>
      </div>
      <h2>Login Confirmed!</h2>
      <p>Welcome back! Ready to explore volunteer opportunities?</p>
      <div class="login-actions">
        <button class="login-btn btn-cancel" onclick="goBack()">
          <i class="fas fa-arrow-left" style="margin-right: 6px;"></i> Not now
        </button>
        <button class="login-btn btn-continue" id="continueBtn" onclick="proceedToDashboard()">
          <i class="fas fa-arrow-right" style="margin-right: 6px;"></i> Continue
        </button>
      </div>
    </div>
  </div>

  <script>
    function goBack() {
      window.location.href = 'volunteer_login.php';
    }

    function proceedToDashboard() {
      const btn = document.getElementById('continueBtn');
      btn.classList.add('loading');
      btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 6px;"></i> Loading...';
      setTimeout(() => {
        window.location.href = '<?= htmlspecialchars($redirect_url, ENT_QUOTES) ?>';
      }, 800);
    }
  </script>
<?php else: ?>
  <!-- Login Form -->
  <div class="container">
    <div style="text-align:center;margin-bottom:6px;">
      <span style="font-size:2rem;">🌿</span>
    </div>

    <h2>Volunteer Login</h2>

    <?php if ($login_error): ?>
      <div class="vh-alert vh-alert-error">
        <i class="fas fa-lock"></i>
        <span><?= htmlspecialchars($login_error, ENT_QUOTES) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" id="loginForm" novalidate>
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email"
             placeholder="you@example.com"
             value="<?= $last_email ?>" required autofocus>

      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="••••••••" required>

      <div class="forgot-link">
        <a href="/VolunteerHub/forgot_password.php?role=Volunteer">Forgot password?</a>
      </div>

      <input type="hidden" name="role" value="Volunteer">

      <button type="submit" class="btn-login" id="submitBtn"
              onclick="this.classList.add('loading')">
        Login as Volunteer
      </button>
    </form>

    <div class="footer-links">
      <a href="../index.php">← Back to Home</a>
      <a href="../register.php">Create Account →</a>
    </div>
  </div>
<?php endif; ?>

</body>
</html>