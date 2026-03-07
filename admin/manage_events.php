<?php
// manage_events.php
include '../conn.php';
include './check_session.php';

// Page title and page-specific CSS (keeps your existing pattern)
$pageTitle = "Manage Events";
$pageCSS = "../styles/admin_manage.css";
include '../includes/header_admin.php';

$admin_id = $_SESSION['user_id'] ?? null;

// 1) Auto-update statuses based on date
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

// 2) Handle creation (keeps latitude/longitude fields)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date        = trim($_POST['date'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $latitude    = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? floatval($_POST['latitude']) : null;
    $longitude   = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? floatval($_POST['longitude']) : null;

    // Basic validation (you can expand)
    if ($title !== '' && $description !== '' && $date !== '' && $location !== '') {
        $stmt = $conn->prepare("INSERT INTO events (title, description, date, location, latitude, longitude, status, created_by) VALUES (?, ?, ?, ?, ?, ?, 'Open', ?)");
        $stmt->bind_param("sssddsi", $title, $description, $date, $location, $latitude, $longitude, $admin_id);
        $stmt->execute();
        $stmt->close();
    }
}

// 3) Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_event'])) {
    $event_id    = intval($_POST['event_id']);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date        = trim($_POST['date'] ?? '');
    $location    = trim($_POST['location'] ?? '');

    if ($event_id && $title !== '') {
        $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, location = ? WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ssssii", $title, $description, $date, $location, $event_id, $admin_id);
        $stmt->execute();
        $stmt->close();
    }
}

// 4) Handle cancel event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_event'])) {
    $event_id = intval($_POST['event_id']);
    if ($event_id) {
        $stmt = $conn->prepare("UPDATE events SET status = 'Cancelled' WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ii", $event_id, $admin_id);
        $stmt->execute();
        $stmt->close();
    }
}

// 5) Search / Filter (build SQL dynamically)
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';

$sql = "SELECT * FROM events WHERE created_by = ?";
$params = [$admin_id];
$types = "i";

