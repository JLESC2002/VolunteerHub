<?php
/**
 * File: forgot_password.php
 * Location: VolunteerHub/forgot_password.php
 *
 * Handles both steps:
 *   Step 1 — User enters email → system verifies & stores reset token
 *   Step 2 — User enters new password (after clicking token link)
 */

include 'conn.php';

$step     = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$token    = $_GET['token'] ?? '';
$role     = $_GET['role'] ?? 'Volunteer'; // preserve role for back-link context
$message  = '';
$msgType  = ''; // 'success' | 'error' | 'info'

/* ════════════════════════════════════════════════════════════════
   STEP 1 POST — verify email & store reset token
   ════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $msgType  = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res  = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        if (!$user) {
            /* Email not found — show error */
            $message = 'Email address not found in our records. Please check and try again.';
            $msgType  = 'error';
        } else {
            // Generate a secure token valid for 1 hour
            $resetToken  = bin2hex(random_bytes(32));
            $expiry      = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $upd = $conn->prepare(
                "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?"
            );
            $upd->bind_param("ssi", $resetToken, $expiry, $user['id']);
            $upd->execute();
            $upd->close();

            // Skip the link step — go directly to password reset form
            $role = $user['role'];
            $step = 2;
            $token = $resetToken;
        }
    }
}

