<?php
include '../conn.php';
include './check_session.php';

header("Content-Type: text/html; charset=UTF-8"); // Important for correct content-type

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);

    $query = "
        SELECT users.id, users.name 
        FROM users
        INNER JOIN volunteer_applications ON users.id = volunteer_applications.user_id
        WHERE volunteer_applications.event_id = ? 
          AND volunteer_applications.status = 'approved'
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<option value='" . htmlspecialchars($row['id']) . "'>" . htmlspecialchars($row['name']) . "</option>";
        }
    } else {
        echo "<option value=''>No approved volunteers found</option>";
    }
} else {
    echo "<option value=''>Invalid request</option>";
}
?>
