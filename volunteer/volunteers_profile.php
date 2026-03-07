<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "My Profile";
$pageCSS   = "/VolunteerHub/styles/volunteer_layout.css";
include '../includes/header_volunteer.php';

$user_id = $_SESSION['user_id'];

// Core account info
$stmt = $conn->prepare("SELECT name, email, created_at, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id); $stmt->execute();
$volunteer = $stmt->get_result()->fetch_assoc();

// Volunteer details
$stmt2 = $conn->prepare("SELECT gender, age, address, phone_number, hobbies, skills FROM volunteer_details WHERE user_id = ?");
$stmt2->bind_param("i", $user_id); $stmt2->execute();
$details = $stmt2->get_result()->fetch_assoc();

// Completed events
$cevStmt = $conn->prepare("
    SELECT DISTINCT e.id, e.title, e.date, e.location
    FROM events e
    JOIN tasks t ON t.event_id = e.id
    JOIN task_assignments ta ON ta.task_id = t.id AND ta.volunteer_id = ?
    JOIN event_attendance ea ON ea.event_id = e.id AND ea.volunteer_id = ?
    WHERE ta.progress = 'Completed' AND ea.attended = 2
    ORDER BY e.date DESC
");
$cevStmt->bind_param("ii", $user_id, $user_id); $cevStmt->execute();
$completedEventsResult = $cevStmt->get_result();
$completedEvents = [];
while ($row = $completedEventsResult->fetch_assoc()) {
    $completedEvents[] = $row;
}

// Stats
$totalTasksQ = $conn->prepare("SELECT COUNT(*) AS c FROM task_assignments WHERE volunteer_id = ?");
$totalTasksQ->bind_param("i", $user_id); $totalTasksQ->execute();
$totalTasks  = $totalTasksQ->get_result()->fetch_assoc()['c'] ?? 0;

$doneTasksQ = $conn->prepare("SELECT COUNT(*) AS c FROM task_assignments WHERE volunteer_id = ? AND progress = 'Completed'");
$doneTasksQ->bind_param("i", $user_id); $doneTasksQ->execute();
$doneTasks  = $doneTasksQ->get_result()->fetch_assoc()['c'] ?? 0;

$eventsJoinedQ = $conn->prepare("SELECT COUNT(*) AS c FROM volunteer_applications WHERE user_id = ? AND status = 'approved'");
$eventsJoinedQ->bind_param("i", $user_id); $eventsJoinedQ->execute();
$eventsJoined  = $eventsJoinedQ->get_result()->fetch_assoc()['c'] ?? 0;

// Volunteer donation history
$dq = $conn->prepare("
    SELECT 'GCash' AS method, amount, reference_number, status, created_at
      FROM gcash_donations WHERE user_id = ?
    UNION ALL
    SELECT 'Bank Transfer', amount, reference_number, status, created_at
      FROM bank_payments WHERE user_id = ?
    ORDER BY created_at DESC
");
$dq->bind_param("ii", $user_id, $user_id);
$dq->execute();
$donations = $dq->get_result();

// Item donations
$iq = $conn->prepare("
    SELECT item_category, item_description, quantity, dropoff_date, status, created_at
    FROM dropoff_donations WHERE user_id = ?
    ORDER BY created_at DESC
");
$iq->bind_param("i", $user_id);
$iq->execute();
$items = $iq->get_result();

$hasDonations = ($donations->num_rows > 0 || $items->num_rows > 0);

// Handle profile update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $gender  = $_POST['gender']       ?? null;
    $age     = $_POST['age']          ?? null;
    $address = $_POST['address']      ?? null;
    $phone   = $_POST['phone_number'] ?? null;
    $hobbies = $_POST['hobbies']      ?? null;
    $skills  = $_POST['skills']       ?? null;

    if ($details) {
        $u = $conn->prepare("UPDATE volunteer_details SET gender=?, age=?, address=?, phone_number=?, hobbies=?, skills=? WHERE user_id=?");
        $u->bind_param("sissssi", $gender, $age, $address, $phone, $hobbies, $skills, $user_id);
    } else {
        $u = $conn->prepare("INSERT INTO volunteer_details (user_id, gender, age, address, phone_number, hobbies, skills) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $u->bind_param("iissss", $user_id, $gender, $age, $address, $phone, $hobbies, $skills);
    }
    $u->execute();

    // Handle profile pic upload
    if (!empty($_FILES['profile_pic']['name'])) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $filename = "profile_{$user_id}_" . time() . ".$ext";
            $target   = "../uploads/profile/" . $filename;
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target)) {
                $pu = $conn->prepare("UPDATE users SET profile_pic=? WHERE id=?");
                $pu->bind_param("si", $filename, $user_id);
                $pu->execute();
            }
        }
    }

    header("Location: volunteers_profile.php?updated=1");
    exit;
}
?>

