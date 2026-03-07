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

// ✅ Output HTML for modal
?>
<div style="text-align:center;">
    <h3>QR Codes for: <?= htmlspecialchars($event['title']) ?></h3>

    <div style="display:flex; justify-content:center; gap:30px; flex-wrap:wrap;">
        <div>
            <h4>Check-In QR</h4>
            <img src="<?= $checkinFile ?>" alt="Check-In QR" width="200">
            <br>
            <a href="<?= $checkinFile ?>" download class="btn-primary" style="display:inline-block;margin-top:5px;">⬇ Download</a>
        </div>
        <div>
            <h4>Check-Out QR</h4>
            <img src="<?= $checkoutFile ?>" alt="Check-Out QR" width="200">
            <br>
            <a href="<?= $checkoutFile ?>" download class="btn-primary" style="display:inline-block;margin-top:5px;">⬇ Download</a>
        </div>
    </div>
</div>
