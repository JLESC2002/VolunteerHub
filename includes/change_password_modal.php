<?php
/**
 * File: change_password_modal.php
 * Location: VolunteerHub/includes/change_password_modal.php
 *
 * Drop-in modal for Change Password — works for both Admin and Volunteer.
 *
 * Usage: include this file AFTER the main page HTML.
 * Required variables before include:
 *   $pw_action  — form action URL  e.g. 'volunteer_change_password.php'
 *
 * The modal is opened by calling: openChangePasswordModal()
 */

// Determine alert from ?pw_status query parameter
$pw_status    = $_GET['pw_status'] ?? '';
$pw_alert     = '';
$pw_alert_type= '';

switch ($pw_status) {
    case 'success':
        $pw_alert      = '<i class="fas fa-check-circle"></i> Password changed successfully!';
        $pw_alert_type = 'success';
        break;
    case 'mismatch':
        $pw_alert      = '<i class="fas fa-exclamation-circle"></i> New passwords do not match. Please try again.';
        $pw_alert_type = 'error';
        break;
    case 'wrong_current':
        $pw_alert      = '<i class="fas fa-lock"></i> Your current password is incorrect.';
        $pw_alert_type = 'error';
        break;
    case 'short':
        $pw_alert      = '<i class="fas fa-exclamation-triangle"></i> New password must be at least 8 characters.';
        $pw_alert_type = 'warning';
        break;
    case 'empty':
        $pw_alert      = '<i class="fas fa-exclamation-triangle"></i> All password fields are required.';
        $pw_alert_type = 'warning';
        break;
    case 'db_error':
        $pw_alert      = '<i class="fas fa-times-circle"></i> A database error occurred. Please try again.';
        $pw_alert_type = 'error';
        break;
}
?>

<!-- ════════════════════════════════════════════════════════════
     CHANGE PASSWORD MODAL
     ════════════════════════════════════════════════════════════ -->