<style>
/* ── Page wrapper ─────────────────────────────────────────── */
.profile-page { padding: 28px 28px 60px; }

.profile-page-title {
  font-size: 1.35rem; font-weight: 700;
  color: var(--text-primary); display: flex; align-items: center;
  gap: 10px; margin: 0 0 22px;
}
.profile-page-title i { color: var(--green-mid); }

/* Toast */
.profile-toast {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 18px; border-radius: 10px; margin-bottom: 20px;
  background: #dcfce7; color: #14532d; border: 1px solid #bbf7d0;
  font-size: .875rem; font-weight: 500;
}

/* ── Two-column layout ────────────────────────────────────── */
.profile-layout {
  display: grid;
  grid-template-columns: 270px 1fr;
  gap: 20px;
  align-items: start;
}
@media (max-width: 860px) {
  .profile-layout { grid-template-columns: 1fr; }
  .profile-left-col { position: static !important; }
}

/* ── LEFT COLUMN — identity card ──────────────────────────── */
.profile-left-col { position: sticky; top: 20px; }

.identity-card {
  background: #fff;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 16px;
  box-shadow: 0 1px 6px rgba(0,0,0,.06);
  overflow: hidden;
}

.id-banner {
  height: 72px;
  background: linear-gradient(135deg, #1a5c3a, #2d8653);
}

.id-body { padding: 0 20px 22px; text-align: center; }

.id-avatar-wrap {
  width: 86px; height: 86px;
  border-radius: 50%;
  border: 4px solid #fff;
  margin: -43px auto 14px;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,.12);
  cursor: pointer;
  position: relative;
}
.id-avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
.id-avatar-placeholder {
  width: 100%; height: 100%;
  background: #e8f5ee;
  display: flex; align-items: center; justify-content: center;
  color: #2d8653; font-size: 2.4rem;
}
.id-avatar-hover {
  position: absolute; inset: 0;
  background: rgba(0,0,0,.38);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity .18s;
  color: #fff; font-size: .68rem; font-weight: 700; border-radius: 50%;
  flex-direction: column; gap: 3px;
}
.id-avatar-hover i { font-size: .95rem; }
.id-avatar-wrap:hover .id-avatar-hover { opacity: 1; }

