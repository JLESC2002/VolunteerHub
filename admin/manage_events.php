<?php
// manage_events.php
include '../conn.php';
include './check_session.php';

$pageTitle = "Manage Events";
$pageCSS   = "../styles/admin_manage.css";
include '../includes/header_admin.php';

$admin_id = $_SESSION['user_id'] ?? null;

// ── 1) Auto-update event statuses ───────────────────────────
$update = $conn->prepare("
    UPDATE events
    SET status = CASE
        WHEN status NOT IN ('Completed','Cancelled') AND date > CURDATE() THEN 'Open'
        WHEN status NOT IN ('Completed','Cancelled') AND date = CURDATE() THEN 'Ongoing'
        ELSE status
    END
    WHERE created_by = ?
");
$update->bind_param("i", $admin_id);
$update->execute();
$update->close();

// ── 2) Create event ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $date        = trim($_POST['date']        ?? '');
    $location    = trim($_POST['location']    ?? '');
    $latitude  = (isset($_POST['latitude'])  && is_numeric($_POST['latitude']))  ? floatval($_POST['latitude'])  : null;
    $longitude = (isset($_POST['longitude']) && is_numeric($_POST['longitude'])) ? floatval($_POST['longitude']) : null;
    if ($title !== '' && $description !== '' && $date !== '' && $location !== '') {
        $lat_val = ($latitude  !== null) ? (string)$latitude  : null;
        $lng_val = ($longitude !== null) ? (string)$longitude : null;

        $stmt = $conn->prepare("INSERT INTO events (title, description, date, location, latitude, longitude, status, created_by) VALUES (?, ?, ?, ?, ?, ?, 'Open', ?)");
        $stmt->bind_param("ssssssi", $title, $description, $date, $location, $lat_val, $lng_val, $admin_id);
        $stmt->execute();
        $stmt->close();
    }
}

// ── 3) Edit event ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_event'])) {
    $event_id    = intval($_POST['event_id']  ?? 0);
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $date        = trim($_POST['date']        ?? '');
    $location    = trim($_POST['location']    ?? '');

    if ($event_id && $title !== '') {
        $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, location = ? WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ssssii", $title, $description, $date, $location, $event_id, $admin_id);
        $stmt->execute();
        $stmt->close();
    }
}

// ── 4) Cancel event ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_event'])) {
    $event_id = intval($_POST['event_id'] ?? 0);
    if ($event_id) {
        $stmt = $conn->prepare("UPDATE events SET status = 'Cancelled' WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ii", $event_id, $admin_id);
        $stmt->execute();
        $stmt->close();
    }
}

// ── 5) Search / Filter ───────────────────────────────────────
$search        = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';

$sql    = "SELECT * FROM events WHERE created_by = ?";
$params = [$admin_id];
$types  = "i";

if ($search !== '') {
    $sql     .= " AND title LIKE ?";
    $params[] = "%$search%";
    $types   .= "s";
}
if ($filter_status !== '') {
    $sql     .= " AND status = ?";
    $params[] = $filter_status;
    $types   .= "s";
}
$sql .= " ORDER BY date DESC";

$stmt = $conn->prepare($sql);
if ($stmt === false) { die("Prepare failed: " . htmlspecialchars($conn->error)); }
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="../assets/leaflet/leaflet.css">

