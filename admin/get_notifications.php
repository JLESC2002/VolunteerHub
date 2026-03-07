<?php
// admin/get_notifications.php
// AJAX endpoint — returns notification items as JSON

include '../conn.php';
include './check_session.php';

header('Content-Type: application/json');

$admin_id = $_SESSION['user_id'] ?? 0;

// Get this admin's organization
$orgStmt = $conn->prepare("SELECT id FROM organizations WHERE admin_id = ?");
$orgStmt->bind_param("i", $admin_id);
$orgStmt->execute();
$orgData = $orgStmt->get_result()->fetch_assoc();
$org_id  = $orgData['id'] ?? 0;

$notifications = [];

// ── 1. Pending volunteer applications for admin's events ─────────────────────
$stmt = $conn->prepare("
    SELECT u.name, e.title, va.created_at
    FROM volunteer_applications va
    JOIN users u  ON u.id  = va.user_id
    JOIN events e ON e.id  = va.event_id
    WHERE e.created_by = ? AND va.status = 'pending'
    ORDER BY va.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $notifications[] = [
        'message' => htmlspecialchars($r['name']) . ' applied to your event: <strong>' . htmlspecialchars($r['title']) . '</strong>',
        'icon'    => 'fas fa-user-plus',
        'color'   => 'green',
        'link'    => '/VolunteerHub/admin/manage_events.php',
        'time'    => time_ago($r['created_at']),
        'ts'      => strtotime($r['created_at']),
        'unread'  => true,
    ];
}

// ── 2. Recent check-ins/check-outs for admin's events ────────────────────────
$stmt = $conn->prepare("
    SELECT u.name, e.title, ea.check_in, ea.check_out, ea.attended
    FROM event_attendance ea
    JOIN users u  ON u.id  = ea.volunteer_id
    JOIN events e ON e.id  = ea.event_id
    WHERE e.created_by = ?
      AND (ea.check_in IS NOT NULL OR ea.check_out IS NOT NULL)
    ORDER BY COALESCE(ea.check_out, ea.check_in) DESC
    LIMIT 5
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    if ($r['attended'] == 2 && $r['check_out']) {
        $msg  = htmlspecialchars($r['name']) . ' checked <strong>out</strong> of: ' . htmlspecialchars($r['title']);
        $icon = 'fas fa-sign-out-alt';
        $col  = 'blue';
        $ts   = strtotime($r['check_out']);
        $time = time_ago($r['check_out']);
    } else {
        $msg  = htmlspecialchars($r['name']) . ' checked <strong>in</strong> to: ' . htmlspecialchars($r['title']);
        $icon = 'fas fa-sign-in-alt';
        $col  = 'green';
        $ts   = strtotime($r['check_in']);
        $time = time_ago($r['check_in']);
    }
    $notifications[] = [
        'message' => $msg,
        'icon'    => $icon,
        'color'   => $col,
        'link'    => '/VolunteerHub/admin/manage_tasks.php',
        'time'    => $time,
        'ts'      => $ts,
        'unread'  => false,
    ];
}

// ── 3. New donations for admin's organization ─────────────────────────────────
if ($org_id) {
    $stmt = $conn->prepare("
        SELECT u.name, g.amount, g.created_at, 'GCash' AS method
        FROM gcash_donations g
        JOIN users u ON u.id = g.user_id
        WHERE g.organization_id = ? AND g.status = 'Pending'
        ORDER BY g.created_at DESC
        LIMIT 3
    ");
    $stmt->bind_param("i", $org_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $notifications[] = [
            'message' => htmlspecialchars($r['name']) . ' submitted a <strong>' . $r['method'] . ' donation</strong> of ₱' . number_format($r['amount'], 2),
            'icon'    => 'fas fa-hand-holding-heart',
            'color'   => 'purple',
            'link'    => '/VolunteerHub/admin/manage_donations.php',
            'time'    => time_ago($r['created_at']),
            'ts'      => strtotime($r['created_at']),
            'unread'  => true,
        ];
    }

    $stmt = $conn->prepare("
        SELECT u.name, b.amount, b.created_at, 'Bank Transfer' AS method
        FROM bank_payments b
        JOIN users u ON u.id = b.user_id
        WHERE b.organization_id = ? AND b.status = 'Pending'
        ORDER BY b.created_at DESC
        LIMIT 3
    ");
    $stmt->bind_param("i", $org_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $notifications[] = [
            'message' => htmlspecialchars($r['name']) . ' submitted a <strong>' . $r['method'] . ' donation</strong> of ₱' . number_format($r['amount'], 2),
            'icon'    => 'fas fa-hand-holding-heart',
            'color'   => 'purple',
            'link'    => '/VolunteerHub/admin/manage_donations.php',
            'time'    => time_ago($r['created_at']),
            'ts'      => strtotime($r['created_at']),
            'unread'  => true,
        ];
    }
}

// ── 4. Task status updates ────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT u.name, t.description, ta.progress, e.title AS event_title, ta.assigned_at
    FROM task_assignments ta
    JOIN users u  ON u.id  = ta.volunteer_id
    JOIN tasks t  ON t.id  = ta.task_id
    JOIN events e ON e.id  = t.event_id
    WHERE e.created_by = ? AND ta.progress IN ('In Progress','Completed')
    ORDER BY ta.assigned_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $color = $r['progress'] === 'Completed' ? 'green' : 'amber';
    $notifications[] = [
        'message' => htmlspecialchars($r['name']) . ' marked task as <strong>' . htmlspecialchars($r['progress']) . '</strong> in ' . htmlspecialchars($r['event_title']),
        'icon'    => $r['progress'] === 'Completed' ? 'fas fa-check-circle' : 'fas fa-spinner',
        'color'   => $color,
        'link'    => '/VolunteerHub/admin/manage_tasks.php',
        'time'    => time_ago($r['assigned_at']),
        'ts'      => strtotime($r['assigned_at']),
        'unread'  => false,
    ];
}

// Sort by newest first, cap at 15
usort($notifications, fn($a, $b) => $b['ts'] - $a['ts']);
$notifications = array_slice($notifications, 0, 15);

// If count_only requested, just return unread count
if (isset($_GET['count_only'])) {
    $unread = count(array_filter($notifications, fn($n) => $n['unread']));
    echo json_encode(['unread' => $unread]);
    exit;
}

$unread = count(array_filter($notifications, fn($n) => $n['unread']));
echo json_encode(['items' => $notifications, 'unread' => $unread]);

// ── Helpers ───────────────────────────────────────────────────────────────────
function time_ago($datetime) {
    if (!$datetime) return '';
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d', strtotime($datetime));
}