.id-name {
  font-size: 1.08rem; font-weight: 700;
  color: var(--text-primary, #1a1f2e); margin: 0 0 4px;
}
.id-email {
  font-size: .76rem; color: var(--text-muted, #94a3b8);
  margin: 0 0 10px; word-break: break-all;
}
.id-role {
  display: inline-flex; align-items: center; gap: 5px;
  background: #e8f5ee; color: #1a5c3a;
  font-size: .72rem; font-weight: 700; padding: 3px 12px; border-radius: 99px;
  margin-bottom: 6px;
}
.id-since {
  font-size: .72rem; color: var(--text-muted, #94a3b8); margin-bottom: 20px;
}

.id-divider { height: 1px; background: #f1f5f9; margin: 0 -20px 18px; }

/* Stat rows inside left card */
.id-stats { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
.id-stat {
  display: flex; align-items: center; justify-content: space-between;
  background: #fafbfc; border: 1px solid #f1f5f9;
  border-radius: 8px; padding: 9px 14px;
}
.id-stat-label {
  font-size: .75rem; color: var(--text-muted, #94a3b8);
  display: flex; align-items: center; gap: 7px;
}
.id-stat-val { font-size: .95rem; font-weight: 700; color: var(--text-primary, #1a1f2e); }

/* Left card buttons */
.id-actions { display: flex; flex-direction: column; gap: 8px; }
.btn-id-edit, .btn-id-pw {
  display: flex; align-items: center; justify-content: center; gap: 7px;
  padding: 9px 14px; border-radius: 8px;
  font-size: .82rem; font-weight: 600; cursor: pointer;
  border: none; transition: opacity .18s; text-decoration: none; width: 100%;
}
.btn-id-edit {
  background: linear-gradient(135deg, #2d8653, #1a5c3a);
  color: #fff; box-shadow: 0 2px 6px rgba(45,134,83,.25);
}
.btn-id-pw {
  background: #f1f5f9; color: #64748b;
  border: 1px solid #e2e8f0;
}
.btn-id-edit:hover { opacity: .87; color: #fff; }
.btn-id-pw:hover   { opacity: .83; color: inherit; }

/* ── RIGHT COLUMN ─────────────────────────────────────────── */
.profile-right-col { display: flex; flex-direction: column; gap: 18px; }

/* Shared card */
.pcard {
  background: #fff;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 16px;
  box-shadow: 0 1px 6px rgba(0,0,0,.06);
  overflow: hidden;
}
.pcard-header {
  padding: 13px 20px;
  border-bottom: 1px solid #f1f5f9;
  display: flex; align-items: center; justify-content: space-between;
  background: #fafbfc;
}
.pcard-header h3 {
  font-size: .82rem; font-weight: 700; color: var(--text-primary, #1a1f2e);
  margin: 0; display: flex; align-items: center; gap: 8px;
  text-transform: uppercase; letter-spacing: .05em;
}
.pcard-header h3 i { color: #2d8653; }
.pcard-body { padding: 20px; }
.pcard-body-flush { padding: 0; }

/* Info display grid */
.info-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 20px; }
.info-full  { grid-column: span 2; }
@media (max-width: 600px) {
  .info-grid { grid-template-columns: 1fr; }
  .info-full { grid-column: span 1; }
}

.info-label {
  font-size: .68rem; text-transform: uppercase; letter-spacing: .07em;
  color: var(--text-muted, #94a3b8); font-weight: 700; margin-bottom: 5px;
}
.info-value {
  font-size: .875rem; color: var(--text-primary, #1a1f2e); font-weight: 500;
  padding: 8px 12px; background: #fafbfc;
  border: 1px solid #f1f5f9; border-radius: 7px;
  min-height: 38px; display: flex; align-items: center;
}
.info-value.empty { color: var(--text-muted, #94a3b8); font-style: italic; }

/* Edit form */
.form-field { display: flex; flex-direction: column; gap: 5px; }
.form-label {
  font-size: .68rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .07em; color: var(--text-muted, #94a3b8);
}
.form-control-custom {
  padding: 9px 12px; border: 1px solid var(--border, #e2e8f0);
  border-radius: 7px; font-size: .875rem; color: var(--text-primary, #1a1f2e);
  background: #fff; outline: none; transition: border-color .2s, box-shadow .2s; width: 100%;
}
.form-control-custom:focus {
  border-color: #2d8653; box-shadow: 0 0 0 3px rgba(45,134,83,.1);
}
.form-actions {
  display: flex; gap: 10px; margin-top: 12px;
  padding-top: 16px; border-top: 1px solid #f1f5f9;
}
.btn-save {
  padding: 9px 22px;
  background: linear-gradient(135deg, #2d8653, #1a5c3a);
  color: #fff; border: none; border-radius: 8px;
  font-size: .875rem; font-weight: 600; cursor: pointer; transition: opacity .18s;
}
.btn-save:hover { opacity: .88; }
.btn-cancel {
  padding: 9px 20px; background: #f1f5f9; color: #64748b;
  border: 1px solid #e2e8f0; border-radius: 8px;
  font-size: .875rem; font-weight: 600; cursor: pointer;
}

/* Completed events table */
.cev-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.cev-table thead tr { background: #e8f5ee; }
.cev-table thead th {
  padding: 11px 16px; font-weight: 700; font-size: .75rem;
  text-transform: uppercase; letter-spacing: .06em; color: #1a5c3a;
  text-align: left; border-bottom: 1px solid #e2e8f0;
}
.cev-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
.cev-table tbody tr:last-child { border-bottom: none; }
.cev-table tbody tr:hover { background: #f8fdf9; }
.cev-table td { padding: 12px 16px; color: var(--text-primary, #1a1f2e); }
.cev-empty {
  padding: 36px; text-align: center;
  color: var(--text-muted, #94a3b8); font-size: .875rem;
}
.cev-empty i { display: block; font-size: 2rem; opacity: .3; margin-bottom: 8px; }

/* Status badge */
.badge-completed {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 10px; border-radius: 99px; font-size: .72rem; font-weight: 700;
  background: #dcfce7; color: #15803d;
}

/* Donation history tabs */
.donation-tabs { display: flex; border-bottom: 1px solid var(--border); }
.donation-tab {
  flex: 1; padding: 10px 8px; font-size: .78rem; font-weight: 600;
  color: var(--text-muted); background: none; border: none;
  cursor: pointer; border-bottom: 2px solid transparent;
  transition: all var(--transition);
}
.donation-tab.active { color: var(--green-dark); border-bottom-color: var(--green-mid); }
.donation-tab-panel { display: none; }
.donation-tab-panel.active { display: block; }

/* Donation table */
.don-table { width: 100%; border-collapse: collapse; font-size: .81rem; }
.don-table th {
  background: #e8f5ee; padding: 9px 14px;
  font-size: .7rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .05em; color: #1a5c3a;
  text-align: left; border-bottom: 1px solid var(--border);
}
.don-table td {
  padding: 9px 14px; border-bottom: 1px solid var(--border-light);
  color: var(--text-primary); vertical-align: middle;
}
.don-table tr:last-child td { border-bottom: none; }
.don-table tr:hover td { background: var(--green-soft); }
.don-table .amt { font-weight: 700; color: var(--green-dark); }

/* Empty state */
.don-empty {
  text-align: center; padding: 32px 20px; color: var(--text-muted);
}
.don-empty i { font-size: 1.8rem; display: block; margin-bottom: 8px; }
.don-empty p { font-size: .84rem; margin: 0; }

#avatarInput { display: none; }
</style>

<div class="profile-page">

  <h1 class="profile-page-title"><i class="fas fa-user-circle"></i> My Profile</h1>

  <?php if (isset($_GET['updated'])): ?>
    <div class="profile-toast"><i class="fas fa-check-circle"></i> Profile updated successfully!</div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="profileForm">
    <input type="hidden" name="update_profile" value="1">
    <input type="file" id="avatarInput" name="profile_pic" accept="image/*"
           onchange="handleAvatarChange(this)">

    <div class="profile-layout">

      <!-- ══════════════════════════════════════════════
           LEFT COLUMN — Identity card
           ══════════════════════════════════════════════ -->
      <div class="profile-left-col">
        <div class="identity-card">
          <div class="id-banner"></div>
          <div class="id-body">

            <!-- Avatar -->
            <div class="id-avatar-wrap"
                 onclick="document.getElementById('avatarInput').click()"
                 title="Change profile photo">
              <?php if (!empty($volunteer['profile_pic']) && file_exists("../uploads/profile/" . $volunteer['profile_pic'])): ?>
                <img id="avatarPreview"
                     src="../uploads/profile/<?= htmlspecialchars($volunteer['profile_pic']) ?>"
                     alt="Profile Photo">
              <?php else: ?>
                <div class="id-avatar-placeholder" id="avatarPlaceholder">
                  <i class="fas fa-user"></i>
                </div>
              <?php endif; ?>
              <div class="id-avatar-hover">
                <i class="fas fa-camera"></i>
                Change
              </div>
            </div>

            <h2 class="id-name"><?= htmlspecialchars($volunteer['name'] ?? '') ?></h2>
            <p class="id-email"><?= htmlspecialchars($volunteer['email'] ?? '') ?></p>
            <span class="id-role"><i class="fas fa-leaf"></i> Volunteer</span>
            <p class="id-since">
              <i class="fas fa-calendar-alt me-1"></i>
              Member since <?= date('F Y', strtotime($volunteer['created_at'] ?? 'now')) ?>
            </p>

            <div class="id-divider"></div>

            <!-- Quick stats -->
            <div class="id-stats">
              <div class="id-stat">
                <span class="id-stat-label">
                  <i class="fas fa-calendar-check" style="color:#2563eb;width:14px;text-align:center;"></i>
                  Events Joined
                </span>
                <span class="id-stat-val"><?= $eventsJoined ?></span>
              </div>
              <div class="id-stat">
                <span class="id-stat-label">
                  <i class="fas fa-tasks" style="color:#d97706;width:14px;text-align:center;"></i>
                  Total Tasks
                </span>
                <span class="id-stat-val"><?= $totalTasks ?></span>
              </div>
              <div class="id-stat">
                <span class="id-stat-label">
                  <i class="fas fa-check-circle" style="color:#16a34a;width:14px;text-align:center;"></i>
                  Completed
                </span>
                <span class="id-stat-val"><?= $doneTasks ?></span>
              </div>
            </div>

            <div class="id-divider"></div>

            <!-- Actions -->
            <div class="id-actions">
              <button type="button" class="btn-id-edit" id="editProfileBtn"
                      onclick="toggleEdit(true)">
                <i class="fas fa-pen"></i> Edit Profile
              </button>
              <button type="button" class="btn-id-pw"
                      onclick="openChangePasswordModal()"
                <i class="fas fa-lock"></i> Change Password
              </button>
            </div>

          </div>
        </div>
      </div>
      <!-- /LEFT COLUMN -->

      <!-- ══════════════════════════════════════════════
           RIGHT COLUMN — Detail cards
           ══════════════════════════════════════════════ -->
      <div class="profile-right-col">

        <!-- VIEW MODE: Personal Information -->
        <div class="pcard" id="viewMode">
          <div class="pcard-header">
            <h3><i class="fas fa-id-card"></i> Personal Information</h3>
          </div>
          <div class="pcard-body">
            <div class="info-grid">
              <?php
              $viewFields = [
                'Gender'       => ['val' => $details['gender']       ?? null, 'full' => false],
                'Age'          => ['val' => $details['age']           ?? null, 'full' => false],
                'Phone Number' => ['val' => $details['phone_number']  ?? null, 'full' => false],
                'Address'      => ['val' => $details['address']       ?? null, 'full' => true],
                'Hobbies'      => ['val' => $details['hobbies']       ?? null, 'full' => true],
                'Skills'       => ['val' => $details['skills']        ?? null, 'full' => true],
              ];
              foreach ($viewFields as $label => $field):
                $isEmpty = empty($field['val']);
                $fullClass = $field['full'] ? 'info-full' : '';
              ?>
                <div class="<?= $fullClass ?>">
                  <div class="info-label"><?= $label ?></div>
                  <div class="info-value <?= $isEmpty ? 'empty' : '' ?>">
                    <?= $isEmpty ? 'Not set' : htmlspecialchars($field['val']) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- EDIT MODE: Edit Personal Information -->
        <div class="pcard" id="editMode" style="display:none;">
          <div class="pcard-header">
            <h3><i class="fas fa-pen"></i> Edit Personal Information</h3>
          </div>
          <div class="pcard-body">
            <div class="info-grid">

              <div class="form-field">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-control-custom">
                  <option value="">— Select —</option>
                  <?php foreach (['Male','Female','Non-binary','Prefer not to say'] as $g): ?>
                    <option value="<?= $g ?>"
                      <?= ($details['gender'] ?? '') === $g ? 'selected' : '' ?>>
                      <?= $g ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-field">
                <label class="form-label">Age</label>
                <input type="number" name="age" class="form-control-custom"
                       min="1" max="120"
                       value="<?= htmlspecialchars($details['age'] ?? '') ?>">
              </div>

              <div class="form-field">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone_number" class="form-control-custom"
                       placeholder="e.g. 09171234567"
                       value="<?= htmlspecialchars($details['phone_number'] ?? '') ?>">
              </div>

              <div class="form-field info-full">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control-custom"
                       placeholder="e.g. 123 Main St, Cebu City"
                       value="<?= htmlspecialchars($details['address'] ?? '') ?>">
              </div>

              <div class="form-field info-full">
                <label class="form-label">Hobbies</label>
                <input type="text" name="hobbies" class="form-control-custom"
                       placeholder="e.g. Reading, Hiking, Photography"
                       value="<?= htmlspecialchars($details['hobbies'] ?? '') ?>">
              </div>

              <div class="form-field info-full">
                <label class="form-label">Skills</label>
                <input type="text" name="skills" class="form-control-custom"
                       placeholder="e.g. First Aid, Web Design, Teaching"
                       value="<?= htmlspecialchars($details['skills'] ?? '') ?>">
              </div>

            </div>

            <div class="form-actions">
              <button type="submit" class="btn-save">
                <i class="fas fa-save me-1"></i> Save Changes
              </button>
              <button type="button" class="btn-cancel" onclick="toggleEdit(false)">
                Cancel
              </button>
            </div>
          </div>
        </div>

        <!-- Completed Events -->
        <div class="pcard">
          <div class="pcard-header">
            <h3><i class="fas fa-calendar-check"></i> Completed Events</h3>
            <span style="font-size:.75rem;color:var(--text-muted,#94a3b8);">
              <?= count($completedEvents) ?> event<?= count($completedEvents) !== 1 ? 's' : '' ?>
            </span>
          </div>
          <div class="pcard-body-flush">
            <?php if (empty($completedEvents)): ?>
              <div class="cev-empty">
                <i class="fas fa-calendar-times"></i>
                No completed events yet.
              </div>
            <?php else: ?>
              <table class="cev-table">
                <thead>
                  <tr>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($completedEvents as $ev): ?>
                    <tr>
                      <td><?= htmlspecialchars($ev['title']) ?></td>
                      <td><?= date('M d, Y', strtotime($ev['date'])) ?></td>
                      <td><?= htmlspecialchars($ev['location']) ?></td>
                      <td>
                        <span class="badge-completed">
                          <i class="fas fa-check-circle"></i> Completed
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- Donation History -->
        <?php if ($hasDonations): ?>
        <div class="pcard">
          <div class="pcard-header">
            <h3><i class="fas fa-receipt"></i> My Donation History</h3>
          </div>

          <!-- Tabs -->
          <div class="donation-tabs">
            <button type="button" class="donation-tab active" onclick="switchDonationTab(this,'tab-money')">
              <i class="fas fa-peso-sign"></i> Monetary
              <?php if ($donations->num_rows > 0): ?>
                <span style="margin-left:5px;font-size:.65rem;background:#e8f5ee;color:#1a5c3a;padding:2px 6px;border-radius:3px;"><?= $donations->num_rows ?></span>
              <?php endif; ?>
            </button>
            <button type="button" class="donation-tab" onclick="switchDonationTab(this,'tab-items')">
              <i class="fas fa-box"></i> Items
              <?php if ($items->num_rows > 0): ?>
                <span style="margin-left:5px;font-size:.65rem;background:#e8f5ee;color:#1a5c3a;padding:2px 6px;border-radius:3px;"><?= $items->num_rows ?></span>
              <?php endif; ?>
            </button>
          </div>

          <!-- Monetary tab -->
          <div id="tab-money" class="donation-tab-panel active">
            <?php if ($donations->num_rows > 0): ?>
              <div style="overflow-x:auto;">
                <table class="don-table">
                  <thead>
                    <tr>
                      <th>Method</th>
                      <th>Amount</th>
                      <th>Reference #</th>
                      <th>Status</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $donations->data_seek(0); while ($d = $donations->fetch_assoc()): ?>
                      <tr>
                        <td><?= htmlspecialchars($d['method']) ?></td>
                        <td class="amt">₱<?= number_format($d['amount'], 2) ?></td>
                        <td style="font-family:monospace;font-size:.78rem;"><?= htmlspecialchars($d['reference_number']) ?></td>
                        <td>
                          <span class="badge-completed" style="background:<?= strtolower($d['status']) === 'approved' ? '#dcfce7' : (strtolower($d['status']) === 'pending' ? '#fef9c3' : '#f1f5f9') ?>;color:<?= strtolower($d['status']) === 'approved' ? '#15803d' : (strtolower($d['status']) === 'pending' ? '#92400e' : '#475569') ?>;">
                            <?= htmlspecialchars($d['status']) ?>
                          </span>
                        </td>
                        <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($d['created_at'])) ?></td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="don-empty"><i class="fas fa-receipt"></i><p>No monetary donations yet.</p></div>
            <?php endif; ?>
          </div>

          <!-- Items tab -->
          <div id="tab-items" class="donation-tab-panel">
            <?php if ($items->num_rows > 0): ?>
              <div style="overflow-x:auto;">
                <table class="don-table">
                  <thead>
                    <tr>
                      <th>Category</th>
                      <th>Description</th>
                      <th>Qty</th>
                      <th>Drop-off Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $items->data_seek(0); while ($it = $items->fetch_assoc()): ?>
                      <tr>
                        <td><?= htmlspecialchars($it['item_category']) ?></td>
                        <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                          <?= htmlspecialchars($it['item_description']) ?>
                        </td>
                        <td style="font-weight:700;"><?= (int)$it['quantity'] ?></td>
                        <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($it['dropoff_date'])) ?></td>
                        <td>
                          <span class="badge-completed" style="background:<?= strtolower($it['status']) === 'received' ? '#dcfce7' : '#fef9c3' ?>;color:<?= strtolower($it['status']) === 'received' ? '#15803d' : '#92400e' ?>;">
                            <?= htmlspecialchars($it['status']) ?>
                          </span>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="don-empty"><i class="fas fa-box-open"></i><p>No item donations yet.</p></div>
            <?php endif; ?>
          </div>

        </div>
        <?php endif; ?>

      </div>
      <!-- /RIGHT COLUMN -->

    </div><!-- /profile-layout -->
  </form>

</div><!-- /profile-page -->
<?php
  $pw_action = 'volunteer_change_password.php';
  include '../includes/change_password_modal.php';
?>
<script>
function toggleEdit(editing) {
  document.getElementById('viewMode').style.display    = editing ? 'none' : '';
  document.getElementById('editMode').style.display    = editing ? ''     : 'none';
  document.getElementById('editProfileBtn').style.display = editing ? 'none' : '';
  if (editing) {
    document.getElementById('editMode').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function handleAvatarChange(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = function(e) {
    // Update avatar preview
    const wrap = document.querySelector('.id-avatar-wrap');
    let img = wrap.querySelector('img');
    if (!img) {
      const placeholder = wrap.querySelector('.id-avatar-placeholder');
      if (placeholder) placeholder.style.display = 'none';
      img = document.createElement('img');
      img.id = 'avatarPreview';
      img.alt = 'Profile Photo';
      img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
      wrap.insertBefore(img, wrap.querySelector('.id-avatar-hover'));
    }
    img.src = e.target.result;
    // Auto-submit to save immediately
    document.getElementById('profileForm').submit();
  };
  reader.readAsDataURL(input.files[0]);
}

function switchDonationTab(btn, panelId) {
  btn.closest('.pcard').querySelectorAll('.donation-tab').forEach(function(t) {
    t.classList.remove('active');
  });
  btn.closest('.pcard').querySelectorAll('.donation-tab-panel').forEach(function(p) {
    p.classList.remove('active');
  });
  btn.classList.add('active');
  document.getElementById(panelId).classList.add('active');
}
</script>

<?php include '../includes/footer.php'; ?>