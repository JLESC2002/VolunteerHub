<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "Manage Donations";
$pageCSS   = "../styles/admin_manage.css";
include '../includes/header_admin.php';

// ✅ Handle approve/reject actions for monetary donations
if (isset($_POST['action']) && isset($_POST['donation_id']) && isset($_POST['type'])) {
    $donation_id = intval($_POST['donation_id']);
    $action = $_POST['action'] === 'approve' ? 'Approved' : 'Rejected';
    $type = $_POST['type']; // gcash or bank

    $table = ($type === 'gcash') ? 'gcash_donations' : 'bank_payments';
    $stmt = $conn->prepare("UPDATE $table SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $action, $donation_id);
    $stmt->execute();
}

// ✅ Handle marking item drop-offs as received/rejected
if (isset($_POST['dropoff_action']) && isset($_POST['dropoff_id'])) {
    $dropoff_id = intval($_POST['dropoff_id']);
    $action = $_POST['dropoff_action'] === 'received' ? 'Received' : 'Rejected';

    $stmt = $conn->prepare("UPDATE dropoff_donations SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $action, $dropoff_id);
    $stmt->execute();
}

// ✅ Fetch all monetary donations (GCash + Bank)
$admin_id = $_SESSION['user_id'] ?? 0;

// 🔹 Get the organization of the current admin
$orgStmt = $conn->prepare("SELECT id FROM organizations WHERE admin_id = ?");
$orgStmt->bind_param("i", $admin_id);
$orgStmt->execute();
$orgRes = $orgStmt->get_result();
$orgData = $orgRes->fetch_assoc();
$org_id = $orgData['id'] ?? 0;

// ✅ Fetch monetary donations (GCash + Bank) for THIS organization only
$donations = $conn->prepare("
    SELECT 'GCash' AS method, g.id, u.name AS donor_name,
           o.name AS organization_name, g.amount, g.reference_number,
           g.status, g.proof_image, g.created_at
    FROM gcash_donations g
    JOIN users u ON g.user_id = u.id
    JOIN organizations o ON g.organization_id = o.id
    WHERE g.organization_id = ?

    UNION ALL

    SELECT 'Bank Transfer' AS method, b.id, u.name AS donor_name,
           o.name AS organization_name, b.amount, b.reference_number,
           b.status, b.uploaded_proof AS proof_image, b.created_at
    FROM bank_payments b
    JOIN users u ON b.user_id = u.id
    JOIN organizations o ON b.organization_id = o.id
    WHERE b.organization_id = ?

    ORDER BY created_at DESC
");
$donations->bind_param("ii", $org_id, $org_id);
$donations->execute();
$donations = $donations->get_result();

// ✅ Fetch drop-off donations for THIS organization only
$dropoffs = $conn->prepare("
    SELECT d.id, u.name AS donor_name, o.name AS organization_name,
           d.item_category, d.item_description, d.quantity, d.dropoff_date,
           d.status, d.created_at
    FROM dropoff_donations d
    JOIN users u ON d.user_id = u.id
    JOIN organizations o ON d.organization_id = o.id
    WHERE d.organization_id = ?
    ORDER BY d.created_at DESC
");
$dropoffs->bind_param("i", $org_id);
$dropoffs->execute();
$dropoffs = $dropoffs->get_result();
?>


<style>
/* ── Donations page layout ──────────────────────────────── */
.manage-donations-page { padding: 28px 28px 60px; }

.md-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 26px;
  flex-wrap: wrap;
  gap: 12px;
}
.md-page-title {
  font-size: 1.45rem;
  font-weight: 700;
  color: var(--text-primary, #1a1f2e);
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
}
.md-page-title i { color: #2d8653; }

/* ── Section cards ──────────────────────────────────────── */
.md-section {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
  margin-bottom: 28px;
  overflow: visible; /* allow dropdown menus to escape card boundaries */
}
.md-section-header {
  border-radius: 14px 14px 0 0; /* restore rounded top corners lost from overflow:visible */
}
.md-section-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px 22px;
  border-bottom: 1px solid #f1f5f9;
  background: #fafbfc;
}
.md-section-header h5 {
  font-size: .975rem;
  font-weight: 700;
  color: #1a1f2e;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.md-section-header h5 i { color: #2d8653; }

/* ── Table ──────────────────────────────────────────────── */
.md-table-wrap { overflow: visible; }
.md-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .875rem;
}
.md-table thead th {
  padding: 11px 14px;
  font-weight: 700;
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: #64748b;
  background: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
  white-space: nowrap;
}
.md-table tbody tr {
  border-bottom: 1px solid #f1f5f9;
  transition: background .12s;
}
.md-table tbody tr:last-child { border-bottom: none; }
.md-table tbody tr:hover { background: #f8fafc; }
.md-table td {
  padding: 12px 14px;
  vertical-align: middle;
  color: #374151;
}

/* ── Status badges ──────────────────────────────────────── */
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
.status-approved  { background: #dcfce7; color: #15803d; }
.status-received  { background: #dcfce7; color: #15803d; }
.status-rejected  { background: #fee2e2; color: #b91c1c; }
.status-pending   { background: #fef9c3; color: #a16207; }

/* ── Proof thumbnail ────────────────────────────────────── */
.proof-thumb {
  width: 44px;
  height: 44px;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  cursor: zoom-in;
  transition: transform .15s;
}
.proof-thumb:hover { transform: scale(1.1); }

/* ── Proof lightbox ─────────────────────────────────────── */
#proofLightbox {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(6,10,17,.6);
  backdrop-filter: blur(6px);
  z-index: 2000;
  align-items: center;
  justify-content: center;
}
#proofLightbox.show { display: flex; }
#proofLightbox img {
  max-width: 90vw;
  max-height: 86vh;
  border-radius: 12px;
  box-shadow: 0 24px 60px rgba(0,0,0,.4);
}
#proofLightbox .lb-close {
  position: absolute;
  top: 18px; right: 22px;
  color: #fff;
  font-size: 1.8rem;
  cursor: pointer;
  line-height: 1;
}

/* ── Action dropdown ────────────────────────────────────── */
.dropdown { position: relative; display: inline-block; }
.action-menu-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px; height: 34px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  cursor: pointer;
  border-radius: 7px;
  font-size: .9rem;
  transition: all .15s;
}
.action-menu-btn:hover { background: #f1f5f9; color: #1a1f2e; border-color: #cbd5e0; }
.action-menu {
  display: none;
  position: absolute;
  right: 0;
  top: calc(100% + 5px); /* overridden by JS when flipping up */
  background: #fff;
  min-width: 180px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 8px 28px rgba(0,0,0,.12);
  z-index: 9999;
  overflow: visible;
  animation: menuIn .14s ease both;
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
  margin: 0;
}
.action-menu button:hover { background: #f8fafc; }
.action-menu button.approve { color: #15803d; }
.action-menu button.approve:hover { background: #f0fdf4; }
.action-menu button.reject  { color: #dc2626; }
.action-menu button.reject:hover  { background: #fef2f2; }
.action-menu button[disabled] { color: #94a3b8; cursor: default; }
.action-menu button[disabled]:hover { background: transparent; }

/* ── Empty state ────────────────────────────────────────── */
.md-empty { text-align: center; padding: 44px 24px; color: #94a3b8; }
.md-empty i { font-size: 2rem; color: #cbd5e0; display: block; margin-bottom: 10px; }
.md-empty p { font-size: .875rem; margin: 0; }

/* ── Method pill ────────────────────────────────────────── */
.method-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 99px;
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .03em;
}
.method-gcash { background: #ede9fe; color: #5b21b6; }
.method-bank  { background: #dbeafe; color: #1d4ed8; }
</style>

<!-- ── Proof Lightbox ─────────────────────────────────── -->
<div id="proofLightbox">
  <span class="lb-close" onclick="closeLightbox()">&times;</span>
  <img id="proofLightboxImg" src="" alt="Proof">
</div>

<div class="manage-donations-page">

  <!-- Page Header -->
  <div class="md-page-header">
    <h1 class="md-page-title">
      <i class="fas fa-hand-holding-heart"></i> Manage Donations
    </h1>
  </div>

  <!-- ── Monetary Donations ─────────────────────────────── -->
  <div class="md-section">
    <div class="md-section-header">
      <h5><i class="fas fa-money-bill-wave"></i> Monetary Donations</h5>
    </div>
    <div class="md-table-wrap">
      <table class="md-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Method</th>
            <th>Donor</th>
            <th>Reference #</th>
            <th>Amount</th>
            <th>Proof</th>
            <th>Status</th>
            <th>Date</th>
            <th style="text-align:center;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($donations->num_rows > 0): $count = 1; ?>
            <?php while ($row = $donations->fetch_assoc()): ?>
              <tr>
                <td><?= $count++ ?></td>
                <td>
                  <?php $isGcash = ($row['method'] === 'GCash'); ?>
                  <span class="method-pill <?= $isGcash ? 'method-gcash' : 'method-bank' ?>">
                    <i class="fas <?= $isGcash ? 'fa-mobile-alt' : 'fa-university' ?>"></i>
                    <?= htmlspecialchars($row['method']) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($row['donor_name']) ?></td>
                <td style="font-family:monospace;font-size:.82rem;">
                  <?= htmlspecialchars($row['reference_number']) ?>
                </td>
                <td style="font-weight:700;color:#15803d;">
                  ₱<?= number_format($row['amount'], 2) ?>
                </td>
                <td>
                  <?php if (!empty($row['proof_image'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($row['proof_image']) ?>"
                         class="proof-thumb"
                         onclick="openLightbox('../uploads/<?= htmlspecialchars($row['proof_image']) ?>')"
                         alt="Proof">
                  <?php else: ?>
                    <span style="color:#94a3b8;font-size:.8rem;">None</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php $s = strtolower($row['status']); ?>
                  <span class="status-badge status-<?= $s ?>">
                    <?= $s === 'approved' ? '<i class="fas fa-check-circle"></i>' :
                        ($s === 'rejected' ? '<i class="fas fa-times-circle"></i>' :
                        '<i class="fas fa-clock"></i>') ?>
                    <?= htmlspecialchars($row['status']) ?>
                  </span>
                </td>
                <td style="font-size:.82rem;color:#64748b;">
                  <?= htmlspecialchars(date('M d, Y', strtotime($row['created_at']))) ?>
                </td>
                <td style="text-align:center;">
                  <div class="dropdown">
                    <button class="action-menu-btn" type="button" onclick="toggleActionMenu(this)">
                      <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="action-menu">
                      <?php if ($row['status'] === 'Pending'): ?>
                        <form method="POST" style="margin:0;">
                          <input type="hidden" name="donation_id" value="<?= $row['id'] ?>">
                          <input type="hidden" name="type" value="<?= strtolower(str_replace(' ', '_', $row['method'])) ?>">
                          <button type="submit" name="action" value="approve" class="approve">
                            <i class="fas fa-check-circle" style="width:16px;"></i> Approve
                          </button>
                        </form>
                        <form method="POST" style="margin:0;">
                          <input type="hidden" name="donation_id" value="<?= $row['id'] ?>">
                          <input type="hidden" name="type" value="<?= strtolower(str_replace(' ', '_', $row['method'])) ?>">
                          <button type="submit" name="action" value="reject" class="reject">
                            <i class="fas fa-ban" style="width:16px;"></i> Reject
                          </button>
                        </form>
                      <?php else: ?>
                        <button disabled><i class="fas fa-minus-circle" style="width:16px;"></i> No Actions</button>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="9">
                <div class="md-empty">
                  <i class="fas fa-file-invoice-dollar"></i>
                  <p>No monetary donations found.</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Drop-off Donations ──────────────────────────────── -->
  <div class="md-section">
    <div class="md-section-header">
      <h5><i class="fas fa-box-open"></i> In-Kind (Drop-off) Donations</h5>
    </div>
    <div class="md-table-wrap">
      <table class="md-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Donor</th>
            <th>Category</th>
            <th>Description</th>
            <th>Qty</th>
            <th>Drop-off Date</th>
            <th>Status</th>
            <th style="text-align:center;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($dropoffs->num_rows > 0): $count = 1; ?>
            <?php while ($row = $dropoffs->fetch_assoc()): ?>
              <tr>
                <td><?= $count++ ?></td>
                <td><?= htmlspecialchars($row['donor_name']) ?></td>
                <td>
                  <span style="font-size:.8rem;background:#f1f5f9;padding:3px 9px;border-radius:99px;color:#475569;font-weight:600;">
                    <?= htmlspecialchars($row['item_category']) ?>
                  </span>
                </td>
                <td style="max-width:200px;font-size:.85rem;">
                  <?= htmlspecialchars($row['item_description']) ?>
                </td>
                <td style="font-weight:600;"><?= htmlspecialchars($row['quantity']) ?></td>
                <td style="font-size:.82rem;color:#64748b;">
                  <?= htmlspecialchars($row['dropoff_date']) ?>
                </td>
                <td>
                  <?php $s = strtolower($row['status']); ?>
                  <span class="status-badge status-<?= $s ?>">
                    <?= $s === 'received' ? '<i class="fas fa-box"></i>' :
                        ($s === 'rejected' ? '<i class="fas fa-times-circle"></i>' :
                        '<i class="fas fa-clock"></i>') ?>
                    <?= htmlspecialchars($row['status']) ?>
                  </span>
                </td>
                <td style="text-align:center;">
                  <div class="dropdown">
                    <button class="action-menu-btn" type="button" onclick="toggleActionMenu(this)">
                      <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="action-menu">
                      <?php if ($row['status'] === 'Pending'): ?>
                        <form method="POST" style="margin:0;">
                          <input type="hidden" name="dropoff_id" value="<?= $row['id'] ?>">
                          <button type="submit" name="dropoff_action" value="received" class="approve">
                            <i class="fas fa-box" style="width:16px;"></i> Mark Received
                          </button>
                        </form>
                        <form method="POST" style="margin:0;">
                          <input type="hidden" name="dropoff_id" value="<?= $row['id'] ?>">
                          <button type="submit" name="dropoff_action" value="rejected" class="reject">
                            <i class="fas fa-ban" style="width:16px;"></i> Reject
                          </button>
                        </form>
                      <?php else: ?>
                        <button disabled><i class="fas fa-minus-circle" style="width:16px;"></i> No Actions</button>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="8">
                <div class="md-empty">
                  <i class="fas fa-boxes"></i>
                  <p>No drop-off donations found.</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /.manage-donations-page -->

<script>
// Action menu toggle (matches manage_events.php pattern)
// Action menu toggle — smart positioning (flips up if near bottom of viewport)
function toggleActionMenu(btn) {
  const menu = btn.nextElementSibling;
  const visible = menu.style.display === 'block';
  document.querySelectorAll('.action-menu').forEach(m => {
    m.style.display = 'none';
    m.style.top = '';
    m.style.bottom = '';
  });
  if (!visible) {
    const rect = btn.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    menu.style.display = 'block';
    if (spaceBelow < 140) {
      // flip upward
      menu.style.top = 'auto';
      menu.style.bottom = 'calc(100% + 5px)';
    } else {
      menu.style.top = 'calc(100% + 5px)';
      menu.style.bottom = 'auto';
    }
  }
}

// Proof lightbox
function openLightbox(src) {
  document.getElementById('proofLightboxImg').src = src;
  document.getElementById('proofLightbox').classList.add('show');
}
function closeLightbox() {
  document.getElementById('proofLightbox').classList.remove('show');
}
document.getElementById('proofLightbox').addEventListener('click', function(e) {
  if (e.target === this) closeLightbox();
});
</script>

<?php include '../includes/footer.php'; ?>