<?php 
include '../conn.php'; 
include './check_session.php'; 

$admin_id = $_SESSION['user_id'] ?? null; 

if (!$admin_id) { 
    echo "<script>alert('Error: Unauthorized access.'); window.location.href='../index.php';</script>"; 
    exit(); 
} 

// Prevent duplicate org registration
$checkQuery = $conn->prepare("SELECT id FROM organizations WHERE admin_id = ?");
$checkQuery->bind_param("i", $admin_id);
$checkQuery->execute();
$checkResult = $checkQuery->get_result();

if ($checkResult->num_rows > 0) { 
    echo "<script>alert('You have already registered an organization!'); window.location.href='admin_dashboard.php';</script>"; 
    exit(); 
} 

if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    $name = trim($_POST['name']); 
    $description = trim($_POST['description']); 
    $location = trim($_POST['location']); 
    $contact_email = trim($_POST['contact_email']); 
    $contact_phone = trim($_POST['contact_phone']); 

    if (!$name || !$description || !$location || !$contact_email || !$contact_phone) { 
        echo "<script>alert('Error: All fields are required.');</script>"; 
    } else { 
        $insertOrgQuery = $conn->prepare("INSERT INTO organizations (admin_id, name, description, location, contact_email, contact_phone) VALUES (?, ?, ?, ?, ?, ?)");
        $insertOrgQuery->bind_param("isssss", $admin_id, $name, $description, $location, $contact_email, $contact_phone);

        if ($insertOrgQuery->execute()) { 
            echo "<script>alert('Organization Registered Successfully!'); window.location.href='admin_dashboard.php';</script>"; 
            exit(); 
        } else { 
            echo "<script>alert('Error: Registration failed. Please try again.');</script>"; 
        } 
    } 
} 

$pageTitle = "Register Organization"; 
$pageCSS = "/VolunteerHub/styles/register_org.css"; 
include '../includes/header_admin.php'; 
?>

<div class="auth-container">
    <h2 class="section-title">Register Your Organization</h2>
    <form method="POST" class="form-card">
        <label>Organization Name:</label>
        <input type="text" name="name" required>

        <label>Description:</label>
        <textarea name="description" required></textarea>

        <label>Location:</label>
        <input type="text" name="location" required>

        <label>Contact Email:</label>
        <input type="email" name="contact_email" required>

        <label>Contact Phone:</label>
        <input type="text" name="contact_phone" required>

        <button type="submit" class="btn-primary">Register</button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
