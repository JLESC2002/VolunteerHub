<?php
include '../conn.php';
include './check_session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$admin_id     = $_SESSION['user_id'];
$event_id     = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$volunteer_id = isset($_POST['volunteer_id']) ? intval($_POST['volunteer_id']) : 0;
$action       = $_POST['action'] ?? '';

if (!$event_id || !$volunteer_id || !in_array($action, ['accept','reject'], true)) {
    http_response_code(400);
    echo "Invalid data";
    exit;
}

/* Ensure the event belongs to this admin */
$own = $conn->prepare("SELECT id FROM events WHERE id = ? AND created_by = ?");
$own->bind_param("ii", $event_id, $admin_id);
$own->execute();
$owns_event = $own->get_result()->num_rows > 0;
$own->close();

if (!$owns_event) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$new_status = $action === 'accept' ? 'approved' : 'rejected';

$upd = $conn->prepare("
    UPDATE volunteer_applications 
       SET status = ? 
     WHERE event_id = ? AND user_id = ?
");
$upd->bind_param("sii", $new_status, $event_id, $volunteer_id);
$upd->execute();
$upd->close();

/* Optional: if rejecting, you might want to unassign tasks of this event for this volunteer.
   Keeping untouched per your request to not remove other functions. */

echo "OK";