<style>
/* ── Page header ─────────────────────────────────────────── */
.me-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 24px;
}
.me-page-title {
  font-size: 1.45rem;
  font-weight: 700;
  color: #1a1f2e;
  letter-spacing: -.02em;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.me-page-title i { color: #2d8653; }

/* ── Create button ───────────────────────────────────────── */
.btn-create {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 20px;
  background: linear-gradient(135deg, #2d8653, #1a5c3a);
  color: #fff;
  border: none;
  border-radius: 9px;
  font-size: .875rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 3px 10px rgba(45,134,83,.3);
  transition: all .18s ease;
  white-space: nowrap;
}
.btn-create:hover {
  background: linear-gradient(135deg, #4caf80, #2d8653);
  box-shadow: 0 5px 16px rgba(45,134,83,.4);
  transform: translateY(-1px);
  color: #fff;
}

/* ── Filter bar ──────────────────────────────────────────── */
.me-filter-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 20px;
  background: #fff;
  padding: 12px 16px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 4px rgba(0,0,0,.05);
}
.me-filter-bar .search-wrap {
  position: relative;
  flex: 1;
  min-width: 160px;
  max-width: 340px;
}
.me-filter-bar .search-wrap i {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  font-size: .85rem;
  pointer-events: none;
}
.me-filter-bar input[type="text"] {
  width: 100%;
  padding: 8px 12px 8px 33px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font-size: .875rem;
  font-family: inherit;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
  background: #f8fafc;
}
.me-filter-bar input[type="text"]:focus {
  border-color: #2d8653;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(45,134,83,.1);
}
.me-filter-bar select {
  width: auto;
  min-width: 145px;
  max-width: 175px;
  flex-shrink: 0;
  padding: 8px 34px 8px 12px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font-size: .875rem;
  font-family: inherit;
  outline: none;
  appearance: none;
  background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%2394a3b8' stroke-width='1.5' fill='none'/%3E%3C/svg%3E") no-repeat right 11px center;
  cursor: pointer;
  transition: border-color .15s;
}
.me-filter-bar select:focus { border-color: #2d8653; }
.me-filter-bar .btn-filter {
  padding: 8px 16px;
  background: #2d8653;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: .875rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background .15s;
  flex-shrink: 0;
}
.me-filter-bar .btn-filter:hover { background: #1a5c3a; }
.me-filter-bar .btn-clear {
  font-size: .825rem;
  color: #94a3b8;
  text-decoration: none;
  padding: 4px 6px;
  border-radius: 6px;
  transition: color .15s;
}
.me-filter-bar .btn-clear:hover { color: #dc2626; }

/* ── Table card ──────────────────────────────────────────── */
.me-table-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
  overflow: hidden;
  overflow-x: auto;
}
.me-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .875rem;
  min-width: 720px;
}
.me-table thead tr {
  background: linear-gradient(135deg, #1a5c3a, #2d8653);
  color: #fff;
}
.me-table thead th {
  padding: 13px 16px;
  font-size: .75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  white-space: nowrap;
  border: none;
}
.me-table tbody tr {
  border-bottom: 1px solid #f1f5f9;
  transition: background .15s;
}
.me-table tbody tr:last-child { border-bottom: none; }
.me-table tbody tr:nth-child(even) { background: #fafcff; }
.me-table tbody tr:hover { background: #edf7f2; }
.me-table td {
  padding: 12px 16px;
  vertical-align: middle;
  color: #1a1f2e;
}
.me-table td.td-title    { font-weight: 600; }
.me-table td.td-desc     { color: #4a5568; max-width: 240px; white-space: normal; line-height: 1.45; }
.me-table td.td-date     { white-space: nowrap; color: #64748b; }
.me-table td.td-location { white-space: nowrap; color: #64748b; }
.me-table td.td-actions  { text-align: center; white-space: nowrap; }

/* ── Status badges ───────────────────────────────────────── */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 11px;
  border-radius: 99px;
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .03em;
  white-space: nowrap;
}
.status-open      { background: #dcfce7; color: #15803d; }
.status-ongoing   { background: #fef9c3; color: #a16207; }
.status-completed { background: #f1f5f9; color: #475569; }
.status-cancelled { background: #fee2e2; color: #b91c1c; }

/* ── Action dropdown ─────────────────────────────────────── */
.dropdown { position: relative; display: inline-block; }
.action-menu-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  cursor: pointer;
  border-radius: 7px;
  font-size: .9rem;
  transition: all .15s ease;
}
.action-menu-btn:hover { background: #f1f5f9; color: #1a1f2e; border-color: #cbd5e0; }
.action-menu {
  display: none;
  position: absolute;
  right: 0;
  top: calc(100% + 5px);
  background: #fff;
  min-width: 195px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 8px 28px rgba(0,0,0,.12);
  z-index: 1000;
  overflow: hidden;
  animation: menuIn .14s ease both;
}
.action-menu.open-up {
  top: auto;
  bottom: calc(100% + 5px);
}
@keyframes menuIn {
  from { opacity: 0; transform: translateY(-5px); }
  to   { opacity: 1; transform: translateY(0); }
}
.action-menu button {
  display: flex;
  align-items: center;
  gap: 9px;
  width: 100%;
  padding: 10px 16px;
  border: none;
  background: transparent;
  font-family: inherit;
  font-size: .875rem;
  color: #374151;
  cursor: pointer;
  text-align: left;
  transition: background .12s;
}
.action-menu button:hover { background: #f8fafc; }
.action-menu button.danger { color: #dc2626; }
.action-menu button.danger:hover { background: #fef2f2; }
.action-menu .menu-sep { height: 1px; background: #f1f5f9; margin: 4px 0; }

/* ── Empty state ─────────────────────────────────────────── */
.me-empty { text-align: center; padding: 52px 24px; color: #94a3b8; }
.me-empty i { font-size: 2.5rem; color: #cbd5e0; display: block; margin-bottom: 14px; }
.me-empty p { font-size: .9rem; margin: 0; }

/* ── Modal base ──────────────────────────────────────────── */
.wh-modal {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 1400;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.wh-modal[aria-hidden="false"] { display: flex; }
.wh-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(6,10,20,.45);
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
  cursor: pointer;
}
.wh-modal-content {
  position: relative;
  z-index: 1;
  background: #fff;
  border-radius: 16px;
  padding: 28px 28px 24px;
  width: 100%;
  max-width: 540px;
  max-height: 92vh;
  overflow-y: auto;
  box-shadow: 0 20px 52px rgba(0,0,0,.2);
  transform: translateY(14px) scale(.97);
  opacity: 0;
  transition: transform .22s ease, opacity .22s ease;
}
.wh-modal[aria-hidden="false"] .wh-modal-content {
  transform: translateY(0) scale(1);
  opacity: 1;
}
.wh-modal-content.wide { max-width: 760px; }
.wh-modal-close {
  position: absolute;
  right: 14px; top: 12px;
  border: none; background: none;
  font-size: 1.25rem; color: #94a3b8;
  cursor: pointer;
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 6px;
  transition: background .12s, color .12s;
}
.wh-modal-close:hover { background: #f1f5f9; color: #1a1f2e; }
.wh-modal-content h4 {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1a1f2e;
  margin: 0 0 22px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding-right: 28px;
}
.wh-modal-content h4 i { color: #2d8653; }

/* ── Modal form ──────────────────────────────────────────── */
.modal-form .form-group { margin-bottom: 16px; }
.modal-form label {
  display: block;
  font-size: .78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: #64748b;
  margin-bottom: 6px;
}
.modal-form input,
.modal-form textarea {
  width: 100%;
  padding: 10px 13px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font-family: inherit;
  font-size: .9rem;
  color: #1a1f2e;
  background: #f8fafc;
  outline: none;
  transition: border-color .15s, box-shadow .15s, background .15s;
  box-sizing: border-box;
}
.modal-form input:focus,
.modal-form textarea:focus {
  border-color: #2d8653;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(45,134,83,.12);
}
.modal-form input[readonly] { cursor: default; }
.modal-form textarea { resize: vertical; min-height: 90px; }
.modal-form .form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.modal-form .form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 24px;
  padding-top: 18px;
  border-top: 1px solid #f1f5f9;
}
.modal-form .btn-cancel {
  padding: 9px 18px;
  background: #f1f5f9;
  color: #64748b;
  border: none;
  border-radius: 8px;
  font-family: inherit;
  font-size: .875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s;
}
.modal-form .btn-cancel:hover { background: #e2e8f0; }
.modal-form .btn-save {
  padding: 9px 22px;
  background: linear-gradient(135deg, #2d8653, #1a5c3a);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-family: inherit;
  font-size: .875rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 3px 10px rgba(45,134,83,.28);
  transition: all .15s;
}
.modal-form .btn-save:hover {
  background: linear-gradient(135deg, #4caf80, #2d8653);
  box-shadow: 0 5px 14px rgba(45,134,83,.38);
  transform: translateY(-1px);
}
.modal-form .btn-location {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-family: inherit;
  font-size: .825rem;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s;
  margin-top: 10px;
}
.modal-form .btn-location:hover { background: #e2e8f0; }

/* ── Map picker ──────────────────────────────────────────── */
#createEventMap {
  width: 100%;
  height: 240px;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
  margin-top: 6px;
  z-index: 1;
}
.map-hint {
  font-size: .78rem;
  color: #94a3b8;
  margin: 6px 0 0;
  display: flex;
  align-items: center;
  gap: 5px;
}
.map-coords-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 10px;
}

/* ── Volunteer table ─────────────────────────────────────── */
.vol-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.vol-table thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
.vol-table thead th { padding: 10px 14px; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
.vol-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .12s; }
.vol-table tbody tr:last-child { border-bottom: none; }
.vol-table tbody tr:hover { background: #f8fafc; }
.vol-table td { padding: 11px 14px; vertical-align: middle; }

/* ── QR modal body ───────────────────────────────────────── */
#qrModalBody .qr-pair {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  gap: 32px;
  flex-wrap: wrap;
  padding: 10px 0 6px;
}
#qrModalBody .qr-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px 24px;
  text-align: center;
}
#qrModalBody .qr-item h5 {
  font-size: .85rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #475569;
  margin: 0 0 14px;
}
#qrModalBody .qr-item img {
  display: block;
  width: 180px;
  height: 180px;
  margin: 0 auto 14px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}
#qrModalBody .btn-dl {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: linear-gradient(135deg, #2d8653, #1a5c3a);
  color: #fff;
  border-radius: 7px;
  font-size: .8rem;
  font-weight: 700;
  text-decoration: none;
  transition: opacity .15s;
}
#qrModalBody .btn-dl:hover { opacity: .85; color: #fff; }
#qrModalBody .qr-error {
  padding: 32px;
  color: #dc2626;
  font-size: .9rem;
}
</style>

<!-- ═══════════════════════════════════════════════════════════
     PAGE HTML
═══════════════════════════════════════════════════════════════ -->
<div class="manage-events-page">

  <!-- Page header -->
  <div class="me-page-header">
    <h1 class="me-page-title">
      <i class="fas fa-calendar-alt"></i> Manage Events
    </h1>
    <button class="btn-create" onclick="openCreateEventModal()">
      <i class="fas fa-plus"></i> Create Event
    </button>
  </div>

  <!-- Filter bar -->
  <form method="GET" action="" class="me-filter-bar">
    <div class="search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" name="search"
             placeholder="Search events…"
             value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="status">
      <option value="">All Statuses</option>
      <?php foreach (['Open','Ongoing','Completed','Cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-filter">
      <i class="fas fa-filter"></i> Filter
    </button>
    <?php if ($search !== '' || $filter_status !== ''): ?>
      <a href="manage_events.php" class="btn-clear">
        <i class="fas fa-times"></i> Clear
      </a>
    <?php endif; ?>
  </form>

  <!-- Events table -->
  <div class="me-table-card">
    <table class="me-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Description</th>
          <th>Date</th>
          <th>Location</th>
          <th>Status</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result->num_rows === 0): ?>
          <tr>
            <td colspan="6">
              <div class="me-empty">
                <i class="fas fa-calendar-times"></i>
                <p><?= ($search || $filter_status) ? 'No events match your filters. Try clearing them.' : 'No events yet. Create your first one!' ?></p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr id="event-row-<?= intval($row['id']) ?>">

              <td class="td-title"><?= htmlspecialchars($row['title']) ?></td>

              <td class="td-desc"><?= nl2br(htmlspecialchars($row['description'])) ?></td>

              <td class="td-date">
                <i class="fas fa-calendar-day" style="color:#94a3b8;font-size:.8rem;margin-right:4px;"></i>
                <?= htmlspecialchars($row['date']) ?>
              </td>

              <td class="td-location">
                <i class="fas fa-map-marker-alt" style="color:#94a3b8;font-size:.8rem;margin-right:4px;"></i>
                <?= htmlspecialchars($row['location']) ?>
              </td>

              <td>
                <?php
                  $st = strtolower($row['status']);
                  $stIcon = match($st) {
                    'open'      => 'fa-circle-dot',
                    'ongoing'   => 'fa-clock',
                    'completed' => 'fa-circle-check',
                    'cancelled' => 'fa-ban',
                    default     => 'fa-circle',
                  };
                ?>
                <span class="status-badge status-<?= $st ?>">
                  <i class="fas <?= $stIcon ?>" style="font-size:.65rem;"></i>
                  <?= htmlspecialchars($row['status']) ?>
                </span>
              </td>

              <td class="td-actions">
                <div class="dropdown">
                  <button class="action-menu-btn" type="button"
                          onclick="toggleActionMenu(this)"
                          aria-label="Actions">
                    <i class="fas fa-ellipsis-v"></i>
                  </button>
                  <div class="action-menu">

                    <button type="button" onclick="openEditModal(<?= $row['id'] ?>); closeAllMenus();">
                      <i class="fas fa-edit" style="color:#2d8653;width:16px;"></i> Edit Event
                    </button>

                    <button type="button" onclick="openVolunteerModal(<?= $row['id'] ?>); closeAllMenus();">
                      <i class="fas fa-users" style="color:#3b82f6;width:16px;"></i> View Volunteers
                    </button>

                    <?php if ($row['status'] === 'Open' || $row['status'] === 'Ongoing'): ?>
                      <div class="menu-sep"></div>

                      <button type="button" onclick="openQRModal(<?= $row['id'] ?>); closeAllMenus();">
                        <i class="fas fa-qrcode" style="color:#7c3aed;width:16px;"></i> Generate QR
                      </button>

                      <form method="POST" action="complete_event.php"
                            onsubmit="return confirm('Mark \'<?= addslashes($row['title']) ?>\' as Completed?')"
                            style="margin:0;">
                        <input type="hidden" name="event_id" value="<?= $row['id'] ?>">
                        <button type="submit">
                          <i class="fas fa-circle-check" style="color:#16a34a;width:16px;"></i> Mark Complete
                        </button>
                      </form>

                      <form method="POST"
                            onsubmit="return confirm('Cancel \'<?= addslashes($row['title']) ?>\'?')"
                            style="margin:0;">
                        <input type="hidden" name="event_id" value="<?= $row['id'] ?>">
                        <button type="submit" name="cancel_event">
                          <i class="fas fa-ban" style="color:#f59e0b;width:16px;"></i> Cancel Event
                        </button>
                      </form>
                    <?php endif; ?>

                    <div class="menu-sep"></div>

                    <button type="button" class="danger"
                            onclick="confirmDelete(<?= $row['id'] ?>, '<?= addslashes($row['title']) ?>')">
                      <i class="fas fa-trash" style="width:16px;"></i> Delete
                    </button>

                  </div><!-- /.action-menu -->
                </div><!-- /.dropdown -->
              </td>

            </tr>
          <?php endwhile; ?>
          <?php $stmt->close(); ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div><!-- /.me-table-card -->

</div><!-- /.manage-events-page -->


<!-- ═══════════════════════════════════════════════════════════
     MODAL: Edit Event
═══════════════════════════════════════════════════════════════ -->
<div id="editEventModal" class="wh-modal" aria-hidden="true">
  <div class="wh-modal-backdrop" onclick="closeEditModal()"></div>
  <div class="wh-modal-content" role="dialog" aria-modal="true">
    <button class="wh-modal-close" onclick="closeEditModal()" aria-label="Close">
      <i class="fas fa-times"></i>
    </button>
    <h4><i class="fas fa-edit"></i> Edit Event</h4>
    <form id="editEventForm" method="POST" class="modal-form">
      <input type="hidden" name="event_id" id="edit_event_id">

      <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" id="edit_title" required>
      </div>

      <div class="form-group">
        <label>Description</label>
        <textarea name="description" id="edit_description" rows="3" required></textarea>
      </div>

      <div class="form-group">
        <label>Date</label>
        <input type="date" name="date" id="edit_date" required>
      </div>

      <div class="form-actions">
        <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
        <button type="submit" name="edit_event" class="btn-save">
          <i class="fas fa-save" style="margin-right:5px;"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     MODAL: Create Event
═══════════════════════════════════════════════════════════════ -->
<div id="createEventModal" class="wh-modal" aria-hidden="true">
  <div class="wh-modal-backdrop" onclick="closeCreateEventModal()"></div>
  <div class="wh-modal-content" role="dialog" aria-modal="true">
    <button class="wh-modal-close" onclick="closeCreateEventModal()" aria-label="Close">
      <i class="fas fa-times"></i>
    </button>
    <h4><i class="fas fa-plus-circle"></i> Create New Event</h4>

    <form method="POST" class="modal-form">

      <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" required placeholder="e.g. Community Clean-up Drive">
      </div>

      <div class="form-group">
        <label>Description</label>
        <textarea name="description" rows="3" required placeholder="Describe the event…"></textarea>
      </div>

      <div class="form-group">
        <label>Date</label>
        <input type="date" name="date" required>
      </div>

      <div class="form-group">
        <label>Location Name</label>
        <input type="text" name="location" id="create_location" required
               placeholder="e.g. Rizal Park, Barangay Hall">
      </div>

      <div class="form-group">
        <label>
          <i class="fas fa-map-location-dot" style="color:#2d8653;margin-right:4px;"></i>
          Pick Location on Map
        </label>
          <input type="hidden" name="latitude" id="latitude">
          <input type="hidden" name="longitude" id="longitude">
        <div id="createEventMap"></div>
        <p class="map-hint">
          <i class="fas fa-hand-pointer"></i>
          Click the map to drop a pin — or use the button below to snap to your location.
        </p>
            <div class="map-coords-row">
              <div class="form-group">
                <label>Latitude</label>
                <input type="text" id="latitude_display" readonly>
              </div>

              <div class="form-group">
                <label>Longitude</label>
                <input type="text" id="longitude_display" readonly>
              </div>
            </div>
          </div>
        <button type="button" class="btn-location" onclick="getLocation()">
          <i class="fas fa-location-crosshairs"></i> Snap to My Current Location
        </button>

      <div class="form-actions">
        <button type="button" class="btn-cancel" onclick="closeCreateEventModal()">Cancel</button>
        <button type="submit" name="create_event" class="btn-save">
          <i class="fas fa-save" style="margin-right:5px;"></i> Save Event
        </button>
      </div>
    </form>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     MODAL: Volunteer Applications
═══════════════════════════════════════════════════════════════ -->
<div id="volunteerModal" class="wh-modal" aria-hidden="true">
  <div class="wh-modal-backdrop" onclick="closeVolunteerModal()"></div>
  <div class="wh-modal-content wide" role="dialog" aria-modal="true">
    <button class="wh-modal-close" onclick="closeVolunteerModal()" aria-label="Close">
      <i class="fas fa-times"></i>
    </button>
    <h4><i class="fas fa-users"></i> Volunteer Applications</h4>
    <div style="overflow-x:auto;">
      <table id="volunteerTable" class="vol-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Status</th>
            <th style="min-width:150px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="3" style="text-align:center;padding:24px;color:#94a3b8;">Loading…</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     MODAL: QR Codes
═══════════════════════════════════════════════════════════════ -->
<div id="qrModal" class="wh-modal" aria-hidden="true">
  <div class="wh-modal-backdrop" onclick="closeQRModal()"></div>
  <div class="wh-modal-content wide" role="dialog" aria-modal="true">
    <button class="wh-modal-close" onclick="closeQRModal()" aria-label="Close">
      <i class="fas fa-times"></i>
    </button>
    <h4><i class="fas fa-qrcode"></i> Event QR Codes</h4>
    <div id="qrModalBody" style="text-align:center;min-height:160px;padding:10px 0;">
      <div style="padding:40px;color:#94a3b8;">
        <i class="fas fa-spinner fa-spin fa-2x" style="display:block;margin-bottom:12px;color:#2d8653;"></i>
        Generating QR codes…
      </div>
    </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════════ -->
<script src="../assets/leaflet/leaflet.js"></script>
<script>
/* ── Modal helpers ───────────────────────────────────────── */
function showModal(id) {
  const el = document.getElementById(id);
  if (el) el.setAttribute('aria-hidden', 'false');
}
function hideModal(id) {
  const el = document.getElementById(id);
  if (el) el.setAttribute('aria-hidden', 'true');
}

/* ── Open / close ────────────────────────────────────────── */
function openCreateEventModal() {
  showModal('createEventModal');
  setTimeout(initCreateMap, 260);
}
function closeCreateEventModal() { hideModal('createEventModal'); }
function closeEditModal()        { hideModal('editEventModal'); }
function closeVolunteerModal()   { hideModal('volunteerModal'); }
function closeQRModal()          { hideModal('qrModal'); }

/* ── Edit modal: pre-fill from row ──────────────────────── */
function openEditModal(eventId) {
  const row = document.getElementById('event-row-' + eventId);
  if (!row) { alert('Event row not found.'); return; }
  const cells = row.getElementsByTagName('td');
  // 0=title 1=desc 2=date 3=location 4=status 5=actions
  document.getElementById('edit_event_id').value    = eventId;
  document.getElementById('edit_title').value       = cells[0].innerText.trim();
  document.getElementById('edit_description').value = cells[1].innerText.trim();
  document.getElementById('edit_date').value        = cells[2].innerText.trim();
  showModal('editEventModal');
}

/* ── Volunteer modal (AJAX) ──────────────────────────────── */
function openVolunteerModal(eventId) {
  showModal('volunteerModal');
  const tbody = document.querySelector('#volunteerTable tbody');
  tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:24px;color:#94a3b8;"><i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i>Loading…</td></tr>';

  fetch('fetch_applicants.php?event_id=' + encodeURIComponent(eventId))
    .then(r => r.text())
    .then(html => {
      tbody.innerHTML = html;
      tbody.querySelectorAll('.accept-btn, .reject-btn').forEach(btn => {
        btn.addEventListener('click', function () {
          fetch('update_application.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              event_id:     eventId,
              volunteer_id: this.dataset.volunteer,
              action:       this.dataset.action
            }).toString()
          }).then(() => openVolunteerModal(eventId));
        });
      });
    })
    .catch(() => {
      tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:20px;color:#dc2626;">Failed to load. Please try again.</td></tr>';
    });
}

/* ── QR modal (AJAX via GET) ─────────────────────────────── */
function openQRModal(eventId) {
  showModal('qrModal');
  const body = document.getElementById('qrModalBody');
  body.innerHTML =
    '<div style="padding:40px;color:#94a3b8;">' +
    '<i class="fas fa-spinner fa-spin fa-2x" style="display:block;margin-bottom:12px;color:#2d8653;"></i>' +
    'Generating QR codes…</div>';

  fetch('generate_qr.php?event_id=' + encodeURIComponent(eventId))
    .then(r => {
      if (!r.ok) throw new Error('Server error ' + r.status);
      return r.text();
    })
    .then(html => {
      body.innerHTML = '<div class="qr-pair">' + html + '</div>';
    })
    .catch(err => {
      body.innerHTML =
        '<div class="qr-error">' +
        '<i class="fas fa-circle-exclamation" style="margin-right:6px;"></i>' +
        'Could not generate QR codes. Make sure the event has coordinates set.<br>' +
        '<small style="color:#94a3b8;display:block;margin-top:6px;">Error: ' + err.message + '</small>' +
        '</div>';
    });
}

/* ── Delete confirmation ─────────────────────────────────── */
function confirmDelete(eventId, eventTitle) {
  if (!confirm('Delete "' + eventTitle + '"?\n\nThis cannot be undone.')) return;
  const f = document.createElement('form');
  f.method = 'POST';
  f.action = 'delete_item.php';
  f.innerHTML = '<input type="hidden" name="type" value="event">' +
                '<input type="hidden" name="id" value="' + eventId + '">';
  document.body.appendChild(f);
  f.submit();
}

/* ── Action dropdown ─────────────────────────────────────── */
function closeAllMenus() {
  document.querySelectorAll('.action-menu').forEach(m => {
    m.style.display = 'none';
    m.classList.remove('open-up');
  });
}
function toggleActionMenu(btn) {
  const menu      = btn.nextElementSibling;
  const isVisible = menu.style.display === 'block';
  closeAllMenus();
  if (!isVisible) {
    menu.style.display = 'block';
    // Check if menu goes off-screen bottom
    setTimeout(() => {
      const btnRect = btn.getBoundingClientRect();
      const menuRect = menu.getBoundingClientRect();
      const windowHeight = window.innerHeight;
      
      if (menuRect.bottom > windowHeight - 10) {
        // Not enough space below, open upward
        menu.classList.add('open-up');
      } else {
        menu.classList.remove('open-up');
      }
    }, 0);
  }
}
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown')) closeAllMenus();
});

/* ── ESC closes everything ───────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') return;
  hideModal('editEventModal');
  hideModal('createEventModal');
  hideModal('volunteerModal');
  hideModal('qrModal');
  closeAllMenus();
});

/* ── Leaflet map picker ──────────────────────────────────── */
let createMap    = null;
let createMarker = null;
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconUrl:       '../assets/leaflet/images/marker-icon.png',
  iconRetinaUrl: '../assets/leaflet/images/marker-icon-2x.png',
  shadowUrl:     '../assets/leaflet/images/marker-shadow.png'
});
function initCreateMap() {
  if (createMap) {
    createMap.invalidateSize();
    return;
  }
  createMap = L.map('createEventMap').setView([12.8797, 121.7740], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19
  }).addTo(createMap);
  createMap.on('click', function (e) {
    setMapPin(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6));
  });
}

function setMapPin(lat, lng) {
  // Hidden fields — these are what the form submits
  document.getElementById('latitude').value  = lat;
  document.getElementById('longitude').value = lng;

  // Display fields — visual feedback for the admin
  const dispLat = document.getElementById('latitude_display');
  const dispLng = document.getElementById('longitude_display');
  if (dispLat) dispLat.value = lat;
  if (dispLng) dispLng.value = lng;

  if (createMarker) {
    createMarker.setLatLng([lat, lng]);
  } else {
    createMarker = L.marker([lat, lng], { draggable: true }).addTo(createMap);
    createMarker.on('dragend', function () {
      const p = createMarker.getLatLng();
      setMapPin(p.lat.toFixed(6), p.lng.toFixed(6)); // reuse this function on drag
    });
  }
  createMap.setView([lat, lng], 15);
}

function getLocation() {
  if (!navigator.geolocation) { alert('Geolocation is not supported by your browser.'); return; }
  navigator.geolocation.getCurrentPosition(
    pos => setMapPin(pos.coords.latitude.toFixed(6), pos.coords.longitude.toFixed(6)),
    ()  => alert('Could not get your location. Please allow location access or click the map.')
  );
}
</script>

<?php include '../includes/footer.php'; ?>