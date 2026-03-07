<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "Organization Profile";
$pageCSS   = "/VolunteerHub/styles/volunteer_layout.css";
include '../includes/header_volunteer.php';

if (!isset($_GET['id'])) {
    echo "<div class='alert alert-danger m-4'>Organization not found.</div>";
    include '../includes/footer.php'; exit;
}

$organization_id = intval($_GET['id']);

// ── Organization details ──────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT id, name, description, location, contact_email, contact_phone,
           gcash_qr, gcash_name, gcash_number,
           bank_name, bank_account_name, bank_account_number,
           dropoff_location, dropoff_instructions,
           logo, facebook_link, admin_id
    FROM organizations WHERE id = ?
");
$stmt->bind_param("i", $organization_id);
$stmt->execute();
$org_result = $stmt->get_result();

if ($org_result->num_rows === 0) {
    echo "<div class='alert alert-danger m-4'>Organization not found.</div>";
    include '../includes/footer.php'; exit;
}
$org      = $org_result->fetch_assoc();
$admin_id = (int)$org['admin_id'];

// Logo path (stored directly in uploads/, NOT uploads/logo/)
$logoFile = trim($org['logo'] ?? '');
$hasLogo  = $logoFile !== '' && file_exists('../uploads/' . $logoFile);
$logoSrc  = '/VolunteerHub/uploads/' . htmlspecialchars($logoFile);

