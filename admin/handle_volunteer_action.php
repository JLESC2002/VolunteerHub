<?php
include '../conn.php';
include './check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $app_id = intval($_POST['application_id']);
    $new_status = $_POST['status'];

    if (!in_array($new_status, ['approved', 'rejected'])) {
        echo "Invalid status.";
        exit;
    }

    $stmt = $conn->prepare("UPDATE volunteer_applications SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $app_id);

    if ($stmt->execute()) {
        echo "Volunteer " . ucfirst($new_status) . "!";
    } else {
        echo "Failed to update status.";
    }
} else {
    echo "Invalid request.";
}
?>
