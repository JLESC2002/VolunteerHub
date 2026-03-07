<?php
include '../conn.php';
include './check_session.php';

$admin_id = $_SESSION['user_id'];
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if ($event_id <= 0) {
    echo "<tr><td colspan='3'>Invalid event.</td></tr>";
    exit;
}

/* Guard: ensure this event belongs to the logged-in admin */
$own = $conn->prepare("SELECT id FROM events WHERE id = ? AND created_by = ?");
$own->bind_param("ii", $event_id, $admin_id);
$own->execute();
$owns_event = $own->get_result()->num_rows > 0;
$own->close();

if (!$owns_event) {
    echo "<tr><td colspan='3'>Unauthorized.</td></tr>";
    exit;
}

/* Fetch applicants */
$stmt = $conn->prepare("
    SELECT u.id AS uid,
           u.name AS full_name,
           va.status
    FROM volunteer_applications va
    JOIN users u ON u.id = va.user_id
    WHERE va.event_id = ?
    ORDER BY u.name ASC
");

$stmt->bind_param("i", $event_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<tr><td colspan='3'>No volunteers yet.</td></tr>";
    exit;
}

while ($row = $res->fetch_assoc()) {
    $name   = htmlspecialchars($row['full_name'] ?? '');
    $status = htmlspecialchars($row['status'] ?? 'pending');
    $uid    = intval($row['uid']);

    echo "<tr>";
    echo "<td>{$name}</td>";
    echo "<td>{$status}</td>";

    // Action column: show Accept/Reject only when pending. Otherwise show '-'.
    if ($status === 'pending') {
        echo "<td style='display:flex; gap:.4rem; flex-wrap:wrap;'>";
        echo "  <button type='button' class='btn-success accept-btn' data-action='accept' data-volunteer='{$uid}'>";
        echo "    <i class='fas fa-check'></i> Accept";
        echo "  </button>";
        echo "  <button type='button' class='btn-danger reject-btn' data-action='reject' data-volunteer='{$uid}'>";
        echo "    <i class='fas fa-times'></i> Reject";
        echo "  </button>";
        echo "</td>";
    } else {
        echo "<td>-</td>";
    }
    echo "</tr>";
}
$stmt->close();
