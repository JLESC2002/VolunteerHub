<?php
include '../conn.php';
include './check_session.php';

// Set the CSS file before including header
$pageCSS = "/VolunteerHub/styles/dashboard.css"; 
include '../includes/header_admin.php';

// Leaderboard Query
$leaderboardQuery = "
    SELECT users.id, users.name, COUNT(tasks.id) AS completed_tasks
    FROM users
    JOIN task_assignments ON users.id = task_assignments.volunteer_id
    JOIN tasks ON task_assignments.task_id = tasks.id
    WHERE tasks.status = 'Completed'
    GROUP BY users.id
    ORDER BY completed_tasks DESC
    LIMIT 10";
$leaderboardResult = $conn->query($leaderboardQuery);

// Daily Reports
$dailyReportQuery = "
    SELECT 
        events.id, 
        events.title, 
        (
            SELECT COUNT(*) 
            FROM volunteer_applications 
            WHERE event_id = events.id AND status = 'approved'
        ) AS approved_volunteers,
        (
            SELECT COUNT(*) 
            FROM tasks 
            WHERE event_id = events.id AND status = 'Completed'
        ) AS completed_tasks
    FROM events
    ORDER BY events.date DESC
    LIMIT 5";
$dailyReportResult = $conn->query($dailyReportQuery);

// Weekly Events
$weeklyEventsQuery = "
    SELECT id, title, date, location 
    FROM events 
    WHERE WEEK(date) = WEEK(CURDATE()) AND status = 'Open'
    ORDER BY date ASC";
$weeklyEventsResult = $conn->query($weeklyEventsQuery);

// Gender Participation Data (from volunteer dashboard)
$genderQuery = "
    SELECT vd.gender, COUNT(DISTINCT va.event_id) AS event_count
    FROM volunteer_details vd
    JOIN volunteer_applications va ON vd.user_id = va.user_id
    WHERE va.status = 'approved'
    GROUP BY vd.gender
    ORDER BY FIELD(vd.gender, 'Male', 'Female')
";
$genderData = $conn->query($genderQuery);

$genderLabels = [];
$genderCounts = [];
while ($row = $genderData->fetch_assoc()) {
    $genderLabels[] = $row['gender'];
    $genderCounts[] = $row['event_count'];
}

// ── Stat card counts ──────────────────────────────────
$totalVolunteers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'Volunteer'")->fetch_assoc()['c'] ?? 0;
$totalEvents     = $conn->query("SELECT COUNT(*) AS c FROM events WHERE created_by = {$_SESSION['user_id']}")->fetch_assoc()['c'] ?? 0;
$openEvents      = $conn->query("SELECT COUNT(*) AS c FROM events WHERE created_by = {$_SESSION['user_id']} AND status = 'Open'")->fetch_assoc()['c'] ?? 0;
$totalTasks      = $conn->query("SELECT COUNT(*) AS c FROM tasks t JOIN events e ON e.id = t.event_id WHERE e.created_by = {$_SESSION['user_id']}")->fetch_assoc()['c'] ?? 0;
$pendingApps     = $conn->query("SELECT COUNT(*) AS c FROM volunteer_applications va JOIN events e ON e.id = va.event_id WHERE e.created_by = {$_SESSION['user_id']} AND va.status = 'pending'")->fetch_assoc()['c'] ?? 0;
?>

