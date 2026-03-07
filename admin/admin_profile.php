<?php 
include '../conn.php'; 
include './check_session.php'; 

$pageTitle = "Admin Profile"; 
include '../includes/header_admin.php'; 

$admin_id = $_SESSION['user_id']; 

// ✅ Fetch Admin Info (with profile_pic)
$adminQuery = "SELECT name, email, created_at, profile_pic FROM users WHERE id = ?"; 
$stmt = $conn->prepare($adminQuery); 
$stmt->bind_param("i", $admin_id); 
$stmt->execute(); 
$admin = $stmt->get_result()->fetch_assoc(); 

// ✅ Fetch Organization Info (with logo & facebook_link)
$orgQuery = "
    SELECT id, name, description, location, contact_email, contact_phone,
           gcash_name, gcash_number, gcash_qr,
           bank_name, bank_account_name, bank_account_number,
           dropoff_location, dropoff_instructions,
           logo, facebook_link
    FROM organizations WHERE admin_id = ?
";
$stmt = $conn->prepare($orgQuery);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$organization = $stmt->get_result()->fetch_assoc();
?> 

<div class="page-content">
  <div class="wh-page-container">

    <div class="wh-header-card">
      <h2 class="wh-title">Admin Profile</h2>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'donation_updated'): ?> 
      <div class="wh-alert wh-alert-success">Donation information updated successfully!</div>
    <?php endif; ?> 

    <!-- ===== ADMIN PROFILE CARD ===== -->
    <div class="wh-card">
      <div class="wh-card-grid">
        <div class="wh-card-left">
          <div class="profile-avatar" id="profileAvatar" onclick="openModal('profileModal')">
            <?php if (!empty($admin['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile/' . $admin['profile_pic'])): ?>
              <img id="profilePreview" src="../uploads/profile/<?= htmlspecialchars($admin['profile_pic']); ?>" alt="Profile Photo" class="avatar-img">
            <?php else: ?>
              <i class="fas fa-user-circle default-avatar"></i>
            <?php endif; ?>
            <div class="overlay"><i class="fas fa-camera"></i></div>
          </div>

          <h3 class="admin-name"><?= htmlspecialchars($admin['name']); ?></h3>
          <p class="admin-role">Administrator</p>
        </div>

        <div class="wh-card-right">
          <p><strong>Name:</strong> <?= htmlspecialchars($admin['name']); ?></p> 
          <p><strong>Email:</strong> <?= htmlspecialchars($admin['email']); ?></p> 
          <p><strong>Joined:</strong> <?= htmlspecialchars($admin['created_at']); ?></p> 
        </div>
      </div>
    </div>

    <!-- ===== ORGANIZATION DETAILS ===== -->
    <h3 class="section-title">Organization Details</h3>
    <div class="wh-card org-card-grid">
      <?php if ($organization) { ?>
        <div class="wh-card-left">
          <div class="profile-avatar" id="logoAvatar" onclick="openModal('logoModal')">
            <?php if (!empty($organization['logo']) && file_exists(__DIR__ . '/../uploads/' . $organization['logo'])): ?>
              <img id="logoPreview" src="../uploads/<?= htmlspecialchars($organization['logo']); ?>" alt="Organization Logo" class="avatar-img">
            <?php else: ?>
              <img id="logoPreview" src="../uploads/default_logo.png" alt="Default Logo" class="avatar-img">
            <?php endif; ?>
            <div class="overlay"><i class="fas fa-camera"></i></div>
          </div>
        </div>

        <div class="wh-card-right">
          <p><strong>Name:</strong> <?= htmlspecialchars($organization['name']); ?></p> 
          <p><strong>Description:</strong> <?= htmlspecialchars($organization['description']); ?></p> 
          <p><strong>Location:</strong> <?= htmlspecialchars($organization['location']); ?></p> 
          <p><strong>Contact Email:</strong> <?= htmlspecialchars($organization['contact_email']); ?></p> 
          <p><strong>Contact Phone:</strong> <?= htmlspecialchars($organization['contact_phone']); ?></p>
          <!-- ✅ Inline Facebook Link Edit Form -->
          <p><strong>Contact Phone:</strong> <?= htmlspecialchars($organization['contact_phone']); ?></p>

<?php if (empty($organization['facebook_link'])): ?>
  <!-- Show input when link not set -->
  <form method="POST" action="update_facebook_link.php" class="fb-edit-form">
    <label for="facebook_link"><strong>Facebook Page Link:</strong></label>
    <div class="fb-edit-row">
      <input 
        type="url" 
        id="facebook_link" 
        name="facebook_link" 
        placeholder="https://www.facebook.com/yourpage"
        required>
      <input type="hidden" name="organization_id" value="<?= htmlspecialchars($organization['id']); ?>">
      <button type="submit" class="btn-save">💾</button>
    </div>
  </form>
<?php else: ?>
  <!-- Show link when saved -->
  <p class="fb-display">
    <strong>Facebook Page:</strong>
    <a href="<?= htmlspecialchars($organization['facebook_link']); ?>" target="_blank" rel="noopener noreferrer" class="fb-link">
      <i class="fab fa-facebook"></i> <?= htmlspecialchars($organization['facebook_link']); ?>
    </a>
  </p>
<?php endif; ?>

        </div>
      <?php } else { ?>
        <p class="no-data">No organization registered yet.</p>
      <?php } ?>
    </div>

    <!-- ===== DONATION INFORMATION ===== -->
    <?php if ($organization) { ?>
    <h2 class="section-title">Donation Information</h2>
    <p style="color:#555;">Update your GCash and Bank transfer details below.</p>
    <hr>

    <form method="POST" action="process_update_donation_info.php" enctype="multipart/form-data">
      <input type="hidden" name="organization_id" value="<?= htmlspecialchars($organization['id']); ?>">

      <div class="donation-info-container">
        <div class="donation-section">
          <h3>📱 GCash Details</h3>
          <label>GCash Name:</label>
          <input type="text" name="gcash_name" value="<?= htmlspecialchars($organization['gcash_name'] ?? ''); ?>" required>

          <label>GCash Number:</label>
          <input type="text" name="gcash_number" value="<?= htmlspecialchars($organization['gcash_number'] ?? ''); ?>" required>

          <label>GCash QR Code:</label>
          <input type="file" name="gcash_qr" accept="image/*">

          <?php if (!empty($organization['gcash_qr'])): ?>
            <p>Current QR:</p>
            <img src="../uploads/<?= htmlspecialchars($organization['gcash_qr']); ?>" width="150" height="150" style="border-radius:8px;">
          <?php else: ?>
            <p style="color:#999;">No QR uploaded yet.</p>
          <?php endif; ?>
        </div>

        <div class="donation-section">
          <h3>🏦 Bank Details</h3>
          <label>Bank Name:</label>
          <input type="text" name="bank_name" value="<?= htmlspecialchars($organization['bank_name'] ?? ''); ?>">

          <label>Account Name:</label>
          <input type="text" name="bank_account_name" value="<?= htmlspecialchars($organization['bank_account_name'] ?? ''); ?>">

          <label>Account Number:</label>
          <input type="text" name="bank_account_number" value="<?= htmlspecialchars($organization['bank_account_number'] ?? ''); ?>">

          <h3 style="margin-top: 20px;">📦 Drop-off Donations</h3>
          <label>Drop-off Location:</label>
          <input type="text" name="dropoff_location" value="<?= htmlspecialchars($organization['dropoff_location'] ?? ''); ?>">

          <label>Instructions (optional):</label>
          <textarea name="dropoff_instructions" rows="3"><?= htmlspecialchars($organization['dropoff_instructions'] ?? ''); ?></textarea>
        </div>
      </div>

      <div style="text-align:center; margin-top:20px;">
        <button type="submit" class="btn-primary">💾 Save Donation Information</button>
      </div>
    </form>
    <?php } ?>
  </div>
</div>

<!-- ===== MODALS ===== -->
<div id="profileModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('profileModal')">&times;</span>
    <h3>Profile Photo</h3>
    <div class="modal-buttons">
      <button class="btn-primary" onclick="document.getElementById('profileInput').click()">Choose Profile Photo</button>
      <button class="btn-secondary" onclick="viewImage('profilePreview')">View Profile Photo</button>
    </div>
    <form method="POST" action="admin_update_profile.php" enctype="multipart/form-data">
      <input type="file" name="profile_pic" id="profileInput" accept="image/*" style="display:none;">
      <input type="hidden" name="update_profile_photo" value="1">
    </form>
  </div>
</div>

<!-- === CSS for Modal === -->
<style>
/* Overlay */
.modal {
  display: none;
  position: fixed;
  top: 0; left: 0;
  width: 100%;
  height: 100%;
  background: rgba(15, 23, 42, 0.45); /* Soft dark overlay */
  align-items: center;
  justify-content: center;
  z-index: 999;
}

/* Modal Box */
.modal-content {
  background: #fff;
  border-radius: 14px;
  padding: 30px 24px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.15);
  width: 100%;
  max-width: 420px; /* 🧩 Slightly wider for visual balance */
  text-align: center;
  position: relative;
  animation: fadeIn 0.25s ease;
}

