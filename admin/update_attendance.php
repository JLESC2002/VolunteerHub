<?php
// admin/update_attendance.php

include '../conn.php';
include './check_session.php'; // ensure the user is logged in and $_SESSION['user_id'] is set

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed";
    exit;
}

// Ensure required POST data
if (!isset($_POST['event_id'], $_POST['volunteer_id'], $_POST['attended'])) {
    http_response_code(400);
    echo "Missing parameters.";
    exit;
}

$event_id = intval($_POST['event_id']);
$volunteer_id = intval($_POST['volunteer_id']);
$attended = intval($_POST['attended']); // 1 = check in, 2 = check out, 0 = not checked in

// SECURITY: only allow the logged-in volunteer to check themselves in/out (or admin role)
$logged_user = $_SESSION['volunteer_id'] ?? ($_SESSION['user_id'] ?? null);
$user_role = $_SESSION['user_role'] ?? 'Volunteer';

if ($user_role !== 'Admin' && $logged_user !== $volunteer_id) {
    http_response_code(403);
    echo "Forbidden: volunteer identity mismatch.";
    exit;
}

// Check if record exists in event_attendance
$check = $conn->prepare("SELECT event_id FROM event_attendance WHERE event_id = ? AND volunteer_id = ?");
$check->bind_param("ii", $event_id, $volunteer_id);
$check->execute();
$result = $check->get_result();
$exists = $result->num_rows > 0;
$check->close();

if ($attended === 1) {
    // ✅ CHECK-IN
    if ($exists) {
        $stmt = $conn->prepare("
            UPDATE event_attendance 
            SET attended = 1, check_in = NOW(), check_out = NULL 
            WHERE event_id = ? AND volunteer_id = ?
        ");
    } else {
        $stmt = $conn->prepare("
            INSERT INTO event_attendance (event_id, volunteer_id, attended, check_in)
            VALUES (?, ?, 1, NOW())
        ");
    }
    $stmt->bind_param("ii", $event_id, $volunteer_id);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo "DB error: " . $stmt->error;
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Progress = In Progress
    $progress = "In Progress";
    $update = $conn->prepare("
        UPDATE task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        SET ta.progress = ?
        WHERE t.event_id = ? AND ta.volunteer_id = ?
    ");
    $update->bind_param("sii", $progress, $event_id, $volunteer_id);
    $update->execute();
    $update->close();

    echo "Checked in successfully.";
    exit;

} elseif ($attended === 2) {
    // ✅ CHECK-OUT
    $stmt = $conn->prepare("
        UPDATE event_attendance 
        SET attended = 2, check_out = NOW() 
        WHERE event_id = ? AND volunteer_id = ?
    ");
    $stmt->bind_param("ii", $event_id, $volunteer_id);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo "DB error: " . $stmt->error;
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Progress = Completed
    $progress = "Completed";
    $update = $conn->prepare("
        UPDATE task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        SET ta.progress = ?
        WHERE t.event_id = ? AND ta.volunteer_id = ?
    ");
    $update->bind_param("sii", $progress, $event_id, $volunteer_id);
    $update->execute();
    $update->close();

    echo "Checked out successfully.";
    exit;

} else {
    // Default reset or invalid
    $stmt = $conn->prepare("
        UPDATE event_attendance 
        SET attended = 0, check_in = NULL, check_out = NULL 
        WHERE event_id = ? AND volunteer_id = ?
    ");
    $stmt->bind_param("ii", $event_id, $volunteer_id);
    $stmt->execute();
    $stmt->close();

    // Progress = Not Started
    $progress = "Not Started";
    $update = $conn->prepare("
        UPDATE task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        SET ta.progress = ?
        WHERE t.event_id = ? AND ta.volunteer_id = ?
    ");
    $update->bind_param("sii", $progress, $event_id, $volunteer_id);
    $update->execute();
    $update->close();

    echo "Attendance reset to not started.";
    exit;
}
