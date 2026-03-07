<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "Manage Tasks";
include '../includes/header_admin.php';

$admin_id = $_SESSION['user_id'];

/* ── Fetch all events for selector ── */
$events_stmt = $conn->prepare("SELECT id, title, date, status FROM events WHERE created_by = ?");
$events_stmt->bind_param("i", $admin_id);
$events_stmt->execute();
$events_result = $events_stmt->get_result();
$all_events = [];
while ($ev = $events_result->fetch_assoc()) $all_events[] = $ev;

$event_data        = null;
$selected_event_id = $_GET['event_id'] ?? null;

/* ── Fetch selected event (ownership guard) ── */
if ($selected_event_id) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $selected_event_id, $admin_id);
    $stmt->execute();
    $event_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ── Create task ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $event_id    = intval($_POST['event_id']);
    $description = trim($_POST['task_description']);
    $check = $conn->prepare("SELECT id FROM events WHERE id = ? AND created_by = ?");
    $check->bind_param("ii", $event_id, $admin_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO tasks (event_id, description, assigned_by) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $event_id, $description, $admin_id);
        $stmt->execute();
        $stmt->close();
    }
    $check->close();
}

/* ── Edit task ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {
    $task_id     = intval($_POST['task_id']);
    $description = trim($_POST['task_description']);
    $guard = $conn->prepare("SELECT t.id FROM tasks t JOIN events e ON e.id = t.event_id WHERE t.id = ? AND e.created_by = ?");
    $guard->bind_param("ii", $task_id, $admin_id);
    $guard->execute();
    if ($guard->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE tasks SET description = ? WHERE id = ?");
        $stmt->bind_param("si", $description, $task_id);
        $stmt->execute();
        $stmt->close();
    }
    $guard->close();
}

/* ── Delete task ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
    $task_id = intval($_POST['task_id']);
    $guard = $conn->prepare("SELECT t.id FROM tasks t JOIN events e ON e.id = t.event_id WHERE t.id = ? AND e.created_by = ?");
    $guard->bind_param("ii", $task_id, $admin_id);
    $guard->execute();
    if ($guard->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $stmt->close();
    }
    $guard->close();
}

/* ── Fetch tasks ── */
$tasks = [];
if ($selected_event_id && $event_data) {
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE event_id = ?");
    $stmt->bind_param("i", $selected_event_id);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $tasks[] = $row;
    $stmt->close();
}

/* ── Assign volunteer ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_volunteer'])) {
    $volunteer_id = intval($_POST['volunteer_id']);
    $task_id      = intval($_POST['task_id']);
    $assigned_by  = $_SESSION['user_id'];

    $check = $conn->prepare("SELECT id FROM task_assignments WHERE task_id = ? AND volunteer_id = ?");
    $check->bind_param("ii", $task_id, $volunteer_id);
    $check->execute();

    if ($check->get_result()->num_rows === 0) {
        $insert = $conn->prepare("INSERT INTO task_assignments (task_id, volunteer_id, assigned_by, assigned_at, progress) VALUES (?, ?, ?, NOW(), 'Not Started')");
        $insert->bind_param("iii", $task_id, $volunteer_id, $assigned_by);
        if ($insert->execute()) {
            $updateTask = $conn->prepare("UPDATE tasks SET volunteer_id = ?, assigned_by = ? WHERE id = ?");
            $updateTask->bind_param("iii", $volunteer_id, $assigned_by, $task_id);
            $updateTask->execute();
            $updateTask->close();

            $updateStatus = $conn->prepare("UPDATE volunteer_applications va JOIN tasks t ON t.event_id = va.event_id SET va.status = 'approved' WHERE va.user_id = ? AND t.id = ? AND va.status = 'pending'");
            $updateStatus->bind_param("ii", $volunteer_id, $task_id);
            $updateStatus->execute();
            $updateStatus->close();

            echo "<script>alert('Volunteer assigned and approved!'); window.location.href='manage_tasks.php?event_id=$selected_event_id';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Volunteer is already assigned to this task.');</script>";
    }
}

/* ── Fetch assignments for selected event ── */
$assignments = [];
if ($selected_event_id && $event_data) {
    $q = $conn->prepare("
        SELECT u.id AS uid, u.name AS full_name, t.id AS tid, t.description,
               COALESCE(ea.attended, 0) AS attended,
               COALESCE(ta.progress, 'Not Started') AS progress
        FROM task_assignments ta
        JOIN users u ON u.id = ta.volunteer_id
        JOIN tasks t ON t.id = ta.task_id
        LEFT JOIN event_attendance ea ON ea.event_id = t.event_id AND ea.volunteer_id = u.id
        WHERE t.event_id = ?
        ORDER BY t.id, u.name
    ");
    $q->bind_param("i", $selected_event_id);
    $q->execute();
    $qr = $q->get_result();
    while ($row = $qr->fetch_assoc()) $assignments[] = $row;
    $q->close();
}

/* ── Volunteers for assign dropdown ── */
$volunteers_for_event = [];
if ($selected_event_id) {
    $vs = $conn->prepare("SELECT DISTINCT u.id, u.name FROM volunteer_applications va JOIN users u ON u.id = va.user_id WHERE va.event_id = ? ORDER BY u.name");
    $vs->bind_param("i", $selected_event_id);
    $vs->execute();
    $vr = $vs->get_result();
    while ($v = $vr->fetch_assoc()) $volunteers_for_event[] = $v;
    $vs->close();
}
?>

<style>
/* ═══════════════════════════════════════════════════════
   Manage Tasks — Page Styles
   ═══════════════════════════════════════════════════════ */

.mt-page { padding: 24px 28px 56px; }

/* ── Page header ──────────────────────────────────────── */
.mt-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 22px;
  flex-wrap: wrap;
  gap: 10px;
}
.mt-page-title {
  font-size: 1.35rem;
  font-weight: 700;
  color: #1a1f2e;
  display: flex;
  align-items: center;
  gap: 9px;
  margin: 0;
}
.mt-page-title i { color: #2d8653; }

/* ── Two-column layout ────────────────────────────────── */
.mt-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 20px;
  align-items: start;
}
@media (max-width: 900px) {
  .mt-layout { grid-template-columns: 1fr; }
}

