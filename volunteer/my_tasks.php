<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "My Tasks";
$pageCSS   = "/VolunteerHub/styles/volunteer_layout.css";
include '../includes/header_volunteer.php';

$volunteer_id = $_SESSION['volunteer_id'] ?? $_SESSION['user_id'] ?? null;

// Handle progress update (preserve existing backend logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'], $_POST['progress'])) {
    $task_id  = intval($_POST['task_id']);
    $progress = $_POST['progress'];
    $allowed  = ['Not Started', 'In Progress', 'Completed'];
    if (in_array($progress, $allowed)) {
        $upd = $conn->prepare("UPDATE task_assignments SET progress = ? WHERE task_id = ? AND volunteer_id = ?");
        $upd->bind_param("sii", $progress, $task_id, $volunteer_id);
        $upd->execute();
    }
    header("Location: my_tasks.php");
    exit;
}

// Fetch tasks
$query = $conn->prepare("
    SELECT e.id AS event_id, e.title AS event_title, e.date, e.location,
           t.id AS task_id, t.description,
           COALESCE(ta.progress, 'Not Started') AS progress,
           COALESCE(ea.attended, 0) AS attended,
           ea.check_in, ea.check_out
    FROM task_assignments ta
    JOIN tasks t ON ta.task_id = t.id
    JOIN events e ON e.id = t.event_id
    LEFT JOIN event_attendance ea ON ea.event_id = e.id AND ea.volunteer_id = ta.volunteer_id
    WHERE ta.volunteer_id = ?
    ORDER BY e.date DESC
");
$query->bind_param("i", $volunteer_id);
$query->execute();
$result = $query->get_result();

// Count by status for summary
$counts = ['Not Started' => 0, 'In Progress' => 0, 'Completed' => 0];
$allRows = [];
while ($r = $result->fetch_assoc()) {
    $counts[$r['progress']] = ($counts[$r['progress']] ?? 0) + 1;
    $allRows[] = $r;
}
?>

<style>
.tasks-page { padding: 28px 28px 60px; }

/* Header */
.tasks-header { margin-bottom: 22px; }
.tasks-title { font-size: 1.45rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 10px; margin: 0 0 4px; }
.tasks-title i { color: var(--green-mid); }
.tasks-subtitle { font-size: .875rem; color: var(--text-muted); margin: 0; }

/* Summary pills */
.task-summary { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
.task-summary-pill {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 8px 16px; border-radius: var(--radius-md); font-size: .83rem; font-weight: 600;
}
.tsp-todo     { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.tsp-progress { background: #fef9c3; color: #92400e; border: 1px solid #fde68a; }
.tsp-done     { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.task-summary-pill .pill-count { font-size: 1.1rem; font-weight: 800; }

/* Table wrapper */
.tasks-table-wrap {
  background: #fff; border: 1px solid var(--border);
  border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
  overflow: hidden; overflow-x: auto;
}

/* Table */
.tasks-table { width: 100%; border-collapse: collapse; font-size: .875rem; min-width: 700px; }
.tasks-table thead tr {
  background: linear-gradient(135deg, var(--green-dark, #1a5c3a), var(--green-mid, #2d8653));
  color: #fff;
}
.tasks-table thead th {
  padding: 14px 18px; font-weight: 600; font-size: .8rem;
  text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; border: none;
}
.tasks-table tbody tr { border-bottom: 1px solid var(--border-light); transition: background var(--transition); }
.tasks-table tbody tr:last-child { border-bottom: none; }
.tasks-table tbody tr:nth-child(even) { background: #fafcff; }
.tasks-table tbody tr:hover { background: var(--green-soft, #e8f5ee); }
.tasks-table td { padding: 14px 18px; vertical-align: middle; color: var(--text-primary); }

/* Event title cell */
.task-event-name { font-weight: 600; margin-bottom: 3px; }
.task-event-meta { font-size: .75rem; color: var(--text-muted); }
.task-event-meta i { margin-right: 3px; }

/* Status pills */
.status-pill {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 11px; border-radius: 99px; font-size: .75rem; font-weight: 600; white-space: nowrap;
}
.sp-todo     { background: #f1f5f9; color: #475569; }
.sp-progress { background: #fef9c3; color: #92400e; }
.sp-done     { background: #dcfce7; color: #15803d; }

/* Attendance badge */
.attend-badge {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: .75rem; font-weight: 600; padding: 4px 10px;
  border-radius: 99px; white-space: nowrap;
}
.ab-none     { background: #f1f5f9; color: #64748b; }
.ab-in       { background: #dbeafe; color: #1d4ed8; }
.ab-out      { background: #dcfce7; color: #15803d; }

/* Progress dropdown */
.progress-select {
  padding: 6px 10px; border: 1px solid var(--border); border-radius: var(--radius-sm);
  font-size: .8rem; background: #fff; color: var(--text-primary); cursor: pointer;
  outline: none; transition: border-color .2s;
}
.progress-select:focus { border-color: var(--green-mid); }

/* Save button */
.btn-save-progress {
  padding: 6px 12px; border: none; border-radius: var(--radius-sm);
  background: var(--green-mid); color: #fff; font-size: .78rem; font-weight: 600;
  cursor: pointer; transition: opacity .2s;
}
.btn-save-progress:hover { opacity: .85; }

/* Empty */
.tasks-empty { padding: 60px; text-align: center; color: var(--text-muted); }
.tasks-empty i { font-size: 3rem; opacity: .3; display: block; margin-bottom: 14px; }
</style>

<div class="tasks-page">

  <div class="tasks-header">
    <h1 class="tasks-title"><i class="fas fa-tasks"></i> My Tasks</h1>
    <p class="tasks-subtitle">Track and update your assigned volunteer tasks.</p>
  </div>

  <!-- Summary Pills -->
  <div class="task-summary">
    <div class="task-summary-pill tsp-todo">
      <i class="fas fa-circle-dot"></i>
      <span class="pill-count"><?= $counts['Not Started'] ?></span> Not Started
    </div>
    <div class="task-summary-pill tsp-progress">
      <i class="fas fa-spinner"></i>
      <span class="pill-count"><?= $counts['In Progress'] ?></span> In Progress
    </div>
    <div class="task-summary-pill tsp-done">
      <i class="fas fa-check-circle"></i>
      <span class="pill-count"><?= $counts['Completed'] ?></span> Completed
    </div>
  </div>

  <!-- Table -->
  <div class="tasks-table-wrap">
    <?php if (!empty($allRows)): ?>
      <table class="tasks-table">
        <thead>
          <tr>
            <th>Event</th>
            <th>Task Description</th>
            <th>Date</th>
            <th>Location</th>
            <th>Attendance</th>
            <th>Status</th>
            <th>Update Progress</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allRows as $row):
            $prog = $row['progress'];
            $pillClass = match($prog) {
              'Completed'   => 'sp-done',
              'In Progress' => 'sp-progress',
              default       => 'sp-todo',
            };
            $pillIcon = match($prog) {
              'Completed'   => 'fa-check-circle',
              'In Progress' => 'fa-spinner',
              default       => 'fa-circle',
            };
            $att = (int)$row['attended'];
            [$attClass, $attLabel, $attIcon] = match(true) {
              ($att == 2 && !empty($row['check_out'])) => ['ab-out', 'Checked Out', 'fa-sign-out-alt'],
              ($att >= 1 && !empty($row['check_in']))  => ['ab-in',  'Checked In',  'fa-sign-in-alt'],
              default                                   => ['ab-none','Not Checked In','fa-clock'],
            };
          ?>
            <tr>
              <td>
                <div class="task-event-name"><?= htmlspecialchars($row['event_title']) ?></div>
              </td>
              <td><?= htmlspecialchars($row['description']) ?></td>
              <td>
                <span style="font-size:.8rem; background:#eff6ff; color:#1e40af; padding:3px 9px; border-radius:99px; font-weight:600;">
                  <i class="fas fa-calendar me-1"></i><?= date('M d, Y', strtotime($row['date'])) ?>
                </span>
              </td>
              <td>
                <span style="font-size:.82rem;">
                  <i class="fas fa-map-marker-alt me-1" style="color:#dc2626;"></i>
                  <?= htmlspecialchars($row['location']) ?>
                </span>
              </td>
              <td>
                <span class="attend-badge <?= $attClass ?>">
                  <i class="fas <?= $attIcon ?>"></i> <?= $attLabel ?>
                </span>
                <?php if (!empty($row['check_in'])): ?>
                  <div style="font-size:.7rem; color:var(--text-muted); margin-top:3px;">
                    In: <?= date('g:i A', strtotime($row['check_in'])) ?>
                    <?php if (!empty($row['check_out'])): ?>
                      · Out: <?= date('g:i A', strtotime($row['check_out'])) ?>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <span class="status-pill <?= $pillClass ?>">
                  <i class="fas <?= $pillIcon ?>"></i> <?= htmlspecialchars($prog) ?>
                </span>
              </td>
              <td>
                <form method="POST" action="" style="display:flex; gap:6px; align-items:center;">
                  <input type="hidden" name="task_id" value="<?= (int)$row['task_id'] ?>">
                  <select name="progress" class="progress-select">
                    <option value="Not Started" <?= $prog === 'Not Started' ? 'selected' : '' ?>>Not Started</option>
                    <option value="In Progress" <?= $prog === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="Completed"   <?= $prog === 'Completed'   ? 'selected' : '' ?>>Completed</option>
                  </select>
                  <button type="submit" class="btn-save-progress">
                    <i class="fas fa-save"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="tasks-empty">
        <i class="fas fa-clipboard-list"></i>
        <p>No tasks have been assigned to you yet.</p>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php include '../includes/footer.php'; ?>