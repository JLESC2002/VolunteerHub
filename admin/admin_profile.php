<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "Admin Profile";
include '../includes/header_admin.php';

$admin_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name, email, created_at, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("
    SELECT id, name, description, location, contact_email, contact_phone,
           gcash_name, gcash_number, gcash_qr,
           bank_name, bank_account_name, bank_account_number,
           dropoff_location, dropoff_instructions,
           logo, facebook_link
    FROM organizations WHERE admin_id = ?
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$organization = $stmt->get_result()->fetch_assoc();
?>

<style>
/* ═══════════════════════════════════════════════════════
   Admin Profile — Page Styles
   ═══════════════════════════════════════════════════════ */

.ap-page { padding: 24px 28px 56px; }

/* ── Page header ──────────────────────────────────────── */
.ap-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 22px;
  flex-wrap: wrap;
  gap: 10px;
}
.ap-page-title {
  font-size: 1.35rem;
  font-weight: 700;
  color: #1a1f2e;
  display: flex;
  align-items: center;
  gap: 9px;
  margin: 0;
}
.ap-page-title i { color: #2d8653; }

/* ── Alert banners ────────────────────────────────────── */
.ap-alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 16px;
  border-radius: 9px;
  font-size: .845rem;
  font-weight: 500;
  border: 1px solid transparent;
  margin-bottom: 18px;
}
.ap-alert-success { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
.ap-alert-error   { background: #fef2f2; color: #991b1b; border-color: #fecaca; }

/* ── Two-column layout ────────────────────────────────── */
.ap-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 20px;
  align-items: start;
}
@media (max-width: 900px) {
  .ap-layout { grid-template-columns: 1fr; }
}

/* ── Card base ────────────────────────────────────────── */
.ap-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: 0 1px 4px rgba(0,0,0,.05);
  overflow: hidden;
}
.ap-card + .ap-card { margin-top: 18px; }

