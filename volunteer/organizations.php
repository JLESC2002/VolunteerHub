<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "Organizations";
$pageCSS   = "/VolunteerHub/styles/volunteer_layout.css";
include '../includes/header_volunteer.php';

// BUG FIX: events.organization_id is NULL for most records.
// Correct join: organizations.admin_id → events.created_by
$stmt = $conn->prepare("
    SELECT o.id, o.name, o.description, o.location, o.logo,
           COUNT(DISTINCT e.id) AS event_count
    FROM organizations o
    LEFT JOIN events e ON e.created_by = o.admin_id
    GROUP BY o.id
    ORDER BY o.name ASC
");
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
/* ── Page ────────────────────────────────────────────── */
.org-page { padding: 28px 28px 60px; }

.org-page-header { margin-bottom: 28px; }
.org-page-title {
  font-size: 1.45rem; font-weight: 700; color: var(--text-primary);
  display: flex; align-items: center; gap: 10px; margin: 0 0 4px;
}
.org-page-title i { color: var(--green-mid); }
.org-page-subtitle { font-size: .875rem; color: var(--text-muted); margin: 0; }

/* ── Search ──────────────────────────────────────────── */
.org-search-wrap {
  background: #fff; border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 13px 18px;
  box-shadow: var(--shadow-sm); margin-bottom: 24px;
  display: flex; align-items: center; gap: 10px;
}
.org-search-wrap i { color: var(--text-muted); font-size: .9rem; flex-shrink: 0; }
.org-search-wrap input {
  border: none; outline: none; flex: 1;
  font-size: .9rem; color: var(--text-primary); background: transparent;
}
.org-search-wrap input::placeholder { color: var(--text-muted); }

/* ── Grid — fixed 3 columns, responsive ──────────────── */
.org-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  align-items: start;
}
@media (max-width: 1100px) { .org-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  {
  .org-grid  { grid-template-columns: 1fr; }
  .org-page  { padding: 14px 14px 40px; }
}

/* ── Card ────────────────────────────────────────────── */
.org-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  display: flex; flex-direction: column;
  overflow: hidden;
  transition: box-shadow var(--transition), transform var(--transition);
}
.org-card:hover { box-shadow: var(--shadow-md); transform: translateY(-3px); }

.org-card-bar {
  height: 4px;
  background: linear-gradient(90deg, var(--green-mid), var(--green-light));
  flex-shrink: 0;
}

/* ── Card body ───────────────────────────────────────── */
.org-card-body {
  padding: 20px 20px 14px;
  flex: 1; display: flex; flex-direction: column;
}

/* Logo + name row */
.org-logo-row {
  display: flex; align-items: center; gap: 13px;
  margin-bottom: 14px;
}

/* Logo — real photo */
.org-logo-img {
  width: 52px; height: 52px;
  border-radius: var(--radius-md);
  object-fit: cover;
  border: 2px solid var(--border);
  background: var(--green-soft);
  flex-shrink: 0; display: block;
}

/* Fallback placeholder */
.org-logo-placeholder {
  width: 52px; height: 52px;
  border-radius: var(--radius-md);
  background: var(--green-soft); color: var(--green-mid);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.35rem; flex-shrink: 0;
  border: 2px solid var(--border-light);
}

.org-name-block { flex: 1; min-width: 0; }

