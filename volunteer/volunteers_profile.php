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
$completedEvents = $cevStmt->get_result();

// Stats
$totalTasksQ  = $conn->prepare("SELECT COUNT(*) AS c FROM task_assignments WHERE volunteer_id = ?");
$totalTasksQ->bind_param("i", $user_id); $totalTasksQ->execute();
$totalTasks   = $totalTasksQ->get_result()->fetch_assoc()['c'] ?? 0;

$doneTasksQ   = $conn->prepare("SELECT COUNT(*) AS c FROM task_assignments WHERE volunteer_id = ? AND progress = 'Completed'");
$doneTasksQ->bind_param("i", $user_id); $doneTasksQ->execute();
$doneTasks    = $doneTasksQ->get_result()->fetch_assoc()['c'] ?? 0;

$eventsJoinedQ = $conn->prepare("SELECT COUNT(*) AS c FROM volunteer_applications WHERE user_id = ? AND status = 'approved'");
$eventsJoinedQ->bind_param("i", $user_id); $eventsJoinedQ->execute();
$eventsJoined  = $eventsJoinedQ->get_result()->fetch_assoc()['c'] ?? 0;

// Handle update (preserve existing logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $gender  = $_POST['gender'] ?? null;
    $age     = $_POST['age'] ?? null;
    $address = $_POST['address'] ?? null;
    $phone   = $_POST['phone_number'] ?? null;
    $hobbies = $_POST['hobbies'] ?? null;
    $skills  = $_POST['skills'] ?? null;

    if ($details) {
        $u = $conn->prepare("UPDATE volunteer_details SET gender=?, age=?, address=?, phone_number=?, hobbies=?, skills=? WHERE user_id=?");
        $u->bind_param("sissssi", $gender, $age, $address, $phone, $hobbies, $skills, $user_id);
    } else {
        $u = $conn->prepare("INSERT INTO volunteer_details (user_id, gender, age, address, phone_number, hobbies, skills) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $u->bind_param("isissss", $user_id, $gender, $age, $address, $phone, $hobbies, $skills);
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
.profile-page { padding: 28px 28px 60px; max-width: 1050px; }

/* Page header */
.profile-page-title { font-size: 1.45rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 10px; margin: 0 0 24px; }
.profile-page-title i { color: var(--green-mid); }

/* Success toast */
.profile-toast {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px;
  background: #dcfce7; color: #14532d; border: 1px solid #bbf7d0; font-size: .875rem; font-weight: 500;
}

/* Profile hero card */
.profile-hero {
  background: #fff; border: 1px solid var(--border);
  border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
  overflow: hidden; margin-bottom: 20px;
}
.profile-hero-banner {
  height: 100px;
  background: linear-gradient(135deg, var(--green-dark, #1a5c3a), var(--green-mid, #2d8653));
}
.profile-hero-body {
  padding: 0 28px 24px;
  display: flex; align-items: flex-end; gap: 20px; flex-wrap: wrap;
}
.profile-avatar-wrap {
  width: 90px; height: 90px; border-radius: 50%;
  border: 4px solid #fff; margin-top: -45px;
  background: #fff; overflow: hidden; flex-shrink: 0;
  box-shadow: var(--shadow-sm); cursor: pointer; position: relative;
}
.profile-avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
.profile-avatar-wrap .avatar-placeholder {
  width: 100%; height: 100%; background: var(--green-soft);
  display: flex; align-items: center; justify-content: center;
  color: var(--green-mid); font-size: 2.5rem;
}
.profile-hero-info { flex: 1; padding-top: 12px; min-width: 180px; }
.profile-hero-name { font-size: 1.2rem; font-weight: 700; color: var(--text-primary); margin: 0 0 4px; }
.profile-hero-email { font-size: .83rem; color: var(--text-muted); margin: 0 0 6px; }
.profile-role-badge {
  display: inline-flex; align-items: center; gap: 5px;
  background: var(--green-soft); color: var(--green-dark);
  font-size: .75rem; font-weight: 700; padding: 3px 10px; border-radius: 99px;
}
.profile-hero-joined { font-size: .75rem; color: var(--text-muted); margin-top: 6px; }
.profile-hero-actions { padding-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; }
.btn-edit-profile, .btn-change-pw {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: var(--radius-sm); font-size: .83rem; font-weight: 600;
  cursor: pointer; border: none; transition: opacity .2s; text-decoration: none;
}
.btn-edit-profile { background: linear-gradient(135deg, var(--green-mid), var(--green-dark)); color: #fff; }
.btn-change-pw { background: #f1f5f9; color: var(--text-secondary); border: 1px solid var(--border); }
.btn-edit-profile:hover, .btn-change-pw:hover { opacity: .85; color: inherit; }

/* Stat chips */
.profile-stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.profile-stat-chip {
  background: #fff; border: 1px solid var(--border); border-radius: var(--radius-md);
  padding: 16px 22px; display: flex; align-items: center; gap: 14px;
  box-shadow: var(--shadow-sm); flex: 1; min-width: 140px;
}
.psc-icon {
  width: 42px; height: 42px; border-radius: var(--radius-sm);
  display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;
}
.psc-green  { background: #dcfce7; color: #16a34a; }
.psc-blue   { background: #dbeafe; color: #2563eb; }
.psc-amber  { background: #fef9c3; color: #92400e; }
.psc-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); font-weight: 600; }
.psc-value { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); line-height: 1; }

/* Info section */
.profile-section {
  background: #fff; border: 1px solid var(--border);
  border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
  overflow: hidden; margin-bottom: 20px;
}
.profile-section-header {
  padding: 16px 22px; border-bottom: 1px solid var(--border-light);
  display: flex; align-items: center; gap: 8px;
}
.profile-section-header h3 {
  font-size: .95rem; font-weight: 700; color: var(--text-primary); margin: 0;
  display: flex; align-items: center; gap: 8px;
}
.profile-section-header h3 i { color: var(--green-mid); }
.profile-section-body { padding: 22px; }

/* Info grid */
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
@media (max-width: 600px) { .info-grid { grid-template-columns: 1fr; } }
.info-field {}
.info-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); font-weight: 600; margin-bottom: 5px; }
.info-value { font-size: .9rem; color: var(--text-primary); font-weight: 500; padding: 8px 0; border-bottom: 1px solid var(--border-light); }
.info-value.empty { color: var(--text-muted); font-style: italic; }

/* Form inputs */
.form-field { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); }
.form-control-custom {
  padding: 9px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm);
  font-size: .875rem; color: var(--text-primary); background: #fff; outline: none;
  transition: border-color .2s;
}
.form-control-custom:focus { border-color: var(--green-mid); box-shadow: 0 0 0 3px rgba(45,134,83,.1); }
.form-col-2 { grid-column: span 2; }
@media (max-width: 600px) { .form-col-2 { grid-column: span 1; } }

/* Form buttons */
.form-actions { display: flex; gap: 10px; margin-top: 8px; padding-top: 16px; border-top: 1px solid var(--border-light); }
.btn-save {
  padding: 9px 20px; background: linear-gradient(135deg, var(--green-mid), var(--green-dark));
  color: #fff; border: none; border-radius: var(--radius-sm);
  font-size: .875rem; font-weight: 600; cursor: pointer; transition: opacity .2s;
}
.btn-save:hover { opacity: .88; }
.btn-cancel {
  padding: 9px 20px; background: #f1f5f9; color: var(--text-secondary);
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  font-size: .875rem; font-weight: 600; cursor: pointer;
}

/* Completed events table */
.cev-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.cev-table thead tr { background: var(--green-soft); }
.cev-table thead th { padding: 11px 16px; font-weight: 700; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; color: var(--green-dark); text-align: left; border-bottom: 1px solid var(--border); }
.cev-table tbody tr { border-bottom: 1px solid var(--border-light); transition: background var(--transition); }
.cev-table tbody tr:last-child { border-bottom: none; }
.cev-table tbody tr:hover { background: #f8fdf9; }
.cev-table td { padding: 12px 16px; color: var(--text-primary); }
.cev-empty { padding: 30px; text-align: center; color: var(--text-muted); font-size: .875rem; }
.cev-empty i { display: block; font-size: 2rem; opacity: .3; margin-bottom: 8px; }

/* Avatar file input hidden */
#avatarInput { display: none; }
</style>

<div class="profile-page">

  <h1 class="profile-page-title"><i class="fas fa-user-circle"></i> My Profile</h1>

  <?php if (isset($_GET['updated'])): ?>
    <div class="profile-toast"><i class="fas fa-check-circle"></i> Profile updated successfully!</div>
  <?php endif; ?>

  <!-- Hero Card -->
  <div class="profile-hero">
    <div class="profile-hero-banner"></div>
    <div class="profile-hero-body">

      <!-- Avatar -->
      <div class="profile-avatar-wrap" onclick="document.getElementById('avatarInput').click()" title="Click to change photo">
        <?php if (!empty($volunteer['profile_pic']) && file_exists("../uploads/profile/" . $volunteer['profile_pic'])): ?>
          <img id="avatarPreview" src="../uploads/profile/<?= htmlspecialchars($volunteer['profile_pic']) ?>" alt="Profile">
        <?php else: ?>
          <div class="avatar-placeholder"><i class="fas fa-user"></i></div>
        <?php endif; ?>
      </div>

      <div class="profile-hero-info">
        <h2 class="profile-hero-name"><?= htmlspecialchars($volunteer['name'] ?? '') ?></h2>
        <p class="profile-hero-email"><?= htmlspecialchars($volunteer['email'] ?? '') ?></p>
        <span class="profile-role-badge"><i class="fas fa-leaf"></i> Volunteer</span>
        <p class="profile-hero-joined">
          <i class="fas fa-calendar-alt me-1"></i>
          Joined <?= date('F Y', strtotime($volunteer['created_at'] ?? 'now')) ?>
        </p>
      </div>

      <div class="profile-hero-actions">
        <button class="btn-edit-profile" onclick="toggleEditMode(true)">
          <i class="fas fa-pen"></i> Edit Profile
        </button>
        <button class="btn-change-pw" onclick="alert('Change password feature coming soon.')">
          <i class="fas fa-lock"></i> Change Password
        </button>
      </div>
    </div>
  </div>

  <!-- Stat Chips -->
  <div class="profile-stats">
    <div class="profile-stat-chip">
      <div class="psc-icon psc-blue"><i class="fas fa-calendar-check"></i></div>
      <div><div class="psc-label">Events Joined</div><div class="psc-value"><?= $eventsJoined ?></div></div>
    </div>
    <div class="profile-stat-chip">
      <div class="psc-icon psc-amber"><i class="fas fa-tasks"></i></div>
      <div><div class="psc-label">Total Tasks</div><div class="psc-value"><?= $totalTasks ?></div></div>
    </div>
    <div class="profile-stat-chip">
      <div class="psc-icon psc-green"><i class="fas fa-check-circle"></i></div>
      <div><div class="psc-label">Completed Tasks</div><div class="psc-value"><?= $doneTasks ?></div></div>
    </div>
  </div>

  <!-- View Mode: Volunteer Details -->
  <div class="profile-section" id="viewMode">
    <div class="profile-section-header">
      <h3><i class="fas fa-id-card"></i> Personal Information</h3>
    </div>
    <div class="profile-section-body">
      <div class="info-grid">
        <?php
        $fields = [
          'Gender'       => $details['gender']       ?? null,
          'Age'          => $details['age']           ?? null,
          'Address'      => $details['address']       ?? null,
          'Phone Number' => $details['phone_number']  ?? null,
          'Hobbies'      => $details['hobbies']       ?? null,
          'Skills'       => $details['skills']        ?? null,
        ];
        foreach ($fields as $label => $val):
        ?>
          <div class="info-field">
            <div class="info-label"><?= $label ?></div>
            <div class="info-value <?= empty($val) ? 'empty' : '' ?>">
              <?= empty($val) ? '—' : htmlspecialchars($val) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Edit Mode: Form -->
  <div class="profile-section" id="editMode" style="display:none;">
    <div class="profile-section-header">
      <h3><i class="fas fa-pen"></i> Edit Personal Information</h3>
    </div>
    <div class="profile-section-body">
      <form method="POST" enctype="multipart/form-data">
        <!-- Hidden file input for avatar -->
        <input type="file" id="avatarInput" name="profile_pic" accept="image/*" onchange="previewAvatar(this)">

        <div class="info-grid">
          <div class="form-field">
            <label class="form-label">Gender</label>
            <input type="text" name="gender" class="form-control-custom" value="<?= htmlspecialchars($details['gender'] ?? '') ?>">
          </div>
          <div class="form-field">
            <label class="form-label">Age</label>
            <input type="number" name="age" class="form-control-custom" value="<?= htmlspecialchars($details['age'] ?? '') ?>">
          </div>
          <div class="form-field form-col-2">
            <label class="form-label">Address</label>
            <input type="text" name="address" class="form-control-custom" value="<?= htmlspecialchars($details['address'] ?? '') ?>">
          </div>
          <div class="form-field">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone_number" class="form-control-custom" value="<?= htmlspecialchars($details['phone_number'] ?? '') ?>">
          </div>
          <div class="form-field">
            <label class="form-label">Hobbies</label>
            <input type="text" name="hobbies" class="form-control-custom" value="<?= htmlspecialchars($details['hobbies'] ?? '') ?>">
          </div>
          <div class="form-field form-col-2">
            <label class="form-label">Skills</label>
            <input type="text" name="skills" class="form-control-custom" value="<?= htmlspecialchars($details['skills'] ?? '') ?>">
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" name="update_profile" class="btn-save"><i class="fas fa-save me-1"></i> Save Changes</button>
          <button type="button" class="btn-cancel" onclick="toggleEditMode(false)">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Completed Events -->
  <div class="profile-section">
    <div class="profile-section-header">
      <h3><i class="fas fa-award"></i> Completed Events</h3>
    </div>
    <div class="profile-section-body" style="padding:0;">
      <?php if ($completedEvents->num_rows > 0): ?>
        <table class="cev-table">
          <thead>
            <tr>
              <th>Event Title</th>
              <th>Date</th>
              <th>Location</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($r = $completedEvents->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($r['title']) ?></td>
                <td><?= date('M d, Y', strtotime($r['date'])) ?></td>
                <td><i class="fas fa-map-marker-alt me-1" style="color:#dc2626;font-size:.75rem;"></i><?= htmlspecialchars($r['location']) ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="cev-empty"><i class="fas fa-star"></i><p>No completed events yet. Keep volunteering!</p></div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
function toggleEditMode(show) {
  document.getElementById('viewMode').style.display = show ? 'none' : '';
  document.getElementById('editMode').style.display = show ? '' : 'none';
}

function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const wrap = document.querySelector('.profile-avatar-wrap');
      wrap.innerHTML = `<img id="avatarPreview" src="${e.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;">`;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php include '../includes/footer.php'; ?>