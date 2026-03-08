<?php
/**
 * New File: application_history.php
 * Location: volunteer/application_history.php
 *
 * Shows all events the volunteer has applied to, with status and actions.
 */

include '../conn.php';
include './check_session.php';

$pageTitle = "Application History";
include '../includes/header_volunteer.php';

$user_id = $_SESSION['user_id'];

/* ── Fetch all applications for this volunteer ─────────────────────────────── */
$stmt = $conn->prepare("
    SELECT
        va.id          AS app_id,
        va.status,
        va.applied_at,
        e.id           AS event_id,
        e.title        AS event_title,
        e.date         AS event_date,
        e.location     AS event_location,
        e.status       AS event_status,
        o.name         AS org_name
    FROM volunteer_applications va
    JOIN events e ON e.id = va.event_id
    LEFT JOIN organizations o ON o.id = e.organization_id
    WHERE va.user_id = ?
    ORDER BY va.applied_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applications = $stmt->get_result();
$total = $applications->num_rows;

/* ── Count by status ───────────────────────────────────────────────────────── */
$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$rows   = [];
while ($r = $applications->fetch_assoc()) {
    $rows[] = $r;
    $s = strtolower($r['status']);
    if (isset($counts[$s])) $counts[$s]++;
}
?>

<style>
/* ═══════════════════════════════════════════════════════
   Application History — Page Styles
   ═══════════════════════════════════════════════════════ */
.ah-page { padding: 24px 28px 56px; }

.ah-header { margin-bottom: 24px; }
.ah-title {
  font-size: 1.35rem; font-weight: 700; color: #1a1f2e;
  display: flex; align-items: center; gap: 9px; margin: 0 0 4px;
}
.ah-title i { color: #2d8653; }
.ah-subtitle { font-size: .875rem; color: #64748b; margin: 0; }

/* Stats row */
.ah-stats {
  display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 22px;
}
.ah-stat {
  flex: 1; min-width: 120px;
  background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
  padding: 14px 18px; display: flex; align-items: center; gap: 12px;
  box-shadow: 0 1px 4px rgba(0,0,0,.04);
}
.ah-stat-icon {
  width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: .95rem;
}
.ah-stat-icon.total   { background: #eff6ff; color: #2563eb; }
.ah-stat-icon.pending { background: #fef9c3; color: #92400e; }
.ah-stat-icon.approved{ background: #dcfce7; color: #16a34a; }
.ah-stat-icon.rejected{ background: #fee2e2; color: #dc2626; }
.ah-stat-label { font-size: .72rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 2px; }
.ah-stat-value { font-size: 1.45rem; font-weight: 800; color: #1a1f2e; line-height: 1; }

/* Filter bar */
.ah-filter-bar {
  display: flex; align-items: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap;
}
.ah-filter-label { font-size: .8rem; font-weight: 600; color: #64748b; }
.ah-filter-btn {
  padding: 6px 14px; border-radius: 99px; font-size: .8rem; font-weight: 600;
  border: 1.5px solid #e2e8f0; background: #fff; color: #374151; cursor: pointer;
  transition: all .15s;
}
.ah-filter-btn:hover { background: #f0fdf4; border-color: #86efac; color: #16a34a; }
.ah-filter-btn.active { background: #2d8653; border-color: #2d8653; color: #fff; }

/* Table card */
.ah-card {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
  box-shadow: 0 1px 4px rgba(0,0,0,.05); overflow: hidden;
}
.ah-table-wrap { overflow-x: auto; }
.ah-table {
  width: 100%; border-collapse: collapse; font-size: .875rem;
}
.ah-table thead th {
  padding: 13px 16px; background: #fafbfc; border-bottom: 2px solid #f1f5f9;
  font-size: .72rem; font-weight: 700; color: #64748b; text-transform: uppercase;
  letter-spacing: .05em; white-space: nowrap; text-align: left;
}
.ah-table tbody tr { border-bottom: 1px solid #f8fafc; transition: background .12s; }
.ah-table tbody tr:last-child { border-bottom: none; }
.ah-table tbody tr:hover { background: #f8fdf9; }
.ah-table td { padding: 14px 16px; vertical-align: middle; color: #374151; }

/* Event name cell */
.ah-event-title { font-weight: 600; color: #1a1f2e; margin: 0 0 3px; }
.ah-event-org   { font-size: .78rem; color: #64748b; display: flex; align-items: center; gap: 4px; }

/* Date cell */
.ah-date-pill {
  display: inline-flex; align-items: center; gap: 5px;
  background: #f1f5f9; color: #475569; border-radius: 6px;
  padding: 4px 10px; font-size: .78rem; font-weight: 600; white-space: nowrap;
}

/* Status badges */
.ah-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 12px; border-radius: 99px; font-size: .78rem; font-weight: 700;
  white-space: nowrap;
}
.ah-badge-pending  { background: #fef9c3; color: #78350f; }
.ah-badge-approved { background: #dcfce7; color: #15803d; }
.ah-badge-rejected { background: #fee2e2; color: #7f1d1d; }

/* View button */
.ah-view-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 6px 14px; background: #f0fdf4; color: #15803d;
  border: 1.5px solid #bbf7d0; border-radius: 8px; font-size: .8rem;
  font-weight: 600; text-decoration: none; transition: all .15s; white-space: nowrap;
}
.ah-view-btn:hover { background: #2d8653; color: #fff; border-color: #2d8653; }

/* Applied date */
.ah-applied-time { font-size: .78rem; color: #94a3b8; }

/* Empty state */
.ah-empty {
  text-align: center; padding: 56px 24px; color: #94a3b8;
}
.ah-empty i { font-size: 2.5rem; color: #dde3ea; display: block; margin-bottom: 12px; }
.ah-empty h3 { font-size: 1rem; font-weight: 700; color: #374151; margin: 0 0 6px; }
.ah-empty p  { font-size: .875rem; margin: 0 0 18px; }
.ah-empty a  {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 10px 22px; background: linear-gradient(135deg, #2d8653, #1a5c3a);
  color: #fff; border-radius: 9px; font-size: .875rem; font-weight: 700;
  text-decoration: none; transition: opacity .15s;
}
.ah-empty a:hover { opacity: .88; }

@media (max-width: 640px) {
  .ah-page { padding: 16px 16px 48px; }
  .ah-stats { gap: 10px; }
  .ah-stat  { min-width: 100%; }
}
</style>

<div class="ah-page">

  <!-- Header -->
  <div class="ah-header">
    <h1 class="ah-title"><i class="fas fa-clipboard-list"></i> Application History</h1>
    <p class="ah-subtitle">Track the status of all your event applications.</p>
  </div>

  <!-- Stat cards -->
  <div class="ah-stats">
    <div class="ah-stat">
      <div class="ah-stat-icon total"><i class="fas fa-list"></i></div>
      <div>
        <div class="ah-stat-label">Total</div>
        <div class="ah-stat-value"><?= $total ?></div>
      </div>
    </div>
    <div class="ah-stat">
      <div class="ah-stat-icon pending"><i class="fas fa-clock"></i></div>
      <div>
        <div class="ah-stat-label">Pending</div>
        <div class="ah-stat-value"><?= $counts['pending'] ?></div>
      </div>
    </div>
    <div class="ah-stat">
      <div class="ah-stat-icon approved"><i class="fas fa-check-circle"></i></div>
      <div>
        <div class="ah-stat-label">Approved</div>
        <div class="ah-stat-value"><?= $counts['approved'] ?></div>
      </div>
    </div>
    <div class="ah-stat">
      <div class="ah-stat-icon rejected"><i class="fas fa-times-circle"></i></div>
      <div>
        <div class="ah-stat-label">Rejected</div>
        <div class="ah-stat-value"><?= $counts['rejected'] ?></div>
      </div>
    </div>
  </div>

  <!-- Filter buttons -->
  <div class="ah-filter-bar">
    <span class="ah-filter-label">Filter:</span>
    <button class="ah-filter-btn active" onclick="filterRows('all', this)">All</button>
    <button class="ah-filter-btn" onclick="filterRows('pending', this)">Pending</button>
    <button class="ah-filter-btn" onclick="filterRows('approved', this)">Approved</button>
    <button class="ah-filter-btn" onclick="filterRows('rejected', this)">Rejected</button>
  </div>

  <!-- Table -->
  <div class="ah-card">
    <?php if (empty($rows)): ?>
      <div class="ah-empty">
        <i class="fas fa-clipboard"></i>
        <h3>No applications yet</h3>
        <p>You haven't applied to any events. Browse available events and get started!</p>
        <a href="/VolunteerHub/volunteer/list_events.php">
          <i class="fas fa-calendar-alt"></i> Browse Events
        </a>
      </div>
    <?php else: ?>
      <div class="ah-table-wrap">
        <table class="ah-table" id="appTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Event</th>
              <th>Event Date</th>
              <th>Applied On</th>
              <th>Status</th>
              <th style="text-align:center;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $r):
              $status    = strtolower($r['status']);
              $badgeClass = 'ah-badge-' . $status;
              $badgeIcon  = match($status) {
                'approved' => 'fa-check-circle',
                'rejected' => 'fa-times-circle',
                default    => 'fa-clock',
              };
              $statusLabel = ucfirst($status);
              $eventDate   = (!empty($r['event_date']) && $r['event_date'] !== '0000-00-00')
                             ? date('M d, Y', strtotime($r['event_date']))
                             : '—';
              $appliedDate = date('M d, Y', strtotime($r['applied_at']));
            ?>
            <tr data-status="<?= htmlspecialchars($status) ?>">
              <td style="color:#94a3b8;font-weight:700;font-size:.78rem;"><?= $i + 1 ?></td>
              <td>
                <p class="ah-event-title"><?= htmlspecialchars($r['event_title']) ?></p>
                <?php if (!empty($r['org_name'])): ?>
                  <span class="ah-event-org">
                    <i class="fas fa-building" style="color:#2d8653;font-size:.7rem;"></i>
                    <?= htmlspecialchars($r['org_name']) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <span class="ah-date-pill">
                  <i class="fas fa-calendar" style="color:#2d8653;"></i>
                  <?= $eventDate ?>
                </span>
              </td>
              <td>
                <span class="ah-applied-time">
                  <i class="fas fa-paper-plane" style="margin-right:4px;"></i>
                  <?= $appliedDate ?>
                </span>
              </td>
              <td>
                <span class="ah-badge <?= $badgeClass ?>">
                  <i class="fas <?= $badgeIcon ?>"></i>
                  <?= $statusLabel ?>
                </span>
              </td>
              <td style="text-align:center;">
                <a href="/VolunteerHub/volunteer/list_events.php"
                   class="ah-view-btn">
                  <i class="fas fa-eye"></i> View Events
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div><!-- /.ah-page -->

<script>
function filterRows(status, btn) {
  // Update active button
  document.querySelectorAll('.ah-filter-btn').forEach(function(b) {
    b.classList.remove('active');
  });
  btn.classList.add('active');

  // Show/hide rows
  document.querySelectorAll('#appTable tbody tr').forEach(function(row) {
    if (status === 'all' || row.dataset.status === status) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}
</script>

<?php include '../includes/footer.php'; ?>