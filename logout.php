<?php
session_start();
include 'conn.php';

if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];

        // Clear session token from database
        $stmt = $conn->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }

    session_unset();
    session_destroy();
    setcookie("session_token", "", time() - 3600, "/"); // Remove cookie
    echo "<script>alert('You have been logged out successfully.'); window.location.href='index.php';</script>";
    exit();
} else {
    // Ask for confirmation first
    echo "<script>
        if (confirm('Are you sure you want to log out?')) {
            window.location.href = 'logout.php?confirm=yes';
        } else {
            window.location.href = 'volunteer/volunteer_dashboard.php'; // cancel → back to dashboard
        }
    </script>";
    exit();
}
?>
