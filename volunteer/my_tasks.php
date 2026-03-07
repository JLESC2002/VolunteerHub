<?php
include '../conn.php';
include './check_session.php';

// ── Must be resolved before ANY output ───────────────────────────────────────
$volunteer_id = $_SESSION['volunteer_id'] ?? $_SESSION['user_id'] ?? null;

// ── Page setup ─────────────────────────────────────────────────────────────────
$pageTitle = "My Tasks";
$pageCSS   = "/VolunteerHub/styles/volunteer_layout.css";
include '../includes/header_volunteer.php';

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
/* Empty */
.tasks-empty { padding: 60px; text-align: center; color: var(--text-muted); }
.tasks-empty i { font-size: 3rem; opacity: .3; display: block; margin-bottom: 14px; }
/* ── Attendance Action Buttons ─────────────────────────── */
.btn-action {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 13px; border: none; border-radius: var(--radius-sm);
  font-size: .78rem; font-weight: 600; cursor: pointer;
  text-decoration: none; transition: opacity .18s, transform .12s;
  white-space: nowrap;
}
.btn-action:hover { opacity: .85; transform: translateY(-1px); }

.btn-checkin  { background: #dcfce7; color: #15803d; }
.btn-checkout { background: #dbeafe; color: #1d4ed8; }
.btn-cert     { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; }
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
            <th style="min-width: 130px; white-space: nowrap;">Date</th>
            <th>Location</th>
            <th>Attendance</th>
            <th>Status</th>
            <th style="min-width:140px; white-space:nowrap;">Attendance Action</th>
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
              <td style="white-space: nowrap;">
                <span style="font-size:.8rem; background:#eff6ff; color:#1e40af; padding:4px 11px; border-radius:99px; font-weight:600; white-space: nowrap; display:inline-flex; align-items:center; gap:5px;">
                  <i class="fas fa-calendar"></i><?= date('M d, Y', strtotime($row['date'])) ?>
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

              <!-- ── Attendance Action Cell ─────────────────── -->
              <td>
                <?php
                  $att       = (int)$row['attended'];
                  $checkedIn  = ($att >= 1 && !empty($row['check_in']));
                  $checkedOut = ($att == 2 && !empty($row['check_out']));
                  $eventId    = (int)$row['event_id'];
                ?>

                <?php if ($checkedOut): ?>
                  <!-- Fully done: show Download Certificate button -->
                  <a href="/VolunteerHub/volunteer/generate_acknowledgement.php?event_id=<?= $eventId ?>"
                     target="_blank"
                     class="btn-action btn-cert"
                     title="Download Certificate of Acknowledgement">
                    <i class="fas fa-file-certificate"></i> Certificate
                  </a>

                <?php elseif ($checkedIn): ?>
                  <!-- Checked in — offer Check-Out scan -->
                  <button class="btn-action btn-checkout"
                          onclick="openScanner(<?= $eventId ?>)"
                          title="Scan Check-Out QR">
                    <i class="fas fa-sign-out-alt"></i> Check-Out
                  </button>

                <?php else: ?>
                  <!-- Not checked in yet — offer Check-In scan -->
                  <button class="btn-action btn-checkin"
                          onclick="openScanner(<?= $eventId ?>)"
                          title="Scan Check-In QR">
                    <i class="fas fa-qrcode"></i> Check-In
                  </button>

                <?php endif; ?>
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
<!-- ══════════════════════════════════════════════
     QR SCANNER MODAL
═════════════════════════════════════════════════ -->
<div id="qrScannerModal" style="
  display:none; position:fixed; inset:0; z-index:9999;
  background:rgba(0,0,0,.65); backdrop-filter:blur(4px);
  align-items:center; justify-content:center;">

  <div style="
    background:#fff; border-radius:16px; padding:28px 28px 24px;
    width:min(420px,92vw); box-shadow:0 20px 60px rgba(0,0,0,.35);
    position:relative; text-align:center;">

    <!-- Close -->
    <button onclick="closeScanner()" style="
      position:absolute; top:14px; right:16px;
      background:none; border:none; font-size:1.3rem;
      color:#94a3b8; cursor:pointer;">
      <i class="fas fa-times"></i>
    </button>

    <h5 style="font-weight:700; color:#1a1f2e; margin:0 0 4px;">
      <i class="fas fa-qrcode" style="color:#2d8653; margin-right:6px;"></i>
      Scan Event QR Code
    </h5>
    <p style="font-size:.83rem; color:#64748b; margin:0 0 18px;">
      Point your camera at the Check-In or Check-Out QR code at the event.
    </p>

    <!-- Camera Preview -->
    <div style="position:relative; border-radius:12px; overflow:hidden;
                background:#0f172a; width:100%; aspect-ratio:1/1; margin-bottom:16px;">
      <video id="qrVideo" style="width:100%; height:100%; object-fit:cover;" playsinline></video>
      <!-- Scanning overlay reticle -->
      <div style="
        position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
        pointer-events:none;">
        <div style="
          width:58%; aspect-ratio:1/1; border:3px solid rgba(45,134,83,.85);
          border-radius:10px; box-shadow:0 0 0 2000px rgba(0,0,0,.25);">
        </div>
      </div>
    </div>

    <!-- Status -->
    <div id="qrStatus" style="
      min-height:38px; font-size:.85rem; font-weight:600;
      display:flex; align-items:center; justify-content:center; gap:8px;
      color:#64748b;">
      <i class="fas fa-circle-notch fa-spin"></i> Initialising camera…
    </div>
  </div>
</div>

<!-- jsQR (lightweight, no CDN key required) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsqr/1.4.0/jsQR.min.js"></script>

<script>
/* ── State ─────────────────────────────────────────────── */
let _stream      = null;
let _rafId       = null;
let _scanning    = false;
let _activeEvent = null;
const _canvas    = document.createElement('canvas');
const _ctx       = _canvas.getContext('2d');

/* ── Open modal ─────────────────────────────────────────── */
function openScanner(eventId) {
  _activeEvent = eventId;
  const modal = document.getElementById('qrScannerModal');
  modal.style.display = 'flex';
  setStatus('scanning', '<i class="fas fa-circle-notch fa-spin"></i> Starting camera…');
  startCamera();
}

/* ── Close modal ────────────────────────────────────────── */
function closeScanner() {
  stopCamera();
  document.getElementById('qrScannerModal').style.display = 'none';
  _activeEvent = null;
  _scanning    = false;
}

/* ── Camera helpers ─────────────────────────────────────── */
async function startCamera() {
  const video = document.getElementById('qrVideo');
  try {
    _stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 640 } }
    });
    video.srcObject = _stream;
    await video.play();
    _scanning = true;
    setStatus('scanning', '<i class="fas fa-search" style="color:#2d8653;"></i> Scanning… hold QR steady');
    requestAnimationFrame(scanFrame);
  } catch(err) {
    setStatus('error', '<i class="fas fa-exclamation-triangle" style="color:#dc2626;"></i> Camera access denied. Please allow camera permission.');
  }
}

