<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "Reports";
include '../includes/header_admin.php';

$admin_id = $_SESSION['user_id'] ?? 0;

// ── Get admin's organization ──────────────────────────────────────────────────
$orgStmt = $conn->prepare("SELECT id, name FROM organizations WHERE admin_id = ?");
$orgStmt->bind_param("i", $admin_id);
$orgStmt->execute();
$orgData = $orgStmt->get_result()->fetch_assoc();
$org_id  = $orgData['id'] ?? 0;

// ── Summary stat cards ────────────────────────────────────────────────────────
$totalEvents = $conn->prepare("SELECT COUNT(*) AS c FROM events WHERE created_by = ?");
$totalEvents->bind_param("i", $admin_id);
$totalEvents->execute();
$totalEvents = $totalEvents->get_result()->fetch_assoc()['c'] ?? 0;

$totalApplicants = $conn->prepare("
    SELECT COUNT(*) AS c FROM volunteer_applications va
    JOIN events e ON e.id = va.event_id
    WHERE e.created_by = ?
");
$totalApplicants->bind_param("i", $admin_id);
$totalApplicants->execute();
$totalApplicants = $totalApplicants->get_result()->fetch_assoc()['c'] ?? 0;

$totalApproved = $conn->prepare("
    SELECT COUNT(*) AS c FROM volunteer_applications va
    JOIN events e ON e.id = va.event_id
    WHERE e.created_by = ? AND va.status = 'approved'
");
$totalApproved->bind_param("i", $admin_id);
$totalApproved->execute();
$totalApproved = $totalApproved->get_result()->fetch_assoc()['c'] ?? 0;

$totalCheckins = $conn->prepare("
    SELECT COUNT(*) AS c FROM event_attendance ea
    JOIN events e ON e.id = ea.event_id
    WHERE e.created_by = ? AND ea.attended >= 1
");
$totalCheckins->bind_param("i", $admin_id);
$totalCheckins->execute();
$totalCheckins = $totalCheckins->get_result()->fetch_assoc()['c'] ?? 0;

$totalCheckouts = $conn->prepare("
    SELECT COUNT(*) AS c FROM event_attendance ea
    JOIN events e ON e.id = ea.event_id
    WHERE e.created_by = ? AND ea.attended = 2
");
$totalCheckouts->bind_param("i", $admin_id);
$totalCheckouts->execute();
$totalCheckouts = $totalCheckouts->get_result()->fetch_assoc()['c'] ?? 0;

$totalTasks = $conn->prepare("
    SELECT COUNT(*) AS c FROM tasks t
    JOIN events e ON e.id = t.event_id
    WHERE e.created_by = ?
");
$totalTasks->bind_param("i", $admin_id);
$totalTasks->execute();
$totalTasks = $totalTasks->get_result()->fetch_assoc()['c'] ?? 0;

$completedTasks = $conn->prepare("
    SELECT COUNT(*) AS c FROM tasks t
    JOIN events e ON e.id = t.event_id
    WHERE e.created_by = ? AND t.status = 'Completed'
");
$completedTasks->bind_param("i", $admin_id);
$completedTasks->execute();
$completedTasks = $completedTasks->get_result()->fetch_assoc()['c'] ?? 0;

// ── Donation totals ───────────────────────────────────────────────────────────
$donTotal = 0; $donCount = 0;
if ($org_id) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt
        FROM (
            SELECT amount FROM gcash_donations WHERE organization_id = ? AND status = 'Approved'
            UNION ALL
            SELECT amount FROM bank_payments    WHERE organization_id = ? AND status = 'Approved'
        ) AS combined
    ");
    $stmt->bind_param("ii", $org_id, $org_id);
    $stmt->execute();
    $donRow  = $stmt->get_result()->fetch_assoc();
    $donTotal = $donRow['total'] ?? 0;
    $donCount = $donRow['cnt']   ?? 0;
}

// ── Event-by-event breakdown ──────────────────────────────────────────────────
$eventBreakdown = $conn->prepare("
    SELECT
        e.id, e.title, e.date, e.status, e.location,
        (SELECT COUNT(*) FROM volunteer_applications WHERE event_id = e.id)                         AS total_applicants,
        (SELECT COUNT(*) FROM volunteer_applications WHERE event_id = e.id AND status = 'approved') AS approved_vols,
        (SELECT COUNT(*) FROM event_attendance WHERE event_id = e.id AND attended >= 1)             AS checkins,
        (SELECT COUNT(*) FROM event_attendance WHERE event_id = e.id AND attended  = 2)             AS checkouts,
        (SELECT COUNT(*) FROM tasks WHERE event_id = e.id)                                          AS total_tasks,
        (SELECT COUNT(*) FROM tasks WHERE event_id = e.id AND status = 'Completed')                 AS done_tasks
    FROM events e
    WHERE e.created_by = ?
    ORDER BY e.date DESC
");
$eventBreakdown->bind_param("i", $admin_id);
$eventBreakdown->execute();
$eventRows = $eventBreakdown->get_result();

// ── Top volunteers ────────────────────────────────────────────────────────────
$topVols = $conn->prepare("
    SELECT u.name,
           COUNT(DISTINCT ta.task_id) AS tasks_done,
           COUNT(DISTINCT ea.event_id) AS events_attended
    FROM task_assignments ta
    JOIN users u ON u.id = ta.volunteer_id
    JOIN tasks t ON t.id = ta.task_id
    JOIN events e ON e.id = t.event_id
    LEFT JOIN event_attendance ea ON ea.volunteer_id = ta.volunteer_id AND ea.event_id = e.id
    WHERE e.created_by = ? AND ta.progress = 'Completed'
    GROUP BY u.id
    ORDER BY tasks_done DESC
    LIMIT 8
");
$topVols->bind_param("i", $admin_id);
$topVols->execute();
$topVolsRes = $topVols->get_result();

// ── Chart data: events per month ─────────────────────────────────────────────
$monthlyData = $conn->prepare("
    SELECT DATE_FORMAT(date,'%b %Y') AS month_label,
           MONTH(date) AS mo, YEAR(date) AS yr,
           COUNT(*) AS total
    FROM events
    WHERE created_by = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY yr, mo
    ORDER BY yr, mo
");
$monthlyData->bind_param("i", $admin_id);
$monthlyData->execute();
$monthlyRes = $monthlyData->get_result();
$chartLabels = []; $chartData = [];
while ($row = $monthlyRes->fetch_assoc()) {
    $chartLabels[] = $row['month_label'];
    $chartData[]   = $row['total'];
}
?>

<style>
.reports-page { padding: 28px 28px 60px; }
.reports-header { margin-bottom: 28px; }
.reports-title {
  font-size: 1.45rem; font-weight: 700; color: var(--text-primary);
  display: flex; align-items: center; gap: 10px; margin: 0;
}
.reports-title i { color: var(--green-mid); }
.reports-subtitle { color: var(--text-muted); font-size: .875rem; margin-top: 4px; }

/* ── Stat cards grid ── */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 32px;
}
.stat-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 20px 18px;
  box-shadow: var(--shadow-sm);
  display: flex; align-items: flex-start; gap: 14px;
  transition: box-shadow var(--transition);
}
.stat-card:hover { box-shadow: var(--shadow-md); }
.stat-icon {
  width: 44px; height: 44px; border-radius: var(--radius-sm);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; flex-shrink: 0;
}
.si-green  { background: #dcfce7; color: #16a34a; }
.si-blue   { background: #dbeafe; color: #2563eb; }
.si-amber  { background: #fef9c3; color: #ca8a04; }
.si-purple { background: #ede9fe; color: #7c3aed; }
.si-teal   { background: #ccfbf1; color: #0f766e; }
.stat-body {}
.stat-value { font-size: 1.75rem; font-weight: 700; line-height: 1.1; color: var(--text-primary); }
.stat-label { font-size: .75rem; color: var(--text-muted); font-weight: 500; margin-top: 2px; }

/* ── Section wrapper ── */
.report-section {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
  margin-bottom: 26px;
  overflow: hidden;
}
.report-section-header {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 20px;
  border-bottom: 1px solid var(--border-light);
  background: #fafbfc;
}
.report-section-header h5 {
  font-size: .95rem; font-weight: 700; color: var(--text-primary); margin: 0;
  display: flex; align-items: center; gap: 8px;
}
.report-section-header h5 i { color: var(--green-mid); }

/* ── Table ── */
.rep-table-wrap { overflow-x: auto; }
.rep-table {
  width: 100%; border-collapse: collapse; font-size: .855rem;
}
.rep-table thead th {
  padding: 10px 14px;
  font-weight: 700; font-size: .75rem;
  text-transform: uppercase; letter-spacing: .04em;
  color: #64748b; background: #f8fafc;
  border-bottom: 2px solid var(--border);
  white-space: nowrap;
}
.rep-table tbody td {
  padding: 11px 14px;
  border-bottom: 1px solid var(--border-light);
  vertical-align: middle;
}
.rep-table tbody tr:last-child td { border-bottom: none; }
.rep-table tbody tr:hover { background: #f8fafc; }

/* Progress bar */
.prog-bar-wrap { background: #e2e8f0; border-radius: 99px; height: 7px; min-width: 80px; }
.prog-bar-fill { height: 7px; border-radius: 99px; background: var(--green-mid); }

/* Status pills */
.pill { display:inline-flex;align-items:center;padding:2px 9px;border-radius:99px;font-size:.7rem;font-weight:700;white-space:nowrap; }
.pill-open      { background:#dcfce7;color:#166534; }
.pill-completed { background:#dbeafe;color:#1d4ed8; }
.pill-cancelled { background:#fee2e2;color:#991b1b; }
.pill-ongoing   { background:#fef9c3;color:#92400e; }

/* Chart container */
.chart-wrap { padding: 20px; }

/* Top vols */
.topvol-row {
  display: flex; align-items: center; gap: 14px;
  padding: 12px 20px; border-bottom: 1px solid var(--border-light);
}
.topvol-row:last-child { border-bottom: none; }
.topvol-rank {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .75rem; font-weight: 700; flex-shrink: 0;
  background: var(--green-soft); color: var(--green-dark);
}
.topvol-rank.gold   { background: #fef9c3; color: #92400e; }
.topvol-rank.silver { background: #f1f5f9; color: #475569; }
.topvol-rank.bronze { background: #fef3c7; color: #78350f; }
.topvol-name { flex: 1; font-weight: 600; font-size: .875rem; color: var(--text-primary); }
.topvol-stat { font-size: .78rem; color: var(--text-muted); }
.topvol-badge {
  font-size: .72rem; font-weight: 700; padding: 3px 9px; border-radius: 99px;
  background: var(--green-soft); color: var(--green-dark);
}
</style>

<div class="reports-page">
  <div class="reports-header">
    <h1 class="reports-title"><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
    <p class="reports-subtitle">Overview of your events, volunteer participation, and donation data.</p>
  </div>

  <!-- ── Stat Cards ──────────────────────────────────────────────────────── -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="fas fa-calendar-alt"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= $totalEvents ?></div>
        <div class="stat-label">Total Events</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="fas fa-users"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= $totalApplicants ?></div>
        <div class="stat-label">Total Applicants</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="fas fa-user-check"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= $totalApproved ?></div>
        <div class="stat-label">Approved Volunteers</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-teal"><i class="fas fa-sign-in-alt"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= $totalCheckins ?></div>
        <div class="stat-label">Total Check-ins</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="fas fa-sign-out-alt"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= $totalCheckouts ?></div>
        <div class="stat-label">Total Check-outs</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-amber"><i class="fas fa-tasks"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= $completedTasks ?>/<?= $totalTasks ?></div>
        <div class="stat-label">Tasks Completed</div>
      </div>
    </div>
    <?php if ($org_id): ?>
    <div class="stat-card">
      <div class="stat-icon si-purple"><i class="fas fa-hand-holding-heart"></i></div>
      <div class="stat-body">
        <div class="stat-value">₱<?= number_format($donTotal, 0) ?></div>
        <div class="stat-label">Approved Donations (<?= $donCount ?>)</div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Events Chart ────────────────────────────────────────────────────── -->
  <?php if (!empty($chartLabels)): ?>
  <div class="report-section" style="margin-bottom:26px;">
    <div class="report-section-header">
      <h5><i class="fas fa-chart-line"></i> Events Created — Last 6 Months</h5>
    </div>
    <div class="chart-wrap">
      <canvas id="eventsChart" height="100"></canvas>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Event Breakdown Table ───────────────────────────────────────────── -->
  <div class="report-section">
    <div class="report-section-header">
      <h5><i class="fas fa-table"></i> Event Participation Overview</h5>
    </div>
    <div class="rep-table-wrap">
      <table class="rep-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Event</th>
            <th>Date</th>
            <th>Status</th>
            <th>Applicants</th>
            <th>Approved</th>
            <th>Check-ins</th>
            <th>Check-outs</th>
            <th>Tasks</th>
            <th>Task Progress</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($eventRows->num_rows === 0): ?>
            <tr><td colspan="10" style="text-align:center;padding:28px;color:#94a3b8;">No events found.</td></tr>
          <?php else: $idx = 1; while ($r = $eventRows->fetch_assoc()):
            $statusClass = match(strtolower($r['status'])) {
              'open'      => 'pill-open',
              'completed' => 'pill-completed',
              'cancelled' => 'pill-cancelled',
              default     => 'pill-ongoing',
            };
            $taskPct = $r['total_tasks'] > 0 ? round($r['done_tasks'] / $r['total_tasks'] * 100) : 0;
          ?>
            <tr>
              <td style="color:var(--text-muted);font-size:.8rem;"><?= $idx++ ?></td>
              <td style="font-weight:600;max-width:200px;"><?= htmlspecialchars($r['title']) ?></td>
              <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($r['date'])) ?></td>
              <td><span class="pill <?= $statusClass ?>"><?= htmlspecialchars($r['status']) ?></span></td>
              <td style="text-align:center;"><?= $r['total_applicants'] ?></td>
              <td style="text-align:center;"><?= $r['approved_vols'] ?></td>
              <td style="text-align:center;"><?= $r['checkins'] ?></td>
              <td style="text-align:center;"><?= $r['checkouts'] ?></td>
              <td style="text-align:center;"><?= $r['total_tasks'] ?></td>
              <td style="min-width:120px;">
                <div style="display:flex;align-items:center;gap:8px;">
                  <div class="prog-bar-wrap" style="flex:1;">
                    <div class="prog-bar-fill" style="width:<?= $taskPct ?>%;"></div>
                  </div>
                  <span style="font-size:.75rem;color:var(--text-muted);white-space:nowrap;"><?= $taskPct ?>%</span>
                </div>
              </td>
            </tr>
          <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Top Volunteers ─────────────────────────────────────────────────── -->
  <div class="report-section">
    <div class="report-section-header">
      <h5><i class="fas fa-trophy"></i> Top Volunteers by Completed Tasks</h5>
    </div>
    <?php if ($topVolsRes->num_rows === 0): ?>
      <div style="padding:28px;text-align:center;color:var(--text-muted);font-size:.875rem;">
        <i class="fas fa-users" style="font-size:1.6rem;opacity:.3;display:block;margin-bottom:8px;"></i>
        No completed task data yet.
      </div>
    <?php else: $rank = 1; while ($r = $topVolsRes->fetch_assoc()):
      $rankClass = match($rank) { 1 => 'gold', 2 => 'silver', 3 => 'bronze', default => '' };
    ?>
      <div class="topvol-row">
        <div class="topvol-rank <?= $rankClass ?>"><?= $rank++ ?></div>
        <div class="topvol-name"><?= htmlspecialchars($r['name']) ?></div>
        <div class="topvol-stat"><?= $r['events_attended'] ?> event(s)</div>
        <div class="topvol-badge"><i class="fas fa-check-circle me-1"></i><?= $r['tasks_done'] ?> tasks</div>
      </div>
    <?php endwhile; endif; ?>
  </div>

</div>

<!-- Chart.js (via CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<?php if (!empty($chartLabels)): ?>
<script>
const ctx = document.getElementById('eventsChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'Events Created',
      data: <?= json_encode($chartData) ?>,
      backgroundColor: 'rgba(45,134,83,0.18)',
      borderColor: '#2d8653',
      borderWidth: 2,
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.y} event(s)` } }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { stepSize: 1, color: '#94a3b8', font: { size: 11 } },
        grid: { color: '#f1f5f9' }
      },
      x: {
        ticks: { color: '#64748b', font: { size: 11 } },
        grid: { display: false }
      }
    }
  }
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>