<?php
include '../conn.php';
include './check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_photo'])) {
    $user_id = $_SESSION['user_id'];

    if (empty($_FILES['profile_pic']['name'])) {
        echo "<script>alert('No file selected'); window.location.href='volunteers_profile.php';</script>";
        exit;
    }

    $upload_dir = "../uploads/profile/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $file_name = basename($_FILES['profile_pic']['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_ext = ['jpg','jpeg','png','gif'];

    if (!in_array($file_ext, $allowed_ext)) {
        echo "<script>alert('Invalid file type. Use JPG/PNG/GIF.'); window.location.href='volunteers_profile.php';</script>";
        exit;
    }

    $new_name = "volunteer_" . $user_id . "_" . time() . "_" . uniqid() . "." . $file_ext;
    $target_path = $upload_dir . $new_name;

    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_path)) {
        // Delete old image
        $old = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
        $old->bind_param("i", $user_id);
        $old->execute();
        $old_res = $old->get_result()->fetch_assoc();
        if (!empty($old_res['profile_pic']) && file_exists($upload_dir . $old_res['profile_pic'])) {
            @unlink($upload_dir . $old_res['profile_pic']);
        }

        // Save new image
        $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
        $stmt->bind_param("si", $new_name, $user_id);
        $stmt->execute();

        echo "<script>alert('Profile photo updated successfully'); window.location.href='volunteers_profile.php';</script>";
        exit;
    } else {
        echo "<script>alert('Failed to upload file'); window.location.href='volunteers_profile.php';</script>";
        exit;
    }
} else {
    header('Location: volunteers_profile.php');
    exit;
}
?>
