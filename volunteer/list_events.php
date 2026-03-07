<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "Available Events";
$pageCSS   = "/VolunteerHub/styles/volunteer_layout.css";
include '../includes/header_volunteer.php';

$user_id = $_SESSION['user_id'];

// Handle sign-up POST (preserve existing logic)
$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
    $event_id = intval($_POST['event_id']);

    $chk = $conn->prepare("SELECT id FROM volunteer_applications WHERE user_id = ? AND event_id = ?");
    $chk->bind_param("ii", $user_id, $event_id);
    $chk->execute();

    if ($chk->get_result()->num_rows > 0) {
        $alert = ['type' => 'warning', 'msg' => 'You have already applied for this event.'];
    } else {
        $ins = $conn->prepare("INSERT INTO volunteer_applications (user_id, event_id, status) VALUES (?, ?, 'pending')");
        $ins->bind_param("ii", $user_id, $event_id);
        $alert = $ins->execute()
            ? ['type' => 'success', 'msg' => 'Application submitted! You\'ll be notified once approved.']
            : ['type' => 'danger',  'msg' => 'Something went wrong. Please try again.'];
    }
}

// Fetch open events not yet applied to
$stmt = $conn->prepare("
    SELECT e.id, e.title, e.description, e.date, e.location, u.name AS created_by,
           o.name AS org_name
    FROM events e
    JOIN users u ON e.created_by = u.id
    LEFT JOIN organizations o ON o.id = e.organization_id
    WHERE e.status = 'Open'
      AND e.id NOT IN (
          SELECT event_id FROM volunteer_applications WHERE user_id = ?
      )
    ORDER BY e.date ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$eventsResult = $stmt->get_result();
?>

<style>
.events-page { padding: 28px 28px 60px; }

/* Header */
.events-header { margin-bottom: 28px; display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.events-title { font-size: 1.45rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 10px; margin: 0 0 4px; }
.events-title i { color: var(--green-mid); }
.events-subtitle { font-size: .875rem; color: var(--text-muted); margin: 0; }

/* Alert */
.ev-alert {
  display: flex; align-items: center; gap: 10px;
  padding: 13px 18px; border-radius: var(--radius-md); margin-bottom: 20px;
  font-size: .875rem; font-weight: 500;
}
.ev-alert-success { background: #dcfce7; color: #14532d; border: 1px solid #bbf7d0; }
.ev-alert-warning { background: #fef9c3; color: #78350f; border: 1px solid #fde68a; }
.ev-alert-danger  { background: #fee2e2; color: #7f1d1d; border: 1px solid #fecaca; }

/* Table wrapper */
.ev-table-wrap {
  background: #fff; border: 1px solid var(--border);
  border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
  overflow: hidden; overflow-x: auto;
}

/* Table */
.ev-table { width: 100%; border-collapse: collapse; font-size: .875rem; min-width: 700px; }
.ev-table thead tr {
  background: linear-gradient(135deg, var(--green-dark, #1a5c3a), var(--green-mid, #2d8653));
  color: #fff;
}
.ev-table thead th {
  padding: 14px 18px; font-weight: 600; font-size: .8rem;
  text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; border: none;
}
.ev-table tbody tr { border-bottom: 1px solid var(--border-light); transition: background var(--transition); }
.ev-table tbody tr:last-child { border-bottom: none; }
.ev-table tbody tr:nth-child(even) { background: #fafcff; }
.ev-table tbody tr:hover { background: var(--green-soft, #e8f5ee); }
.ev-table td { padding: 14px 18px; vertical-align: middle; color: var(--text-primary); }

/* Event title in cell */
.ev-cell-title { font-weight: 600; color: var(--text-primary); margin-bottom: 3px; }
.ev-cell-org { font-size: .75rem; color: var(--text-muted); }

/* Date pill */
.ev-date-pill {
  display: inline-flex; align-items: center; gap: 5px;
  background: #eff6ff; color: #1e40af;
  padding: 4px 10px; border-radius: 99px; font-size: .75rem; font-weight: 600;
}

/* Location */
.ev-location { display: flex; align-items: center; gap: 5px; font-size: .83rem; }
.ev-location i { color: #dc2626; font-size: .75rem; }

/* Description truncate */
.ev-desc { font-size: .82rem; color: var(--text-muted); max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Apply button */
.btn-apply {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px; border: none; border-radius: var(--radius-sm);
  background: linear-gradient(135deg, var(--green-mid, #2d8653), var(--green-dark, #1a5c3a));
  color: #fff; font-size: .82rem; font-weight: 600; cursor: pointer;
  white-space: nowrap; transition: opacity .2s;
}
.btn-apply:hover { opacity: .88; }

/* Empty state */
.ev-empty { padding: 60px; text-align: center; color: var(--text-muted); }
.ev-empty i { font-size: 3rem; opacity: .3; display: block; margin-bottom: 14px; }
</style>

<div class="events-page">

  <div class="events-header">
    <div>
      <h1 class="events-title"><i class="fas fa-calendar-alt"></i> Available Events</h1>
      <p class="events-subtitle">Browse and apply to open volunteer events.</p>
    </div>
  </div>

  <?php if (!empty($alert)): ?>
    <div class="ev-alert ev-alert-<?= $alert['type'] ?>">
      <i class="fas <?= $alert['type'] === 'success' ? 'fa-check-circle' : ($alert['type'] === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
      <?= htmlspecialchars($alert['msg']) ?>
    </div>
  <?php endif; ?>

  <div class="ev-table-wrap">
    <?php if ($eventsResult->num_rows > 0): ?>
      <table class="ev-table">
        <thead>
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th>Location</th>
            <th>Description</th>
            <th>Organizer</th>
            <th style="text-align:center;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $eventsResult->fetch_assoc()): ?>
            <tr>
              <td>
                <div class="ev-cell-title"><?= htmlspecialchars($row['title']) ?></div>
                <?php if (!empty($row['org_name'])): ?>
                  <div class="ev-cell-org"><i class="fas fa-building me-1"></i><?= htmlspecialchars($row['org_name']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <span class="ev-date-pill">
                  <i class="fas fa-calendar"></i>
                  <?= date('M d, Y', strtotime($row['date'])) ?>
                </span>
              </td>
              <td>
                <div class="ev-location">
                  <i class="fas fa-map-marker-alt"></i>
                  <?= htmlspecialchars($row['location']) ?>
                </div>
              </td>
              <td><div class="ev-desc"><?= htmlspecialchars($row['description']) ?></div></td>
              <td><?= htmlspecialchars($row['created_by']) ?></td>
              <td style="text-align:center;">
                <form method="POST" action="" onsubmit="return confirm('Apply to this event?')">
                  <input type="hidden" name="event_id" value="<?= (int)$row['id'] ?>">
                  <button type="submit" class="btn-apply">
                    <i class="fas fa-paper-plane"></i> Apply
                  </button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="ev-empty">
        <i class="fas fa-calendar-times"></i>
        <p>No open events available right now. Check back later!</p>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php include '../includes/footer.php'; ?>