/* ════════════════════════════════════════════════════════════════
   STEP 2 POST — validate token & update password
   ════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $token       = trim($_POST['token'] ?? '');
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 8) {
        $message = 'Password must be at least 8 characters.';
        $msgType  = 'error';
    } elseif ($newPass !== $confirmPass) {
        $message = 'Passwords do not match.';
        $msgType  = 'error';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, role FROM users
              WHERE reset_token = ?
                AND reset_token_expiry > NOW()"
        );
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res  = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $message = 'This reset link is invalid or has expired. Please request a new one.';
            $msgType  = 'error';
        } else {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT);

            $upd = $conn->prepare(
                "UPDATE users
                    SET password = ?, reset_token = NULL, reset_token_expiry = NULL
                  WHERE id = ?"
            );
            $upd->bind_param("si", $hashed, $user['id']);
            $upd->execute();
            $upd->close();

            $role     = $user['role'];
            $loginUrl = $role === 'Admin'
                ? '/VolunteerHub/admin/admin_login.php'
                : '/VolunteerHub/volunteer/volunteer_login.php';

            $message = 'Password changed successfully! '
                     . "<a href=\"{$loginUrl}\" class=\"reset-direct-link\">Click here to log in.</a>";
            $msgType  = 'success';
            $step     = 3; // "done" state — hide form
        }
    }
}

/* ════════════════════════════════════════════════════════════════
   STEP 2 GET — show reset form (token came from URL)
   ════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $step === 2) {
    // Validate token exists & is not expired before showing form
    $stmt = $conn->prepare(
        "SELECT id FROM users
          WHERE reset_token = ?
            AND reset_token_expiry > NOW()"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if ($res->num_rows === 0) {
        $message = 'This reset link is invalid or has expired. Please request a new one.';
        $msgType  = 'error';
        $step     = 1; // fall back to step 1 UI
    }
}

/* Back-link based on role */
$backLink = ($role === 'Admin')
    ? '/VolunteerHub/admin/admin_login.php'
    : '/VolunteerHub/volunteer/volunteer_login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — VolunteerHub</title>
  <link rel="stylesheet" href="/VolunteerHub/styles/login_styles.css">
  <style>
    /* ── Extra styles for forgot-password page ──────────── */
    .fp-step-indicator {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0;
      margin-bottom: 28px;
    }
    .fp-step {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      font-size: .78rem;
      font-weight: 700;
      background: var(--neutral-300);
      color: var(--neutral-500);
      position: relative;
      z-index: 1;
      transition: background .3s, color .3s;
    }
    .fp-step.active {
      background: linear-gradient(135deg, var(--green-mid), var(--green-dark));
      color: #fff;
      box-shadow: 0 3px 10px rgba(45,134,83,.35);
    }
    .fp-step.done {
      background: var(--green-light);
      color: #fff;
    }
    .fp-step-line {
      flex: 1;
      height: 2px;
      background: var(--neutral-300);
      max-width: 60px;
    }
    .fp-step-line.done { background: var(--green-light); }

    /* Alert enhancements */
    .alert { margin-bottom: 22px; border-radius: 12px; padding: 13px 16px; font-size: .88rem; font-weight: 500; display: flex; align-items: flex-start; gap: 10px; }
    .alert i { font-size: 1rem; margin-top: 1px; flex-shrink: 0; }
    .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .alert-info    { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

    .reset-direct-link {
      color: var(--green-mid);
      font-weight: 600;
      word-break: break-all;
      text-decoration: underline;
    }

    /* Password strength bar */
    .pw-strength-wrap { margin-top: 8px; display: none; }
    .pw-strength-bar  { height: 4px; border-radius: 99px; background: var(--neutral-300); overflow: hidden; }
    .pw-strength-fill { height: 100%; border-radius: 99px; transition: width .3s, background .3s; width: 0; }
    .pw-strength-label { font-size: .75rem; color: var(--neutral-500); margin-top: 4px; }

    .back-link-wrap { text-align: center; margin-top: 20px; }
    .back-link-wrap a { font-size: .87rem; color: var(--green-mid); font-weight: 500; text-decoration: none; }
    .back-link-wrap a:hover { color: var(--green-dark); text-decoration: underline; }

    .pw-toggle { position: relative; }
    .pw-eye {
      position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
      cursor: pointer; color: var(--neutral-500); font-size: .9rem;
      background: none; border: none; padding: 0;
    }
    .pw-eye:hover { color: var(--green-mid); }
    .pw-toggle input { padding-right: 42px; }
  </style>
</head>
<body>

<div class="container">

  <!-- Brand mark -->
  <div style="text-align:center;margin-bottom:6px;">
    <span style="font-size:2rem;">🌿</span>
  </div>

  <h2>
    <?php if ($step === 1): ?>Forgot Password
    <?php elseif ($step === 2): ?>Set New Password
    <?php else: ?>Password Reset
    <?php endif; ?>
  </h2>

  <!-- Step indicator -->
  <div class="fp-step-indicator">
    <div class="fp-step <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'done' : '' ?>">1</div>
    <div class="fp-step-line <?= $step > 1 ? 'done' : '' ?>"></div>
    <div class="fp-step <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'done' : '' ?>">2</div>
    <div class="fp-step-line <?= $step > 2 ? 'done' : '' ?>"></div>
    <div class="fp-step <?= $step >= 3 ? 'active done' : '' ?>">✓</div>
  </div>

  <?php if ($message): ?>
    <?php
      $iconMap = ['success' => 'fa-check-circle', 'error' => 'fa-exclamation-circle', 'info' => 'fa-info-circle'];
      $icon = $iconMap[$msgType] ?? 'fa-info-circle';
    ?>
    <div class="alert alert-<?= $msgType ?>">
      <i class="fas <?= $icon ?>"></i>
      <span><?= $message ?></span>
    </div>
  <?php endif; ?>

  <!-- ── STEP 1: Email Entry ─────────────────────────────── -->
  <?php if ($step === 1): ?>
    <p style="font-size:.88rem;color:var(--neutral-500);text-align:center;margin-bottom:20px;">
      Enter your registered email address and we'll generate a password reset link.
    </p>
    <form method="POST" action="forgot_password.php?step=1" novalidate>
      <label for="email">Registered Email</label>
      <input type="email" id="email" name="email"
             placeholder="you@example.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      <button type="submit">
        <i class="fas fa-paper-plane" style="margin-right:6px;"></i>
        Send Reset Link
      </button>
    </form>

  <!-- ── STEP 2: New Password Form ──────────────────────── -->
<?php elseif ($step === 2): ?>
  <p style="font-size:.88rem;color:var(--neutral-500);text-align:center;margin-bottom:20px;">
    Choose a strong password for your account.
  </p>
  <form method="POST" action="forgot_password.php?step=2" novalidate id="resetForm">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

    <label for="new_password">New Password</label>
    <div class="pw-toggle" style="display: flex; align-items: center; background: var(--neutral-100); border: 1.5px solid var(--neutral-300); border-radius: var(--radius); transition: all 0.2s;">
      <input type="password" id="new_password" name="new_password"
             placeholder="Minimum 8 characters" required autocomplete="new-password"
             oninput="checkStrength(this.value)"
             style="flex: 1; border: none; background: transparent; padding: 12px 16px; outline: none; box-shadow: none;">
      <a href="javascript:void(0)" onclick="togglePw('new_password', this)"
         style="padding: 0 15px; text-decoration: none; color: #94a3b8; display: flex; align-items: center; transition: color 0.2s;">
        <i class="fas fa-eye"></i>
      </a>
    </div>

    <div id="strengthWrap" class="pw-strength-wrap">
      <div class="pw-strength-bar"><div id="strengthFill" class="pw-strength-fill"></div></div>
      <div id="strengthLabel" class="pw-strength-label"></div>
    </div>

    <label for="confirm_password" style="margin-top: 18px;">Confirm Password</label>
    <div class="pw-toggle" style="display: flex; align-items: center; background: var(--neutral-100); border: 1.5px solid var(--neutral-300); border-radius: var(--radius); transition: all 0.2s;">
      <input type="password" id="confirm_password" name="confirm_password"
             placeholder="Re-enter password" required autocomplete="new-password"
             style="flex: 1; border: none; background: transparent; padding: 12px 16px; outline: none; box-shadow: none;">
      <a href="javascript:void(0)" onclick="togglePw('confirm_password', this)"
         style="padding: 0 15px; text-decoration: none; color: #94a3b8; display: flex; align-items: center; transition: color 0.2s;">
        <i class="fas fa-eye"></i>
      </a>
    </div>

    <button type="submit">
      <i class="fas fa-lock" style="margin-right:6px;"></i>
      Reset Password
    </button>
  </form>

  <style>
    /* Add this small script to handle the focus glow on the custom wrapper */
    .pw-toggle:focus-within {
      border-color: var(--green-mid) !important;
      background: var(--white) !important;
      box-shadow: 0 0 0 3px rgba(45,134,83,.14);
    }
    .pw-toggle a:hover {
      color: var(--green-mid) !important;
    }
  </style>

   <script>
function checkStrength(pw) {
  const wrap  = document.getElementById('strengthWrap');
  const fill  = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');
  
  if (!pw.length) {
    wrap.style.display = 'none';
    return;
  }
  
  wrap.style.display = 'block';
  let score = 0;
  if (pw.length >= 8) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;

  const levels = [
    { w: '25%', bg: '#ef4444', txt: 'Weak' },
    { w: '50%', bg: '#f59e0b', txt: 'Fair' },
    { w: '75%', bg: '#3b82f6', txt: 'Good' },
    { w: '100%',bg: '#16a34a', txt: 'Strong' },
  ];
  
  const lvl = levels[Math.max(0, score - 1)];
  fill.style.width = lvl.w;
  fill.style.background = lvl.bg;
  label.textContent = lvl.txt;
  label.style.color = lvl.bg;
}

function togglePw(id, anchor) {
  const inp = document.getElementById(id);
  const icon = anchor.querySelector('i');
  
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'fas fa-eye-slash'; // Swaps the icon
  } else {
    inp.type = 'password';
    icon.className = 'fas fa-eye'; // Swaps back
  }
}
</script>
  <?php endif; ?>

  <div class="back-link-wrap">
    <a href="<?= htmlspecialchars($backLink) ?>">
      <i class="fas fa-arrow-left" style="margin-right:4px;font-size:.75rem;"></i>
      Back to Login
    </a>
  </div>

</div>
</body>
</html>