if ($search !== '') {
    $sql .= " AND title LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if ($filter_status !== '') {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
$sql .= " ORDER BY date DESC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}

// bind params (works in modern PHP)
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>


<div class="container-fluid manage-events-page">

  <!-- Filter/Search card -->
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
      <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" style="min-width:280px;">
        <select name="status" class="form-select form-select-sm" style="width:220px; padding-top:10px; padding-bottom:10px;">
          <option value="">All Statuses</option>
          <option value="Open"      <?= $filter_status === 'Open'      ? 'selected' : '' ?>>Open</option>
          <option value="Ongoing"   <?= $filter_status === 'Ongoing'   ? 'selected' : '' ?>>Ongoing</option>
          <option value="Completed" <?= $filter_status === 'Completed' ? 'selected' : '' ?>>Completed</option>
          <option value="Cancelled" <?= $filter_status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>

        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search event title..." style="width:220px; padding-top:10px; padding-bottom:10px;" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search me-1" style="width:20px height:20px;"></i> Search</button>
      </form>

      <button type="button" class="btn btn-success btn-sm ms-auto" onclick="openCreateEventModal()">
        <i class="fas fa-plus me-1"></i> Create Event
      </button>
    </div>
  </div>

  <!-- Events table card -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white border-0 pb-0 d-flex align-items-center justify-content-between">
      <h5 class="fw-bold text-primary mb-0"><i class="fas fa-calendar-alt me-2"></i>Manage Events</h5>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table class="table align-middle table-hover">
          <thead class="table-light">
            <tr>
              <th style="min-width:200px;">Title</th>
              <th>Description</th>
              <th style="min-width:120px;">Date</th>
              <th style="min-width:160px;">Location</th>
              <th style="min-width:120px;">Status</th>
              <th style="width:150px;" class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr id="event-row-<?= intval($row['id']) ?>">
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td class="text-wrap" style="max-width:420px;"><?= nl2br(htmlspecialchars($row['description'])) ?></td>
                <td><?= htmlspecialchars($row['date']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td>
                  <?php
                    $status = $row['status'];
                    $badgeClass = $status === 'Open' ? 'bg-info' : ($status === 'Ongoing' ? 'bg-warning text-dark' : ($status === 'Completed' ? 'bg-success' : 'bg-danger'));
                  ?>
                  <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                </td>
<td class="text-center position-relative">
  <div class="dropdown">
    <button class="action-menu-btn" type="button" onclick="toggleActionMenu(this)">
      <i class="fas fa-ellipsis-v"></i>
    </button>

    <div class="action-menu shadow-sm">
      <button type="button" onclick="openEditModal(<?= $row['id'] ?>)">
        ✏️ Edit
      </button>

      <button type="button" onclick="openVolunteerModal(<?= $row['id'] ?>)">
        👥 View Volunteers
      </button>

      <?php if ($row['status'] !== 'Completed' && $row['status'] !== 'Cancelled'): ?>
        <form method="POST" action="complete_event.php" onsubmit="return confirm('Mark this event as Completed?')" style="margin:0;">
          <input type="hidden" name="event_id" value="<?= $row['id'] ?>">
          <button type="submit" style="width:100%;text-align:left;">✅ Complete</button>
        </form>
        <form method="POST" onsubmit="return confirm('Cancel this event?')" style="margin:0;">
          <input type="hidden" name="event_id" value="<?= $row['id'] ?>">
          <button type="submit" name="cancel_event" style="width:100%;text-align:left;">🚫 Cancel</button>
        </form>
      <?php endif; ?>

      <button type="button" onclick="confirmDelete(<?= $row['id'] ?>)">
        🗑 Delete
      </button>
    </div>
  </div>
</td>

              </tr>
            <?php endwhile; ?>
            <?php $stmt->close(); ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ========== Edit Event Modal ========== -->
<div id="editEventModal" class="wh-modal" aria-hidden="true">
  <div class="wh-modal-backdrop" onclick="closeEditModal()"></div>
  <div class="wh-modal-content" role="dialog" aria-modal="true">
    <button class="wh-modal-close" aria-label="Close" onclick="closeEditModal()">&times;</button>
    <h4 class="mb-2"><i class="fas fa-edit me-2"></i>Edit Event</h4>
    <form id="editEventForm" method="POST" onsubmit="return submitEditForm();">
      <input type="hidden" name="event_id" id="edit_event_id">
      <div class="mb-2">
        <label class="form-label">Title</label>
        <input type="text" name="title" id="edit_title" class="form-control" required>
      </div>
      <div class="mb-2">
        <label class="form-label">Description</label>
        <textarea name="description" id="edit_description" class="form-control" rows="3" required></textarea>
      </div>
      <div class="row g-2">
        <div class="col-md-6 mb-2">
          <label class="form-label">Date</label>
          <input type="date" name="date" id="edit_date" class="form-control" required>
        </div>
        <div class="col-md-6 mb-2">
          <label class="form-label">Location</label>
          <input type="text" name="location" id="edit_location" class="form-control" required>
        </div>
      </div>
      <div class="d-flex gap-2 justify-content-end mt-3">
        <button type="button" class="btn btn-outline-secondary" onclick="closeEditModal()">Cancel</button>
        <button type="submit" name="edit_event" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ========== Create Event Modal (same style) ========== -->
<div id="createEventModal" class="wh-modal" aria-hidden="true">
  <div class="wh-modal-backdrop" onclick="closeCreateEventModal()"></div>
  <div class="wh-modal-content" role="dialog" aria-modal="true">
    <button class="wh-modal-close" aria-label="Close" onclick="closeCreateEventModal()">&times;</button>
    <h4 class="mb-2"><i class="fas fa-plus-circle me-2"></i>Create Event</h4>

    <form method="POST" class="form-card">
      <div class="mb-2">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" required>
      </div>

      <div class="mb-2">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3" required></textarea>
      </div>

      <div class="row g-2">
        <div class="col-md-6 mb-2">
          <label class="form-label">Date</label>
          <input type="date" name="date" class="form-control" required>
        </div>
        <div class="col-md-6 mb-2">
          <label class="form-label">Location</label>
          <input type="text" name="location" id="create_location" class="form-control" required>
        </div>
      </div>

      <div class="row g-2">
        <div class="col-md-6">
          <label class="form-label">Latitude</label>
          <input type="text" name="latitude" id="latitude" class="form-control" readonly>
        </div>
        <div class="col-md-6">
          <label class="form-label">Longitude</label>
          <input type="text" name="longitude" id="longitude" class="form-control" readonly>
        </div>
      </div>

      <div class="mt-2 d-flex gap-2 align-items-center">
        <button type="button" class="btn btn-outline-secondary" onclick="getLocation()">📍 Get Current Location</button>
        <small class="text-muted">Click to auto-fill coordinates</small>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <button type="button" class="btn btn-outline-secondary" onclick="closeCreateEventModal()">Close</button>
        <button type="submit" name="create_event" class="btn btn-primary">Save Event</button>
      </div>
    </form>
  </div>
</div>

<!-- ========== Volunteer Modal (loads via fetch) ========== -->
<div id="volunteerModal" class="wh-modal" aria-hidden="true">
  <div class="wh-modal-backdrop" onclick="closeVolunteerModal()"></div>
  <div class="wh-modal-content" role="dialog" aria-modal="true" style="max-width:800px;">
    <button class="wh-modal-close" aria-label="Close" onclick="closeVolunteerModal()">&times;</button>
    <h4 class="mb-2"><i class="fas fa-users me-2"></i>Volunteer Applications</h4>
    <div class="table-responsive">
      <table id="volunteerTable" class="table table-sm table-hover">
        <thead class="table-light">
          <tr>
            <th>Name</th>
            <th>Status</th>
            <th style="min-width:140px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="3">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
/* Modal shell */
.wh-modal { display: none; position: fixed; inset: 0; z-index: 1200; }
.wh-modal[aria-hidden="false"] { display: block; }

/* Backdrop blur and darken */
.wh-modal-backdrop {
  position: fixed; inset: 0;
  background: rgba(6, 10, 17, 0.36);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  transition: opacity .2s ease;
}

/* Centered content box */
.wh-modal-content {
  position: relative;
  width: 100%;
  max-width: 820px;
  margin: 6vh auto;
  background: #fff;
  border-radius: 12px;
  padding: 18px;
  box-shadow: 0 12px 40px rgba(2,8,23,0.12);
  transform: translateY(6px) scale(.98);
  opacity: 0;
  transition: transform .18s ease, opacity .18s ease;
}

/* show state animation */
.wh-modal[aria-hidden="false"] .wh-modal-content {
  transform: translateY(0) scale(1);
  opacity: 1;
}

/* close button */
.wh-modal-close {
  position: absolute;
  right: 12px;
  top: 10px;
  border: none;
  background: none;
  font-size: 22px;
  color: #6b7280;
  cursor: pointer;
}

/* small responsive adjustments */
@media (max-width: 576px) {
  .wh-modal-content { margin: 4vh 12px; padding: 14px; }
}

/* table adjustments */
.manage-events-page .table td, .manage-events-page .table th {
  vertical-align: middle;
  font-size: 0.95rem;
}

/* small fix for badge color contrast */
.badge.bg-warning.text-dark { background:#ffc107; color:#212529; }

/* ensure modal text areas & inputs look consistent */
.wh-modal-content .form-control { border-radius: 8px; }

/* style dropdown for actions */
.action-menu-btn {
  border: none;
  background: transparent;
  font-size: 18px;
  color: #6b7280;
  cursor: pointer;
  border-radius: 6px;
  padding: 4px 8px;
  transition: background 0.15s ease;
}
.action-menu-btn:hover {
  background: rgba(0,0,0,0.05);
  color: #111;
}

.action-menu {
  display: none;
  position: absolute;
  right: 0;
  top: 110%;
  background: #fff;
  min-width: 180px;
  border-radius: 8px;
  box-shadow: 0 6px 20px rgba(0,0,0,0.12);
  z-index: 999;
  animation: fadeIn 0.15s ease forwards;

}

.action-menu button {
  display: block;
  width: 100%;
  border: none;
  background: transparent;
  text-align: left;
  padding: 10px 14px;
  font-size: 14px;
  cursor: pointer;
  color: #333;
  transition: background 0.2s;
}
.action-menu button:hover {
  background: #f1f5f9;
}

/* subtle appear animation */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.table-responsive {
  overflow: visible;
  position: relative;
}

</style>

<!-- =========================
     JavaScript: modal, edit prefill, volunteers fetch, delete confirm
     ========================= -->
<script>
// Utility: open modal by id
function showModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.setAttribute('aria-hidden', 'false');
}
function hideModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.setAttribute('aria-hidden', 'true');
}

/* Create modal controls */
function openCreateEventModal() { showModal('createEventModal'); }
function closeCreateEventModal() { hideModal('createEventModal'); }

/* Edit modal: fetch event data from row and fill form */
function openEditModal(eventId) {
  // find the row
  const row = document.getElementById('event-row-' + eventId);
  if (!row) { alert('Event not found.'); return; }

  // Grab data from the row columns
  const cols = row.getElementsByTagName('td');
  const title = cols[0].innerText.trim();
  const description = cols[1].innerText.trim();
  const date = cols[2].innerText.trim();
  const location = cols[3].innerText.trim();

  document.getElementById('edit_event_id').value = eventId;
  document.getElementById('edit_title').value = title;
  document.getElementById('edit_description').value = description.replace(/<br\s*\/?>/gi, "\n");
  document.getElementById('edit_date').value = date;
  document.getElementById('edit_location').value = location;

  showModal('editEventModal');
}
function closeEditModal() { hideModal('editEventModal'); }

function submitEditForm() {
  // allow normal form submit (which will POST to same page and process edit_event)
  return true;
}

/* Volunteer modal fetch */
function openVolunteerModal(eventId) {
  showModal('volunteerModal');
  const tbody = document.querySelector('#volunteerTable tbody');
  tbody.innerHTML = '<tr><td colspan="3">Loading...</td></tr>';

  fetch('fetch_applicants.php?event_id=' + encodeURIComponent(eventId))
    .then(res => res.text())
    .then(html => {
      tbody.innerHTML = html;

      // attach accept / reject listeners (these buttons are produced by fetch_applicants)
      document.querySelectorAll('.accept-btn, .reject-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const action = this.dataset.action; // accept / reject
          const volunteer = this.dataset.volunteer;
          const payload = new URLSearchParams();
          payload.append('event_id', eventId);
          payload.append('volunteer_id', volunteer);
          payload.append('action', action);

          fetch('update_application.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload.toString()
          }).then(() => openVolunteerModal(eventId));
        });
      });
    })
    .catch(err => {
      tbody.innerHTML = '<tr><td colspan="3">Failed to load volunteers.</td></tr>';
    });
}
function closeVolunteerModal() { hideModal('volunteerModal'); }