function stopCamera() {
  _scanning = false;
  cancelAnimationFrame(_rafId);
  if (_stream) { _stream.getTracks().forEach(t => t.stop()); _stream = null; }
  const video = document.getElementById('qrVideo');
  video.srcObject = null;
}

/* ── Scan loop ──────────────────────────────────────────── */
function scanFrame() {
  if (!_scanning) return;
  const video = document.getElementById('qrVideo');

  if (video.readyState === video.HAVE_ENOUGH_DATA) {
    _canvas.width  = video.videoWidth;
    _canvas.height = video.videoHeight;
    _ctx.drawImage(video, 0, 0);

    const imageData = _ctx.getImageData(0, 0, _canvas.width, _canvas.height);
    const code      = jsQR(imageData.data, imageData.width, imageData.height, {
      inversionAttempts: 'dontInvert'
    });

    if (code) {
      _scanning = false; // stop further scans immediately
      processQR(code.data);
      return;
    }
  }
  _rafId = requestAnimationFrame(scanFrame);
}

/* ── Process decoded QR ─────────────────────────────────── */
function processQR(raw) {
  let parsed;
  try { parsed = JSON.parse(raw); } catch(e) {
    setStatus('error', '<i class="fas fa-times-circle" style="color:#dc2626;"></i> Invalid QR Code. Please scan an event QR.');
    setTimeout(() => { _scanning = true; requestAnimationFrame(scanFrame); }, 2000);
    return;
  }

  // Validate fields
  if (!parsed.event_id || !parsed.type || !['checkin','checkout'].includes(parsed.type)) {
    setStatus('error', '<i class="fas fa-times-circle" style="color:#dc2626;"></i> Unrecognised QR. Please use the event QR code.');
    setTimeout(() => { _scanning = true; requestAnimationFrame(scanFrame); }, 2000);
    return;
  }

  // Guard: scanned QR event must match the row's event
  if (parseInt(parsed.event_id) !== parseInt(_activeEvent)) {
    setStatus('error', '<i class="fas fa-exclamation-circle" style="color:#f59e0b;"></i> Wrong event QR. Please scan the correct event code.');
    setTimeout(() => { _scanning = true; requestAnimationFrame(scanFrame); }, 2500);
    return;
  }

  const attendedValue = parsed.type === 'checkin' ? 1 : 2;
  setStatus('scanning', '<i class="fas fa-circle-notch fa-spin" style="color:#2d8653;"></i> Processing…');
  submitAttendance(parsed.event_id, attendedValue);
}

/* ── POST to update_attendance.php ──────────────────────── */
function submitAttendance(eventId, attendedValue) {
  const body = new URLSearchParams({
    event_id:     eventId,
    volunteer_id: <?= (int)$volunteer_id ?>,
    attended:     attendedValue
  });

  fetch('/VolunteerHub/admin/update_attendance.php', {
    method:      'POST',
    credentials: 'same-origin',
    headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:        body.toString()
  })
  .then(async res => {
    const text = await res.text();
    if (!res.ok) throw new Error(text);

    const label  = attendedValue === 1 ? 'Checked In' : 'Checked Out';
    const icon   = attendedValue === 1 ? 'fa-sign-in-alt' : 'fa-sign-out-alt';
    const colour = attendedValue === 1 ? '#15803d'        : '#1d4ed8';

    setStatus('success',
      `<i class="fas ${icon}" style="color:${colour};"></i>
       <span style="color:${colour};">Successfully ${label}!</span>`
    );

    stopCamera();
    // Reload page after brief success display so button state updates
    setTimeout(() => { closeScanner(); location.reload(); }, 1800);
  })
  .catch(err => {
    const msg = err.message || 'Server error. Please try again.';
    setStatus('error', `<i class="fas fa-times-circle" style="color:#dc2626;"></i> ${msg}`);
    // Allow retry
    setTimeout(() => {
      setStatus('scanning', '<i class="fas fa-search" style="color:#2d8653;"></i> Scanning… hold QR steady');
      _scanning = true;
      requestAnimationFrame(scanFrame);
    }, 3000);
  });
}

/* ── Status helper ──────────────────────────────────────── */
function setStatus(type, html) {
  document.getElementById('qrStatus').innerHTML = html;
}

/* ── Close on backdrop click ────────────────────────────── */
document.getElementById('qrScannerModal').addEventListener('click', function(e) {
  if (e.target === this) closeScanner();
});
</script>
<?php include '../includes/footer.php'; ?>