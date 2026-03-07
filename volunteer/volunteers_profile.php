<?php
include '../conn.php';
include './check_session.php';

// Page title + shared CSS with admin profile
$pageTitle = "Volunteer Profile";
$pageCSS   = "/VolunteerHub/styles/profile.css";
include '../includes/header_volunteer.php';

$user_id = $_SESSION['user_id'];

/* --- Core account info --- */
$profileQuery = "SELECT name, email, created_at, profile_pic FROM users WHERE id = ?";
$stmt = $conn->prepare($profileQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profileResult = $stmt->get_result();
$volunteer = $profileResult->fetch_assoc();

/* --- Completed events (based on applications + check-in/out) --- */
$completedEventsQuery = "
    SELECT DISTINCT e.id, e.title, e.date, e.location
    FROM events e
    JOIN volunteer_applications va ON va.event_id = e.id
    WHERE va.user_id = ?
      AND va.status = 'Completed'
    ORDER BY e.date DESC
";

$stmt = $conn->prepare($completedEventsQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$completedEventsResult = $stmt->get_result();

/* --- Volunteer extra details --- */
$detailsQuery = "SELECT gender, age, address, phone_number, hobbies, skills FROM volunteer_details WHERE user_id = ?";
$stmt2 = $conn->prepare($detailsQuery);
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$detailsResult = $stmt2->get_result();
$details = $detailsResult->fetch_assoc();

/* --- Handle update --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $gender  = $_POST['gender'] ?? null;
    $age     = $_POST['age'] ?? null;
    $address = $_POST['address'] ?? null;
    $phone   = $_POST['phone_number'] ?? null;
    $hobbies = $_POST['hobbies'] ?? null;
    $skills  = $_POST['skills'] ?? null;

    if ($details) {
        $updateStmt = $conn->prepare("
            UPDATE volunteer_details
            SET gender = ?, age = ?, address = ?, phone_number = ?, hobbies = ?, skills = ?
            WHERE user_id = ?
        ");
        $updateStmt->bind_param("sissssi", $gender, $age, $address, $phone, $hobbies, $skills, $user_id);
    } else {
        $updateStmt = $conn->prepare("
            INSERT INTO volunteer_details (user_id, gender, age, address, phone_number, hobbies, skills)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $updateStmt->bind_param("isissss", $user_id, $gender, $age, $address, $phone, $hobbies, $skills);
    }
    $updateStmt->execute();
    echo "<script>alert('Profile updated successfully'); location.href='volunteers_profile.php';</script>";
    exit;
}

?>

<!-- Core profile -->
<div class="profile-container">
  <!-- Profile Header Section -->
  <div class="profile-header">
    <!-- Profile Photo on Left -->
    <div class="profile-avatar" id="profileAvatar" onclick="openModal('profileModal')">
      <?php if (!empty($volunteer['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile/' . $volunteer['profile_pic'])): ?>
        <img id="profilePreview" src="../uploads/profile/<?= htmlspecialchars($volunteer['profile_pic']); ?>" alt="Profile Photo" class="avatar-img">
      <?php else: ?>
        <i class="fas fa-user-circle default-avatar"></i>
      <?php endif; ?>
      <div class="overlay"><i class="fas fa-camera"></i></div>
    </div>

    <!-- Profile Details on Right -->
    <div class="profile-info">
      <h2 class="section-title">Volunteer Profile</h2>
      <p><strong>Name:</strong> <?= htmlspecialchars($volunteer['name'] ?? '') ?></p>
      <p><strong>Email:</strong> <?= htmlspecialchars($volunteer['email'] ?? '') ?></p>
      <p><strong>Joined:</strong> <?= htmlspecialchars($volunteer['created_at'] ?? '') ?></p>
    </div>
  </div>

  <!-- Additional Info -->
  <h2 class="section-title">Additional Information</h2>
  <div id="infoView" class="card" style="margin:2px;">
    <p><strong>Gender:</strong> <?= htmlspecialchars($details['gender'] ?? '') ?></p>
    <p><strong>Age:</strong> <?= htmlspecialchars($details['age'] ?? '') ?></p>
    <p><strong>Address:</strong> <?= htmlspecialchars($details['address'] ?? '') ?></p>
    <p><strong>Phone Number:</strong> <?= htmlspecialchars($details['phone_number'] ?? '') ?></p>
    <p><strong>Hobbies:</strong> <?= htmlspecialchars($details['hobbies'] ?? '') ?></p>
    <p><strong>Skills:</strong> <?= htmlspecialchars($details['skills'] ?? '') ?></p>
    <button class="btn-primary" onclick="toggleEdit(true)">Edit</button>
  </div>

  <form id="editForm" method="POST" class="card" style="display:none;">
    <div class="form-grid">
      <label><span>Gender</span><input type="text" name="gender" value="<?= htmlspecialchars($details['gender'] ?? '') ?>"></label>
      <label><span>Age</span><input type="number" name="age" value="<?= htmlspecialchars($details['age'] ?? '') ?>"></label>
      <label class="grid-col-2"><span>Address</span><input type="text" name="address" value="<?= htmlspecialchars($details['address'] ?? '') ?>"></label>
      <label><span>Phone Number</span><input type="text" name="phone_number" value="<?= htmlspecialchars($details['phone_number'] ?? '') ?>"></label>
      <label><span>Hobbies</span><input type="text" name="hobbies" value="<?= htmlspecialchars($details['hobbies'] ?? '') ?>"></label>
      <label><span>Skills</span><input type="text" name="skills" value="<?= htmlspecialchars($details['skills'] ?? '') ?>"></label>
    </div>
    <div class="form-actions">
      <button type="submit" name="update_profile" class="btn-success">Save</button>
      <button type="button" class="btn-secondary" onclick="toggleEdit(false)">Cancel</button>
    </div>
  </form>
</div>


    <!-- Edit mode -->
    <form id="editForm" method="POST" class="card" style="display:none ;">
        <div class="form-grid">
            <label>
                <span>Gender</span>
                <input type="text" name="gender" value="<?= htmlspecialchars($details['gender'] ?? '') ?>">
            </label>
            <label>
                <span>Age</span>
                <input type="number" name="age" value="<?= htmlspecialchars($details['age'] ?? '') ?>">
            </label>
            <label class="grid-col-2">
                <span>Address</span>
                <input type="text" name="address" value="<?= htmlspecialchars($details['address'] ?? '') ?>">
            </label>
            <label>
                <span>Phone Number</span>
                <input type="text" name="phone_number" value="<?= htmlspecialchars($details['phone_number'] ?? '') ?>">
            </label>
            <label>
                <span>Hobbies</span>
                <input type="text" name="hobbies" value="<?= htmlspecialchars($details['hobbies'] ?? '') ?>">
            </label>
            <label>
                <span>Skills</span>
                <input type="text" name="skills" value="<?= htmlspecialchars($details['skills'] ?? '') ?>">
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" name="update_profile" class="btn-success">Save</button>
            <button type="button" class="btn-secondary" onclick="toggleEdit(false)">Cancel</button>
        </div>
    </form>

    <!-- Completed Events Section -->
<div class="profile-section">
    <h3><i class="fas fa-award"></i> Completed Events</h3>

    <?php
    // Ensure volunteer is logged in and connection exists
    $volunteer_id = $_SESSION['user_id'];

    // Fetch only valid completed events (strict conditions)
    $stmt = $conn->prepare("
        SELECT DISTINCT e.id AS event_id, e.title, e.date
        FROM events e
        JOIN tasks t ON t.event_id = e.id
        JOIN task_assignments ta ON ta.task_id = t.id AND ta.volunteer_id = ?
        JOIN event_attendance ea ON ea.event_id = e.id AND ea.volunteer_id = ?
        WHERE e.status = 'Completed'
          AND ea.check_in IS NOT NULL
          AND ea.check_out IS NOT NULL
        ORDER BY e.date DESC
    ");
    $stmt->bind_param("ii", $volunteer_id, $volunteer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    ?>

    <div class="table-wrapper" style="margin-top:10px;">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Event Title</th>
                    <th>Date</th>
                    <th>Certificate</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($res->num_rows === 0) {
                    echo '<tr><td colspan="3">No completed events yet.</td></tr>';
                } else {
                    while ($row = $res->fetch_assoc()) {
                        $eid   = intval($row['event_id']);
                        $title = htmlspecialchars($row['title']);
                        $date  = htmlspecialchars($row['date']);
                        echo "
                            <tr>
                                <td>{$title}</td>
                                <td>{$date}</td>
                                <td>
                                    <a href='generate_acknowledgement.php?event_id={$eid}' 
                                       target='_blank' 
                                       class='btn-primary'>
                                        <i class='fas fa-download'></i> Download PDF
                                    </a>
                                </td>
                            </tr>
                        ";
                    }
                }
                $stmt->close();
                ?>
            </tbody>
        </table>
    </div>
</div>
<!-- ===== PROFILE PHOTO MODAL ===== -->
<div id="profileModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('profileModal')">&times;</span>
    <h3>Profile Photo</h3>
    <div class="modal-buttons">
      <button class="btn-primary" onclick="document.getElementById('profileInput').click()">Choose Photo</button>
      <button class="btn-secondary" onclick="viewImage('profilePreview')">View Photo</button>
    </div>
    <form method="POST" action="volunteer_update_profile.php" enctype="multipart/form-data">
      <input type="file" name="profile_pic" id="profileInput" accept="image/*" style="display:none;">
      <input type="hidden" name="update_profile_photo" value="1">
    </form>
  </div>
</div>
<script>
function openModal(id){ document.getElementById(id).style.display='flex'; }
function closeModal(id){ document.getElementById(id).style.display='none'; }
function viewImage(id){
  const src=document.getElementById(id).src;
  const w=window.open();
  w.document.write(`<img src="${src}" style="max-width:100%;border-radius:8px;">`);
}

document.getElementById('profileInput')?.addEventListener('change',e=>{
  const file=e.target.files[0]; if(!file)return;
  const reader=new FileReader();
  reader.onload=ev=>{document.getElementById('profilePreview').src=ev.target.result;};
  reader.readAsDataURL(file);
  e.target.form.submit();
});
</script>


<style>
    .profile-avatar {
  position: relative;
  display: inline-block;
  margin: 10px 0;
}
.avatar-img {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #eef2f7;
  cursor: pointer;
}
.default-avatar {
  font-size: 100px;
  color: #cbd5e1;
  cursor: pointer;
}
.overlay {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.3);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  opacity: 0;
  transition: 0.3s;
}
.profile-avatar:hover .overlay {
  opacity: 1;
}
.profile-card {
  display: flex;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
}

.profile-left {
  flex: 0 0 140px;
  text-align: center;
}

.profile-right {
  flex: 1;
}

@media (max-width: 600px) {
  .profile-card {
    flex-direction: column;
    text-align: center;
  }
  .profile-right {
    text-align: center;
  }
}
.profile-header {
  display: flex;
  align-items: center;
  background: #fff;
  padding: 20px 30px;
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.05);
  margin-bottom: 25px;
  gap: 30px;
}

.profile-avatar {
  position: relative;
  flex-shrink: 0;
}

.avatar-img {
  width: 130px;
  height: 130px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #e2e8f0;
  cursor: pointer;
}

.default-avatar {
  font-size: 120px;
  color: #cbd5e1;
  cursor: pointer;
}

.overlay {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.3);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  opacity: 0;
  transition: 0.3s;
}

.profile-avatar:hover .overlay {
  opacity: 1;
}

.profile-info {
  flex: 1;
}

.profile-info p {
  font-size: 16px;
  margin: 8px 0;
  color: #334155;
}

.profile-info strong {
  color: #0f172a;
}
/* === EDIT MODE STYLING (FOR VOLUNTEER PROFILE) === */
#editForm {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.05);
  padding: 25px;
  margin-top: 20px;
}

#editForm label {
  display: flex;
  flex-direction: column;
  font-weight: 600;
  color: #334155;
  margin-bottom: 15px;
}

