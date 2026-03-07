<?php
// admin/admin_update_profile.php
include '../conn.php';
include './check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_photo'])) {
    $admin_id = $_SESSION['user_id'];

    if (empty($_FILES['profile_pic']['name'])) {
        echo "<script>alert('No file selected'); window.location.href='admin_profile.php';</script>";
        exit;
    }

    $upload_dir = "../uploads/profile/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_name = basename($_FILES['profile_pic']['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_ext = ['jpg','jpeg','png','gif'];

    if (!in_array($file_ext, $allowed_ext)) {
        echo "<script>alert('Invalid file type. Use JPG/PNG/GIF.'); window.location.href='admin_profile.php';</script>";
        exit;
    }

    // Create a unique filename
    $new_name = "profile_" . $admin_id . "_" . time() . "_" . uniqid() . "." . $file_ext;
    $target_path = $upload_dir . $new_name;

    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_path)) {
        // Optionally, remove previous profile picture file (safe-check)
        $oldQ = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
        $oldQ->bind_param("i", $admin_id);
        $oldQ->execute();
        $oldRes = $oldQ->get_result();
        if ($oldRes && $row = $oldRes->fetch_assoc()) {
            if (!empty($row['profile_pic']) && file_exists($upload_dir . $row['profile_pic'])) {
                @unlink($upload_dir . $row['profile_pic']);
            }
        }

        // Update DB
        $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
        $stmt->bind_param("si", $new_name, $admin_id);
        if ($stmt->execute()) {
            echo "<script>alert('Profile photo updated'); window.location.href='admin_profile.php';</script>";
            exit;
        } else {
            // rollback file if DB failed
            @unlink($target_path);
            echo "<script>alert('Failed to save profile info.'); window.location.href='admin_profile.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Failed to upload file.'); window.location.href='admin_profile.php';</script>";
        exit;
    }
} else {
    header("Location: admin_profile.php");
    exit;
}