/* Geolocation for create modal */
function getLocation() {
  if (!navigator.geolocation) { alert('Geolocation not supported.'); return; }
  navigator.geolocation.getCurrentPosition(pos => {
    document.getElementById('latitude').value = pos.coords.latitude.toFixed(6);
    document.getElementById('longitude').value = pos.coords.longitude.toFixed(6);
  }, err => {
    alert('Unable to retrieve location. Allow browser permissions or enter manually.');
  }, { enableHighAccuracy: true, timeout: 10000 });
}

/* Delete confirmation */
function confirmDelete(eventId) {
  if (!confirm('Delete this event? This action cannot be undone.')) return;
  const f = document.createElement('form');
  f.method = 'POST';
  f.action = 'delete_item.php';
  f.style.display = 'none';
  f.innerHTML = '<input type="hidden" name="type" value="event"><input type="hidden" name="id" value="' + eventId + '">';
  document.body.appendChild(f);
  f.submit();
}

// Toggle 3-dot dropdown menu
function toggleActionMenu(btn) {
  const menu = btn.nextElementSibling;
  const isVisible = menu.style.display === "block";

  // close all first
  document.querySelectorAll('.action-menu').forEach(m => m.style.display = "none");

  // then toggle this one
  if (!isVisible) menu.style.display = "block";
}

// Close dropdown when clicking outside
window.addEventListener('click', e => {
  if (!e.target.closest('.dropdown')) {
    document.querySelectorAll('.action-menu').forEach(m => m.style.display = "none");
  }
});

/* Close modal by clicking backdrop already handled by backdrop onclick attribute above */
</script>

<?php include '../includes/footer.php'; ?>
