<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "Notifications";
$pageCSS   = "/VolunteerHub/styles/volunteer_layout.css";
include '../includes/header_volunteer.php';

$user_id = $_SESSION['user_id'];

// ── 1. Application status updates ────────────────────────────────────────────
$appStmt = $conn->prepare("
    SELECT e.title, va.status, va.created_at
    FROM volunteer_applications va
    JOIN events e ON e.id = va.event_id
    WHERE va.user_id = ?
    ORDER BY va.created_at DESC
    LIMIT 15
");
$appStmt->bind_param("i", $user_id); $appStmt->execute();
$appResult = $appStmt->get_result();

// ── 2. Task assignments ───────────────────────────────────────────────────────
$taskStmt = $conn->prepare("
    SELECT t.description, e.title AS event_title, ta.assigned_at
    FROM task_assignments ta
    JOIN tasks t ON t.id = ta.task_id
    JOIN events e ON e.id = t.event_id
    WHERE ta.volunteer_id = ?
    ORDER BY ta.assigned_at DESC
    LIMIT 10
");
$taskStmt->bind_param("i", $user_id); $taskStmt->execute();
$taskResult = $taskStmt->get_result();

// ── 3. Upcoming events (within 3 days) ───────────────────────────────────────
$upcomingStmt = $conn->prepare("
    SELECT e.title, e.date, e.location
    FROM volunteer_applications va
    JOIN events e ON e.id = va.event_id
    WHERE va.user_id = ?
      AND va.status = 'approved'
      AND e.date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY e.date ASC
");
$upcomingStmt->bind_param("i", $user_id); $upcomingStmt->execute();
$upcomingResult = $upcomingStmt->get_result();

// ── 4. Attendance records (check-in/out confirmations) ────────────────────────
$attStmt = $conn->prepare("
    SELECT e.title, ea.check_in, ea.check_out, ea.attended
    FROM event_attendance ea
    JOIN events e ON e.id = ea.event_id
    WHERE ea.volunteer_id = ?
      AND (ea.check_in IS NOT NULL OR ea.check_out IS NOT NULL)
    ORDER BY COALESCE(ea.check_out, ea.check_in) DESC
    LIMIT 10
");
$attStmt->bind_param("i", $user_id); $attStmt->execute();
$attResult = $attStmt->get_result();

// Helper: human-readable time ago
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M d, Y', strtotime($datetime));
}
?>

<style>
.notifs-page { padding: 28px 28px 60px; }

/* Header */
.notifs-header { margin-bottom: 28px; }
.notifs-title { font-size: 1.45rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 10px; margin: 0 0 4px; }
.notifs-title i { color: var(--green-mid); }
.notifs-subtitle { font-size: .875rem; color: var(--text-muted); margin: 0; }

/* Tabs */
.notif-tabs {
  display: flex; gap: 4px; flex-wrap: wrap;
  border-bottom: 2px solid var(--border); margin-bottom: 24px; padding-bottom: 0;
}
.notif-tab {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 16px; border-radius: var(--radius-sm) var(--radius-sm) 0 0;
  font-size: .83rem; font-weight: 600; cursor: pointer; border: none;
  background: transparent; color: var(--text-muted);
  transition: all .18s; margin-bottom: -2px; border-bottom: 2px solid transparent;
}
.notif-tab:hover { color: var(--green-mid); background: var(--green-soft); }
.notif-tab.active { color: var(--green-dark); border-bottom-color: var(--green-mid); background: transparent; }
.tab-count {
  background: var(--green-soft); color: var(--green-dark);
  padding: 1px 7px; border-radius: 99px; font-size: .72rem; font-weight: 700;
}

/* Section */
.notif-section { display: none; }
.notif-section.active { display: block; }

/* Card container */
.notif-card {
  background: #fff; border: 1px solid var(--border);
  border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden;
}

/* Notification row */
.notif-row {
  display: flex; align-items: flex-start; gap: 14px;
  padding: 16px 22px; border-bottom: 1px solid var(--border-light);
  transition: background var(--transition); cursor: default;
}
.notif-row:last-child { border-bottom: none; }
.notif-row:hover { background: #fafcff; }

/* Icon */
.notif-icon-wrap {
  width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: .9rem;
  margin-top: 2px;
}
.ni-green  { background: #dcfce7; color: #16a34a; }
.ni-amber  { background: #fef9c3; color: #92400e; }
.ni-blue   { background: #dbeafe; color: #1d4ed8; }
.ni-red    { background: #fee2e2; color: #dc2626; }
.ni-teal   { background: #ccfbf1; color: #0f766e; }
.ni-gray   { background: #f1f5f9; color: #64748b; }

/* Content */
.notif-content { flex: 1; min-width: 0; }
.notif-msg { font-size: .875rem; color: var(--text-primary); line-height: 1.45; margin-bottom: 4px; }
.notif-msg strong { font-weight: 700; }
.notif-meta { display: flex; gap: 12px; flex-wrap: wrap; }
.notif-time { font-size: .75rem; color: var(--text-muted); display: flex; align-items: center; gap: 4px; }
.notif-tag {
  font-size: .72rem; font-weight: 600; padding: 2px 8px; border-radius: 99px;
}
.nt-approved  { background: #dcfce7; color: #15803d; }
.nt-pending   { background: #fef9c3; color: #78350f; }
.nt-rejected  { background: #fee2e2; color: #7f1d1d; }
.nt-completed { background: #dbeafe; color: #1e40af; }
.nt-default   { background: #f1f5f9; color: #475569; }

/* Empty state */
.notif-empty { padding: 50px; text-align: center; color: var(--text-muted); }
.notif-empty i { font-size: 2.5rem; opacity: .3; display: block; margin-bottom: 12px; }
.notif-empty p { font-size: .875rem; margin: 0; }
</style>

<div class="notifs-page">

  <div class="notifs-header">
    <h1 class="notifs-title"><i class="fas fa-bell"></i> Notifications</h1>
    <p class="notifs-subtitle">Stay updated on your events, tasks, and attendance.</p>
  </div>

  <!-- Tabs -->
  <?php
  $appCount      = $appResult->num_rows;
  $taskCount     = $taskResult->num_rows;
  $upcomingCount = $upcomingResult->num_rows;
  $attCount      = $attResult->num_rows;
  ?>
  <div class="notif-tabs">
    <button class="notif-tab active" onclick="switchTab('tab-apps', this)">
      <i class="fas fa-clipboard-check"></i> Applications
      <?php if ($appCount > 0): ?><span class="tab-count"><?= $appCount ?></span><?php endif; ?>
    </button>
    <button class="notif-tab" onclick="switchTab('tab-tasks', this)">
      <i class="fas fa-tasks"></i> Task Assignments
      <?php if ($taskCount > 0): ?><span class="tab-count"><?= $taskCount ?></span><?php endif; ?>
    </button>
    <button class="notif-tab" onclick="switchTab('tab-upcoming', this)">
      <i class="fas fa-calendar-exclamation"></i> Upcoming Events
      <?php if ($upcomingCount > 0): ?><span class="tab-count"><?= $upcomingCount ?></span><?php endif; ?>
    </button>
    <button class="notif-tab" onclick="switchTab('tab-attendance', this)">
      <i class="fas fa-qrcode"></i> Attendance
      <?php if ($attCount > 0): ?><span class="tab-count"><?= $attCount ?></span><?php endif; ?>
    </button>
  </div>

  <!-- Tab: Applications -->
  <div class="notif-section active" id="tab-apps">
    <div class="notif-card">
      <?php if ($appCount > 0):
        $appResult->data_seek(0);
        while ($r = $appResult->fetch_assoc()):
          $status = strtolower($r['status']);
          [$iconClass, $icon, $tagClass] = match($status) {
            'approved'  => ['ni-green', 'fa-check-circle',  'nt-approved'],
            'rejected'  => ['ni-red',   'fa-times-circle',  'nt-rejected'],
            'completed' => ['ni-blue',  'fa-award',         'nt-completed'],
            default     => ['ni-amber', 'fa-clock',         'nt-pending'],
          };
      ?>
        <div class="notif-row">
          <div class="notif-icon-wrap <?= $iconClass ?>"><i class="fas <?= $icon ?>"></i></div>
          <div class="notif-content">
            <div class="notif-msg">
              Your application for <strong><?= htmlspecialchars($r['title']) ?></strong> is
              <strong><?= htmlspecialchars(ucfirst($r['status'])) ?></strong>.
            </div>
            <div class="notif-meta">
              <span class="notif-time"><i class="fas fa-clock"></i><?= timeAgo($r['created_at']) ?></span>
              <span class="notif-tag <?= $tagClass ?>"><?= ucfirst($r['status']) ?></span>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
      <?php else: ?>
        <div class="notif-empty"><i class="fas fa-clipboard"></i><p>No application updates yet.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tab: Task Assignments -->
  <div class="notif-section" id="tab-tasks">
    <div class="notif-card">
      <?php if ($taskCount > 0):
        $taskResult->data_seek(0);
        while ($r = $taskResult->fetch_assoc()):
      ?>
        <div class="notif-row">
          <div class="notif-icon-wrap ni-teal"><i class="fas fa-clipboard-list"></i></div>
          <div class="notif-content">
            <div class="notif-msg">
              New task assigned: <strong><?= htmlspecialchars($r['description']) ?></strong>
              under event <strong><?= htmlspecialchars($r['event_title']) ?></strong>.
            </div>
            <div class="notif-meta">
              <span class="notif-time"><i class="fas fa-clock"></i><?= timeAgo($r['assigned_at']) ?></span>
              <span class="notif-tag nt-default">Task Assigned</span>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
      <?php else: ?>
        <div class="notif-empty"><i class="fas fa-tasks"></i><p>No task assignments yet.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tab: Upcoming Events -->
  <div class="notif-section" id="tab-upcoming">
    <div class="notif-card">
      <?php if ($upcomingCount > 0):
        $upcomingResult->data_seek(0);
        while ($r = $upcomingResult->fetch_assoc()):
          $daysLeft = ceil((strtotime($r['date']) - time()) / 86400);
      ?>
        <div class="notif-row">
          <div class="notif-icon-wrap ni-amber"><i class="fas fa-calendar-exclamation"></i></div>
          <div class="notif-content">
            <div class="notif-msg">
              Reminder: <strong><?= htmlspecialchars($r['title']) ?></strong> is happening
              <?= $daysLeft <= 0 ? 'today' : "in {$daysLeft} day" . ($daysLeft > 1 ? 's' : '') ?>!
            </div>
            <div class="notif-meta">
              <span class="notif-time"><i class="fas fa-calendar"></i><?= date('M d, Y', strtotime($r['date'])) ?></span>
              <span class="notif-time"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($r['location']) ?></span>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
      <?php else: ?>
        <div class="notif-empty"><i class="fas fa-calendar-times"></i><p>No upcoming events in the next 3 days.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tab: Attendance -->
  <div class="notif-section" id="tab-attendance">
    <div class="notif-card">
      <?php if ($attCount > 0):
        $attResult->data_seek(0);
        while ($r = $attResult->fetch_assoc()):
          $hasOut = !empty($r['check_out']);
          $iconClass = $hasOut ? 'ni-green' : 'ni-blue';
          $icon      = $hasOut ? 'fa-sign-out-alt' : 'fa-sign-in-alt';
          $msg       = $hasOut
            ? "You <strong>checked out</strong> of <strong>" . htmlspecialchars($r['title']) . "</strong>."
            : "You <strong>checked in</strong> to <strong>" . htmlspecialchars($r['title']) . "</strong>.";
          $ts = $hasOut ? $r['check_out'] : $r['check_in'];
      ?>
        <div class="notif-row">
          <div class="notif-icon-wrap <?= $iconClass ?>"><i class="fas <?= $icon ?>"></i></div>
          <div class="notif-content">
            <div class="notif-msg"><?= $msg ?></div>
            <div class="notif-meta">
              <span class="notif-time"><i class="fas fa-clock"></i><?= timeAgo($ts) ?></span>
              <?php if (!empty($r['check_in'])): ?>
                <span class="notif-time"><i class="fas fa-sign-in-alt"></i> In: <?= date('g:i A', strtotime($r['check_in'])) ?></span>
              <?php endif; ?>
              <?php if ($hasOut): ?>
                <span class="notif-time"><i class="fas fa-sign-out-alt"></i> Out: <?= date('g:i A', strtotime($r['check_out'])) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
      <?php else: ?>
        <div class="notif-empty"><i class="fas fa-qrcode"></i><p>No check-in/check-out records yet.</p></div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
function switchTab(tabId, btn) {
  document.querySelectorAll('.notif-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.notif-tab').forEach(b => b.classList.remove('active'));
  document.getElementById(tabId).classList.add('active');
  btn.classList.add('active');
}
</script>

<?php include '../includes/footer.php'; ?>