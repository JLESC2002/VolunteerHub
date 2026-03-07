<?php
include '../conn.php';
include './check_session.php';
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


<div class="container-fluid px-4 py-4 manage-donations-page">
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h4 class="fw-bold text-primary mb-0"><i class="fas fa-donate me-2"></i>Manage Donations</h4>
    </div>
  </div>

  <!-- 💸 Monetary Donations -->
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
      <h5 class="fw-semibold mb-0"><i class="fas fa-money-bill-wave me-2 text-primary"></i>Monetary Donations</h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Method</th>
              <th>Donor</th>
              <th>Organization</th>
              <th>Reference #</th>
              <th>Amount</th>
              <th>Proof</th>
              <th>Status</th>
              <th>Date</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($donations->num_rows > 0): $count = 1; ?>
              <?php while ($row = $donations->fetch_assoc()): ?>
                <tr>
                  <td><?= $count++ ?></td>
                  <td><?= htmlspecialchars($row['method']) ?></td>
                  <td><?= htmlspecialchars($row['donor_name']) ?></td>
                  <td><?= htmlspecialchars($row['organization_name']) ?></td>
                  <td><?= htmlspecialchars($row['reference_number']) ?></td>
                  <td>₱<?= number_format($row['amount'], 2) ?></td>
                  <td>
                    <?php if (!empty($row['proof_image'])): ?>
                      <img src="../uploads/<?= htmlspecialchars($row['proof_image']) ?>" class="proof-thumb" onclick="viewProof('../uploads/<?= htmlspecialchars($row['proof_image']) ?>')">
                    <?php else: ?>
                      <span class="text-muted">No Proof</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge <?= strtolower($row['status']) === 'approved' ? 'bg-success' :
                                            (strtolower($row['status']) === 'rejected' ? 'bg-danger' :
                                            (strtolower($row['status']) === 'pending' ? 'bg-warning text-dark' : 'bg-secondary')) ?>">
                      <?= htmlspecialchars($row['status']) ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($row['created_at']) ?></td>
                  <td class="text-center position-relative">
                    <div class="dropdown">
                      <button class="action-menu-btn" type="button" onclick="toggleActionMenu(this)">
                        <i class="fas fa-ellipsis-v"></i>
                      </button>
                      <div class="action-menu shadow-sm">
                        <?php if ($row['status'] === 'Pending'): ?>
                          <form method="POST">
                            <input type="hidden" name="donation_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="type" value="<?= strtolower(str_replace(' ', '_', $row['method'])) ?>">
                            <button type="submit" name="action" value="approve">✅ Approve</button>
                          </form>
                          <form method="POST">
                            <input type="hidden" name="donation_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="type" value="<?= strtolower(str_replace(' ', '_', $row['method'])) ?>">
                            <button type="submit" name="action" value="reject">🚫 Reject</button>
                          </form>
                        <?php else: ?>
                          <button disabled>No Actions</button>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="10" class="text-center text-muted">No monetary donations found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- 📦 Item (Drop-off) Donations -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
      <h5 class="fw-semibold mb-0"><i class="fas fa-box-open me-2 text-primary"></i>In-Kind (Drop-off) Donations</h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Donor</th>
              <th>Organization</th>
              <th>Category</th>
              <th>Description</th>
              <th>Quantity</th>
              <th>Preferred Drop-off Date</th>
              <th>Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($dropoffs->num_rows > 0): $count = 1; ?>
              <?php while ($row = $dropoffs->fetch_assoc()): ?>
                <tr>
                  <td><?= $count++ ?></td>
                  <td><?= htmlspecialchars($row['donor_name']) ?></td>
                  <td><?= htmlspecialchars($row['organization_name']) ?></td>
                  <td><?= htmlspecialchars($row['item_category']) ?></td>
                  <td><?= htmlspecialchars($row['item_description']) ?></td>
                  <td><?= htmlspecialchars($row['quantity']) ?></td>
                  <td><?= htmlspecialchars($row['dropoff_date']) ?></td>
                  <td>
                    <span class="badge <?= strtolower($row['status']) === 'received' ? 'bg-success' :
                                            (strtolower($row['status']) === 'rejected' ? 'bg-danger' :
                                            (strtolower($row['status']) === 'pending' ? 'bg-warning text-dark' : 'bg-secondary')) ?>">
                      <?= htmlspecialchars($row['status']) ?>
                    </span>
                  </td>
                  <td class="text-center position-relative">
                    <div class="dropdown">
                      <button class="action-menu-btn" type="button" onclick="toggleActionMenu(this)">
                        <i class="fas fa-ellipsis-v"></i>
                      </button>
                      <div class="action-menu shadow-sm">
                        <?php if ($row['status'] === 'Pending'): ?>
                          <form method="POST">
                            <input type="hidden" name="dropoff_id" value="<?= $row['id'] ?>">
                            <button type="submit" name="dropoff_action" value="received">📦 Mark Received</button>
                          </form>
                          <form method="POST">
                            <input type="hidden" name="dropoff_id" value="<?= $row['id'] ?>">
                            <button type="submit" name="dropoff_action" value="rejected">🚫 Reject</button>
                          </form>
                        <?php else: ?>
                          <button disabled>No Actions</button>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="9" class="text-center text-muted">No item donations found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- 🔍 Proof Modal -->
