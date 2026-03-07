<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "Notifications";
include '../includes/header_admin.php';

$admin_id = $_SESSION['user_id'] ?? 0;

// Get org
$orgStmt = $conn->prepare("SELECT id FROM organizations WHERE admin_id = ?");
$orgStmt->bind_param("i", $admin_id);
$orgStmt->execute();
$orgData = $orgStmt->get_result()->fetch_assoc();
$org_id  = $orgData['id'] ?? 0;

// ── Collect all notification groups ──────────────────────────────────────────

// 1. Pending applications
$appStmt = $conn->prepare("
    SELECT u.name, u.id AS uid, e.title, e.id AS eid, va.status, va.applied_at
    FROM volunteer_applications va
    JOIN users u  ON u.id  = va.user_id
    JOIN events e ON e.id  = va.event_id
    WHERE e.created_by = ? AND va.status = 'pending'
    ORDER BY va.applied_at DESC
");
$appStmt->bind_param("i", $admin_id);
$appStmt->execute();
$pendingApps = $appStmt->get_result();

// Display pending applications
while ($app = $pendingApps->fetch_assoc()) {
    // Create notification item for each pending application
}

// 2. Recent attendance
$attStmt = $conn->prepare("
    SELECT u.name, e.title, e.id AS eid, ea.check_in, ea.check_out, ea.attended
    FROM event_attendance ea
    JOIN users u  ON u.id  = ea.volunteer_id
    JOIN events e ON e.id  = ea.event_id
    WHERE e.created_by = ?
    ORDER BY COALESCE(ea.check_out, ea.check_in) DESC
    LIMIT 20
");
$attStmt->bind_param("i", $admin_id);
$attStmt->execute();
$recentAttendance = $attStmt->get_result();

// 3. Pending donations
$donStmt = null;
$pendingDonations = [];
if ($org_id) {
    $donStmt = $conn->prepare("
        SELECT u.name, g.amount, 'GCash' AS method, g.created_at, g.status
        FROM gcash_donations g JOIN users u ON u.id = g.user_id
        WHERE g.organization_id = ?
        UNION ALL
        SELECT u.name, b.amount, 'Bank Transfer' AS method, b.created_at, b.status
        FROM bank_payments b JOIN users u ON u.id = b.user_id
        WHERE b.organization_id = ?
        ORDER BY created_at DESC
        LIMIT 15
    ");
    $donStmt->bind_param("ii", $org_id, $org_id);
    $donStmt->execute();
    $pendingDonations = $donStmt->get_result();
}

// 4. Task updates
$taskStmt = $conn->prepare("
    SELECT u.name, e.title AS event_title, ta.progress, ta.assigned_at
    FROM task_assignments ta
    JOIN users u ON u.id = ta.volunteer_id
    JOIN tasks t ON t.id = ta.task_id
    JOIN events e ON e.id = t.event_id
    WHERE e.created_by = ?
    ORDER BY ta.assigned_at DESC
");
$taskStmt->bind_param("i", $admin_id);
$taskStmt->execute();
$taskUpdates = $taskStmt->get_result();
?>

<style>
.notif-page { padding: 28px 28px 60px; }
.notif-page-header {
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 28px;
}
.notif-page-title {
  font-size: 1.45rem; font-weight: 700;
  color: var(--text-primary); margin: 0;
}
.notif-page-title i { color: var(--green-mid); margin-right: 6px; }

/* Section cards */
.notif-section {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
  margin-bottom: 26px;
  overflow: hidden;
}
.notif-section-header {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 20px;
  border-bottom: 1px solid var(--border-light);
  background: #fafbfc;
}
.notif-section-header h5 {
  font-size: .95rem; font-weight: 700;
  color: var(--text-primary); margin: 0;
  display: flex; align-items: center; gap: 8px;
}
.notif-section-header h5 i { color: var(--green-mid); }
.notif-count-badge {
  font-size: .7rem; font-weight: 700;
  padding: 2px 9px; border-radius: 99px;
  background: var(--green-soft); color: var(--green-dark);
  margin-left: auto;
}

/* Row items */
.notif-row {
  display: flex; align-items: flex-start; gap: 14px;
  padding: 14px 20px;
  border-bottom: 1px solid var(--border-light);
  transition: background var(--transition);
}
.notif-row:last-child { border-bottom: none; }
.notif-row:hover { background: var(--green-soft); }
.notif-row-icon {
  width: 38px; height: 38px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .9rem; flex-shrink: 0;
}
.ni-green  { background: #dcfce7; color: #16a34a; }
.ni-blue   { background: #dbeafe; color: #2563eb; }
.ni-amber  { background: #fef9c3; color: #ca8a04; }
.ni-purple { background: #ede9fe; color: #7c3aed; }
.ni-red    { background: #fee2e2; color: #dc2626; }
.notif-row-body { flex: 1; min-width: 0; }
.notif-row-msg { font-size: .875rem; color: var(--text-primary); line-height: 1.5; margin-bottom: 3px; }
.notif-row-meta { font-size: .75rem; color: var(--text-muted); }
.notif-row-action {
  flex-shrink: 0;
  display: flex; align-items: center;
}
.notif-link-btn {
  font-size: .78rem; font-weight: 600;
  color: var(--green-mid); text-decoration: none;
  padding: 4px 10px; border-radius: 6px;
  border: 1px solid #bbf7d0;
  background: var(--green-soft);
  transition: all var(--transition);
  white-space: nowrap;
}
.notif-link-btn:hover { background: var(--green-mid); color: #fff; border-color: var(--green-mid); }
.notif-empty-row {
  padding: 28px 20px; text-align: center;
  color: var(--text-muted); font-size: .875rem;
}
.notif-empty-row i { font-size: 1.6rem; display: block; margin-bottom: 8px; opacity: .35; }

/* Status pills */
.pill { display:inline-flex;align-items:center;padding:2px 9px;border-radius:99px;font-size:.7rem;font-weight:700; }
.pill-pending  { background:#fef9c3;color:#92400e; }
.pill-approved { background:#dcfce7;color:#166534; }
.pill-rejected { background:#fee2e2;color:#991b1b; }
.pill-checkin  { background:#dbeafe;color:#1d4ed8; }
.pill-checkout { background:#e0e7ff;color:#4338ca; }
</style>

<div class="notif-page">
  <div class="notif-page-header">
    <h1 class="notif-page-title"><i class="fas fa-bell"></i> Notifications</h1>
  </div>

  <!-- ── Section 1: Pending Applications ─────────────────────────────────── -->
  <div class="notif-section">
    <div class="notif-section-header">
      <h5><i class="fas fa-user-plus"></i> Volunteer Applications (Pending)</h5>
      <?php $pendingApps->data_seek(0); $apCount = $pendingApps->num_rows; ?>
      <span class="notif-count-badge"><?= $apCount ?></span>
    </div>
    <?php $pendingApps->data_seek(0); if ($apCount === 0): ?>
      <div class="notif-empty-row"><i class="fas fa-user-clock"></i>No pending applications.</div>
    <?php else: while ($r = $pendingApps->fetch_assoc()): ?>
      <div class="notif-row">
        <div class="notif-row-icon ni-green"><i class="fas fa-user-plus"></i></div>
        <div class="notif-row-body">
          <div class="notif-row-msg">
            <strong><?= htmlspecialchars($r['name']) ?></strong> applied to your event:
            <strong><?= htmlspecialchars($r['title']) ?></strong>
          </div>
          <div class="notif-row-meta">
            <?= date('M d, Y g:i A', strtotime($r['applied_at'])) ?>
            &nbsp;·&nbsp;<span class="pill pill-pending">Pending Review</span>
          </div>
        </div>
        <div class="notif-row-action">
          <a href="/VolunteerHub/admin/manage_events.php" class="notif-link-btn">
            <i class="fas fa-external-link-alt me-1"></i>Review
          </a>
        </div>
      </div>
    <?php endwhile; endif; ?>
  </div>

  <!-- ── Section 2: Recent Attendance ────────────────────────────────────── -->
  <div class="notif-section">
    <div class="notif-section-header">
      <h5><i class="fas fa-clipboard-check"></i> Recent Check-ins & Check-outs</h5>
      <span class="notif-count-badge"><?= $recentAttendance->num_rows ?></span>
    </div>
    <?php if ($recentAttendance->num_rows === 0): ?>
      <div class="notif-empty-row"><i class="fas fa-clock"></i>No attendance records yet.</div>
    <?php else: while ($r = $recentAttendance->fetch_assoc()):
      $isOut  = ($r['attended'] == 2 && $r['check_out']);
      $ts     = $isOut ? $r['check_out'] : $r['check_in'];
      $label  = $isOut ? 'Checked Out' : 'Checked In';
      $pClass = $isOut ? 'pill-checkout' : 'pill-checkin';
      $icon   = $isOut ? 'fas fa-sign-out-alt' : 'fas fa-sign-in-alt';
      $iClass = $isOut ? 'ni-blue' : 'ni-green';
    ?>
      <div class="notif-row">
        <div class="notif-row-icon <?= $iClass ?>"><i class="<?= $icon ?>"></i></div>
        <div class="notif-row-body">
          <div class="notif-row-msg">
            <strong><?= htmlspecialchars($r['name']) ?></strong> <?= strtolower($label) ?> for
            <strong><?= htmlspecialchars($r['title']) ?></strong>
          </div>
          <div class="notif-row-meta">
            <?= $ts ? date('M d, Y g:i A', strtotime($ts)) : 'N/A' ?>
            &nbsp;·&nbsp;<span class="pill <?= $pClass ?>"><?= $label ?></span>
          </div>
        </div>
        <div class="notif-row-action">
          <a href="/VolunteerHub/admin/manage_tasks.php" class="notif-link-btn">
            <i class="fas fa-external-link-alt me-1"></i>View
          </a>
        </div>
      </div>
    <?php endwhile; endif; ?>
  </div>

  <!-- ── Section 3: Donations ─────────────────────────────────────────────── -->
  <div class="notif-section">
    <div class="notif-section-header">
      <h5><i class="fas fa-hand-holding-heart"></i> Donation Submissions</h5>
    </div>
    <?php if (empty($pendingDonations) || $pendingDonations->num_rows === 0): ?>
      <div class="notif-empty-row"><i class="fas fa-donate"></i>No recent donations.</div>
    <?php else: while ($r = $pendingDonations->fetch_assoc()):
      $isPending = ($r['status'] === 'Pending');
    ?>
      <div class="notif-row">
        <div class="notif-row-icon ni-purple"><i class="fas fa-hand-holding-heart"></i></div>
        <div class="notif-row-body">
          <div class="notif-row-msg">
            <strong><?= htmlspecialchars($r['name']) ?></strong> submitted a
            <strong><?= htmlspecialchars($r['method']) ?></strong> donation of
            <strong>₱<?= number_format($r['amount'], 2) ?></strong>
          </div>
          <div class="notif-row-meta">
            <?= date('M d, Y g:i A', strtotime($r['created_at'])) ?>
            &nbsp;·&nbsp;<span class="pill <?= $isPending ? 'pill-pending' : 'pill-approved' ?>">
              <?= htmlspecialchars($r['status']) ?>
            </span>
          </div>
        </div>
        <div class="notif-row-action">
          <a href="/VolunteerHub/admin/manage_donations.php" class="notif-link-btn">
            <i class="fas fa-external-link-alt me-1"></i>Review
          </a>
        </div>
      </div>
    <?php endwhile; endif; ?>
  </div>

  <!-- ── Section 4: Task Updates ──────────────────────────────────────────── -->
  <div class="notif-section">
    <div class="notif-section-header">
      <h5><i class="fas fa-tasks"></i> Task Progress Updates</h5>
      <span class="notif-count-badge"><?= $taskUpdates->num_rows ?></span>
    </div>
    <?php if ($taskUpdates->num_rows === 0): ?>
      <div class="notif-empty-row"><i class="fas fa-clipboard-list"></i>No task updates yet.</div>
    <?php else: while ($r = $taskUpdates->fetch_assoc()):
      $isDone  = ($r['progress'] === 'Completed');
      $iClass  = $isDone ? 'ni-green' : 'ni-amber';
      $icon    = $isDone ? 'fas fa-check-circle' : 'fas fa-spinner';
      $pClass  = $isDone ? 'pill-approved' : 'pill-pending';
    ?>
      <div class="notif-row">
        <div class="notif-row-icon <?= $iClass ?>"><i class="<?= $icon ?>"></i></div>
        <div class="notif-row-body">
          <div class="notif-row-msg">
            <strong><?= htmlspecialchars($r['name']) ?></strong> marked a task as
            <strong><?= htmlspecialchars($r['progress']) ?></strong>
            in <strong><?= htmlspecialchars($r['event_title']) ?></strong>
          </div>
          <div class="notif-row-meta">
            <?= date('M d, Y g:i A', strtotime($r['assigned_at'])) ?>
            &nbsp;·&nbsp;<span class="pill <?= $pClass ?>"><?= htmlspecialchars($r['progress']) ?></span>
          </div>
        </div>
        <div class="notif-row-action">
          <a href="/VolunteerHub/admin/manage_tasks.php" class="notif-link-btn">
            <i class="fas fa-external-link-alt me-1"></i>View
          </a>
        </div>
      </div>
    <?php endwhile; endif; ?>
  </div>

</div>

<?php include '../includes/footer.php'; ?>