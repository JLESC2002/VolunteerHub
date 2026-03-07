<?php
include '../conn.php';
include './check_session.php';
require_once '../vendor/phpqrcode/qrlib.php';

if (!isset($_GET['event_id'])) {
    exit("Missing event_id");
}

$event_id = intval($_GET['event_id']);
$admin_id = $_SESSION['user_id'];

// ✅ Fetch event data
$stmt = $conn->prepare("SELECT title, latitude, longitude FROM events WHERE id = ? AND created_by = ?");
$stmt->bind_param("ii", $event_id, $admin_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    exit("Event not found or not authorized.");
}

$event_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $event['title']);
$lat = $event['latitude'];
$lon = $event['longitude'];

// ✅ QR directories
$checkinDir = "../Generator/QRcode Checkin/";
$checkoutDir = "../Generator/QRcode Checkout/";

if (!file_exists($checkinDir)) mkdir($checkinDir, 0777, true);
if (!file_exists($checkoutDir)) mkdir($checkoutDir, 0777, true);

// ✅ QR content (JSON structure)
$checkinData = json_encode(["event_id" => $event_id, "type" => "checkin", "lat" => $lat, "lon" => $lon]);
$checkoutData = json_encode(["event_id" => $event_id, "type" => "checkout", "lat" => $lat, "lon" => $lon]);

// ✅ File paths
$checkinFile = $checkinDir . "{$event_name}_CheckIn.png";
$checkoutFile = $checkoutDir . "{$event_name}_CheckOut.png";

// ✅ Generate QR codes
QRcode::png($checkinData, $checkinFile, QR_ECLEVEL_L, 6);
QRcode::png($checkoutData, $checkoutFile, QR_ECLEVEL_L, 6);

// ── Output: JSON (for modal JS) or legacy HTML ──────────
if (!empty($_GET['json'])) {
    // Return JSON status so the modal can show previews and wire up PDF link
    header('Content-Type: application/json');
    echo json_encode([
        'success'      => true,
        'checkin_url'  => '/VolunteerHub/Generator/QRcode Checkin/'  . basename($checkinFile),
        'checkout_url' => '/VolunteerHub/Generator/QRcode Checkout/' . basename($checkoutFile),
    ]);
} else {
    // Legacy HTML output (kept for backwards compatibility)
    ?>
    <div style="text-align:center;">
        <h3>QR Codes for: <?= htmlspecialchars($event['title']) ?></h3>
        <div style="display:flex;justify-content:center;gap:30px;flex-wrap:wrap;">
            <div>
                <h4>Check-In QR</h4>
                <img src="/VolunteerHub/Generator/QRcode Checkin/<?= basename($checkinFile) ?>"
                     alt="Check-In QR" width="200">
            </div>
            <div>
                <h4>Check-Out QR</h4>
                <img src="/VolunteerHub/Generator/QRcode Checkout/<?= basename($checkoutFile) ?>"
                     alt="Check-Out QR" width="200">
            </div>
        </div>
    </div>
    <?php
}