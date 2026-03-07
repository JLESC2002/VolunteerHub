<?php
include '../conn.php';
include './check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $facebook_link = trim($_POST['facebook_link']);
    $organization_id = intval($_POST['organization_id']);

    if (!empty($facebook_link) && $organization_id > 0) {
        $stmt = $conn->prepare("UPDATE organizations SET facebook_link = ? WHERE id = ?");
        $stmt->bind_param("si", $facebook_link, $organization_id);
        if ($stmt->execute()) {
            header("Location: admin_profile.php?success=fb_updated");
            exit;
        } else {
            header("Location: admin_profile.php?error=fb_failed");
            exit;
        }
    } else {
        header("Location: admin_profile.php?error=invalid_data");
        exit;
    }
} else {
    header("Location: admin_profile.php");
    exit;
}
?>
