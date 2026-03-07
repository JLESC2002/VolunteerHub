<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "Organizations";
$pageCSS = "/VolunteerHub/styles/organization.css"; 
include '../includes/header_volunteer.php';

// Fetch all organizations
$stmt = $conn->prepare("SELECT id, name, description, location, logo FROM organizations ORDER BY name ASC");
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="page-container">
  <div class="card shadow-sm border-0 p-4">
    <h4 class="fw-bold text-primary mb-4">
      <i class="fas fa-hand-holding-heart me-2"></i> Registered Organizations
    </h4>

    <?php if ($result->num_rows > 0): ?>
      <div class="org-grid">
        <?php while ($row = $result->fetch_assoc()): ?>
          <div class="org-card shadow-sm">
            <?php if (!empty($row['logo'])): ?>
              <img src="../uploads/<?= htmlspecialchars($row['logo']) ?>" alt="Logo" class="org-logo mb-3">
            <?php else: ?>
              <div class="org-placeholder d-flex align-items-center justify-content-center mb-3">
                <i class="fas fa-building fa-3x text-secondary"></i>
              </div>
            <?php endif; ?>

            <h5 class="fw-semibold text-dark mb-1"><?= htmlspecialchars($row['name']) ?></h5>
            <p class="text-muted small mb-2"><i class="fas fa-map-marker-alt me-1 text-danger"></i> <?= htmlspecialchars($row['location']) ?></p>

            <p class="text-secondary small mb-3">
              <?= htmlspecialchars(substr($row['description'], 0, 150)) ?><?= strlen($row['description']) > 150 ? '...' : '' ?>
            </p>

            <a href="organization_profile.php?id=<?= $row['id'] ?>" class="btn btn-outline-primary btn-sm w-100">
              <i class="fas fa-eye me-1"></i> View Profile
            </a>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <p class="text-muted mb-0">No organizations have been registered yet.</p>
    <?php endif; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
