<?php
include '../conn.php';
include './check_session.php';
$pageCSS = "../styles/volunteer_tables.css";
include '../includes/header_volunteer.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
    $event_id = intval($_POST['event_id']);

    $checkQuery = "SELECT * FROM volunteer_applications WHERE user_id = ? AND event_id = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<script>alert('You have already signed up for this event!');</script>";
    } else {
        $insertQuery = "INSERT INTO volunteer_applications (user_id, event_id, status) VALUES (?, ?, 'pending')";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("ii", $user_id, $event_id);
        if ($stmt->execute()) {
            echo "<script>alert('You have successfully signed up for the event!');</script>";
        } else {
            echo "<script>alert('Error signing up. Please try again later.');</script>";
        }
    }
}

$eventsQuery = "
    SELECT e.id, e.title, e.description, e.date, e.location, u.name AS created_by 
    FROM events e
    JOIN users u ON e.created_by = u.id
    WHERE e.status = 'Open'
      AND e.id NOT IN (
          SELECT event_id FROM volunteer_applications WHERE user_id = ?
      )
    ORDER BY e.date ASC
";

$stmt = $conn->prepare($eventsQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$eventsResult = $stmt->get_result();

?>

<div class="dashboard-container">
    <h2 class="section-title"><i class="fas fa-calendar-alt"></i> Available Events</h2>
    <div class="table-wrapper">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Event Name</th>
                    <th>Description</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Created By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $eventsResult->fetch_assoc()) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= htmlspecialchars($row['description']) ?></td>
                    <td><?= htmlspecialchars($row['date']) ?></td>
                    <td><?= htmlspecialchars($row['location']) ?></td>
                    <td><?= htmlspecialchars($row['created_by']) ?></td>
                    <td>
                        <form method="POST" action="">
                            <input type="hidden" name="event_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn-primary"><i class="fas fa-plus"></i> Sign Up</button>
                        </form>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