<div id="proofModal" class="wh-modal" aria-hidden="true">
  <div class="wh-modal-backdrop" onclick="closeProof()"></div>
  <div class="wh-modal-content text-center">
    <button class="wh-modal-close" onclick="closeProof()">&times;</button>
    <img id="proofImage" src="" class="img-fluid rounded mb-2" style="max-height:70vh;">
    <button onclick="closeProof()" class="btn btn-outline-secondary btn-sm">Close</button>
  </div>
</div>

<style>
.proof-thumb{width:60px;height:60px;object-fit:cover;border-radius:5px;cursor:pointer;}
.action-menu-btn{border:none;background:transparent;font-size:18px;color:#6b7280;cursor:pointer;padding:4px 8px;border-radius:6px;}
.action-menu-btn:hover{background:rgba(0,0,0,0.05);}
.action-menu{display:none;position:absolute;right:0;top:110%;background:#fff;min-width:180px;border-radius:8px;
box-shadow:0 6px 20px rgba(0,0,0,0.12);z-index:1000;animation:fadeIn .15s ease forwards;}
.action-menu button{display:block;width:100%;border:none;background:transparent;text-align:left;
padding:10px 14px;font-size:14px;cursor:pointer;color:#333;transition:background .2s;}
.action-menu button:hover{background:#f1f5f9;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-4px);}to{opacity:1;transform:translateY(0);}}
.wh-modal{display:none;position:fixed;inset:0;z-index:1200;}
.wh-modal[aria-hidden="false"]{display:block;}
.wh-modal-backdrop{position:fixed;inset:0;background:rgba(6,10,17,0.36);backdrop-filter:blur(4px);}
.wh-modal-content{position:relative;width:100%;max-width:700px;margin:8vh auto;background:#fff;
border-radius:12px;padding:20px;box-shadow:0 12px 40px rgba(2,8,23,0.12);
transform:translateY(6px) scale(.98);opacity:0;transition:transform .18s,opacity .18s;}
.wh-modal[aria-hidden="false"] .wh-modal-content{transform:translateY(0) scale(1);opacity:1;}
.wh-modal-close{position:absolute;right:12px;top:10px;border:none;background:none;font-size:22px;color:#6b7280;cursor:pointer;}
</style>

<script>
function toggleActionMenu(btn){
  const menu=btn.nextElementSibling;
  const visible=menu.style.display==="block";
  document.querySelectorAll('.action-menu').forEach(m=>m.style.display="none");
  if(!visible)menu.style.display="block";
}
window.addEventListener('click',e=>{
  if(!e.target.closest('.dropdown'))
    document.querySelectorAll('.action-menu').forEach(m=>m.style.display="none");
});

function viewProof(src){
  document.getElementById('proofImage').src=src;
  document.getElementById('proofModal').setAttribute('aria-hidden','false');
}
function closeProof(){
  document.getElementById('proofModal').setAttribute('aria-hidden','true');
}
</script>

<?php include '../includes/footer.php'; ?>
