<?php
include '../conn.php';
include './check_session.php';

// Page setup
$pageTitle = "My Tasks";
$pageCSS = "../styles/my_tasks.css";
include '../includes/header_volunteer.php';

$volunteer_id = $_SESSION['volunteer_id'] ?? $_SESSION['user_id'] ?? null;

// Fetch volunteer's assigned tasks
$query = $conn->prepare("
    SELECT e.id AS event_id, e.title AS event_title, e.date, e.location, 
           t.id AS task_id, t.description, 
           COALESCE(ta.progress, 'Not Started') AS progress,
           COALESCE(ea.attended, 0) AS attended,
           ea.check_in,
           ea.check_out
    FROM task_assignments ta
    JOIN tasks t ON ta.task_id = t.id
    JOIN events e ON e.id = t.event_id
    LEFT JOIN event_attendance ea 
           ON ea.event_id = e.id AND ea.volunteer_id = ta.volunteer_id
    WHERE ta.volunteer_id = ?
    ORDER BY e.date DESC
");
$query->bind_param("i", $volunteer_id);
$query->execute();
$result = $query->get_result();
?>

<div class="dashboard-container">
    <h2 class="section-title"><i class="fas fa-list"></i> My Assigned Tasks</h2>

    <div class="table-wrapper">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Task Description</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Attendance</th>
                    <th>Progress</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row['event_title']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td>
                            <?php if ($row['attended'] == 0) { ?>
                                <!-- Not Checked In -->
                                <button class="btn-primary" onclick="openQRScanner('checkin', <?= $row['event_id'] ?>)">
                                    📷 Scan to Check In
                                </button>
                            <?php } elseif ($row['attended'] == 1) { ?>
                                <!-- Checked In -->
                                <button class="btn-secondary" onclick="openQRScanner('checkout', <?= $row['event_id'] ?>)">
                                    📷 Scan to Check Out
                                </button>
                            <?php } elseif ($row['attended'] == 2) { ?>
                                <!-- Checked Out -->
                                <span class="badge bg-success">
                                    ✅ Checked Out<br>
                                    <small><?= date("M d, Y h:i A", strtotime($row['check_out'])) ?></small>
                                </span>
                            <?php } ?>
                        </td>
                        <td>
                            <?php
                            $progressLabel = 'Not Started';
                            $progressClass = 'bg-secondary';

                            if ($row['attended'] == 1) {
                                $progressLabel = 'In Progress';
                                $progressClass = 'bg-warning';
                            } elseif ($row['attended'] == 2) {
                                $progressLabel = 'Completed';
                                $progressClass = 'bg-success';
                            }
                            ?>
                            <span class="badge <?= $progressClass ?>">
                                <?= htmlspecialchars($progressLabel) ?>
                            </span>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ✅ QR Scanner Modal -->
<div id="qrModal" class="modal" style="display:none;">
  <div class="modal-content">
    <h3 id="scanTitle">Scan QR Code</h3>
    <div id="reader" style="width:300px; margin:auto;"></div>
    <div style="margin-top:10px; text-align:center;">
        <button onclick="closeQRScanner()" class="btn-danger">Close</button>
    </div>
  </div>
</div>

<!-- ✅ Scripts -->
<script src="../vendor/html5-qrcode.min.js"></script>
<script>
let html5QrCode = null;
let currentMode = "";
let currentEventId = 0;

function openQRScanner(mode, eventId) {
  currentMode = mode;
  currentEventId = eventId;
  document.getElementById("qrModal").style.display = "flex";
  document.getElementById("scanTitle").innerText = 
    mode === "checkin" ? "📷 Scan Event QR to Check In" : "📷 Scan Event QR to Check Out";

  if (!html5QrCode) html5QrCode = new Html5Qrcode("reader");
  const config = { fps: 10, qrbox: 250 };

  html5QrCode.start({ facingMode: "environment" }, config, qrCodeMessage => {
    try {
      const data = JSON.parse(qrCodeMessage);

      if (!data.event_id) return alert("Invalid QR Code");
      if (data.event_id != currentEventId) return alert("⚠️ This QR code is not for your assigned event!");
      if (!data.lat || !data.lon) return alert("⚠️ This QR code lacks location data!");

      navigator.geolocation.getCurrentPosition(pos => {
        const R = 6371e3;
        const φ1 = pos.coords.latitude * Math.PI / 180;
        const φ2 = data.lat * Math.PI / 180;
        const Δφ = (data.lat - pos.coords.latitude) * Math.PI / 180;
        const Δλ = (data.lon - pos.coords.longitude) * Math.PI / 180;
        const a = Math.sin(Δφ/2)**2 + Math.cos(φ1) * Math.cos(φ2) * Math.sin(Δλ/2)**2;
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        const distance = R * c;

        if (distance > 100) {
          alert("⚠️ You are too far from the event location!");
          return closeQRScanner();
        }

        // ✅ Adjusted: 1 = check-in, 2 = check-out
        const attended = currentMode === "checkin" ? 1 : 2;

        const params = new URLSearchParams();
        params.append('event_id', data.event_id);
        params.append('volunteer_id', <?= json_encode($volunteer_id) ?>);
        params.append('attended', attended);

        fetch("../admin/update_attendance.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: params.toString(),
          credentials: "same-origin"
        })
        .then(async (r) => {
          const text = await r.text();
          if (!r.ok) {
            console.error("Attendance update failed:", r.status, text);
            alert("Error updating attendance: " + (text || r.status));
            return;
          }
          console.log("Attendance update response:", text);
          alert(currentMode === "checkin" ? "✅ Checked In!" : "👋 Checked Out!");
          closeQRScanner();
          location.reload();
        })
        .catch((err) => {
          console.error("Network or JS error updating attendance:", err);
          alert("Network error updating attendance. See console for details.");
        });

      }, () => {
        alert("📍 Please enable location access.");
        closeQRScanner();
      });
    } catch (e) {
      alert("Invalid QR Format.");
    }
  }).catch(err => console.error("QR Scanner failed:", err));
}

function closeQRScanner() {
  document.getElementById("qrModal").style.display = "none";
  if (html5QrCode) html5QrCode.stop().then(() => html5QrCode.clear());
}
</script>

<?php include '../includes/footer.php'; ?>