#editForm input {
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 15px;
  transition: border-color 0.2s ease;
}

#editForm input:focus {
  border-color: #007bff;
  outline: none;
  box-shadow: 0 0 0 2px rgba(0,123,255,0.15);
}

/* Grid layout for input fields */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 20px;
}

/* Save / Cancel Buttons */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 15px;
  margin-top: 10px;
}

.btn-success, .btn-secondary {
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-success {
  background: #28a745;
  color: #fff;
  border: none;
}

.btn-success:hover {
  background: #218838;
}

.btn-secondary {
  background: #6c757d;
  color: #fff;
  border: none;
}

.btn-secondary:hover {
  background: #5a6268;
}

/* When in edit mode, smooth fade */
#editForm, #infoView {
  transition: all 0.3s ease;
}
#editForm {
  opacity: 0;
  transform: translateY(10px);
  transition: opacity 0.3s ease, transform 0.3s ease;
}
#editForm[style*="block"] {
  opacity: 1;
  transform: translateY(0);
}

</style>
<script>
function toggleEdit(isEdit) {
  const infoView = document.getElementById('infoView');
  const editForm = document.getElementById('editForm');
  
  if (isEdit) {
    infoView.style.display = 'none';
    editForm.style.display = 'block';
  } else {
    infoView.style.display = 'block';
    editForm.style.display = 'none';
  }
}
</script>


<?php include '../includes/footer.php'; ?>
