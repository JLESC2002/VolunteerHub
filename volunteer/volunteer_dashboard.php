<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "My Dashboard";
$pageCSS   = "/VolunteerHub/styles/dashboard.css";
include '../includes/header_volunteer.php';

$uid = $_SESSION['user_id'];

// ── Stat counts (volunteer-specific) ─────────────────────────────────────────
$upcomingEvents = $conn->prepare("
    SELECT COUNT(*) AS c FROM volunteer_applications va
    JOIN events e ON e.id = va.event_id
    WHERE va.user_id = ? AND va.status = 'approved' AND e.date >= CURDATE()
");
$upcomingEvents->bind_param("i", $uid); $upcomingEvents->execute();
$cUpcoming = $upcomingEvents->get_result()->fetch_assoc()['c'] ?? 0;

$activeTasks = $conn->prepare("
    SELECT COUNT(*) AS c FROM task_assignments ta
    JOIN tasks t ON t.id = ta.task_id
    WHERE ta.volunteer_id = ? AND ta.progress != 'Completed'
");
$activeTasks->bind_param("i", $uid); $activeTasks->execute();
$cActive = $activeTasks->get_result()->fetch_assoc()['c'] ?? 0;

$completedTasks = $conn->prepare("
    SELECT COUNT(*) AS c FROM task_assignments ta
    WHERE ta.volunteer_id = ? AND ta.progress = 'Completed'
");
$completedTasks->bind_param("i", $uid); $completedTasks->execute();
$cCompleted = $completedTasks->get_result()->fetch_assoc()['c'] ?? 0;

$pendingApps = $conn->prepare("
    SELECT COUNT(*) AS c FROM volunteer_applications
    WHERE user_id = ? AND status = 'pending'
");
$pendingApps->bind_param("i", $uid); $pendingApps->execute();
$cPending = $pendingApps->get_result()->fetch_assoc()['c'] ?? 0;

// ── Upcoming events I'm approved for ─────────────────────────────────────────
$upcomingQ = $conn->prepare("
    SELECT e.id, e.title, e.date, e.location, o.name AS org_name
    FROM volunteer_applications va
    JOIN events e ON e.id = va.event_id
    LEFT JOIN organizations o ON o.id = e.organization_id
    WHERE va.user_id = ? AND va.status = 'approved' AND e.date >= CURDATE()
    ORDER BY e.date ASC LIMIT 5
");
$upcomingQ->bind_param("i", $uid); $upcomingQ->execute();
$upcomingRows = $upcomingQ->get_result();

// ── My active tasks ───────────────────────────────────────────────────────────
$tasksQ = $conn->prepare("
    SELECT t.description, e.title AS event_title, e.date,
           COALESCE(ta.progress,'Not Started') AS progress
    FROM task_assignments ta
    JOIN tasks t ON t.id = ta.task_id
    JOIN events e ON e.id = t.event_id
    WHERE ta.volunteer_id = ?
    ORDER BY e.date ASC LIMIT 5
");
$tasksQ->bind_param("i", $uid); $tasksQ->execute();
$tasksRows = $tasksQ->get_result();

// ── Recent notifications (app status changes + task assignments) ──────────────
$notifsQ = $conn->prepare("
    SELECT 'application' AS type, e.title, va.status, va.created_at AS ts
    FROM volunteer_applications va
    JOIN events e ON e.id = va.event_id
    WHERE va.user_id = ?
    ORDER BY va.created_at DESC LIMIT 5
");
$notifsQ->bind_param("i", $uid); $notifsQ->execute();
$notifsRows = $notifsQ->get_result();

// ── Leaderboard ───────────────────────────────────────────────────────────────
$leaderQ = "
    SELECT u.id, u.name, COUNT(ta.id) AS cnt
    FROM task_assignments ta
    JOIN users u ON u.id = ta.volunteer_id
    WHERE ta.progress = 'Completed'
    GROUP BY u.id ORDER BY cnt DESC LIMIT 5
";
$leaderRows = $conn->query($leaderQ);
?>

<style>
/* ── Volunteer Dashboard Overrides ── */
.vol-dash { padding: 28px 28px 60px; }
.vol-dash-header { margin-bottom: 28px; }
.vol-dash-title {
  font-size: 1.45rem; font-weight: 700; color: var(--text-primary);
  display: flex; align-items: center; gap: 10px; margin: 0;
}
.vol-dash-title i { color: var(--green-mid); }
.vol-dash-subtitle { color: var(--text-muted); font-size: .875rem; margin-top: 4px; }

/* Stat grid */
.v-stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 16px; margin-bottom: 32px;
}
.v-stat-card {
  background: #fff; border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 22px 20px;
  display: flex; align-items: center; gap: 16px;
  box-shadow: var(--shadow-sm);
  transition: box-shadow var(--transition), transform var(--transition);
}
.v-stat-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.v-stat-icon {
  width: 50px; height: 50px; border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; flex-shrink: 0;
}
.si-green  { background: #dcfce7; color: #16a34a; }
.si-blue   { background: #dbeafe; color: #2563eb; }
.si-amber  { background: #fef9c3; color: #ca8a04; }
.si-teal   { background: #ccfbf1; color: #0f766e; }
.v-stat-label { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); margin-bottom: 2px; }
.v-stat-value { font-size: 1.65rem; font-weight: 700; color: var(--text-primary); line-height: 1; }

/* Two col */
.v-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
@media (max-width: 860px) { .v-two-col { grid-template-columns: 1fr; } }

/* Section card */
.v-section {
  background: #fff; border: 1px solid var(--border);
  border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden;
}
.v-section-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 22px; border-bottom: 1px solid var(--border-light);
}
.v-section-title { font-size: .95rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; margin: 0; }
.v-section-title i { color: var(--green-mid); }
.v-section-link { font-size: .8rem; color: var(--green-mid); text-decoration: none; font-weight: 600; }
.v-section-link:hover { text-decoration: underline; }

/* Event rows */
.v-event-list { list-style: none; margin: 0; padding: 0; }
.v-event-item {
  display: flex; align-items: flex-start; gap: 14px;
  padding: 14px 22px; border-bottom: 1px solid var(--border-light);
  transition: background var(--transition);
}
.v-event-item:last-child { border-bottom: none; }
.v-event-item:hover { background: var(--green-soft); }
.v-event-dot {
  width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
  background: var(--green-soft); color: var(--green-mid);
  display: flex; align-items: center; justify-content: center; font-size: .9rem; margin-top: 2px;
}
.v-event-name { font-size: .9rem; font-weight: 600; color: var(--text-primary); margin-bottom: 3px; }
.v-event-meta { font-size: .78rem; color: var(--text-muted); display: flex; gap: 10px; flex-wrap: wrap; }
.v-event-meta i { margin-right: 3px; }

/* Progress pill */
.prog-pill {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 9px; border-radius: 99px; font-size: .72rem; font-weight: 600;
}
.prog-not-started { background: #f1f5f9; color: #64748b; }
.prog-in-progress { background: #fef9c3; color: #92400e; }
.prog-completed   { background: #dcfce7; color: #15803d; }

/* Leaderboard */
.lb-item {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 22px; border-bottom: 1px solid var(--border-light);
  transition: background var(--transition);
}
.lb-item:last-child { border-bottom: none; }
.lb-item:hover { background: var(--green-soft); }
.lb-rank {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--green-soft); color: var(--green-dark);
  display: flex; align-items: center; justify-content: center;
  font-size: .75rem; font-weight: 700; flex-shrink: 0;
}
.lb-rank.gold   { background: #fef9c3; color: #92400e; }
.lb-rank.silver { background: #f1f5f9; color: #475569; }
.lb-rank.bronze { background: #fce7d3; color: #92400e; }
.lb-name { flex: 1; font-size: .88rem; font-weight: 600; color: var(--text-primary); }
.lb-mine { font-size: .7rem; background: var(--green-soft); color: var(--green-dark); padding: 2px 7px; border-radius: 99px; font-weight: 700; }
.lb-count { font-size: .85rem; font-weight: 700; color: var(--green-mid); }

/* Notif rows */
.notif-item {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 13px 22px; border-bottom: 1px solid var(--border-light);
  transition: background var(--transition);
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: #fafcff; }
.notif-icon {
  width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: .85rem;
}
.ni-green  { background: #dcfce7; color: #15803d; }
.ni-amber  { background: #fef9c3; color: #92400e; }
.ni-blue   { background: #dbeafe; color: #1d4ed8; }
.ni-red    { background: #fee2e2; color: #dc2626; }
.notif-msg { font-size: .85rem; color: var(--text-primary); line-height: 1.4; }
.notif-time { font-size: .72rem; color: var(--text-muted); margin-top: 3px; }

.empty-state { padding: 28px; text-align: center; color: var(--text-muted); font-size: .875rem; }
.empty-state i { font-size: 2rem; margin-bottom: 8px; display: block; opacity: .35; }
</style>

<div class="vol-dash">

  <!-- Header -->
  <div class="vol-dash-header">
    <h1 class="vol-dash-title"><i class="fas fa-home"></i> My Dashboard</h1>
    <p class="vol-dash-subtitle">Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Volunteer') ?>! Here's your activity overview.</p>
  </div>

  <!-- Stat Cards -->
  <div class="v-stat-grid">
    <div class="v-stat-card">
      <div class="v-stat-icon si-blue"><i class="fas fa-calendar-check"></i></div>
      <div>
        <div class="v-stat-label">Upcoming Events</div>
        <div class="v-stat-value"><?= $cUpcoming ?></div>
      </div>
    </div>
    <div class="v-stat-card">
      <div class="v-stat-icon si-amber"><i class="fas fa-spinner"></i></div>
      <div>
        <div class="v-stat-label">Active Tasks</div>
        <div class="v-stat-value"><?= $cActive ?></div>
      </div>
    </div>
    <div class="v-stat-card">
      <div class="v-stat-icon si-green"><i class="fas fa-check-circle"></i></div>
      <div>
        <div class="v-stat-label">Completed Tasks</div>
        <div class="v-stat-value"><?= $cCompleted ?></div>
      </div>
    </div>
    <div class="v-stat-card">
      <div class="v-stat-icon si-teal"><i class="fas fa-clock"></i></div>
      <div>
        <div class="v-stat-label">Pending Applications</div>
        <div class="v-stat-value"><?= $cPending ?></div>
      </div>
    </div>
  </div>

  <!-- Row 1: Upcoming Events + Active Tasks -->
  <div class="v-two-col">

    <!-- Upcoming Events -->
    <div class="v-section">
      <div class="v-section-header">
        <h2 class="v-section-title"><i class="fas fa-calendar-alt"></i> Upcoming Events</h2>
        <a href="/VolunteerHub/volunteer/list_events.php" class="v-section-link">Browse all →</a>
      </div>
      <?php if ($upcomingRows->num_rows > 0): ?>
        <ul class="v-event-list">
          <?php while ($r = $upcomingRows->fetch_assoc()): ?>
            <li class="v-event-item">
              <div class="v-event-dot"><i class="fas fa-calendar-day"></i></div>
              <div>
                <div class="v-event-name"><?= htmlspecialchars($r['title']) ?></div>
                <div class="v-event-meta">
                  <span><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($r['location']) ?></span>
                  <span><i class="fas fa-calendar"></i><?= date('M d, Y', strtotime($r['date'])) ?></span>
                  <?php if (!empty($r['org_name'])): ?>
                    <span><i class="fas fa-building"></i><?= htmlspecialchars($r['org_name']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </li>
          <?php endwhile; ?>
        </ul>
      <?php else: ?>
        <div class="empty-state"><i class="fas fa-calendar-times"></i>No upcoming events yet.<br>Browse events and apply!</div>
      <?php endif; ?>
    </div>

    <!-- My Active Tasks -->
    <div class="v-section">
      <div class="v-section-header">
        <h2 class="v-section-title"><i class="fas fa-tasks"></i> My Tasks</h2>
        <a href="/VolunteerHub/volunteer/my_tasks.php" class="v-section-link">View all →</a>
      </div>
      <?php if ($tasksRows->num_rows > 0): ?>
        <ul class="v-event-list">
          <?php while ($r = $tasksRows->fetch_assoc()):
            $prog = $r['progress'];
            $pillClass = match($prog) {
              'Completed'   => 'prog-completed',
              'In Progress' => 'prog-in-progress',
              default       => 'prog-not-started',
            };
          ?>
            <li class="v-event-item">
              <div class="v-event-dot"><i class="fas fa-clipboard-list"></i></div>
              <div style="flex:1;">
                <div class="v-event-name"><?= htmlspecialchars($r['description']) ?></div>
                <div class="v-event-meta">
                  <span><i class="fas fa-calendar"></i><?= date('M d, Y', strtotime($r['date'])) ?></span>
                  <span><i class="fas fa-flag"></i><?= htmlspecialchars($r['event_title']) ?></span>
                </div>
              </div>
              <span class="prog-pill <?= $pillClass ?>"><?= htmlspecialchars($prog) ?></span>
            </li>
          <?php endwhile; ?>
        </ul>
      <?php else: ?>
        <div class="empty-state"><i class="fas fa-clipboard"></i>No tasks assigned yet.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Row 2: Recent Notifications + Leaderboard -->
  <div class="v-two-col">

    <!-- Recent Notifications -->
    <div class="v-section">
      <div class="v-section-header">
        <h2 class="v-section-title"><i class="fas fa-bell"></i> Recent Notifications</h2>
        <a href="/VolunteerHub/volunteer/notifications.php" class="v-section-link">See all →</a>
      </div>
      <?php if ($notifsRows->num_rows > 0): ?>
        <?php while ($r = $notifsRows->fetch_assoc()):
          $status = strtolower($r['status']);
          [$iconClass, $icon] = match($status) {
            'approved'  => ['ni-green', 'fa-check-circle'],
            'rejected'  => ['ni-red',   'fa-times-circle'],
            'completed' => ['ni-blue',  'fa-award'],
            default     => ['ni-amber', 'fa-clock'],
          };
        ?>
          <div class="notif-item">
            <div class="notif-icon <?= $iconClass ?>"><i class="fas <?= $icon ?>"></i></div>
            <div>
              <div class="notif-msg">
                Your application for <strong><?= htmlspecialchars($r['title']) ?></strong>
                was <strong><?= htmlspecialchars(ucfirst($r['status'])) ?></strong>.
              </div>
              <div class="notif-time"><?= date('M d, Y · g:i A', strtotime($r['ts'])) ?></div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty-state"><i class="fas fa-bell-slash"></i>No recent notifications.</div>
      <?php endif; ?>
    </div>

    <!-- Leaderboard -->
    <div class="v-section">
      <div class="v-section-header">
        <h2 class="v-section-title"><i class="fas fa-trophy"></i> Top Volunteers</h2>
      </div>
      <?php if ($leaderRows && $leaderRows->num_rows > 0):
        $rank = 1;
        while ($r = $leaderRows->fetch_assoc()):
          $rankClass = match($rank) { 1 => 'gold', 2 => 'silver', 3 => 'bronze', default => '' };
          $isMe = ($r['id'] == $uid);
      ?>
        <div class="lb-item">
          <div class="lb-rank <?= $rankClass ?>"><?= $rank ?></div>
          <div class="lb-name">
            <?= htmlspecialchars($r['name']) ?>
            <?php if ($isMe): ?><span class="lb-mine">You</span><?php endif; ?>
          </div>
          <div class="lb-count"><?= $r['cnt'] ?> tasks</div>
        </div>
      <?php $rank++; endwhile; ?>
      <?php else: ?>
        <div class="empty-state"><i class="fas fa-medal"></i>No completed tasks yet.</div>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php include '../includes/footer.php'; ?>