<?php
include '../conn.php';
include './check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id']) && isset($_POST['volunteers'])) {
    $task_id = intval($_POST['task_id']);
    $volunteers = $_POST['volunteers'];
    $assigned_by = $_SESSION['user_id'];

    $success_count = 0;
    $error_count = 0;

    foreach ($volunteers as $volunteer_id) {
        $volunteer_id = intval($volunteer_id);

        $checkQuery = "SELECT id FROM task_assignments WHERE task_id = ? AND volunteer_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("ii", $task_id, $volunteer_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 0) {
            $insertQuery = "INSERT INTO task_assignments (task_id, volunteer_id, assigned_by) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("iii", $task_id, $volunteer_id, $assigned_by);
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
    }

    if ($success_count > 0) {
        echo "Successfully assigned $success_count volunteer(s) to the task.";
    } else {
        echo "No new assignments were made.";
    }
} else {
    echo "Invalid request.";
}
?>
