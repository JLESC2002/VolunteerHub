<?php
include '../conn.php';
include './check_session.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;
$notifications = [];

/* ── 1. Application status updates (approved / rejected / pending)
        → application_history.php
   ──────────────────────────────────────────────────────────────── */
$stmt = $conn->prepare("
    SELECT e.title, e.id AS eid, va.status, va.created_at
    FROM volunteer_applications va
    JOIN events e ON e.id = va.event_id
    WHERE va.user_id = ?
    ORDER BY va.created_at DESC
    LIMIT 8
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $status   = $r['status'];
    $approved = $status === 'approved';
    $rejected = $status === 'rejected';

    if ($approved) {
        $icon  = 'fas fa-check-circle';
        $color = 'green';
        $label = 'approved ✓';
        $unread = true;
    } elseif ($rejected) {
        $icon  = 'fas fa-times-circle';
        $color = 'red';
        $label = 'rejected';
        $unread = true;
    } else {
        $icon  = 'fas fa-clock';
        $color = 'amber';
        $label = 'pending review';
        $unread = false;
    }

    $notifications[] = [
        'message' => 'Application for <strong>' . htmlspecialchars($r['title']) . '</strong> is <strong>' . $label . '</strong>',
        'icon'    => $icon,
        'color'   => $color,
        'link'    => '/VolunteerHub/volunteer/application_history.php',   // ← correct destination
        'time'    => time_ago($r['created_at']),
        'ts'      => strtotime($r['created_at']),
        'unread'  => $unread,
    ];
}
$stmt->close();

/* ── 2. New task assignments / task reminders
        → my_tasks.php
   ──────────────────────────────────────────────────────────────── */
$stmt = $conn->prepare("
    SELECT t.description, e.title AS event_title, e.id AS eid, ta.assigned_at
    FROM task_assignments ta
    JOIN tasks t  ON t.id  = ta.task_id
    JOIN events e ON e.id  = t.event_id
    WHERE ta.volunteer_id = ?
    ORDER BY ta.assigned_at DESC
    LIMIT 6
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $notifications[] = [
        'message' => 'Task assigned in <strong>' . htmlspecialchars($r['event_title']) . '</strong>: ' . htmlspecialchars($r['description']),
        'icon'    => 'fas fa-tasks',
        'color'   => 'amber',
        'link'    => '/VolunteerHub/volunteer/my_tasks.php',              // ← correct destination
        'time'    => time_ago($r['assigned_at']),
        'ts'      => strtotime($r['assigned_at']),
        'unread'  => false,
    ];
}
$stmt->close();

/* ── 3. Upcoming event reminders (within 3 days for approved applications)
        → list_events.php
   ──────────────────────────────────────────────────────────────── */
$stmt = $conn->prepare("
    SELECT e.title, e.date, e.location, e.id AS eid
    FROM volunteer_applications va
    JOIN events e ON e.id = va.event_id
    WHERE va.user_id = ?
      AND va.status = 'approved'
      AND e.date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY e.date ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $notifications[] = [
        'message' => 'Upcoming: <strong>' . htmlspecialchars($r['title']) . '</strong> on ' . date('M d', strtotime($r['date'])) . ' — ' . htmlspecialchars($r['location']),
        'icon'    => 'fas fa-calendar-alt',
        'color'   => 'blue',
        'link'    => '/VolunteerHub/volunteer/list_events.php',           // ← correct destination
        'time'    => time_ago($r['date']),
        'ts'      => strtotime($r['date']),
        'unread'  => true,
    ];
}
$stmt->close();

/* ── 4. Check-in / check-out confirmations
        → my_tasks.php  (attendance is task-context)
   ──────────────────────────────────────────────────────────────── */
$stmt = $conn->prepare("
    SELECT e.title, ea.check_in, ea.check_out, ea.attended, e.id AS eid
    FROM event_attendance ea
    JOIN events e ON e.id = ea.event_id
    WHERE ea.volunteer_id = ?
      AND (ea.check_in IS NOT NULL OR ea.check_out IS NOT NULL)
    ORDER BY COALESCE(ea.check_out, ea.check_in) DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    if (!empty($r['check_out']) && $r['attended'] == 2) {
        $msg  = 'Checked <strong>out</strong> of <strong>' . htmlspecialchars($r['title']) . '</strong>';
        $icon = 'fas fa-sign-out-alt';
        $col  = 'blue';
        $ts   = strtotime($r['check_out']);
        $time = time_ago($r['check_out']);
    } else {
        $msg  = 'Checked <strong>in</strong> to <strong>' . htmlspecialchars($r['title']) . '</strong>';
        $icon = 'fas fa-sign-in-alt';
        $col  = 'green';
        $ts   = strtotime($r['check_in']);
        $time = time_ago($r['check_in']);
    }
    $notifications[] = [
        'message' => $msg,
        'icon'    => $icon,
        'color'   => $col,
        'link'    => '/VolunteerHub/volunteer/my_tasks.php',              // ← correct destination
        'time'    => $time,
        'ts'      => $ts,
        'unread'  => false,
    ];
}
$stmt->close();

/* ── Sort newest first, cap at 15 ─────────────────────── */
usort($notifications, fn($a, $b) => $b['ts'] - $a['ts']);
$notifications = array_slice($notifications, 0, 15);

if (isset($_GET['count_only'])) {
    $unread = count(array_filter($notifications, fn($n) => $n['unread']));
    echo json_encode(['unread' => $unread]);
    exit;
}

$unread = count(array_filter($notifications, fn($n) => $n['unread']));
echo json_encode(['items' => $notifications, 'unread' => $unread]);

/* ── Helper ──────────────────────────────────────────── */
function time_ago($datetime) {
    if (!$datetime) return '';
    $diff = time() - strtotime($datetime);
    if ($diff < 0)        return 'soon';
    if ($diff < 60)       return 'just now';
    if ($diff < 3600)     return floor($diff / 60)    . ' min ago';
    if ($diff < 86400)    return floor($diff / 3600)  . ' hr ago';
    if ($diff < 604800)   return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}