.ap-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 13px 18px;
  border-bottom: 1px solid #f1f5f9;
  background: #fafbfc;
}
.ap-card-header h6 {
  font-size: .82rem;
  font-weight: 700;
  color: #1a1f2e;
  text-transform: uppercase;
  letter-spacing: .05em;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 7px;
}
.ap-card-header h6 i { color: #2d8653; font-size: .78rem; }
.ap-card-body { padding: 18px; }

/* ── LEFT COLUMN: Profile identity card ──────────────── */
.ap-identity {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24px 18px 20px;
  text-align: center;
  gap: 0;
}

/* Avatar */
.ap-avatar-ring {
  position: relative;
  width: 80px;
  height: 80px;
  margin-bottom: 14px;
  cursor: pointer;
  flex-shrink: 0;
}
.ap-avatar-img {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #fff;
  box-shadow: 0 0 0 3px #e8f5ee, 0 4px 14px rgba(45,134,83,.18);
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #e8f5ee, #c3e6d0);
  overflow: hidden;
  transition: box-shadow .2s;
}
.ap-avatar-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ap-avatar-img .fa-user-circle { font-size: 3.5rem; color: #4caf80; }
.ap-avatar-overlay {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: rgba(26,92,58,.5);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity .2s;
}
.ap-avatar-ring:hover .ap-avatar-overlay { opacity: 1; }
.ap-avatar-overlay i { color: #fff; font-size: .95rem; }

/* Name + role */
.ap-identity-name {
  font-size: 1.05rem;
  font-weight: 700;
  color: #1a1f2e;
  margin: 0 0 4px;
  line-height: 1.3;
}
.ap-identity-role {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #e8f5ee;
  color: #1a5c3a;
  font-size: .72rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 99px;
  text-transform: uppercase;
  letter-spacing: .04em;
  margin-bottom: 14px;
}

/* Meta list */
.ap-meta-list {
  width: 100%;
  border-top: 1px solid #f1f5f9;
  padding-top: 14px;
  display: flex;
  flex-direction: column;
  gap: 9px;
}
.ap-meta-row {
  display: flex;
  align-items: center;
  gap: 9px;
  font-size: .8rem;
  color: #4a5568;
  text-align: left;
}
.ap-meta-row i {
  width: 28px;
  height: 28px;
  background: #f1f5f9;
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #2d8653;
  font-size: .75rem;
  flex-shrink: 0;
}
.ap-meta-row span { word-break: break-all; line-height: 1.35; }

/* ── LEFT COLUMN: Org logo card ──────────────────────── */
.ap-org-logo-card .ap-card-body {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 18px;
}
.ap-org-logo-wrap {
  position: relative;
  flex-shrink: 0;
  cursor: pointer;
}
.ap-org-logo-img {
  width: 52px;
  height: 52px;
  border-radius: 10px;
  object-fit: cover;
  border: 2px solid #e2e8f0;
  box-shadow: 0 1px 5px rgba(0,0,0,.08);
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f1f5f9;
  overflow: hidden;
  transition: box-shadow .2s;
}
.ap-org-logo-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ap-org-logo-img .fa-image { font-size: 1.4rem; color: #94a3b8; }
.ap-org-logo-overlay {
  position: absolute;
  inset: 0;
  border-radius: 10px;
  background: rgba(26,92,58,.5);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity .2s;
}
.ap-org-logo-wrap:hover .ap-org-logo-overlay { opacity: 1; }
.ap-org-logo-overlay i { color: #fff; font-size: .85rem; }
.ap-org-logo-info { flex: 1; min-width: 0; }
.ap-org-logo-name {
  font-size: .875rem;
  font-weight: 700;
  color: #1a1f2e;
  margin: 0 0 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ap-org-logo-loc {
  font-size: .78rem;
  color: #64748b;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── RIGHT COLUMN: Tab navigation ────────────────────── */
.ap-tabs {
  display: flex;
  border-bottom: 2px solid #f1f5f9;
  margin-bottom: 0;
  background: #fafbfc;
  border-radius: 14px 14px 0 0;
  overflow: hidden;
  gap: 0;
}
.ap-tab {
  flex: 1;
  padding: 12px 10px;
  font-size: .8rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .04em;
  border: none;
  background: transparent;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: color .15s, border-color .15s, background .15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  white-space: nowrap;
}
.ap-tab:hover { background: #f0f4f8; color: #1a1f2e; }
.ap-tab.active {
  color: #2d8653;
  border-bottom-color: #2d8653;
  background: #fff;
}
.ap-tab i { font-size: .78rem; }

/* Tab panels */
.ap-tab-panel { display: none; }
.ap-tab-panel.active { display: block; }

/* ── Form sections ────────────────────────────────────── */
.ap-section-label {
  font-size: .72rem;
  font-weight: 700;
  color: #2d8653;
  text-transform: uppercase;
  letter-spacing: .07em;
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 0 0 8px;
  border-bottom: 1.5px solid #e8f5ee;
  margin: 0 0 14px;
}
.ap-section-label i { font-size: .7rem; }

/* ── Form grid ────────────────────────────────────────── */
.ap-form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 13px;
  margin-bottom: 16px;
}
.ap-fg { display: flex; flex-direction: column; gap: 5px; }
.ap-fg.span2 { grid-column: 1 / -1; }

.ap-fg label {
  font-size: .72rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .05em;
}
.ap-fg input,
.ap-fg textarea,
.ap-fg select {
  padding: 9px 12px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font: inherit;
  font-size: .875rem;
  color: #1a1f2e;
  background: #f8fafc;
  outline: none;
  transition: border-color .18s, box-shadow .18s, background .18s;
  appearance: none;
  -webkit-appearance: none;
}
.ap-fg input:focus,
.ap-fg textarea:focus,
.ap-fg select:focus {
  border-color: #2d8653;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(45,134,83,.1);
}
.ap-fg input[type="file"] {
  background: #f8fafc;
  padding: 7px 10px;
  cursor: pointer;
  font-size: .8rem;
  color: #4a5568;
}
.ap-fg textarea { resize: vertical; min-height: 72px; }
.ap-fg-hint {
  font-size: .72rem;
  color: #94a3b8;
  margin-top: 3px;
}

/* ── Payment blocks ───────────────────────────────────── */
.ap-pay-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 13px;
  margin-bottom: 16px;
}
.ap-pay-block {
  background: #f8fafc;
  border: 1px solid #e9eef5;
  border-radius: 10px;
  padding: 14px 16px;
}
.ap-pay-block-title {
  font-size: .72rem;
  font-weight: 700;
  color: #2d8653;
  text-transform: uppercase;
  letter-spacing: .05em;
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 11px;
}
.ap-pay-block-title i { font-size: .7rem; }

/* QR preview inline */
.ap-qr-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
  padding-top: 10px;
  border-top: 1px solid #e9eef5;
}
.ap-qr-thumb {
  width: 56px;
  height: 56px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  object-fit: cover;
  flex-shrink: 0;
}
.ap-qr-label {
  font-size: .75rem;
  color: #64748b;
  line-height: 1.4;
}

/* ── Save bar ─────────────────────────────────────────── */
.ap-save-bar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 16px;
  border-top: 1px solid #f1f5f9;
  margin-top: 6px;
}
.ap-btn-save {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 10px 22px;
  background: linear-gradient(135deg, #2d8653, #1a5c3a);
  color: #fff;
  border: none;
  border-radius: 9px;
  font: inherit;
  font-size: .875rem;
  font-weight: 700;
  cursor: pointer;
  transition: opacity .15s, transform .15s;
  box-shadow: 0 2px 8px rgba(45,134,83,.28);
}
.ap-btn-save:hover { opacity: .9; transform: translateY(-1px); }

/* ── Modals ───────────────────────────────────────────── */
.ap-modal {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 1300;
}
.ap-modal.show {
  display: flex;
  align-items: center;
  justify-content: center;
}
.ap-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(6,10,17,.45);
  backdrop-filter: blur(5px);
}
.ap-modal-box {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 400px;
  margin: 0 16px;
  background: #fff;
  border-radius: 14px;
  padding: 26px 22px 20px;
  box-shadow: 0 16px 48px rgba(2,8,23,.16);
  animation: apPop .18s ease;
}
@keyframes apPop {
  from { opacity: 0; transform: scale(.96) translateY(6px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}
.ap-modal-close {
  position: absolute;
  right: 13px;
  top: 11px;
  width: 30px;
  height: 30px;
  border: none;
  background: #f1f5f9;
  border-radius: 7px;
  color: #64748b;
  font-size: 1rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background .15s, color .15s;
}
.ap-modal-close:hover { background: #e2e8f0; color: #1a1f2e; }
.ap-modal-title {
  font-size: .975rem;
  font-weight: 700;
  color: #1a1f2e;
  margin: 0 0 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.ap-modal-title i { color: #2d8653; }
.ap-modal-actions { display: flex; flex-direction: column; gap: 9px; }
.ap-modal-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 9px;
  font: inherit;
  font-size: .875rem;
  font-weight: 700;
  cursor: pointer;
  border: none;
  transition: all .15s;
}
.ap-modal-btn.primary {
  background: linear-gradient(135deg, #2d8653, #1a5c3a);
  color: #fff;
  box-shadow: 0 2px 8px rgba(45,134,83,.25);
}
.ap-modal-btn.primary:hover { opacity: .88; }
.ap-modal-btn.secondary {
  background: #f1f5f9;
  color: #374151;
  border: 1.5px solid #e2e8f0;
}
.ap-modal-btn.secondary:hover { background: #e8edf2; }

/* ── Empty org state ──────────────────────────────────── */
.ap-empty-org {
  text-align: center;
  padding: 36px 20px;
  color: #94a3b8;
}
.ap-empty-org i { font-size: 2rem; color: #dde3ea; display: block; margin-bottom: 10px; }
.ap-empty-org p { font-size: .875rem; margin: 0; }
.ap-empty-org a {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 14px;
  padding: 9px 18px;
  background: linear-gradient(135deg, #2d8653, #1a5c3a);
  color: #fff;
  border-radius: 8px;
  font-size: .875rem;
  font-weight: 700;
  text-decoration: none;
  transition: opacity .15s;
}
.ap-empty-org a:hover { opacity: .88; }
</style>

<div class="ap-page">

  <!-- ── Page Header ──────────────────────────────────── -->
  <div class="ap-page-header">
    <h1 class="ap-page-title">
      <i class="fas fa-user-circle"></i> Admin Profile
    </h1>
  </div>

  <!-- ── Alerts ───────────────────────────────────────── -->
  <?php if (isset($_GET['success'])): ?>
    <div class="ap-alert ap-alert-success">
      <i class="fas fa-check-circle"></i>
      <?= match($_GET['success']) {
        'donation_updated' => 'Organization details saved successfully.',
        'fb_updated'       => 'Facebook link updated.',
        'profile_updated'  => 'Profile photo updated.',
        default            => 'Changes saved successfully.',
      } ?>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['error'])): ?>
    <div class="ap-alert ap-alert-error">
      <i class="fas fa-exclamation-circle"></i>
      Something went wrong — please try again.
    </div>
  <?php endif; ?>

  <!-- ── Two-column layout ────────────────────────────── -->
  <div class="ap-layout">

    <!-- ════════════════════════════════════════════════
         LEFT COLUMN
    ════════════════════════════════════════════════════ -->
    <div class="ap-left-col">

      <!-- Profile Identity Card -->
      <div class="ap-card">
        <div class="ap-card-header">
          <h6><i class="fas fa-id-badge"></i> Account</h6>
        </div>
        <div class="ap-identity">
          <!-- Avatar -->
          <div class="ap-avatar-ring" onclick="openModal('profileModal')">
            <div class="ap-avatar-img">
              <?php if (!empty($admin['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile/' . $admin['profile_pic'])): ?>
                <img src="../uploads/profile/<?= htmlspecialchars($admin['profile_pic']) ?>" alt="Profile">
              <?php else: ?>
                <i class="fas fa-user-circle"></i>
              <?php endif; ?>
            </div>
            <div class="ap-avatar-overlay"><i class="fas fa-camera"></i></div>
          </div>

          <p class="ap-identity-name"><?= htmlspecialchars($admin['name']) ?></p>
          <span class="ap-identity-role"><i class="fas fa-shield-alt"></i> Administrator</span>

          <div class="ap-meta-list">
            <div class="ap-meta-row">
              <i class="fas fa-envelope"></i>
              <span><?= htmlspecialchars($admin['email']) ?></span>
            </div>
            <div class="ap-meta-row">
              <i class="fas fa-calendar-check"></i>
              <span>Member since <?= date('M Y', strtotime($admin['created_at'])) ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Organization Logo Card -->
      <?php if ($organization): ?>
      <div class="ap-card ap-org-logo-card">
        <div class="ap-card-header">
          <h6><i class="fas fa-building"></i> Organization</h6>
        </div>
        <div class="ap-card-body" style="display:flex;align-items:center;gap:13px;padding:14px 18px;">
          <div class="ap-org-logo-wrap" onclick="openModal('logoModal')">
            <div class="ap-org-logo-img">
              <?php if (!empty($organization['logo']) && file_exists(__DIR__ . '/../uploads/' . $organization['logo'])): ?>
                <img src="../uploads/<?= htmlspecialchars($organization['logo']) ?>" alt="Logo">
              <?php else: ?>
                <i class="fas fa-image"></i>
              <?php endif; ?>
            </div>
            <div class="ap-org-logo-overlay"><i class="fas fa-camera"></i></div>
          </div>
          <div class="ap-org-logo-info">
            <p class="ap-org-logo-name"><?= htmlspecialchars($organization['name'] ?? '—') ?></p>
            <p class="ap-org-logo-loc">
              <i class="fas fa-map-marker-alt" style="color:#2d8653;font-size:.7rem;margin-right:3px;"></i>
              <?= htmlspecialchars($organization['location'] ?? 'No location set') ?>
            </p>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /.ap-left-col -->


    <!-- ════════════════════════════════════════════════
         RIGHT COLUMN
    ════════════════════════════════════════════════════ -->
    <div class="ap-right-col">
      <?php if ($organization): ?>

        <!-- Tab Container -->
        <div class="ap-card" style="overflow:visible;">

          <!-- Tabs -->
          <div class="ap-tabs" role="tablist">
            <button class="ap-tab active" onclick="switchTab(this,'tab-basic')" role="tab">
              <i class="fas fa-info-circle"></i> Basic Info
            </button>
            <button class="ap-tab" onclick="switchTab(this,'tab-gcash')" role="tab">
              <i class="fas fa-mobile-alt"></i> GCash
            </button>
            <button class="ap-tab" onclick="switchTab(this,'tab-bank')" role="tab">
              <i class="fas fa-university"></i> Bank
            </button>
            <button class="ap-tab" onclick="switchTab(this,'tab-dropoff')" role="tab">
              <i class="fas fa-box-open"></i> Drop-off
            </button>
          </div>

          <!-- Single form wrapping all tabs -->
          <form method="POST" action="process_update_donation_info.php" enctype="multipart/form-data">
            <input type="hidden" name="org_id" value="<?= $organization['id'] ?>">

            <!-- ── Tab: Basic Info ─────────────────────── -->
            <div id="tab-basic" class="ap-tab-panel active">
              <div class="ap-card-body">

                <div class="ap-section-label">
                  <i class="fas fa-building"></i> Organization Details
                </div>
                <div class="ap-form-grid">
                  <div class="ap-fg">
                    <label>Organization Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="org_name"
                           value="<?= htmlspecialchars($organization['name'] ?? '') ?>"
                           required placeholder="e.g. Green Future NGO">
                  </div>
                  <div class="ap-fg">
                    <label>Location</label>
                    <input type="text" name="location"
                           value="<?= htmlspecialchars($organization['location'] ?? '') ?>"
                           placeholder="City, Province">
                  </div>
                  <div class="ap-fg">
                    <label>Contact Email</label>
                    <input type="email" name="contact_email"
                           value="<?= htmlspecialchars($organization['contact_email'] ?? '') ?>"
                           placeholder="org@email.com">
                  </div>
                  <div class="ap-fg">
                    <label>Contact Phone</label>
                    <input type="text" name="contact_phone"
                           value="<?= htmlspecialchars($organization['contact_phone'] ?? '') ?>"
                           placeholder="+63 9XX XXX XXXX">
                  </div>
                  <div class="ap-fg span2">
                    <label>Description</label>
                    <textarea name="description" rows="3"
                              placeholder="Brief description of your organization…"><?= htmlspecialchars($organization['description'] ?? '') ?></textarea>
                  </div>
                  <div class="ap-fg">
                    <label>Facebook Page URL</label>
                    <input type="url" name="facebook_link"
                           value="<?= htmlspecialchars($organization['facebook_link'] ?? '') ?>"
                           placeholder="https://facebook.com/yourpage">
                  </div>
                  <div class="ap-fg">
                    <label>Organization Logo</label>
                    <input type="file" name="logo" accept="image/*">
                    <span class="ap-fg-hint">JPG, PNG — max 2MB. Replaces current logo.</span>
                  </div>
                </div>

                <div class="ap-save-bar">
                  <button type="submit" class="ap-btn-save">
                    <i class="fas fa-floppy-disk"></i> Save Changes
                  </button>
                </div>
              </div>
            </div><!-- /tab-basic -->

            <!-- ── Tab: GCash ──────────────────────────── -->
            <div id="tab-gcash" class="ap-tab-panel">
              <div class="ap-card-body">

                <div class="ap-section-label">
                  <i class="fas fa-mobile-alt"></i> GCash Payment Details
                </div>

                <div class="ap-pay-grid">
                  <!-- GCash Info -->
                  <div class="ap-pay-block">
                    <div class="ap-pay-block-title">
                      <i class="fas fa-user"></i> Account Info
                    </div>
                    <div class="ap-fg" style="margin-bottom:10px;">
                      <label>GCash Name <span style="color:#dc2626;">*</span></label>
                      <input type="text" name="gcash_name"
                             value="<?= htmlspecialchars($organization['gcash_name'] ?? '') ?>"
                             placeholder="Registered name on GCash"
                             required>
                    </div>
                    <div class="ap-fg">
                      <label>GCash Number <span style="color:#dc2626;">*</span></label>
                      <input type="text" name="gcash_number"
                             value="<?= htmlspecialchars($organization['gcash_number'] ?? '') ?>"
                             placeholder="09XX XXX XXXX"
                             required>
                    </div>
                  </div>

                  <!-- GCash QR -->
                  <div class="ap-pay-block">
                    <div class="ap-pay-block-title">
                      <i class="fas fa-qrcode"></i> QR Code
                    </div>
                    <div class="ap-fg">
                      <label>Upload QR Image</label>
                      <input type="file" name="gcash_qr" accept="image/*">
                      <span class="ap-fg-hint">PNG or JPG — shown to donors at checkout.</span>
                    </div>
                    <?php if (!empty($organization['gcash_qr'])): ?>
                      <div class="ap-qr-row">
                        <img src="../uploads/<?= htmlspecialchars($organization['gcash_qr']) ?>"
                             class="ap-qr-thumb" alt="GCash QR">
                        <span class="ap-qr-label">Current QR code<br>
                          <span style="color:#2d8653;font-weight:600;">Active</span>
                        </span>
                      </div>
                    <?php else: ?>
                      <p style="font-size:.78rem;color:#94a3b8;margin:10px 0 0;">No QR uploaded yet.</p>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="ap-save-bar">
                  <button type="submit" class="ap-btn-save">
                    <i class="fas fa-floppy-disk"></i> Save GCash Details
                  </button>
                </div>
              </div>
            </div><!-- /tab-gcash -->

            <!-- ── Tab: Bank ───────────────────────────── -->
            <div id="tab-bank" class="ap-tab-panel">
              <div class="ap-card-body">

                <div class="ap-section-label">
                  <i class="fas fa-university"></i> Bank Transfer Details
                </div>

                <div class="ap-pay-grid">
                  <div class="ap-pay-block">
                    <div class="ap-pay-block-title">
                      <i class="fas fa-university"></i> Bank Account
                    </div>
                    <div class="ap-fg" style="margin-bottom:10px;">
                      <label>Bank Name</label>
                      <input type="text" name="bank_name"
                             value="<?= htmlspecialchars($organization['bank_name'] ?? '') ?>"
                             placeholder="e.g. BDO, BPI, Metrobank">
                    </div>
                    <div class="ap-fg" style="margin-bottom:10px;">
                      <label>Account Name</label>
                      <input type="text" name="bank_account_name"
                             value="<?= htmlspecialchars($organization['bank_account_name'] ?? '') ?>"
                             placeholder="Name registered on the account">
                    </div>
                    <div class="ap-fg">
                      <label>Account Number</label>
                      <input type="text" name="bank_account_number"
                             value="<?= htmlspecialchars($organization['bank_account_number'] ?? '') ?>"
                             placeholder="e.g. 0012 3456 7890">
                      <span class="ap-fg-hint">Double-check for accuracy — donors will use this to transfer.</span>
                    </div>
                  </div>

                  <!-- Info panel -->
                  <div class="ap-pay-block" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:10px;background:linear-gradient(135deg,#eff6ff,#dbeafe);border-color:#bfdbfe;">
                    <i class="fas fa-university" style="font-size:2rem;color:#93c5fd;"></i>
                    <p style="font-size:.78rem;color:#1d4ed8;font-weight:600;margin:0;line-height:1.55;">
                      These details appear on the<br>
                      <strong>Bank Transfer</strong> donation page<br>
                      for donors to reference.
                    </p>
                  </div>
                </div>

                <div class="ap-save-bar">
                  <button type="submit" class="ap-btn-save">
                    <i class="fas fa-floppy-disk"></i> Save Bank Details
                  </button>
                </div>
              </div>
            </div><!-- /tab-bank -->

            <!-- ── Tab: Drop-off ───────────────────────── -->
            <div id="tab-dropoff" class="ap-tab-panel">
              <div class="ap-card-body">

                <div class="ap-section-label">
                  <i class="fas fa-box-open"></i> In-Kind Drop-off Settings
                </div>

                <div class="ap-form-grid">
                  <div class="ap-fg span2">
                    <label>Drop-off Location</label>
                    <input type="text" name="dropoff_location"
                           value="<?= htmlspecialchars($organization['dropoff_location'] ?? '') ?>"
                           placeholder="e.g. 123 Main St, Building A, Room 5">
                  </div>
                  <div class="ap-fg span2">
                    <label>Instructions for Donors</label>
                    <textarea name="dropoff_instructions" rows="4"
                              placeholder="Hours, contact person, accepted items, schedule…"><?= htmlspecialchars($organization['dropoff_instructions'] ?? '') ?></textarea>
                    <span class="ap-fg-hint">This text is shown to donors when they choose the drop-off donation method.</span>
                  </div>
                </div>

                <div class="ap-save-bar">
                  <button type="submit" class="ap-btn-save">
                    <i class="fas fa-floppy-disk"></i> Save Drop-off Info
                  </button>
                </div>
              </div>
            </div><!-- /tab-dropoff -->

          </form>
        </div><!-- /.ap-card (tab container) -->

      <?php else: ?>
        <div class="ap-card">
          <div class="ap-empty-org">
            <i class="fas fa-building"></i>
            <p>No organization linked to this account yet.</p>
            <a href="register_organization.php">
              <i class="fas fa-plus"></i> Register Organization
            </a>
          </div>
        </div>
      <?php endif; ?>

    </div><!-- /.ap-right-col -->

  </div><!-- /.ap-layout -->

</div><!-- /.ap-page -->


<!-- ── Modal: Change Profile Photo ──────────────────────── -->
<div id="profileModal" class="ap-modal">
  <div class="ap-modal-backdrop" onclick="closeModal('profileModal')"></div>
  <div class="ap-modal-box">
    <button class="ap-modal-close" onclick="closeModal('profileModal')">
      <i class="fas fa-times"></i>
    </button>
    <p class="ap-modal-title"><i class="fas fa-camera"></i> Change Profile Photo</p>
    <div class="ap-modal-actions">
      <button class="ap-modal-btn primary" onclick="document.getElementById('profileInput').click()">
        <i class="fas fa-upload"></i> Choose New Photo
      </button>
      <?php if (!empty($admin['profile_pic'])): ?>
        <button class="ap-modal-btn secondary"
                onclick="window.open('../uploads/profile/<?= htmlspecialchars($admin['profile_pic']) ?>','_blank')">
          <i class="fas fa-eye"></i> View Current Photo
        </button>
      <?php endif; ?>
    </div>
    <form method="POST" action="admin_update_profile.php" enctype="multipart/form-data" id="profileUploadForm">
      <input type="file" name="profile_pic" id="profileInput" accept="image/*"
             style="display:none;" onchange="document.getElementById('profileUploadForm').submit()">
      <input type="hidden" name="update_profile_photo" value="1">
    </form>
  </div>
</div>

<!-- ── Modal: Change Organization Logo ──────────────────── -->
<?php if ($organization): ?>
<div id="logoModal" class="ap-modal">
  <div class="ap-modal-backdrop" onclick="closeModal('logoModal')"></div>
  <div class="ap-modal-box">
    <button class="ap-modal-close" onclick="closeModal('logoModal')">
      <i class="fas fa-times"></i>
    </button>
    <p class="ap-modal-title"><i class="fas fa-image"></i> Change Organization Logo</p>
    <div class="ap-modal-actions">
      <button class="ap-modal-btn primary" onclick="document.getElementById('logoInput').click()">
        <i class="fas fa-upload"></i> Choose New Logo
      </button>
      <?php if (!empty($organization['logo'])): ?>
        <button class="ap-modal-btn secondary"
                onclick="window.open('../uploads/<?= htmlspecialchars($organization['logo']) ?>','_blank')">
          <i class="fas fa-eye"></i> View Current Logo
        </button>
      <?php endif; ?>
    </div>
    <form method="POST" action="process_update_donation_info.php" enctype="multipart/form-data" id="logoUploadForm">
      <input type="hidden" name="org_id" value="<?= $organization['id'] ?>">
      <input type="file" name="logo" id="logoInput" accept="image/*"
             style="display:none;" onchange="document.getElementById('logoUploadForm').submit()">
    </form>
  </div>
</div>
<?php endif; ?>

<script>
// ── Modal open/close
function openModal(id) {
  document.getElementById(id).classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).classList.remove('show');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.ap-modal.show').forEach(m => {
      m.classList.remove('show');
    });
    document.body.style.overflow = '';
  }
});

// ── Tab switching
function switchTab(btn, panelId) {
  // Deactivate all tabs + panels
  btn.closest('.ap-tabs').querySelectorAll('.ap-tab').forEach(t => t.classList.remove('active'));
  btn.closest('.ap-card').querySelectorAll('.ap-tab-panel').forEach(p => p.classList.remove('active'));
  // Activate selected
  btn.classList.add('active');
  document.getElementById(panelId).classList.add('active');
}

// ── Restore active tab from URL hash (e.g. #tab-gcash)
(function() {
  const hash = window.location.hash;
  if (hash) {
    const panel = document.getElementById(hash.slice(1));
    if (panel && panel.classList.contains('ap-tab-panel')) {
      const idx = ['tab-basic','tab-gcash','tab-bank','tab-dropoff'].indexOf(hash.slice(1));
      if (idx >= 0) {
        const tabs = document.querySelectorAll('.ap-tab');
        if (tabs[idx]) switchTab(tabs[idx], hash.slice(1));
      }
    }
  }
})();
</script>

<?php include '../includes/footer.php'; ?>