<div id="changePasswordModal"
     style="display:none;position:fixed;inset:0;z-index:2000;align-items:center;justify-content:center;">

  <!-- Backdrop -->
  <div onclick="closeChangePasswordModal()"
       style="position:absolute;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);"></div>

  <!-- Modal box -->
  <div style="position:relative;z-index:1;background:#fff;border-radius:18px;
              width:100%;max-width:420px;margin:16px;padding:36px 32px;
              box-shadow:0 20px 60px rgba(0,0,0,.2);
              animation:pwModalIn .35s cubic-bezier(.22,1,.36,1) both;">

    <!-- Close button -->
    <button onclick="closeChangePasswordModal()"
            style="position:absolute;top:16px;right:16px;background:none;border:none;
                   font-size:1.1rem;color:#94a3b8;cursor:pointer;padding:4px 8px;
                   border-radius:8px;transition:color .2s,background .2s;"
            onmouseover="this.style.color='#1a5c3a';this.style.background='#e8f5ee'"
            onmouseout="this.style.color='#94a3b8';this.style.background='none'">
      <i class="fas fa-times"></i>
    </button>

    <!-- Title -->
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px;">
      <div style="width:38px;height:38px;border-radius:10px;flex-shrink:0;
                  background:linear-gradient(135deg,#2d8653,#1a5c3a);
                  display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-lock" style="color:#fff;font-size:.9rem;"></i>
      </div>
      <div>
        <h3 style="margin:0;font-size:1.1rem;font-weight:700;color:#1a1f2e;">Change Password</h3>
        <p style="margin:0;font-size:.78rem;color:#94a3b8;">Keep your account secure</p>
      </div>
    </div>

    <!-- In-modal alert (pw_status feedback) -->
    <?php if ($pw_alert): ?>
      <div class="pw-modal-alert pw-alert-<?= $pw_alert_type ?>"
           style="margin-bottom:18px;">
        <?= $pw_alert ?>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="<?= htmlspecialchars($pw_action) ?>" id="changePasswordForm" novalidate>

      <div style="margin-bottom:14px;">
        <label class="pw-label">Current Password</label>
        <div class="pw-field-wrap">
          <input type="password" name="current_password" id="curPw"
                 placeholder="Enter current password" autocomplete="current-password" required>
          <button type="button" class="pw-eye-btn" onclick="togglePwField('curPw', this)">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </div>

      <div style="margin-bottom:14px;">
        <label class="pw-label">New Password</label>
        <div class="pw-field-wrap">
          <input type="password" name="new_password" id="newPw"
                 placeholder="Minimum 8 characters" autocomplete="new-password" required
                 oninput="updateStrength(this.value)">
          <button type="button" class="pw-eye-btn" onclick="togglePwField('newPw', this)">
            <i class="fas fa-eye"></i>
          </button>
        </div>
        <!-- Strength indicator -->
        <div id="pwStrengthWrap" style="margin-top:7px;display:none;">
          <div style="height:4px;background:#e2e8f0;border-radius:99px;overflow:hidden;">
            <div id="pwStrengthBar"
                 style="height:100%;border-radius:99px;transition:width .3s,background .3s;width:0;"></div>
          </div>
          <div id="pwStrengthLabel"
               style="font-size:.72rem;margin-top:3px;font-weight:600;"></div>
        </div>
      </div>

      <div style="margin-bottom:22px;">
        <label class="pw-label">Confirm New Password</label>
        <div class="pw-field-wrap">
          <input type="password" name="confirm_password" id="confirmPw"
                 placeholder="Re-enter new password" autocomplete="new-password" required
                 oninput="checkMatch()">
          <button type="button" class="pw-eye-btn" onclick="togglePwField('confirmPw', this)">
            <i class="fas fa-eye"></i>
          </button>
        </div>
        <div id="matchMsg" style="font-size:.72rem;margin-top:3px;font-weight:600;display:none;"></div>
      </div>

      <button type="submit" id="pwSubmitBtn"
              style="width:100%;padding:13px 20px;
                     background:linear-gradient(135deg,#2d8653,#1a5c3a);
                     color:#fff;border:none;border-radius:12px;
                     font-size:.95rem;font-weight:600;cursor:pointer;
                     box-shadow:0 4px 14px rgba(45,134,83,.32);
                     transition:all .22s;">
        <i class="fas fa-shield-alt" style="margin-right:6px;"></i>
        Update Password
      </button>

    </form>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     Shared styles (scoped via .pw- prefix)
     ════════════════════════════════════════════════════════════ -->
<style>
@keyframes pwModalIn {
  from { opacity:0; transform:scale(.94) translateY(12px); }
  to   { opacity:1; transform:scale(1) translateY(0); }
}

.pw-label {
  display:block;
  font-size:.78rem;
  font-weight:700;
  color:#374151;
  text-transform:uppercase;
  letter-spacing:.04em;
  margin-bottom:5px;
}

.pw-field-wrap {
  position:relative;
}
.pw-field-wrap input {
  width:100%;
  padding:11px 42px 11px 14px;
  font-size:.92rem;
  color:#1a1f2e;
  background:#f7fafc;
  border:1.5px solid #d1d5db;
  border-radius:10px;
  outline:none;
  transition:border-color .22s, box-shadow .22s;
  box-sizing:border-box;
  font-family:'DM Sans',sans-serif;
}
.pw-field-wrap input:focus {
  border-color:#2d8653;
  box-shadow:0 0 0 3px rgba(45,134,83,.14);
  background:#fff;
}
.pw-eye-btn {
  position:absolute;
  right:12px;top:50%;
  transform:translateY(-50%);
  background:none;border:none;padding:0;
  color:#94a3b8;cursor:pointer;font-size:.88rem;
  transition:color .2s;
}
.pw-eye-btn:hover { color:#2d8653; }

/* Alert variants inside modal */
.pw-modal-alert {
  display:flex;align-items:center;gap:8px;
  padding:11px 14px;
  border-radius:10px;
  font-size:.83rem;font-weight:500;
  line-height:1.4;
}
.pw-alert-success { background:#f0fdf4;color:#166534;border:1px solid #bbf7d0; }
.pw-alert-error   { background:#fef2f2;color:#b91c1c;border:1px solid #fecaca; }
.pw-alert-warning { background:#fffbeb;color:#92400e;border:1px solid #fde68a; }

/* Page-level toast for pw_status=success (shown outside modal) */
.pw-page-toast {
  position:fixed;
  bottom:24px;right:24px;
  background:#166534;color:#fff;
  padding:13px 20px;
  border-radius:12px;
  font-size:.88rem;font-weight:600;
  display:flex;align-items:center;gap:8px;
  box-shadow:0 8px 24px rgba(22,101,52,.35);
  z-index:3000;
  animation:toastIn .4s cubic-bezier(.22,1,.36,1) both;
}
@keyframes toastIn {
  from { opacity:0; transform:translateY(16px); }
  to   { opacity:1; transform:translateY(0); }
}
</style>

<!-- ════════════════════════════════════════════════════════════
     Modal JS
     ════════════════════════════════════════════════════════════ -->
<script>
function openChangePasswordModal() {
  var modal = document.getElementById('changePasswordModal');
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  <?php if ($pw_status): ?>
  // If there's a status message, scroll modal into view
  modal.querySelector('.pw-modal-alert')?.scrollIntoView({ behavior:'smooth', block:'center' });
  <?php endif; ?>
}

function closeChangePasswordModal() {
  document.getElementById('changePasswordModal').style.display = 'none';
  document.body.style.overflow = '';
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeChangePasswordModal();
});

// Auto-open if there's a pw_status in URL (user was redirected back)
<?php if ($pw_status): ?>
document.addEventListener('DOMContentLoaded', function() {
  <?php if ($pw_status === 'success'): ?>
  // Success: show page toast, don't re-open modal
  var toast = document.createElement('div');
  toast.className = 'pw-page-toast';
  toast.innerHTML = '<i class="fas fa-check-circle"></i> Password changed successfully!';
  document.body.appendChild(toast);
  setTimeout(function() { toast.style.opacity='0'; toast.style.transition='opacity .5s'; }, 3500);
  setTimeout(function() { toast.remove(); }, 4000);
  <?php else: ?>
  // Error/warning: re-open modal so user sees the message
  openChangePasswordModal();
  <?php endif; ?>
});
<?php endif; ?>

/* Password strength meter */
function updateStrength(pw) {
  var wrap  = document.getElementById('pwStrengthWrap');
  var bar   = document.getElementById('pwStrengthBar');
  var label = document.getElementById('pwStrengthLabel');
  wrap.style.display = pw.length ? 'block' : 'none';

  var score = 0;
  if (pw.length >= 8)            score++;
  if (/[A-Z]/.test(pw))         score++;
  if (/[0-9]/.test(pw))         score++;
  if (/[^A-Za-z0-9]/.test(pw))  score++;

  var levels = [
    { w:'25%',  bg:'#ef4444', txt:'Weak'   },
    { w:'50%',  bg:'#f59e0b', txt:'Fair'   },
    { w:'75%',  bg:'#3b82f6', txt:'Good'   },
    { w:'100%', bg:'#16a34a', txt:'Strong' },
  ];
  var lvl = levels[Math.max(0, score - 1)];
  bar.style.width      = lvl.w;
  bar.style.background = lvl.bg;
  label.textContent    = lvl.txt;
  label.style.color    = lvl.bg;
}

/* Confirm match indicator */
function checkMatch() {
  var newPw  = document.getElementById('newPw').value;
  var confPw = document.getElementById('confirmPw').value;
  var msg    = document.getElementById('matchMsg');
  if (!confPw) { msg.style.display = 'none'; return; }
  msg.style.display = 'block';
  if (newPw === confPw) {
    msg.textContent = '✓ Passwords match';
    msg.style.color = '#16a34a';
  } else {
    msg.textContent = '✗ Passwords do not match';
    msg.style.color = '#dc2626';
  }
}

/* Toggle password visibility */
function togglePwField(inputId, btn) {
  var inp  = document.getElementById(inputId);
  var icon = btn.querySelector('i');
  var show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}
</script>