<div class="container-fluid px-0">

  <!-- ── Page header ─────────────────────────────────── -->
  <div class="dash-page-header mb-4">
    <div>
      <h1 class="dash-page-title">
        <i class="fas fa-tachometer-alt"></i> Dashboard
      </h1>
      <p class="dash-page-subtitle">Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>. Here's what's happening today.</p>
    </div>
    <a href="manage_events.php" class="btn dash-btn-primary">
      <i class="fas fa-plus me-2"></i>New Event
    </a>
  </div>

  <!-- ── Stat cards ──────────────────────────────────── -->
  <div class="dash-stats-grid mb-4">
    <div class="dash-stat-card">
      <div class="dash-stat-icon green"><i class="fas fa-users"></i></div>
      <div>
        <div class="dash-stat-label">Total Volunteers</div>
        <div class="dash-stat-value"><?= number_format($totalVolunteers) ?></div>
      </div>
    </div>
    <div class="dash-stat-card">
      <div class="dash-stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
      <div>
        <div class="dash-stat-label">Total Events</div>
        <div class="dash-stat-value"><?= number_format($totalEvents) ?></div>
      </div>
    </div>
    <div class="dash-stat-card">
      <div class="dash-stat-icon amber"><i class="fas fa-calendar-check"></i></div>
      <div>
        <div class="dash-stat-label">Open Events</div>
        <div class="dash-stat-value"><?= number_format($openEvents) ?></div>
      </div>
    </div>
    <div class="dash-stat-card">
      <div class="dash-stat-icon teal"><i class="fas fa-tasks"></i></div>
      <div>
        <div class="dash-stat-label">Total Tasks</div>
        <div class="dash-stat-value"><?= number_format($totalTasks) ?></div>
      </div>
    </div>
    <div class="dash-stat-card">
      <div class="dash-stat-icon red"><i class="fas fa-clock"></i></div>
      <div>
        <div class="dash-stat-label">Pending Applications</div>
        <div class="dash-stat-value"><?= number_format($pendingApps) ?></div>
      </div>
    </div>
  </div>

  <!-- ── Row: Leaderboard + Chart ────────────────────── -->
  <div class="dash-two-col mb-4">

    <!-- Leaderboard -->
    <div class="dash-card">
      <div class="dash-card-header">
        <span><i class="fas fa-trophy text-warning me-2"></i>Top Volunteers</span>
      </div>
      <div class="dash-card-body p-0">
        <table class="dash-table">
          <thead>
            <tr><th>#</th><th>Volunteer</th><th>Completed Tasks</th></tr>
          </thead>
          <tbody>
            <?php $rank = 1; while ($row = $leaderboardResult->fetch_assoc()): ?>
            <tr>
              <td>
                <?php if ($rank === 1): ?>
                  <span class="dash-rank gold">🥇</span>
                <?php elseif ($rank === 2): ?>
                  <span class="dash-rank silver">🥈</span>
                <?php elseif ($rank === 3): ?>
                  <span class="dash-rank bronze">🥉</span>
                <?php else: ?>
                  <span class="dash-rank-num"><?= $rank ?></span>
                <?php endif; ?>
              </td>
              <td>
                <div class="dash-volunteer-name"><?= htmlspecialchars($row['name']) ?></div>
              </td>
              <td>
                <span class="dash-task-badge"><?= $row['completed_tasks'] ?></span>
              </td>
            </tr>
            <?php $rank++; endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Gender Chart -->
    <div class="dash-card">
      <div class="dash-card-header">
        <span><i class="fas fa-venus-mars text-info me-2"></i>Gender Participation</span>
      </div>
      <div class="dash-card-body d-flex align-items-center justify-content-center" style="min-height:260px;">
        <div style="max-width:280px;width:100%;">
          <canvas id="genderChart"></canvas>
        </div>
      </div>
    </div>

  </div>

  <!-- ── Row: Event Reports + Weekly Events ──────────── -->
  <div class="dash-two-col mb-4">

    <!-- Daily Reports -->
    <div class="dash-card">
      <div class="dash-card-header">
        <span><i class="fas fa-chart-bar text-secondary me-2"></i>Recent Event Participation</span>
        <a href="manage_events.php" class="dash-card-link">View all</a>
      </div>
      <div class="dash-card-body p-0">
        <table class="dash-table">
          <thead>
            <tr><th>Event</th><th>Volunteers</th><th>Tasks Done</th></tr>
          </thead>
          <tbody>
            <?php while ($row = $dailyReportResult->fetch_assoc()): ?>
            <tr>
              <td class="dash-event-name"><?= htmlspecialchars($row['title']) ?></td>
              <td><span class="dash-pill blue"><?= $row['approved_volunteers'] ?></span></td>
              <td><span class="dash-pill green"><?= $row['completed_tasks'] ?></span></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Weekly Events -->
    <div class="dash-card">
      <div class="dash-card-header">
        <span><i class="fas fa-calendar-week text-danger me-2"></i>This Week's Events</span>
        <a href="manage_events.php" class="dash-card-link">Manage</a>
      </div>
      <div class="dash-card-body p-0">
        <?php
        // re-run since result was already iterated
        $weeklyEventsResult2 = $conn->query("SELECT id, title, date, location FROM events WHERE created_by = {$_SESSION['user_id']} AND WEEK(date) = WEEK(CURDATE()) AND status = 'Open' ORDER BY date ASC");
        if ($weeklyEventsResult2 && $weeklyEventsResult2->num_rows > 0):
          while ($row = $weeklyEventsResult2->fetch_assoc()): ?>
          <div class="dash-event-item">
            <div class="dash-event-dot"></div>
            <div class="dash-event-info">
              <div class="dash-event-title"><?= htmlspecialchars($row['title']) ?></div>
              <div class="dash-event-meta">
                <i class="fas fa-calendar-day me-1"></i><?= htmlspecialchars($row['date']) ?>
                &nbsp;·&nbsp;
                <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($row['location']) ?>
              </div>
            </div>
          </div>
        <?php endwhile; else: ?>
          <div class="dash-empty">
            <i class="fas fa-calendar-times"></i>
            <p>No events this week</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

</div>

<!-- Chart script (keep existing chart.min.js reference) -->
<script src="../vendor/chart.min.js"></script>
<script>
const genderLabels = <?= json_encode($genderLabels) ?>;
const genderCounts = <?= json_encode($genderCounts) ?>;
const ctx = document.getElementById('genderChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: genderLabels,
        datasets: [{
            data: genderCounts,
            backgroundColor: ['#3b82f6','#ec4899','#f59e0b','#10b981'],
            borderColor: '#fff',
            borderWidth: 3,
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: {
            legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } },
            title: { display: false }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>