<?php
include '../conn.php';
include './check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
    $event_id = intval($_POST['event_id']);

    // Set event status to Completed
    $stmt = $conn->prepare("UPDATE events SET status = 'Completed' WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $event_id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();

    // Update volunteer applications to 'completed' for those who attended
    $update = $conn->prepare("
        UPDATE volunteer_applications va
        JOIN event_attendance ea ON ea.volunteer_id = va.user_id AND ea.event_id = va.event_id
        SET va.status = 'completed'
        WHERE ea.attended = 1 AND va.event_id = ?
    ");
    $update->bind_param("i", $event_id);
    $update->execute();
    $update->close();

    echo "<script>alert('Event marked as completed and attended volunteers updated to completed.'); window.location.href='manage_events.php';</script>";
    exit;
} else {
    echo "<script>alert('Invalid request.'); window.location.href='manage_events.php';</script>";
    exit;
}
?>