/* Header */
.modal-content h3 {
  font-size: 1.25rem;
  margin-bottom: 18px;
  color: #1e293b;
  font-weight: 600;
}

/* Close Button */
.close {
  position: absolute;
  top: 12px;
  right: 16px;
  font-size: 24px;
  color: #64748b;
  cursor: pointer;
  transition: color 0.2s ease;
}
.close:hover { color: #1e293b; }

/* Buttons */
.modal-buttons {
  display: flex;
  justify-content: center;
  gap: 12px;
  margin-bottom: 8px;
}

.btn-primary {
  background: #0d6efd;
  color: #fff;
  border: none;
  padding: 10px 18px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.1s ease;
}
.btn-primary:hover { background: #0056b3; transform: translateY(-1px); }

.btn-secondary {
  background: #e5e7eb;
  color: #111;
  border: none;
  padding: 10px 18px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: background 0.2s ease, transform 0.1s ease;
}
.btn-secondary:hover { background: #d1d5db; transform: translateY(-1px); }

/* Animation */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Responsive tweak */
@media (max-width: 480px) {
  .modal-content {
    max-width: 90%;
    padding: 24px 18px;
  }
  .modal-buttons {
    flex-direction: column;
  }
  .modal-buttons button {
    width: 100%;
  }
}
</style>


<div id="logoModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('logoModal')">&times;</span>
    <h3>Organization Logo</h3>
    <button class="btn-primary" onclick="document.getElementById('logoInput').click()">Choose Logo</button>
    <button class="btn-secondary" onclick="viewImage('logoPreview')">View Logo</button>
    <form method="POST" action="process_update_donation_info.php" enctype="multipart/form-data">
      <input type="file" name="logo" id="logoInput" accept="image/*" style="display:none;">
      <input type="hidden" name="organization_id" value="<?= htmlspecialchars($organization['id']); ?>">
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).style.display='flex'; }
function closeModal(id){ document.getElementById(id).style.display='none'; }
function viewImage(id){ const src=document.getElementById(id).src; const w=window.open(); w.document.write(`<img src="${src}" style="max-width:100%;border-radius:8px;">`); }

document.getElementById('profileInput')?.addEventListener('change',e=>{
  const file=e.target.files[0]; if(!file)return;
  const reader=new FileReader();
  reader.onload=ev=>{document.getElementById('profilePreview').src=ev.target.result;};
  reader.readAsDataURL(file);
  e.target.form.submit();
});

document.getElementById('logoInput')?.addEventListener('change',e=>{
  const file=e.target.files[0]; if(!file)return;
  const reader=new FileReader();
  reader.onload=ev=>{document.getElementById('logoPreview').src=ev.target.result;};
  reader.readAsDataURL(file);
  e.target.form.submit();
});
</script>

<style>
/* Maintain admin_layout aesthetics + restore donation layout */
.wh-card {background:#fff;border-radius:12px;padding:18px;box-shadow:0 8px 28px rgba(15,23,42,0.04);margin-bottom:18px;}
.wh-card-grid,.org-card-grid {display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;}
.wh-card-left {flex:0 0 220px;text-align:center;border-right:1px solid rgba(15,23,42,0.04);padding-right:18px;}
.wh-card-right {flex:1;min-width:220px;padding-left:18px;}
.avatar-img {width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid #eef2f7;cursor:pointer;}
.profile-avatar {position:relative;display:inline-block;}
.overlay {position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.3);border-radius:50%;opacity:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;transition:0.3s;}
.profile-avatar:hover .overlay {opacity:1;}
.fb-link {color:#1877f2;text-decoration:none;font-weight:600;}
.modal {display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:999;}
.modal-content {background:#fff;padding:20px;border-radius:10px;text-align:center;position:relative;box-shadow:0 4px 20px rgba(0,0,0,0.1);}
.close {position:absolute;top:8px;right:12px;font-size:22px;cursor:pointer;}
.btn-secondary {background:#f1f5f9;padding:8px 14px;border:none;border-radius:8px;margin-left:8px;cursor:pointer;}

/* ✅ Donation info styling restored */
.donation-info-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
  gap: 24px;
  margin-top: 20px;
}
.donation-section {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.05);
  transition: box-shadow 0.2s ease;
}
.donation-section:hover {
  box-shadow: 0 6px 24px rgba(0,0,0,0.08);
}
.donation-section h3 {
  color: #0d6efd;
  font-weight: 600;
  margin-bottom: 16px;
}
.donation-section label {
  font-weight: 500;
  color: #333;
  margin-top: 8px;
}
.donation-section input,
.donation-section textarea {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
  margin-top: 4px;
}
.donation-section img {
  border-radius: 8px;
  margin-top: 8px;
}
@media(max-width:880px){
  .wh-card-grid,.org-card-grid{flex-direction:column;}
  .wh-card-left{border-right:none;padding-right:0;}
  .wh-card-right{padding-left:0;}
  .donation-info-container{grid-template-columns:1fr;}
}
/* Facebook Inline Edit */
.fb-edit-form {
  margin-top: 10px;
}
.fb-edit-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 5px;
}
.fb-edit-row input[type="url"] {
  flex: 1;
  padding: 8px 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 14px;
}
.btn-save {
  background: #0d6efd;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 8px 12px;
  cursor: pointer;
  transition: background 0.2s ease;
}
.btn-save:hover {
  background: #0056b3;
}
/* Facebook Inline Edit */
.fb-edit-form { margin-top: 10px; }
.fb-edit-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 5px;
}
.fb-edit-row input[type="url"] {
  flex: 1;
  padding: 8px 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 14px;
}
.btn-save {
  background: #0d6efd;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 8px 12px;
  cursor: pointer;
  transition: background 0.2s ease;
}
.btn-save:hover { background: #0056b3; }

.fb-display {
  margin-top: 10px;
  font-size: 15px;
}
.fb-display .fb-link {
  color: #1877f2;
  text-decoration: none;
  font-weight: 500;
}
.fb-display .fb-link:hover {
  text-decoration: underline;
}
.fb-display i {
  margin-right: 6px;
  color: #1877f2;
}

</style>

<?php include '../includes/footer.php'; ?>