// ── Event counts ──────────────────────────────────────────────────────────────
$cnt = $conn->prepare("SELECT COUNT(*) AS total,
    SUM(status IN ('Open','Ongoing')) AS upcoming,
    SUM(status = 'Completed') AS completed
    FROM events WHERE created_by = ?");
$cnt->bind_param("i", $admin_id); $cnt->execute();
$counts = $cnt->get_result()->fetch_assoc();
$totalEvents    = (int)($counts['total']    ?? 0);
$upcomingEvents = (int)($counts['upcoming'] ?? 0);
$completedCount = (int)($counts['completed']?? 0);

// ── Current & upcoming events ─────────────────────────────────────────────────
$cur = $conn->prepare("
    SELECT id, title, date, location, status FROM events
    WHERE created_by = ? AND status IN ('Open','Ongoing')
    ORDER BY date ASC
");
$cur->bind_param("i", $admin_id); $cur->execute();
$currentEvents = $cur->get_result();

// ── Past events ───────────────────────────────────────────────────────────────
$past = $conn->prepare("
    SELECT id, title, date, location FROM events
    WHERE created_by = ? AND status = 'Completed'
    ORDER BY date DESC LIMIT 8
");
$past->bind_param("i", $admin_id); $past->execute();
$pastEvents = $past->get_result();

// ── Volunteer donation history ────────────────────────────────────────────────
$uid = $_SESSION['user_id'];
$dq = $conn->prepare("
    SELECT 'GCash' AS method, amount, reference_number, status, created_at
      FROM gcash_donations WHERE user_id=? AND organization_id=?
    UNION ALL
    SELECT 'Bank Transfer', amount, reference_number, status, created_at
      FROM bank_payments WHERE user_id=? AND organization_id=?
    ORDER BY created_at DESC
");
$dq->bind_param("iiii", $uid, $organization_id, $uid, $organization_id);
$dq->execute();
$donations = $dq->get_result();

// ── Item donations ────────────────────────────────────────────────────────────
$iq = $conn->prepare("
    SELECT item_category, item_description, quantity, dropoff_date, status, created_at
    FROM dropoff_donations WHERE user_id=? AND organization_id=?
    ORDER BY created_at DESC
");
$iq->bind_param("ii", $uid, $organization_id);
$iq->execute();
$items = $iq->get_result();

$hasDonations = ($donations->num_rows > 0 || $items->num_rows > 0);
?>

<style>
/* ─── Layout ──────────────────────────────────────────── */
.op-page { padding: 24px 24px 60px; }

/* Hero banner */
.op-hero {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
  margin-bottom: 22px;
}
.op-hero-banner {
  height: 90px;
  background: linear-gradient(135deg, var(--green-dark) 0%, var(--green-mid) 55%, var(--green-light) 100%);
}
.op-hero-body {
  display: flex; align-items: flex-start; gap: 20px; flex-wrap: wrap;
  padding: 0 26px 22px;
}
.op-hero-logo-wrap { margin-top: -38px; flex-shrink: 0; }
.op-hero-logo,
.op-hero-logo-ph {
  width: 78px; height: 78px;
  border-radius: var(--radius-md);
  border: 3px solid #fff;
  box-shadow: var(--shadow-md);
}
.op-hero-logo { object-fit: cover; display: block; background: #fff; }
.op-hero-logo-ph {
  background: var(--green-soft); color: var(--green-mid);
  display: flex; align-items: center; justify-content: center; font-size: 2rem;
}
.op-hero-info { flex: 1; min-width: 200px; padding-top: 14px; }
.op-hero-name { font-size: 1.35rem; font-weight: 700; color: var(--text-primary); margin: 0 0 6px; }
.op-hero-meta { display: flex; flex-wrap: wrap; gap: 12px; }
.op-meta-chip {
  font-size: .78rem; color: var(--text-muted);
  display: flex; align-items: center; gap: 5px;
}
.op-meta-chip i { color: var(--green-mid); font-size: .72rem; }
.op-meta-chip a { color: var(--green-mid); text-decoration: none; font-weight: 600; }
.op-meta-chip a:hover { text-decoration: underline; }

/* Stats bar */
.op-stats-bar {
  display: flex; gap: 10px; flex-wrap: wrap;
  padding: 14px 26px;
  background: var(--green-soft);
  border-top: 1px solid var(--border-light);
}
.op-stat {
  display: inline-flex; align-items: center; gap: 7px;
  background: #fff; border: 1px solid var(--border);
  border-radius: 99px; padding: 5px 14px;
  font-size: .78rem; font-weight: 700; color: var(--text-primary);
}
.op-stat i { color: var(--green-mid); font-size: .72rem; }
.op-stat span { color: var(--text-muted); font-weight: 400; margin-left: 2px; }

/* Description card (below hero, full width) */
.op-desc-card {
  background: #fff; border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 18px 24px;
  box-shadow: var(--shadow-sm); margin-bottom: 22px;
  font-size: .88rem; color: var(--text-secondary); line-height: 1.7;
}
.op-desc-card-title {
  font-size: .75rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .07em; color: var(--text-muted); margin-bottom: 8px;
  display: flex; align-items: center; gap: 6px;
}
.op-desc-card-title i { color: var(--green-mid); }

/* ─── Two-column layout ───────────────────────────────── */
.op-two-col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  align-items: start;
  margin-bottom: 20px;
}
@media (max-width: 900px)  { .op-two-col { grid-template-columns: 1fr; } }
@media (max-width: 600px)  { .op-page { padding: 14px 12px 40px; } }

/* Full-width row */
.op-full-row { margin-bottom: 20px; }

/* ─── Section card ────────────────────────────────────── */
.op-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}
.op-card-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 20px;
  border-bottom: 1px solid var(--border-light);
}
.op-card-title {
  font-size: .9rem; font-weight: 700; color: var(--text-primary);
  display: flex; align-items: center; gap: 7px; margin: 0;
}
.op-card-title i { color: var(--green-mid); }
.op-badge {
  font-size: .7rem; font-weight: 700;
  background: var(--green-soft); color: var(--green-dark);
  padding: 3px 9px; border-radius: 99px;
}

/* ─── Event rows ──────────────────────────────────────── */
.op-event-list { list-style: none; margin: 0; padding: 0; }
.op-event-item {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 20px;
  border-bottom: 1px solid var(--border-light);
  transition: background var(--transition);
}
.op-event-item:last-child { border-bottom: none; }
.op-event-item:hover { background: var(--green-soft); }

.op-event-dot {
  width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: .85rem;
}
.dot-open     { background: #dcfce7; color: #16a34a; }
.dot-ongoing  { background: #fef9c3; color: #92400e; }
.dot-past     { background: #f1f5f9; color: #64748b; }

.op-event-info { flex: 1; min-width: 0; }
.op-event-name {
  font-size: .86rem; font-weight: 600; color: var(--text-primary);
  margin: 0 0 2px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.op-event-sub {
  font-size: .73rem; color: var(--text-muted);
  display: flex; gap: 8px; flex-wrap: wrap;
}
.op-event-sub i { margin-right: 2px; }

/* Status pill */
.s-pill {
  display: inline-flex; align-items: center;
  padding: 2px 8px; border-radius: 99px;
  font-size: .68rem; font-weight: 700;
}
.s-open      { background: #dcfce7; color: #15803d; }
.s-ongoing   { background: #fef9c3; color: #92400e; }
.s-completed { background: #f1f5f9; color: #475569; }

/* Apply button */
.btn-apply {
  display: inline-flex; align-items: center; gap: 5px;
  background: var(--green-mid); color: #fff;
  font-size: .74rem; font-weight: 600;
  padding: 5px 12px; border-radius: var(--radius-sm);
  text-decoration: none; flex-shrink: 0;
  transition: background var(--transition);
  white-space: nowrap;
}
.btn-apply:hover { background: var(--green-dark); color: #fff; }

/* Empty state */
.op-empty {
  text-align: center; padding: 32px 20px; color: var(--text-muted);
}
.op-empty i { font-size: 1.8rem; display: block; margin-bottom: 8px; }
.op-empty p { font-size: .84rem; margin: 0; }

/* ─── Contact list ────────────────────────────────────── */
.op-contact-list { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; }
.op-contact-row  { display: flex; align-items: flex-start; gap: 12px; }
.op-contact-icon {
  width: 32px; height: 32px; border-radius: var(--radius-sm);
  background: var(--green-soft); color: var(--green-mid);
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; flex-shrink: 0;
}
.op-contact-label { font-size: .68rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
.op-contact-val   { font-size: .85rem; color: var(--text-primary); font-weight: 500; word-break: break-all; }

/* ─── Donation buttons ────────────────────────────────── */
.op-donate-row {
  display: flex; gap: 10px; flex-wrap: wrap;
  padding: 16px 20px; border-bottom: 1px solid var(--border-light);
}
.btn-don {
  display: inline-flex; align-items: center; gap: 7px;
  font-size: .82rem; font-weight: 600;
  padding: 8px 16px; border-radius: var(--radius-sm);
  border: none; cursor: pointer;
  transition: opacity var(--transition), transform var(--transition);
}
.btn-don:hover { opacity: .87; transform: translateY(-1px); }
.btn-gcash   { background: #0d6dcd; color: #fff; }
.btn-bank    { background: #1a5c3a; color: #fff; }
.btn-dropoff { background: #f59e0b; color: #fff; }

/* ─── Inline donation table (compact) ────────────────── */
.op-don-table { width: 100%; border-collapse: collapse; font-size: .81rem; }
.op-don-table th {
  background: var(--bg-body); padding: 9px 14px;
  font-size: .7rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .05em; color: var(--text-muted);
  text-align: left; border-bottom: 1px solid var(--border);
}
.op-don-table td {
  padding: 9px 14px; border-bottom: 1px solid var(--border-light);
  color: var(--text-primary); vertical-align: middle;
}
.op-don-table tr:last-child td { border-bottom: none; }
.op-don-table tr:hover td { background: var(--green-soft); }
.op-don-table .amt { font-weight: 700; color: var(--green-dark); }

/* ─── Modals ──────────────────────────────────────────── */
.op-modal {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.48); backdrop-filter: blur(3px);
  align-items: center; justify-content: center;
  z-index: 3000; padding: 16px;
}
.op-modal.open { display: flex; }
.op-modal-box {
  background: #fff; border-radius: var(--radius-lg);
  width: 100%; max-width: 440px;
  box-shadow: var(--shadow-lg); overflow: hidden;
  animation: modal-pop .2s ease;
}
@keyframes modal-pop { from { opacity: 0; transform: translateY(14px); } }
.op-modal-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 15px 20px; border-bottom: 1px solid var(--border);
  background: var(--green-soft);
}
.op-modal-head h3 {
  font-size: .92rem; font-weight: 700; color: var(--green-dark);
  margin: 0; display: flex; align-items: center; gap: 7px;
}
.op-modal-x {
  background: none; border: none; font-size: 1rem;
  color: var(--text-muted); cursor: pointer; padding: 4px 8px;
  border-radius: var(--radius-sm);
}
.op-modal-x:hover { background: var(--border); }
.op-modal-body { padding: 18px 20px; }
.op-modal-body label {
  display: block; font-size: .76rem; font-weight: 700;
  color: var(--text-secondary); margin: 12px 0 4px; text-transform: uppercase; letter-spacing: .04em;
}
.op-modal-body label:first-child { margin-top: 0; }
.op-modal-body input,
.op-modal-body textarea,
.op-modal-body select {
  width: 100%; padding: 9px 12px;
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  font-size: .88rem; color: var(--text-primary); outline: none;
  transition: border-color var(--transition);
  font-family: inherit;
}
.op-modal-body input:focus,
.op-modal-body textarea:focus,
.op-modal-body select:focus { border-color: var(--green-mid); }
.op-modal-foot {
  display: flex; gap: 8px; justify-content: flex-end;
  padding: 12px 20px; border-top: 1px solid var(--border-light);
}
.btn-m-submit {
  background: var(--green-mid); color: #fff;
  font-size: .83rem; font-weight: 600;
  padding: 8px 18px; border-radius: var(--radius-sm);
  border: none; cursor: pointer;
  transition: background var(--transition);
}
.btn-m-submit:hover { background: var(--green-dark); }
.btn-m-cancel {
  background: var(--bg-body); color: var(--text-secondary);
  font-size: .83rem; font-weight: 600;
  padding: 8px 16px; border-radius: var(--radius-sm);
  border: 1px solid var(--border); cursor: pointer;
}

/* Tabs inside donation section */
.op-tabs { display: flex; border-bottom: 1px solid var(--border-light); }
.op-tab {
  flex: 1; padding: 10px 8px; font-size: .78rem; font-weight: 600;
  color: var(--text-muted); background: none; border: none;
  cursor: pointer; border-bottom: 2px solid transparent;
  transition: all var(--transition);
}
.op-tab.active { color: var(--green-dark); border-bottom-color: var(--green-mid); }
.op-tab-panel { display: none; }
.op-tab-panel.active { display: block; }
</style>

<div class="op-page">

  <!-- ══ HERO ════════════════════════════════════════════ -->
  <div class="op-hero">
    <div class="op-hero-banner"></div>
    <div class="op-hero-body">

      <!-- Logo -->
      <div class="op-hero-logo-wrap">
        <?php if ($hasLogo): ?>
          <img src="<?= $logoSrc ?>"
               alt="<?= htmlspecialchars($org['name']) ?>"
               class="op-hero-logo"
               onerror="this.style.display='none';document.getElementById('opLogoPh').style.display='flex';">
          <div id="opLogoPh" class="op-hero-logo-ph" style="display:none;">
            <i class="fas fa-building"></i>
          </div>
        <?php else: ?>
          <div class="op-hero-logo-ph"><i class="fas fa-building"></i></div>
        <?php endif; ?>
      </div>

      <!-- Name + meta -->
      <div class="op-hero-info">
        <h1 class="op-hero-name"><?= htmlspecialchars($org['name']) ?></h1>
        <div class="op-hero-meta">
          <?php if (!empty($org['location'])): ?>
            <span class="op-meta-chip">
              <i class="fas fa-map-marker-alt"></i>
              <a href="https://maps.google.com/?q=<?= urlencode($org['location']) ?>"
                 target="_blank" rel="noopener">
                <?= htmlspecialchars($org['location']) ?>
              </a>
            </span>
          <?php endif; ?>
          <?php if (!empty($org['contact_email'])): ?>
            <span class="op-meta-chip">
              <i class="fas fa-envelope"></i>
              <?= htmlspecialchars($org['contact_email']) ?>
            </span>
          <?php endif; ?>
          <?php if (!empty($org['contact_phone'])): ?>
            <span class="op-meta-chip">
              <i class="fas fa-phone"></i>
              <?= htmlspecialchars($org['contact_phone']) ?>
            </span>
          <?php endif; ?>
          <?php if (!empty($org['facebook_link'])): ?>
            <span class="op-meta-chip">
              <i class="fab fa-facebook"></i>
              <a href="<?= htmlspecialchars($org['facebook_link']) ?>"
                 target="_blank" rel="noopener">Facebook Page</a>
            </span>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /op-hero-body -->

    <!-- Stats bar -->
    <div class="op-stats-bar">
      <span class="op-stat">
        <i class="fas fa-calendar-alt"></i>
        <?= $totalEvents ?> <span>Total Events</span>
      </span>
      <span class="op-stat">
        <i class="fas fa-calendar-check"></i>
        <?= $upcomingEvents ?> <span>Upcoming</span>
      </span>
      <span class="op-stat">
        <i class="fas fa-history"></i>
        <?= $completedCount ?> <span>Completed</span>
      </span>
    </div>
  </div><!-- /op-hero -->

  <!-- Description (full width) -->
  <?php if (!empty($org['description'])): ?>
  <div class="op-desc-card">
    <div class="op-desc-card-title"><i class="fas fa-info-circle"></i> About</div>
    <?= nl2br(htmlspecialchars($org['description'])) ?>
  </div>
  <?php endif; ?>

  <!-- ══ TWO COLUMNS ══════════════════════════════════════ -->
  <div class="op-two-col">

    <!-- LEFT: Upcoming Events -->
    <div class="op-card">
      <div class="op-card-header">
        <h2 class="op-card-title"><i class="fas fa-calendar-alt"></i> Upcoming Events</h2>
        <span class="op-badge"><?= $upcomingEvents ?></span>
      </div>
      <?php if ($currentEvents->num_rows > 0): ?>
        <ul class="op-event-list">
          <?php while ($ev = $currentEvents->fetch_assoc()):
            $dotClass = $ev['status'] === 'Open' ? 'dot-open' : 'dot-ongoing';
            $pillClass= $ev['status'] === 'Open' ? 's-open'   : 's-ongoing';
          ?>
            <li class="op-event-item">
              <div class="op-event-dot <?= $dotClass ?>">
                <i class="fas fa-calendar-day"></i>
              </div>
              <div class="op-event-info">
                <p class="op-event-name"><?= htmlspecialchars($ev['title']) ?></p>
                <div class="op-event-sub">
                  <span><i class="fas fa-clock"></i><?= date('M d, Y', strtotime($ev['date'])) ?></span>
                  <?php if (!empty($ev['location'])): ?>
                    <span><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($ev['location']) ?></span>
                  <?php endif; ?>
                  <span class="s-pill <?= $pillClass ?>"><?= $ev['status'] ?></span>
                </div>
              </div>
              <a href="list_events.php?event_id=<?= (int)$ev['id'] ?>" class="btn-apply">
                <i class="fas fa-paper-plane"></i> Apply
              </a>
            </li>
          <?php endwhile; ?>
        </ul>
      <?php else: ?>
        <div class="op-empty">
          <i class="fas fa-calendar-times"></i>
          <p>No upcoming events right now.</p>
        </div>
      <?php endif; ?>
    </div><!-- /left col -->

    <!-- RIGHT: Past Events + Contact -->
    <div style="display:flex;flex-direction:column;gap:20px;">

      <!-- Past Events -->
      <div class="op-card">
        <div class="op-card-header">
          <h2 class="op-card-title"><i class="fas fa-history"></i> Past Events</h2>
          <span class="op-badge"><?= $completedCount ?></span>
        </div>
        <?php if ($pastEvents->num_rows > 0): ?>
          <ul class="op-event-list">
            <?php while ($ev = $pastEvents->fetch_assoc()): ?>
              <li class="op-event-item">
                <div class="op-event-dot dot-past">
                  <i class="fas fa-calendar-check"></i>
                </div>
                <div class="op-event-info">
                  <p class="op-event-name"><?= htmlspecialchars($ev['title']) ?></p>
                  <div class="op-event-sub">
                    <span><i class="fas fa-clock"></i><?= date('M d, Y', strtotime($ev['date'])) ?></span>
                    <?php if (!empty($ev['location'])): ?>
                      <span><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($ev['location']) ?></span>
                    <?php endif; ?>
                    <span class="s-pill s-completed">Completed</span>
                  </div>
                </div>
              </li>
            <?php endwhile; ?>
          </ul>
        <?php else: ?>
          <div class="op-empty">
            <i class="fas fa-calendar-times"></i>
            <p>No completed events yet.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Contact Info -->
      <?php $hasContact = !empty($org['contact_email']) || !empty($org['contact_phone']) || !empty($org['location']) || !empty($org['facebook_link']); ?>
      <?php if ($hasContact): ?>
      <div class="op-card">
        <div class="op-card-header">
          <h2 class="op-card-title"><i class="fas fa-address-card"></i> Contact Info</h2>
        </div>
        <div class="op-contact-list">
          <?php if (!empty($org['location'])): ?>
            <div class="op-contact-row">
              <div class="op-contact-icon"><i class="fas fa-map-marker-alt"></i></div>
              <div>
                <div class="op-contact-label">Location</div>
                <div class="op-contact-val">
                  <a href="https://maps.google.com/?q=<?= urlencode($org['location']) ?>"
                     target="_blank" rel="noopener"
                     style="color:var(--green-mid);text-decoration:none;">
                    <?= htmlspecialchars($org['location']) ?>
                  </a>
                </div>
              </div>
            </div>
          <?php endif; ?>
          <?php if (!empty($org['contact_email'])): ?>
            <div class="op-contact-row">
              <div class="op-contact-icon"><i class="fas fa-envelope"></i></div>
              <div>
                <div class="op-contact-label">Email</div>
                <div class="op-contact-val"><?= htmlspecialchars($org['contact_email']) ?></div>
              </div>
            </div>
          <?php endif; ?>
          <?php if (!empty($org['contact_phone'])): ?>
            <div class="op-contact-row">
              <div class="op-contact-icon"><i class="fas fa-phone"></i></div>
              <div>
                <div class="op-contact-label">Phone</div>
                <div class="op-contact-val"><?= htmlspecialchars($org['contact_phone']) ?></div>
              </div>
            </div>
          <?php endif; ?>
          <?php if (!empty($org['facebook_link'])): ?>
            <div class="op-contact-row">
              <div class="op-contact-icon" style="background:#e7f0fd;color:#1877f2;"><i class="fab fa-facebook"></i></div>
              <div>
                <div class="op-contact-label">Facebook</div>
                <div class="op-contact-val">
                  <a href="<?= htmlspecialchars($org['facebook_link']) ?>"
                     target="_blank" rel="noopener"
                     style="color:var(--green-mid);text-decoration:none;font-weight:600;">
                    Visit Facebook Page
                  </a>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /right col stack -->

  </div><!-- /op-two-col -->

  <!-- ══ SUPPORT / DONATE (full width) ════════════════════ -->
  <div class="op-full-row">
    <div class="op-card">
      <div class="op-card-header">
        <h2 class="op-card-title"><i class="fas fa-hand-holding-heart"></i> Support This Organization</h2>
      </div>
      <div class="op-donate-row">
        <?php if (!empty($org['gcash_qr']) || !empty($org['gcash_number'])): ?>
          <button class="btn-don btn-gcash" onclick="openModal('gcashModal')">
            <i class="fas fa-mobile-alt"></i> Donate via GCash
          </button>
        <?php endif; ?>
        <?php if (!empty($org['bank_name'])): ?>
          <button class="btn-don btn-bank" onclick="openModal('bankModal')">
            <i class="fas fa-university"></i> Bank Transfer
          </button>
        <?php endif; ?>
        <?php if (!empty($org['dropoff_location'])): ?>
          <button class="btn-don btn-dropoff" onclick="openModal('dropoffModal')">
            <i class="fas fa-box-open"></i> Item Drop-off
          </button>
        <?php endif; ?>

        <?php if (empty($org['gcash_qr']) && empty($org['gcash_number']) && empty($org['bank_name']) && empty($org['dropoff_location'])): ?>
          <p style="font-size:.84rem;color:var(--text-muted);margin:4px 0;">
            <i class="fas fa-info-circle"></i> This organization has not set up donation methods yet.
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ══ MY DONATION HISTORY (full width, tabbed) ═════════ -->
  <?php if ($hasDonations): ?>
  <div class="op-full-row">
    <div class="op-card">
      <div class="op-card-header">
        <h2 class="op-card-title"><i class="fas fa-receipt"></i> My Donation History</h2>
      </div>

      <!-- Tabs -->
      <div class="op-tabs">
        <button class="op-tab active" onclick="switchTab(this,'tab-money')">
          <i class="fas fa-peso-sign"></i> Monetary
          <?php if ($donations->num_rows > 0): ?>
            <span class="op-badge" style="margin-left:5px;"><?= $donations->num_rows ?></span>
          <?php endif; ?>
        </button>
        <button class="op-tab" onclick="switchTab(this,'tab-items')">
          <i class="fas fa-box"></i> Item Donations
          <?php if ($items->num_rows > 0): ?>
            <span class="op-badge" style="margin-left:5px;"><?= $items->num_rows ?></span>
          <?php endif; ?>
        </button>
      </div>

      <!-- Monetary tab -->
      <div id="tab-money" class="op-tab-panel active">
        <?php if ($donations->num_rows > 0): ?>
          <div style="overflow-x:auto;">
            <table class="op-don-table">
              <thead>
                <tr>
                  <th>Method</th>
                  <th>Amount</th>
                  <th>Reference #</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($d = $donations->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($d['method']) ?></td>
                    <td class="amt">₱<?= number_format($d['amount'], 2) ?></td>
                    <td style="font-family:monospace;font-size:.78rem;"><?= htmlspecialchars($d['reference_number']) ?></td>
                    <td>
                      <span class="s-pill <?= strtolower($d['status']) === 'approved' ? 's-open' : (strtolower($d['status']) === 'pending' ? 's-ongoing' : 's-completed') ?>">
                        <?= htmlspecialchars($d['status']) ?>
                      </span>
                    </td>
                    <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($d['created_at'])) ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="op-empty"><i class="fas fa-receipt"></i><p>No monetary donations yet.</p></div>
        <?php endif; ?>
      </div>

      <!-- Items tab -->
      <div id="tab-items" class="op-tab-panel">
        <?php if ($items->num_rows > 0): ?>
          <div style="overflow-x:auto;">
            <table class="op-don-table">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Description</th>
                  <th>Qty</th>
                  <th>Drop-off Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($it = $items->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($it['item_category']) ?></td>
                    <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                      <?= htmlspecialchars($it['item_description']) ?>
                    </td>
                    <td style="font-weight:700;"><?= (int)$it['quantity'] ?></td>
                    <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($it['dropoff_date'])) ?></td>
                    <td>
                      <span class="s-pill <?= strtolower($it['status']) === 'received' ? 's-open' : 's-ongoing' ?>">
                        <?= htmlspecialchars($it['status']) ?>
                      </span>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="op-empty"><i class="fas fa-box-open"></i><p>No item donations yet.</p></div>
        <?php endif; ?>
      </div>

    </div>
  </div>
  <?php endif; ?>

</div><!-- /op-page -->

<!-- ══ GCASH MODAL ══════════════════════════════════════════ -->
<div class="op-modal" id="gcashModal">
  <div class="op-modal-box">
    <div class="op-modal-head">
      <h3><i class="fas fa-mobile-alt"></i> GCash Donation</h3>
      <button class="op-modal-x" onclick="closeModal('gcashModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal-body">
      <?php if (!empty($org['gcash_qr'])): ?>
        <p style="text-align:center;font-size:.82rem;color:var(--text-muted);margin-bottom:8px;">
          Scan QR code with your GCash app
        </p>
        <img src="/VolunteerHub/uploads/<?= htmlspecialchars($org['gcash_qr']) ?>"
             alt="GCash QR"
             style="width:150px;display:block;margin:0 auto 14px;border-radius:8px;box-shadow:var(--shadow-sm);">
      <?php endif; ?>
      <?php if (!empty($org['gcash_name'])): ?>
        <p style="font-size:.83rem;text-align:center;color:var(--text-secondary);margin-bottom:14px;">
          <strong><?= htmlspecialchars($org['gcash_name']) ?></strong>
          &nbsp;·&nbsp; <?= htmlspecialchars($org['gcash_number'] ?? '') ?>
        </p>
      <?php endif; ?>
      <form method="POST" action="process_donation.php">
        <input type="hidden" name="payment_method" value="gcash">
        <input type="hidden" name="organization_id" value="<?= $organization_id ?>">
        <label>Amount (₱)</label>
        <input type="number" name="amount" step="0.01" min="1" required placeholder="e.g. 500">
        <label>Reference Number</label>
        <input type="text" name="reference_number" required placeholder="GCash reference #">
        <div class="op-modal-foot">
          <button type="button" class="btn-m-cancel" onclick="closeModal('gcashModal')">Cancel</button>
          <button type="submit" class="btn-m-submit">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══ BANK MODAL ═══════════════════════════════════════════ -->
<div class="op-modal" id="bankModal">
  <div class="op-modal-box">
    <div class="op-modal-head">
      <h3><i class="fas fa-university"></i> Bank Transfer</h3>
      <button class="op-modal-x" onclick="closeModal('bankModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal-body">
      <?php if (!empty($org['bank_name'])): ?>
        <div style="background:var(--green-soft);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:14px;font-size:.84rem;color:var(--text-secondary);line-height:1.7;">
          <strong><?= htmlspecialchars($org['bank_name']) ?></strong><br>
          Account: <?= htmlspecialchars($org['bank_account_name'] ?? '') ?><br>
          Number: <strong style="font-family:monospace;"><?= htmlspecialchars($org['bank_account_number'] ?? '') ?></strong>
        </div>
      <?php endif; ?>
      <form method="POST" action="process_donation.php">
        <input type="hidden" name="payment_method" value="bank">
        <input type="hidden" name="organization_id" value="<?= $organization_id ?>">
        <label>Amount (₱)</label>
        <input type="number" name="amount" step="0.01" min="1" required placeholder="e.g. 1000">
        <label>Reference Number</label>
        <input type="text" name="reference_number" required placeholder="Bank transfer reference">
        <div class="op-modal-foot">
          <button type="button" class="btn-m-cancel" onclick="closeModal('bankModal')">Cancel</button>
          <button type="submit" class="btn-m-submit">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══ DROP-OFF MODAL ═══════════════════════════════════════ -->
<div class="op-modal" id="dropoffModal">
  <div class="op-modal-box">
    <div class="op-modal-head">
      <h3><i class="fas fa-box-open"></i> Item Drop-off</h3>
      <button class="op-modal-x" onclick="closeModal('dropoffModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal-body">
      <?php if (!empty($org['dropoff_location'])): ?>
        <div style="background:var(--amber-soft);border-radius:var(--radius-sm);padding:10px 14px;margin-bottom:14px;font-size:.83rem;color:#92400e;">
          <i class="fas fa-map-marker-alt" style="margin-right:5px;"></i>
          <strong><?= htmlspecialchars($org['dropoff_location']) ?></strong>
          <?php if (!empty($org['dropoff_instructions'])): ?>
            <br><span style="color:var(--text-muted);"><?= htmlspecialchars($org['dropoff_instructions']) ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <form method="POST" action="process_donation.php">
        <input type="hidden" name="payment_method" value="item_donation">
        <input type="hidden" name="organization_id" value="<?= $organization_id ?>">
        <label>Item Category</label>
        <select name="item_category" required>
          <option value="">-- Select Category --</option>
          <option value="Clothes">Clothes</option>
          <option value="Food">Food</option>
          <option value="Books">Books</option>
          <option value="Hygiene Kits">Hygiene Kits</option>
          <option value="Others">Others</option>
        </select>
        <label>Item Description</label>
        <textarea name="item_description" rows="2" required placeholder="Describe what you're donating…"></textarea>
        <label>Quantity</label>
        <input type="number" name="quantity" min="1" required>
        <label>Preferred Drop-off Date</label>
        <input type="date" name="dropoff_date" required>
        <div class="op-modal-foot">
          <button type="button" class="btn-m-cancel" onclick="closeModal('dropoffModal')">Cancel</button>
          <button type="submit" class="btn-m-submit">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Backdrop click closes modal
document.querySelectorAll('.op-modal').forEach(function(m) {
  m.addEventListener('click', function(e) {
    if (e.target === m) m.classList.remove('open');
  });
});

// Tab switching
function switchTab(btn, panelId) {
  btn.closest('.op-card').querySelectorAll('.op-tab').forEach(function(t) {
    t.classList.remove('active');
  });
  btn.closest('.op-card').querySelectorAll('.op-tab-panel').forEach(function(p) {
    p.classList.remove('active');
  });
  btn.classList.add('active');
  document.getElementById(panelId).classList.add('active');
}
</script>

<?php include '../includes/footer.php'; ?>