<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "Organizations";
$pageCSS   = "/VolunteerHub/styles/volunteer_layout.css";
include '../includes/header_volunteer.php';

// Fetch organizations with event count
$stmt = $conn->prepare("
    SELECT o.id, o.name, o.description, o.location, o.logo,
           COUNT(DISTINCT e.id) AS event_count
    FROM organizations o
    LEFT JOIN events e ON e.organization_id = o.id
    GROUP BY o.id
    ORDER BY o.name ASC
");
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
.org-page { padding: 28px 28px 60px; }

/* Page header */
.org-page-header { margin-bottom: 28px; }
.org-page-title {
  font-size: 1.45rem; font-weight: 700; color: var(--text-primary);
  display: flex; align-items: center; gap: 10px; margin: 0 0 4px;
}
.org-page-title i { color: var(--green-mid); }
.org-page-subtitle { font-size: .875rem; color: var(--text-muted); margin: 0; }

/* Search bar */
.org-search-wrap {
  background: #fff; border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 18px 22px;
  box-shadow: var(--shadow-sm); margin-bottom: 24px;
  display: flex; align-items: center; gap: 12px;
}
.org-search-wrap i { color: var(--text-muted); font-size: 1rem; flex-shrink: 0; }
.org-search-wrap input {
  border: none; outline: none; flex: 1;
  font-size: .9rem; color: var(--text-primary); background: transparent;
}
.org-search-wrap input::placeholder { color: var(--text-muted); }

/* Grid */
.org-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 20px;
}

/* Card */
.org-card {
  background: #fff; border: 1px solid var(--border);
  border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
  overflow: hidden; display: flex; flex-direction: column;
  transition: box-shadow var(--transition), transform var(--transition);
}
.org-card:hover { box-shadow: var(--shadow-md); transform: translateY(-3px); }

/* Card banner */
.org-card-banner {
  height: 80px;
  background: linear-gradient(135deg, var(--green-dark), var(--green-mid));
  position: relative;
  flex-shrink: 0;
}
.org-card-logo-wrap {
  position: absolute; bottom: -28px; left: 22px;
  width: 56px; height: 56px; border-radius: 12px;
  background: #fff; border: 2px solid var(--border);
  overflow: hidden; box-shadow: var(--shadow-sm);
  display: flex; align-items: center; justify-content: center;
}
.org-card-logo-wrap img { width: 100%; height: 100%; object-fit: cover; }
.org-card-logo-placeholder { color: var(--green-mid); font-size: 1.5rem; }

/* Card body */
.org-card-body { padding: 40px 22px 18px; flex: 1; }
.org-card-name {
  font-size: 1rem; font-weight: 700; color: var(--text-primary);
  margin: 0 0 6px;
}
.org-card-location {
  font-size: .78rem; color: var(--text-muted);
  display: flex; align-items: center; gap: 5px; margin-bottom: 10px;
}
.org-card-location i { color: var(--danger, #dc2626); font-size: .7rem; }
.org-card-desc {
  font-size: .83rem; color: var(--text-secondary, #4a5568);
  line-height: 1.55; margin-bottom: 14px;
  display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.org-card-meta {
  display: flex; align-items: center; gap: 8px;
  font-size: .75rem; color: var(--text-muted); margin-bottom: 16px;
}
.org-card-meta-pill {
  display: inline-flex; align-items: center; gap: 5px;
  background: var(--green-soft, #e8f5ee); color: var(--green-dark, #1a5c3a);
  padding: 3px 9px; border-radius: 99px; font-weight: 600; font-size: .72rem;
}

/* Card footer */
.org-card-footer { padding: 0 22px 18px; }
.btn-view-org {
  display: flex; align-items: center; justify-content: center; gap: 7px;
  width: 100%; padding: 9px;
  background: linear-gradient(135deg, var(--green-mid, #2d8653), var(--green-dark, #1a5c3a));
  color: #fff; border: none; border-radius: var(--radius-sm, 8px);
  font-size: .85rem; font-weight: 600; text-decoration: none;
  cursor: pointer; transition: opacity .2s;
}
.btn-view-org:hover { opacity: .9; color: #fff; }

/* Empty state */
.org-empty {
  grid-column: 1 / -1; text-align: center;
  padding: 60px 20px; color: var(--text-muted);
}
.org-empty i { font-size: 3rem; display: block; margin-bottom: 14px; opacity: .3; }
.org-empty p { font-size: .9rem; }
</style>

<div class="org-page">

  <div class="org-page-header">
    <h1 class="org-page-title"><i class="fas fa-building"></i> Registered Organizations</h1>
    <p class="org-page-subtitle">Discover NGOs and non-profits you can volunteer with.</p>
  </div>

  <!-- Search -->
  <div class="org-search-wrap">
    <i class="fas fa-search"></i>
    <input type="text" id="orgSearch" placeholder="Search organizations by name or location…">
  </div>

  <!-- Grid -->
  <div class="org-grid" id="orgGrid">
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="org-card" data-search="<?= strtolower(htmlspecialchars($row['name'] . ' ' . $row['location'])) ?>">

          <div class="org-card-banner">
            <div class="org-card-logo-wrap">
              <?php if (!empty($row['logo'])): ?>
                <img src="../uploads/<?= htmlspecialchars($row['logo']) ?>" alt="Logo">
              <?php else: ?>
                <i class="fas fa-building org-card-logo-placeholder"></i>
              <?php endif; ?>
            </div>
          </div>

          <div class="org-card-body">
            <h3 class="org-card-name"><?= htmlspecialchars($row['name']) ?></h3>
            <?php if (!empty($row['location'])): ?>
              <p class="org-card-location">
                <i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars($row['location']) ?>
              </p>
            <?php endif; ?>
            <p class="org-card-desc">
              <?= htmlspecialchars($row['description'] ?: 'No description available.') ?>
            </p>
            <div class="org-card-meta">
              <span class="org-card-meta-pill">
                <i class="fas fa-calendar-alt"></i>
                <?= (int)$row['event_count'] ?> Event<?= $row['event_count'] != 1 ? 's' : '' ?>
              </span>
            </div>
          </div>

          <div class="org-card-footer">
            <a href="organization_profile.php?id=<?= (int)$row['id'] ?>" class="btn-view-org">
              <i class="fas fa-eye"></i> View Profile
            </a>
          </div>

        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="org-empty">
        <i class="fas fa-building"></i>
        <p>No organizations have been registered yet.</p>
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
// Live search filter
document.getElementById('orgSearch').addEventListener('input', function () {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#orgGrid .org-card').forEach(card => {
    card.style.display = card.dataset.search.includes(q) ? '' : 'none';
  });
});
</script>

<?php include '../includes/footer.php'; ?>