/* ── Card base ────────────────────────────────────────── */
.mt-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: 0 1px 4px rgba(0,0,0,.05);
  overflow: hidden;
}
.mt-card + .mt-card { margin-top: 18px; }

.mt-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 13px 18px;
  border-bottom: 1px solid #f1f5f9;
  background: #fafbfc;
  flex-wrap: wrap;
  gap: 8px;
}
.mt-card-header h6 {
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
.mt-card-header h6 i { color: #2d8653; font-size: .78rem; }
.mt-card-body { padding: 18px; }

/* ── LEFT COLUMN: event selector ─────────────────────── */
.mt-event-list {
  display: flex;
  flex-direction: column;
  gap: 0;
  max-height: 420px;
  overflow-y: auto;
}
.mt-event-item {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 11px 16px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  text-decoration: none;
  transition: background .12s;
}
.mt-event-item:last-child { border-bottom: none; }
.mt-event-item:hover { background: #f8fafc; }
.mt-event-item.active {
  background: #f0fdf4;
  border-left: 3px solid #2d8653;
  padding-left: 13px;
}
.mt-event-dot {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  background: #e8f5ee;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: .75rem;
  font-weight: 700;
  color: #2d8653;
}
.mt-event-item.active .mt-event-dot {
  background: #2d8653;
  color: #fff;
}
.mt-event-info { flex: 1; min-width: 0; }
.mt-event-name {
  font-size: .845rem;
  font-weight: 600;
  color: #1a1f2e;
  margin: 0 0 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
}
.mt-event-date {
  font-size: .72rem;
  color: #64748b;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 4px;
}
.mt-event-date i { font-size: .65rem; color: #2d8653; }
.mt-event-status {
  font-size: .68rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 99px;
  text-transform: uppercase;
  letter-spacing: .03em;
  white-space: nowrap;
}
.es-open      { background: #dcfce7; color: #15803d; }
.es-ongoing   { background: #fef9c3; color: #a16207; }
.es-completed { background: #f1f5f9; color: #475569; }
.es-cancelled { background: #fee2e2; color: #b91c1c; }

.mt-empty-events {
  text-align: center;
  padding: 32px 16px;
  color: #94a3b8;
}
.mt-empty-events i { font-size: 1.6rem; color: #dde3ea; display: block; margin-bottom: 9px; }
.mt-empty-events p { font-size: .8rem; margin: 0; }

/* ── LEFT COLUMN: stats card ─────────────────────────── */
.mt-stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  padding: 14px 16px;
}
.mt-stat {
  background: #f8fafc;
  border: 1px solid #e9eef5;
  border-radius: 10px;
  padding: 12px 14px;
  text-align: center;
}
.mt-stat-val {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1a1f2e;
  line-height: 1;
  margin-bottom: 4px;
}
.mt-stat-val.green { color: #2d8653; }
.mt-stat-val.amber { color: #d97706; }
.mt-stat-label {
  font-size: .68rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .05em;
}

/* ── RIGHT COLUMN: tabs ───────────────────────────────── */
.mt-tabs {
  display: flex;
  border-bottom: 2px solid #f1f5f9;
  background: #fafbfc;
  border-radius: 14px 14px 0 0;
  overflow: hidden;
}
.mt-tab {
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
.mt-tab:hover { background: #f0f4f8; color: #1a1f2e; }
.mt-tab.active { color: #2d8653; border-bottom-color: #2d8653; background: #fff; }
.mt-tab i { font-size: .78rem; }

.mt-tab-panel { display: none; }
.mt-tab-panel.active { display: block; }

/* ── Table ────────────────────────────────────────────── */
.mt-table-wrap { overflow-x: auto; }
.mt-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .875rem;
}
.mt-table thead th {
  padding: 10px 14px;
  font-weight: 700;
  font-size: .72rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: #64748b;
  background: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
  white-space: nowrap;
}
.mt-table tbody tr {
  border-bottom: 1px solid #f1f5f9;
  transition: background .12s;
}
.mt-table tbody tr:last-child { border-bottom: none; }
.mt-table tbody tr:hover { background: #f8fafc; }
.mt-table td {
  padding: 11px 14px;
  vertical-align: middle;
  color: #374151;
}

/* ── Task description cell ────────────────────────────── */
.task-desc-text {
  font-size: .875rem;
  color: #1a1f2e;
  font-weight: 500;
  line-height: 1.4;
}
.task-desc-input {
  width: 100%;
  padding: 7px 10px;
  border: 1.5px solid #2d8653;
  border-radius: 8px;
  font: inherit;
  font-size: .875rem;
  color: #1a1f2e;
  background: #f0fdf4;
  outline: none;
  resize: none;
  min-height: 60px;
  display: none;
  transition: border-color .2s, box-shadow .2s;
}
.task-desc-input:focus { box-shadow: 0 0 0 3px rgba(45,134,83,.1); }

/* ── Action dropdown ──────────────────────────────────── */
.mt-action-cell { text-align: center; width: 50px; }
.mt-action-btn {
  width: 32px; height: 32px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  border-radius: 7px;
  font-size: .8rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all .15s;
}
.mt-action-btn:hover { background: #f1f5f9; color: #1a1f2e; border-color: #cbd5e0; }

.mt-dropdown { position: relative; display: inline-block; }
.mt-menu {
  display: none;
  position: fixed;
  background: #fff;
  min-width: 175px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 8px 28px rgba(0,0,0,.13);
  z-index: 9999;
  overflow: hidden;
  animation: mtMenuIn .13s ease;
}
@keyframes mtMenuIn {
  from { opacity: 0; transform: translateY(-4px); }
  to   { opacity: 1; transform: translateY(0); }
}
.mt-menu.open { display: block; }
.mt-menu button {
  display: flex;
  align-items: center;
  gap: 9px;
  width: 100%;
  padding: 9px 15px;
  border: none;
  background: transparent;
  font: inherit;
  font-size: .845rem;
  color: #374151;
  cursor: pointer;
  text-align: left;
  transition: background .1s;
}
.mt-menu button:hover { background: #f8fafc; }
.mt-menu button.danger { color: #dc2626; }
.mt-menu button.danger:hover { background: #fef2f2; }
.mt-menu-sep { height: 1px; background: #f1f5f9; margin: 3px 0; }

/* ── Volunteer avatar + name ──────────────────────────── */
.mt-vol-cell {
  display: flex;
  align-items: center;
  gap: 9px;
}
.mt-vol-avatar {
  width: 30px; height: 30px;
  border-radius: 50%;
  background: #e8f5ee;
  color: #2d8653;
  font-size: .7rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.mt-vol-name { font-weight: 600; font-size: .845rem; }

/* ── Inline selects ───────────────────────────────────── */
.mt-select {
  padding: 5px 9px;
  border: 1.5px solid #e2e8f0;
  border-radius: 7px;
  font-size: .78rem;
  font-family: inherit;
  color: #374151;
  background: #f8fafc;
  outline: none;
  appearance: none;
  cursor: pointer;
  transition: border-color .18s, box-shadow .18s;
  min-width: 120px;
}
.mt-select:focus {
  border-color: #2d8653;
  box-shadow: 0 0 0 3px rgba(45,134,83,.1);
  background: #fff;
}

/* ── Status badges ────────────────────────────────────── */
.mt-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border-radius: 99px;
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .03em;
  white-space: nowrap;
}
.mt-badge-green  { background: #dcfce7; color: #15803d; }
.mt-badge-amber  { background: #fef9c3; color: #a16207; }
.mt-badge-slate  { background: #f1f5f9; color: #475569; }

/* ── Empty state ──────────────────────────────────────── */
.mt-empty {
  text-align: center;
  padding: 40px 20px;
  color: #94a3b8;
}
.mt-empty i { font-size: 1.8rem; color: #dde3ea; display: block; margin-bottom: 10px; }
.mt-empty p { font-size: .845rem; margin: 0; }

/* ── No event selected placeholder ───────────────────── */
.mt-no-event {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 320px;
  color: #94a3b8;
  text-align: center;
  padding: 32px;
}
.mt-no-event i { font-size: 2.6rem; color: #dde3ea; margin-bottom: 14px; }
.mt-no-event p { font-size: .9rem; max-width: 220px; line-height: 1.6; margin: 0; }

/* ── Add task inline button ───────────────────────────── */
.mt-add-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  background: linear-gradient(135deg, #2d8653, #1a5c3a);
  color: #fff;
  border: none;
  border-radius: 8px;
  font: inherit;
  font-size: .78rem;
  font-weight: 700;
  cursor: pointer;
  transition: opacity .15s;
  box-shadow: 0 2px 7px rgba(45,134,83,.25);
}
.mt-add-btn:hover { opacity: .88; }

/* ── Modals ───────────────────────────────────────────── */
.mt-modal {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 1300;
}
.mt-modal.show {
  display: flex;
  align-items: center;
  justify-content: center;
}
.mt-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(6,10,17,.45);
  backdrop-filter: blur(5px);
}
.mt-modal-box {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 420px;
  margin: 0 16px;
  background: #fff;
  border-radius: 14px;
  padding: 26px 22px 20px;
  box-shadow: 0 16px 48px rgba(2,8,23,.16);
  animation: mtPop .18s ease;
}
@keyframes mtPop {
  from { opacity: 0; transform: scale(.96) translateY(6px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}
.mt-modal-close {
  position: absolute;
  right: 13px; top: 11px;
  width: 30px; height: 30px;
  border: none;
  background: #f1f5f9;
  border-radius: 7px;
  color: #64748b;
  font-size: .95rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background .15s, color .15s;
}
.mt-modal-close:hover { background: #e2e8f0; color: #1a1f2e; }
.mt-modal-title {
  font-size: .975rem;
  font-weight: 700;
  color: #1a1f2e;
  margin: 0 0 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.mt-modal-title i { color: #2d8653; }

/* Modal form fields */
.mt-modal-fg { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
.mt-modal-fg label {
  font-size: .72rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .05em;
}
.mt-modal-fg input,
.mt-modal-fg select,
.mt-modal-fg textarea {
  padding: 9px 12px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font: inherit;
  font-size: .875rem;
  color: #1a1f2e;
  background: #f8fafc;
  outline: none;
  transition: border-color .18s, box-shadow .18s;
  appearance: none;
}
.mt-modal-fg input:focus,
.mt-modal-fg select:focus,
.mt-modal-fg textarea:focus {
  border-color: #2d8653;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(45,134,83,.1);
}
.mt-modal-fg textarea { resize: vertical; min-height: 80px; }

.mt-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  padding-top: 14px;
  border-top: 1px solid #f1f5f9;
  margin-top: 4px;
}
.mt-modal-cancel {
  padding: 9px 18px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  color: #4a5568;
  font: inherit;
  font-size: .845rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .15s;
}
.mt-modal-cancel:hover { background: #f1f5f9; }
.mt-modal-submit {
  padding: 9px 20px;
  background: linear-gradient(135deg, #2d8653, #1a5c3a);
  color: #fff;
  border: none;
  border-radius: 8px;
  font: inherit;
  font-size: .845rem;
  font-weight: 700;
  cursor: pointer;
  transition: opacity .15s;
  box-shadow: 0 2px 7px rgba(45,134,83,.25);
}
.mt-modal-submit:hover { opacity: .88; }
.mt-modal-submit.danger {
  background: linear-gradient(135deg, #ef4444, #b91c1c);
  box-shadow: 0 2px 7px rgba(220,38,38,.25);
}
</style>

<div class="mt-page">

  <!-- ── Page Header ──────────────────────────────────── -->
  <div class="mt-page-header">
    <h1 class="mt-page-title">
      <i class="fas fa-tasks"></i> Manage Tasks
    </h1>
  </div>

  <!-- ── Two-column layout ────────────────────────────── -->
  <div class="mt-layout">

    <!-- ════════════════════════════════════════════════
         LEFT COLUMN
    ════════════════════════════════════════════════════ -->
    <div class="mt-left-col">

      <!-- Event list card -->
      <div class="mt-card">
        <div class="mt-card-header">
          <h6><i class="fas fa-calendar-alt"></i> Select Event</h6>
          <span style="font-size:.7rem;color:#94a3b8;font-weight:600;"><?= count($all_events) ?> events</span>
        </div>
        <div class="mt-event-list">
          <?php if (empty($all_events)): ?>
            <div class="mt-empty-events">
              <i class="fas fa-calendar-times"></i>
              <p>No events created yet.</p>
            </div>
          <?php else: ?>
            <?php foreach ($all_events as $ev): 
              $isActive = ($selected_event_id == $ev['id']);
              $initials = strtoupper(substr($ev['title'], 0, 2));
              $s = strtolower($ev['status'] ?? 'open');
              $sCls = match($s) {
                'ongoing'   => 'es-ongoing',
                'completed' => 'es-completed',
                'cancelled' => 'es-cancelled',
                default     => 'es-open',
              };
            ?>
              <a class="mt-event-item <?= $isActive ? 'active' : '' ?>"
                 href="?event_id=<?= $ev['id'] ?>">
                <div class="mt-event-dot"><?= $initials ?></div>
                <div class="mt-event-info">
                  <span class="mt-event-name"><?= htmlspecialchars($ev['title']) ?></span>
                  <p class="mt-event-date">
                    <i class="fas fa-clock"></i>
                    <?= date('M d, Y', strtotime($ev['date'])) ?>
                  </p>
                </div>
                <span class="mt-event-status <?= $sCls ?>"><?= htmlspecialchars($ev['status'] ?? 'Open') ?></span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Stats card (only if event selected) -->
      <?php if ($event_data): ?>
      <div class="mt-card">
        <div class="mt-card-header">
          <h6><i class="fas fa-chart-bar"></i> Quick Stats</h6>
        </div>
        <div class="mt-stats-grid">
          <div class="mt-stat">
            <div class="mt-stat-val green"><?= count($tasks) ?></div>
            <div class="mt-stat-label">Tasks</div>
          </div>
          <div class="mt-stat">
            <div class="mt-stat-val amber"><?= count($assignments) ?></div>
            <div class="mt-stat-label">Assigned</div>
          </div>
          <div class="mt-stat">
            <div class="mt-stat-val"><?= count(array_filter($assignments, fn($a) => $a['progress'] === 'Completed')) ?></div>
            <div class="mt-stat-label">Done</div>
          </div>
          <div class="mt-stat">
            <div class="mt-stat-val"><?= count($volunteers_for_event) ?></div>
            <div class="mt-stat-label">Volunteers</div>
          </div>
        </div>
      </div>

      <!-- Event info card -->
      <div class="mt-card">
        <div class="mt-card-header">
          <h6><i class="fas fa-info-circle"></i> Event Info</h6>
        </div>
        <div class="mt-card-body" style="padding:14px 18px;">
          <div style="display:flex;flex-direction:column;gap:9px;">
            <div style="font-size:.78rem;color:#64748b;display:flex;align-items:center;gap:7px;">
              <i class="fas fa-calendar" style="color:#2d8653;width:14px;"></i>
              <?= date('M d, Y', strtotime($event_data['date'])) ?>
            </div>
            <div style="font-size:.78rem;color:#64748b;display:flex;align-items:center;gap:7px;">
              <i class="fas fa-circle-dot" style="color:#2d8653;width:14px;font-size:.65rem;"></i>
              Status: <strong style="color:#1a1f2e;"><?= htmlspecialchars($event_data['status']) ?></strong>
            </div>
          </div>
            <?php
            // Check if QR files already exist for this event
            $event_name_safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $event_data['title']);
            $checkinPath  = "../Generator/QRcode Checkin/{$event_name_safe}_CheckIn.png";
            $checkoutPath = "../Generator/QRcode Checkout/{$event_name_safe}_CheckOut.png";
            $qr_exists    = file_exists($checkinPath) && file_exists($checkoutPath);
            ?>
            <button class="mt-add-btn" style="width:100%;margin-top:14px;justify-content:center;"
                    onclick="openQRModal(<?= intval($event_data['id']) ?>)">
              <i class="fas fa-qrcode"></i>
              <?= $qr_exists ? 'View QR Codes' : 'Generate QR Codes' ?>
            </button>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /.mt-left-col -->


    <!-- ════════════════════════════════════════════════
         RIGHT COLUMN
    ════════════════════════════════════════════════════ -->
    <div class="mt-right-col">
      <?php if ($event_data): ?>

        <div class="mt-card" style="overflow:visible;">

          <!-- Tabs -->
          <div class="mt-tabs" role="tablist">
            <button class="mt-tab active" onclick="switchTab(this,'tab-tasks')" role="tab">
              <i class="fas fa-list-check"></i> Tasks
              <?php if (!empty($tasks)): ?>
                <span style="background:#2d8653;color:#fff;font-size:.65rem;padding:1px 6px;border-radius:99px;"><?= count($tasks) ?></span>
              <?php endif; ?>
            </button>
            <button class="mt-tab" onclick="switchTab(this,'tab-assignments')" role="tab">
              <i class="fas fa-users-cog"></i> Assignments
              <?php if (!empty($assignments)): ?>
                <span style="background:#64748b;color:#fff;font-size:.65rem;padding:1px 6px;border-radius:99px;"><?= count($assignments) ?></span>
              <?php endif; ?>
            </button>
          </div>

          <!-- ── Tab: Tasks ──────────────────────────── -->
          <div id="tab-tasks" class="mt-tab-panel active">
            <div class="mt-card-header" style="border-top:none;border-radius:0;">
              <h6><i class="fas fa-clipboard-list"></i> Tasks for: <?= htmlspecialchars($event_data['title']) ?></h6>
              <button class="mt-add-btn" onclick="openModal('createTaskModal')">
                <i class="fas fa-plus"></i> Add Task
              </button>
            </div>
            <div class="mt-table-wrap">
              <table class="mt-table">
                <thead>
                  <tr>
                    <th style="width:50px;">#</th>
                    <th>Task Description</th>
                    <th style="text-align:center;width:50px;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($tasks)): ?>
                    <tr>
                      <td colspan="3">
                        <div class="mt-empty">
                          <i class="fas fa-clipboard-list"></i>
                          <p>No tasks yet. Add the first one!</p>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($tasks as $i => $task): ?>
                      <tr data-task-id="<?= $task['id'] ?>">
                        <td style="color:#94a3b8;font-size:.78rem;font-weight:700;"><?= $i + 1 ?></td>
                        <td>
                          <span class="task-desc-text"><?= htmlspecialchars($task['description']) ?></span>
                          <textarea class="task-desc-input"
                                    data-orig="<?= htmlspecialchars($task['description']) ?>"><?= htmlspecialchars($task['description']) ?></textarea>
                        </td>
                        <td class="mt-action-cell">
                          <div class="mt-dropdown">
                            <button class="mt-action-btn" onclick="toggleMenu(this)">
                              <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="mt-menu">
                              <button onclick="openAssignModal(<?= intval($selected_event_id) ?>, <?= intval($task['id']) ?>)">
                                <i class="fas fa-user-plus" style="color:#2d8653;width:16px;"></i> Assign Volunteer
                              </button>
                              <button class="editTaskBtn">
                                <i class="fas fa-pen" style="color:#f59e0b;width:16px;"></i> Edit
                              </button>
                              <form method="POST" class="save-task-form" style="margin:0;">
                                <input type="hidden" name="edit_task" value="1">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <button type="submit" class="saveTaskBtn" style="display:none;">
                                  <i class="fas fa-floppy-disk" style="color:#2d8653;width:16px;"></i> Save
                                </button>
                              </form>
                              <div class="mt-menu-sep"></div>
                              <button class="danger" onclick="openDeleteModal(<?= $task['id'] ?>, '<?= addslashes(htmlspecialchars($task['description'])) ?>')">
                                <i class="fas fa-trash" style="width:16px;"></i> Delete
                              </button>
                            </div>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div><!-- /tab-tasks -->

          <!-- ── Tab: Assignments ────────────────────── -->
          <div id="tab-assignments" class="mt-tab-panel">
            <div class="mt-table-wrap">
              <table class="mt-table">
                <thead>
                  <tr>
                    <th>Volunteer</th>
                    <th>Task</th>
                    <th>Attendance</th>
                    <th>Progress</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($assignments)): ?>
                    <tr>
                      <td colspan="4">
                        <div class="mt-empty">
                          <i class="fas fa-user-clock"></i>
                          <p>No volunteers assigned yet. Assign from the Tasks tab.</p>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($assignments as $a): ?>
                      <tr>
                        <td>
                          <div class="mt-vol-cell">
                            <div class="mt-vol-avatar"><?= strtoupper(substr($a['full_name'], 0, 2)) ?></div>
                            <span class="mt-vol-name"><?= htmlspecialchars($a['full_name']) ?></span>
                          </div>
                        </td>
                        <td style="font-size:.82rem;color:#4a5568;max-width:200px;">
                          <?= htmlspecialchars($a['description']) ?>
                        </td>
                        <td>
                          <select class="mt-select attendance-select"
                                  data-event="<?= $selected_event_id ?>"
                                  data-volunteer="<?= $a['uid'] ?>">
                            <option value="0" <?= intval($a['attended']) == 0 ? 'selected' : '' ?>>Not Checked In</option>
                            <option value="1" <?= intval($a['attended']) == 1 ? 'selected' : '' ?>>Checked In</option>
                            <option value="2" <?= intval($a['attended']) == 2 ? 'selected' : '' ?>>Checked Out</option>
                          </select>
                        </td>
                        <td>
                          <select class="mt-select progress-select"
                                  data-task="<?= $a['tid'] ?>"
                                  data-volunteer="<?= $a['uid'] ?>">
                            <option value="Not Started" <?= $a['progress'] === 'Not Started' ? 'selected' : '' ?>>Not Started</option>
                            <option value="In Progress" <?= $a['progress'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="Completed"   <?= $a['progress'] === 'Completed'   ? 'selected' : '' ?>>Completed</option>
                          </select>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div><!-- /tab-assignments -->

        </div><!-- /.mt-card -->

      <?php else: ?>
        <!-- No event selected -->
        <div class="mt-card">
          <div class="mt-no-event">
            <i class="fas fa-hand-pointer"></i>
            <p>Select an event from the list to view and manage its tasks.</p>
          </div>
        </div>
      <?php endif; ?>
    </div><!-- /.mt-right-col -->

  </div><!-- /.mt-layout -->
</div><!-- /.mt-page -->


<!-- ── Modal: Create Task ───────────────────────────────── -->
<div id="createTaskModal" class="mt-modal">
  <div class="mt-modal-backdrop" onclick="closeModal('createTaskModal')"></div>
  <div class="mt-modal-box">
    <button class="mt-modal-close" onclick="closeModal('createTaskModal')">
      <i class="fas fa-times"></i>
    </button>
    <p class="mt-modal-title"><i class="fas fa-plus-circle"></i> Add New Task</p>
    <form method="POST">
      <input type="hidden" name="create_task" value="1">
      <input type="hidden" name="event_id" value="<?= intval($selected_event_id) ?>">
      <div class="mt-modal-fg">
        <label>Task Description <span style="color:#dc2626;">*</span></label>
        <textarea name="task_description" rows="4"
                  placeholder="Describe what needs to be done…"
                  required></textarea>
      </div>
      <div class="mt-modal-footer">
        <button type="button" class="mt-modal-cancel" onclick="closeModal('createTaskModal')">Cancel</button>
        <button type="submit" class="mt-modal-submit">
          <i class="fas fa-plus"></i> Create Task
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Modal: Assign Volunteer ─────────────────────────── -->
<div id="assignModal" class="mt-modal">
  <div class="mt-modal-backdrop" onclick="closeModal('assignModal')"></div>
  <div class="mt-modal-box">
    <button class="mt-modal-close" onclick="closeModal('assignModal')">
      <i class="fas fa-times"></i>
    </button>
    <p class="mt-modal-title"><i class="fas fa-user-plus"></i> Assign Volunteer</p>
    <form method="POST" id="assignForm">
      <input type="hidden" name="assign_volunteer" value="1">
      <input type="hidden" name="task_id" id="assignTaskId">
      <input type="hidden" name="event_id" id="assignEventId">
      <div class="mt-modal-fg">
        <label>Select Volunteer <span style="color:#dc2626;">*</span></label>
        <select name="volunteer_id" required id="volunteerSelect">
          <option value="">— Choose a volunteer —</option>
          <?php foreach ($volunteers_for_event as $v): ?>
            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (empty($volunteers_for_event)): ?>
        <p style="font-size:.78rem;color:#94a3b8;margin:0 0 12px;">
          <i class="fas fa-info-circle" style="color:#2d8653;"></i>
          No volunteers have applied to this event yet.
        </p>
      <?php endif; ?>
      <div class="mt-modal-footer">
        <button type="button" class="mt-modal-cancel" onclick="closeModal('assignModal')">Cancel</button>
        <button type="submit" class="mt-modal-submit" <?= empty($volunteers_for_event) ? 'disabled style="opacity:.5;cursor:not-allowed;"' : '' ?>>
          <i class="fas fa-user-check"></i> Assign
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Modal: Delete Confirm ────────────────────────────── -->
<div id="deleteModal" class="mt-modal">
  <div class="mt-modal-backdrop" onclick="closeModal('deleteModal')"></div>
  <div class="mt-modal-box">
    <button class="mt-modal-close" onclick="closeModal('deleteModal')">
      <i class="fas fa-times"></i>
    </button>
    <p class="mt-modal-title" style="color:#dc2626;">
      <i class="fas fa-triangle-exclamation" style="color:#dc2626;"></i> Delete Task?
    </p>
    <p style="font-size:.875rem;color:#4a5568;margin:0 0 4px;">
      You are about to delete:
    </p>
    <p id="deleteTaskDesc" style="font-size:.845rem;color:#1a1f2e;font-weight:600;background:#fef2f2;padding:10px 14px;border-radius:8px;margin:0 0 16px;"></p>
    <p style="font-size:.8rem;color:#94a3b8;margin:0 0 16px;">
      This will also remove all volunteer assignments for this task. This cannot be undone.
    </p>
    <form method="POST" id="deleteTaskForm">
      <input type="hidden" name="delete_task" value="1">
      <input type="hidden" name="task_id" id="deleteTaskId">
      <div class="mt-modal-footer">
        <button type="button" class="mt-modal-cancel" onclick="closeModal('deleteModal')">Cancel</button>
        <button type="submit" class="mt-modal-submit danger">
          <i class="fas fa-trash"></i> Delete Task
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Modal: QR Codes ──────────────────────────────────── -->
<!-- ── Modal: QR Codes ──────────────────────────────────── -->
<div id="qrModal" class="mt-modal">
  <div class="mt-modal-backdrop" onclick="closeModal('qrModal')"></div>
  <div class="mt-modal-box" style="max-width:620px;">
    <button class="mt-modal-close" onclick="closeModal('qrModal')">
      <i class="fas fa-times"></i>
    </button>
    <p class="mt-modal-title"><i class="fas fa-qrcode"></i> Event QR Codes</p>

    <!-- QR content injected here by openQRModal() -->
    <div id="qrContent"
         style="text-align:center;min-height:100px;display:flex;align-items:center;
                justify-content:center;flex-direction:column;">
    </div>

    <div class="mt-modal-footer" style="justify-content:center;margin-top:18px;">
      <button class="mt-modal-cancel" onclick="closeModal('qrModal')">
        <i class="fas fa-times" style="margin-right:5px;"></i> Close
      </button>
    </div>
  </div>
</div>

<script>
// ── Modal helpers
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
    document.querySelectorAll('.mt-modal.show').forEach(m => m.classList.remove('show'));
    document.body.style.overflow = '';
  }
});

// ── Tab switching
function switchTab(btn, panelId) {
  btn.closest('.mt-tabs').querySelectorAll('.mt-tab').forEach(t => t.classList.remove('active'));
  btn.closest('.mt-card').querySelectorAll('.mt-tab-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(panelId).classList.add('active');
}

// ── Dropdown menus (viewport-aware flip)
function toggleMenu(btn) {
  const menu = btn.nextElementSibling;
  const isOpen = menu.classList.contains('open');

  // close all
  document.querySelectorAll('.mt-menu.open').forEach(m => m.classList.remove('open'));

  if (!isOpen) {
    const rect = btn.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    menu.style.left   = 'auto';
    menu.style.right  = (window.innerWidth - rect.right) + 'px';

    if (spaceBelow < 160) {
      menu.style.top    = 'auto';
      menu.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
    } else {
      menu.style.top    = (rect.bottom + 4) + 'px';
      menu.style.bottom = 'auto';
    }
    menu.classList.add('open');
  }
}
window.addEventListener('click', e => {
  if (!e.target.closest('.mt-dropdown'))
    document.querySelectorAll('.mt-menu.open').forEach(m => m.classList.remove('open'));
});

// ── Assign modal
function openAssignModal(eventId, taskId) {
  document.getElementById('assignEventId').value = eventId;
  document.getElementById('assignTaskId').value  = taskId;
  document.querySelectorAll('.mt-menu.open').forEach(m => m.classList.remove('open'));
  openModal('assignModal');
}

// ── Delete modal
function openDeleteModal(taskId, desc) {
  document.getElementById('deleteTaskId').value = taskId;
  document.getElementById('deleteTaskDesc').textContent = desc;
  document.querySelectorAll('.mt-menu.open').forEach(m => m.classList.remove('open'));
  openModal('deleteModal');
}

// ── QR modal
function openQRModal(eventId) {
  // Show modal immediately with a proper spinner
  document.getElementById('qrContent').innerHTML =
    '<div style="padding:32px 0;display:flex;flex-direction:column;align-items:center;gap:12px;">' +
      '<i class="fas fa-spinner fa-spin fa-2x" style="color:#2d8653;"></i>' +
      '<span style="font-size:.845rem;color:#94a3b8;">Loading QR codes…</span>' +
    '</div>';
  openModal('qrModal');

  fetch('generate_qr.php?event_id=' + encodeURIComponent(eventId))
    .then(function(r) {
      if (!r.ok) throw new Error('Server responded with status ' + r.status);
      return r.text();
    })
    .then(function(html) {
      // Safety check: if the server returned an error string instead of HTML
      if (!html || html.trim() === '') throw new Error('Empty response from server.');
      document.getElementById('qrContent').innerHTML = html;
    })
    .catch(function(err) {
      document.getElementById('qrContent').innerHTML =
        '<div style="padding:24px;text-align:center;">' +
          '<i class="fas fa-circle-exclamation fa-2x" style="color:#dc2626;margin-bottom:10px;display:block;"></i>' +
          '<p style="font-size:.875rem;color:#1a1f2e;font-weight:600;margin:0 0 6px;">Could not load QR codes.</p>' +
          '<p style="font-size:.8rem;color:#94a3b8;margin:0;">Make sure the event has coordinates set. ' +
          '<br><small>' + err.message + '</small></p>' +
        '</div>';
    });
}

// ── Inline edit
document.querySelectorAll('.editTaskBtn').forEach(btn => {
  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    const row      = this.closest('tr');
    const descText = row.querySelector('.task-desc-text');
    const descInput= row.querySelector('.task-desc-input');
    const saveForm = row.querySelector('.save-task-form');
    const saveBtn  = row.querySelector('.saveTaskBtn');
    const editing  = this.dataset.editing === '1';

    if (editing) {
      // Cancel
      descInput.value = descInput.dataset.orig;
      descText.style.display  = '';
      descInput.style.display = 'none';
      saveBtn.style.display   = 'none';
      this.innerHTML = '<i class="fas fa-pen" style="color:#f59e0b;width:16px;"></i> Edit';
      this.dataset.editing = '0';
    } else {
      // Start edit
      descText.style.display  = 'none';
      descInput.style.display = 'block';
      descInput.focus();
      saveBtn.style.display   = 'flex';
      this.innerHTML = '<i class="fas fa-times" style="color:#dc2626;width:16px;"></i> Cancel';
      this.dataset.editing = '1';
    }
    document.querySelectorAll('.mt-menu.open').forEach(m => m.classList.remove('open'));
  });
});

document.querySelectorAll('.save-task-form').forEach(form => {
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const row       = this.closest('tr');
    const descInput = row.querySelector('.task-desc-input');
    const taskId    = this.querySelector('input[name="task_id"]').value;
    const desc      = descInput.value.trim();
    if (!desc) { alert('Task description cannot be empty.'); return; }

    fetch('manage_tasks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `edit_task=1&task_id=${taskId}&task_description=${encodeURIComponent(desc)}`
    }).then(() => location.reload());
  });
});

// ── Attendance AJAX
document.querySelectorAll('.attendance-select').forEach(sel => {
  sel.addEventListener('change', function () {
    fetch('update_attendance.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `event_id=${this.dataset.event}&volunteer_id=${this.dataset.volunteer}&attended=${this.value}`
    });
  });
});

// ── Progress AJAX
document.querySelectorAll('.progress-select').forEach(sel => {
  sel.addEventListener('change', function () {
    fetch('update_progress.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `task_id=${this.dataset.task}&volunteer_id=${this.dataset.volunteer}&progress=${encodeURIComponent(this.value)}`
    });
  });
});
</script>

<?php include '../includes/footer.php'; ?>