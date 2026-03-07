<?php
include '../conn.php';
include './check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $id = intval($_POST['id']);

    switch ($type) {
        case 'event':
            $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
            break;
        case 'task':
            $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
            break;
        case 'my_task':
            $stmt = $conn->prepare("DELETE FROM volunteer_applications WHERE id = ?");
            break;
        default:
            echo "Invalid type.";
            exit;
    }

    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo "<script>alert('Withdraw Successfully!'); window.location.href='my_tasks.php';</script>";
    } else {
        echo "Failed to delete.";
    }
    $stmt->close();
}
?>