.org-card-name {
  font-size: .97rem; font-weight: 700; color: var(--text-primary);
  margin: 0 0 4px; line-height: 1.25;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* Location — clickable Google Maps link */
.org-card-location {
  font-size: .76rem; color: var(--text-muted);
  display: flex; align-items: center; gap: 4px;
  text-decoration: none;
  transition: color var(--transition);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.org-card-location:hover { color: var(--green-mid); }
.org-card-location i { font-size: .68rem; color: var(--green-mid); flex-shrink: 0; }

/* Description — 3-line clamp */
.org-card-desc {
  font-size: .82rem; color: var(--text-secondary); line-height: 1.55;
  margin: 0 0 14px; flex: 1;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Event count pill */
.org-event-pill {
  display: inline-flex; align-items: center; gap: 5px;
  background: var(--green-soft); color: var(--green-dark);
  font-size: .74rem; font-weight: 600;
  padding: 4px 11px; border-radius: 99px;
  align-self: flex-start;
}
.org-event-pill i { font-size: .68rem; }

/* ── Card footer ─────────────────────────────────────── */
.org-card-footer {
  padding: 12px 20px 16px;
  border-top: 1px solid var(--border-light);
}
.btn-view-org {
  display: flex; align-items: center; justify-content: center; gap: 7px;
  width: 100%;
  background: linear-gradient(135deg, var(--green-mid), var(--green-dark));
  color: #fff; font-size: .84rem; font-weight: 600;
  padding: 9px 14px; border-radius: var(--radius-sm);
  text-decoration: none;
  transition: opacity var(--transition), transform var(--transition);
}
.btn-view-org:hover { opacity: .87; transform: translateY(-1px); color: #fff; }

/* ── Empty / no-results ──────────────────────────────── */
.org-empty, #noResults {
  grid-column: 1 / -1;
  text-align: center; padding: 60px 20px; color: var(--text-muted);
}
.org-empty i, #noResults i { font-size: 2rem; display: block; margin-bottom: 10px; }
.org-empty p, #noResults p { font-size: .88rem; margin: 0; }
#noResults { display: none; }
</style>

<div class="org-page">

  <!-- Page Header -->
  <div class="org-page-header">
    <h1 class="org-page-title">
      <i class="fas fa-building"></i> Organizations
    </h1>
    <p class="org-page-subtitle">
      Browse registered non-profit organizations and their events.
    </p>
  </div>

  <!-- Search -->
  <div class="org-search-wrap">
    <i class="fas fa-search"></i>
    <input type="text" id="orgSearch"
           placeholder="Search by name or location…"
           autocomplete="off">
  </div>

  <!-- Grid -->
  <div class="org-grid" id="orgGrid">

    <?php if ($result && $result->num_rows > 0): ?>

      <?php while ($row = $result->fetch_assoc()):
        $orgId    = (int)$row['id'];
        $evCount  = (int)$row['event_count'];
        $logoFile = trim($row['logo'] ?? '');

        // Logo lives in ../uploads/ (NOT ../uploads/logo/)
        $hasLogo  = $logoFile !== '' && file_exists('../uploads/' . $logoFile);
        $logoSrc  = '/VolunteerHub/uploads/' . htmlspecialchars($logoFile);

        $mapQuery = urlencode($row['location'] ?? '');
        $searchKey = strtolower(($row['name'] ?? '') . ' ' . ($row['location'] ?? ''));
      ?>

      <div class="org-card" data-search="<?= htmlspecialchars($searchKey) ?>">

        <div class="org-card-bar"></div>

        <div class="org-card-body">

          <!-- ── Logo + Name + Location ── -->
          <div class="org-logo-row">

            <?php if ($hasLogo): ?>
              <img src="<?= $logoSrc ?>"
                   alt="<?= htmlspecialchars($row['name']) ?> logo"
                   class="org-logo-img"
                   onerror="this.replaceWith(document.querySelector('#ph<?= $orgId ?>'))">
              <div id="ph<?= $orgId ?>" class="org-logo-placeholder" style="display:none;">
                <i class="fas fa-building"></i>
              </div>
            <?php else: ?>
              <div class="org-logo-placeholder">
                <i class="fas fa-building"></i>
              </div>
            <?php endif; ?>

            <div class="org-name-block">
              <p class="org-card-name">
                <?= htmlspecialchars($row['name']) ?>
              </p>

              <?php if (!empty($row['location'])): ?>
                <a href="https://maps.google.com/?q=<?= $mapQuery ?>"
                   target="_blank" rel="noopener"
                   class="org-card-location"
                   title="View on Google Maps">
                  <i class="fas fa-map-marker-alt"></i>
                  <?= htmlspecialchars($row['location']) ?>
                </a>
              <?php else: ?>
                <span class="org-card-location">
                  <i class="fas fa-map-marker-alt"></i>
                  Location not set
                </span>
              <?php endif; ?>
            </div>

          </div><!-- /org-logo-row -->

          <!-- ── Description ── -->
          <p class="org-card-desc">
            <?= !empty($row['description'])
                  ? htmlspecialchars($row['description'])
                  : 'No description available.' ?>
          </p>

          <!-- ── Event count ── -->
          <span class="org-event-pill">
            <i class="fas fa-calendar-alt"></i>
            <?= $evCount ?> Event<?= $evCount !== 1 ? 's' : '' ?>
          </span>

        </div><!-- /org-card-body -->

        <div class="org-card-footer">
          <a href="organization_profile.php?id=<?= $orgId ?>" class="btn-view-org">
            <i class="fas fa-eye"></i> View Profile
          </a>
        </div>

      </div><!-- /org-card -->

      <?php endwhile; ?>

      <div id="noResults">
        <i class="fas fa-search"></i>
        <p>No organizations match your search.</p>
      </div>

    <?php else: ?>
      <div class="org-empty">
        <i class="fas fa-building"></i>
        <p>No organizations have been registered yet.</p>
      </div>
    <?php endif; ?>

  </div><!-- /org-grid -->

</div><!-- /org-page -->

<script>
(function () {
  const input = document.getElementById('orgSearch');
  const cards = document.querySelectorAll('#orgGrid .org-card');
  const noRes = document.getElementById('noResults');

  input.addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    let visible = 0;

    cards.forEach(function (card) {
      const match = card.dataset.search.includes(q);
      card.style.display = match ? '' : 'none';
      if (match) visible++;
    });

    if (noRes) noRes.style.display = (visible === 0) ? 'block' : 'none';
  });
})();
</script>

<?php include '../includes/footer.php'; ?>