<?php
include '../conn.php';
include './check_session.php';
include '../includes/header_volunteer.php';

if (!isset($_GET['id'])) {
    echo "<div class='alert alert-danger'>Organization not found.</div>";
    exit;
}

$organization_id = intval($_GET['id']);

// ✅ Fetch organization details
$stmt = $conn->prepare("
    SELECT id, name, description, location, contact_email, contact_phone,
           gcash_qr, gcash_name, gcash_number,
           bank_name, bank_account_name, bank_account_number,
           dropoff_location, dropoff_instructions,
           logo, facebook_link
    FROM organizations
    WHERE id = ?
");



$stmt->bind_param("i", $organization_id);
$stmt->execute();
$org_result = $stmt->get_result();

if ($org_result->num_rows === 0) {
    echo "<div class='alert alert-danger'>Organization not found.</div>";
    exit;
}
$organization = $org_result->fetch_assoc();

// ✅ Fetch past (Completed) events
$past_stmt = $conn->prepare("
    SELECT e.title, e.date, e.description
    FROM events e
    JOIN organizations o ON e.created_by = o.admin_id
    WHERE o.id = ? AND e.status = 'Completed'
    ORDER BY e.date DESC
");
$past_stmt->bind_param("i", $organization_id);
$past_stmt->execute();
$past_events = $past_stmt->get_result();

// ✅ Fetch current & upcoming events
$current_stmt = $conn->prepare("
    SELECT e.title, e.date, e.description, e.status
    FROM events e
    JOIN organizations o ON e.created_by = o.admin_id
    WHERE o.id = ? AND e.status IN ('Open', 'Ongoing')
    ORDER BY e.date ASC
");
$current_stmt->bind_param("i", $organization_id);
$current_stmt->execute();
$current_events = $current_stmt->get_result();

// ✅ Fetch user donation history
$user_id = $_SESSION['user_id'];
$donation_query = $conn->prepare("
    SELECT 'GCash' AS method, amount, reference_number, status, created_at 
      FROM gcash_donations WHERE user_id = ? AND organization_id = ?
    UNION ALL
    SELECT 'Bank Transfer' AS method, amount, reference_number, status, created_at
      FROM bank_payments WHERE user_id = ? AND organization_id = ?
    ORDER BY created_at DESC
");
$donation_query->bind_param("iiii", $user_id, $organization_id, $user_id, $organization_id);
$donation_query->execute();
$donation_result = $donation_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($organization['name']) ?> - Profile</title>
<style>
    .org-container {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        margin: 40px auto;
        max-width: 1100px;
        box-shadow: 0 6px 12px rgba(0,0,0,0.1);
    }
    .org-header { text-align: center; margin-bottom: 30px; }
    .org-header h1 { font-size: 28px; color: #333; margin-bottom: 10px; }
    .org-header p { font-size: 16px; color: #666; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
    th { background: #f4f4f4; }
    .section { margin-top: 40px; }
    .section h2 { color: #222; margin-bottom: 10px; border-left: 5px solid #007bff; padding-left: 10px; }
    .donation-buttons {
        display: flex; justify-content: center; gap: 10px; margin: 20px 0;
    }
    .modal {
        display: none; position: fixed; top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        align-items: center; justify-content: center;
        z-index: 999;
    }
    .modal-content {
        background: #fff; padding: 20px; border-radius: 10px;
        max-width: 450px; width: 90%;
        box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    }
    .modal-content img { display: block; margin: 10px auto; border-radius: 8px; }
    input, textarea, select { width: 100%; padding: 8px; margin-bottom: 10px; border-radius: 6px; border: 1px solid #ccc; }
    .org-header {
    text-align: center;
    margin-bottom: 30px;
}

.org-logo {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #e2e8f0;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    margin-bottom: 12px;
}

.fb-link {
    color: #1877f2;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.fb-link:hover {
    text-decoration: underline;
}

/* --- Donation Buttons --- */
.btn-primary, .btn-secondary, .btn-warning, .btn-success {
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    color: #fff;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s ease;
}

.btn-primary { background-color: #007bff; }
.btn-primary:hover { background-color: #0069d9; }

.btn-secondary { background-color: #6c757d; }
.btn-secondary:hover { background-color: #5a6268; }

.btn-warning { background-color: #ffc107; color: #000; }
.btn-warning:hover { background-color: #e0a800; }

.btn-success { background-color: #28a745; }
.btn-success:hover { background-color: #218838; }

.alert {
    text-align: center;
    padding: 12px;
    border-radius: 6px;
    margin: 10px auto;
    max-width: 800px;
}

.alert-success { background: #d4edda; color: #155724; }
.alert-danger { background: #f8d7da; color: #721c24; }


</style>
</head>
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">✅ Donation submitted successfully! Awaiting admin verification.</div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="alert alert-danger">⚠️ Error: <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<body>

<div class="org-container">
    <div class="org-header">
    <?php if (!empty($organization['logo']) && file_exists(__DIR__ . '/../uploads/' . $organization['logo'])): ?>
        <img src="../uploads/<?= htmlspecialchars($organization['logo']); ?>" 
             alt="Organization Logo" class="org-logo">
    <?php else: ?>
        <img src="../uploads/default_logo.png" alt="Default Logo" class="org-logo">
    <?php endif; ?>

    <h1><?= htmlspecialchars($organization['name']) ?></h1>
    <p><?= htmlspecialchars($organization['description']) ?></p>
    <p><strong>Address:</strong> <?= htmlspecialchars($organization['location']) ?></p>
    <p><strong>Contact Email:</strong> <?= htmlspecialchars($organization['contact_email']) ?></p>
    <p><strong>Contact Number:</strong> <?= htmlspecialchars($organization['contact_phone']) ?></p>

    <?php if (!empty($organization['facebook_link'])): ?>
        <p><strong>Facebook Page:</strong>
            <a href="<?= htmlspecialchars($organization['facebook_link']); ?>" 
               target="_blank" class="fb-link">
                <i class="fab fa-facebook"></i> Visit Page
            </a>
        </p>
    <?php endif; ?>
</div>


    </div>

    <!-- 💙 GCash Modal -->
    <div id="gcashModal" class="modal">
        <div class="modal-content">
            <h3>Donate via GCash</h3>
            <?php if (!empty($organization['gcash_qr'])): ?>
                <p>Scan this QR Code to donate directly:</p>
                <img src="../uploads/<?= htmlspecialchars($organization['gcash_qr']) ?>" 
                alt="GCash QR" width="200" height="200" 
               style="border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">

            <?php else: ?>
                <p style="color:#dc3545;">No GCash QR uploaded by this organization.</p>
            <?php endif; ?>

            <form method="POST" action="process_donation.php" enctype="multipart/form-data">
                <input type="hidden" name="payment_method" value="gcash">
                <input type="hidden" name="organization_id" value="<?= $organization_id ?>">
                <label>Reference Number:</label>
                <input type="text" name="reference_number" required>
                <label>Amount (₱):</label>
                <input type="number" name="amount" step="0.01" required>
                <label>Upload Proof of Payment:</label>
                <input type="file" name="uploaded_proof" accept="image/*" required>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-success">Submit Donation</button>
                    <button type="button" class="btn-secondary" onclick="closeModal('gcashModal')">Cancel</button>
                </div>
        </form>

        </div>
    </div>

    <!-- 💛 Bank Transfer Modal -->
    <div id="bankModal" class="modal">
        <div class="modal-content">
            <h3>Donate via Bank Transfer</h3>
            <p><strong>Bank Name:</strong> <?= htmlspecialchars($organization['bank_name'] ?? 'N/A') ?></p>
            <p><strong>Account Name:</strong> <?= htmlspecialchars($organization['bank_account_name'] ?? 'N/A') ?></p>
            <p><strong>Account Number:</strong> <?= htmlspecialchars($organization['bank_account_number'] ?? 'N/A') ?></p>

            <form method="POST" action="process_donation.php">
                <input type="hidden" name="payment_method" value="item_donation">
                <input type="hidden" name="organization_id" value="<?= $organization_id ?>">

                <input type="hidden" name="payment_method" value="bank_transfer">
                <input type="hidden" name="organization_id" value="<?= $organization_id ?>">
                <label>Reference Number:</label>
                <input type="text" name="reference_number" required>
                <label>Amount (₱):</label>
                <input type="number" name="amount" step="0.01" required>
                <label>Upload Proof of Bank Transfer:</label>
                <input type="file" name="uploaded_proof" accept="image/*" required>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-success">Submit Donation</button>
                    <button type="button" class="btn-secondary" onclick="closeModal('bankModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <!-- ✅ Donation Buttons -->
<div class="donation-buttons">
    <button class="btn-primary" onclick="openModal('gcashModal')">Donate via GCash</button>
    <button class="btn-secondary" onclick="openModal('bankModal')">Donate via Bank Transfer</button>
    <button class="btn-warning" onclick="openModal('dropoffModal')">Donate In-Kind (Drop-off)</button>
</div>

<!-- 💙 GCash Modal -->
<div id="gcashModal" class="modal">
    <div class="modal-content">
        <h3>Donate via GCash</h3>
        <?php if (!empty($organization['gcash_qr'])): ?>
            <p>Scan this QR Code to donate directly:</p>
            <img src="/VolunteerHub/uploads/<?= htmlspecialchars($organization['gcash_qr']) ?>"
                 alt="GCash QR" width="200" height="200" style="border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
        <?php else: ?>
            <p style="color:#dc3545;">No GCash QR uploaded by this organization.</p>
        <?php endif; ?>

        <form method="POST" action="process_donation.php" enctype="multipart/form-data">
            <input type="hidden" name="payment_method" value="gcash">
            <input type="hidden" name="organization_id" value="<?= $organization_id ?>">
            <label>Reference Number:</label>
            <input type="text" name="reference_number" required>
            <label>Amount (₱):</label>
            <input type="number" name="amount" step="0.01" required>
            <label>Upload Proof of Payment:</label>
            <input type="file" name="uploaded_proof" accept="image/*" required>
            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn-success">Submit Donation</button>
                <button type="button" class="btn-secondary" onclick="closeModal('gcashModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- 💛 Bank Transfer Modal -->
<div id="bankModal" class="modal">
    <div class="modal-content">
        <h3>Donate via Bank Transfer</h3>
        <p><strong>Bank Name:</strong> <?= htmlspecialchars($organization['bank_name'] ?? 'N/A') ?></p>
        <p><strong>Account Name:</strong> <?= htmlspecialchars($organization['bank_account_name'] ?? 'N/A') ?></p>
        <p><strong>Account Number:</strong> <?= htmlspecialchars($organization['bank_account_number'] ?? 'N/A') ?></p>

        <form method="POST" action="process_donation.php" enctype="multipart/form-data">
            <input type="hidden" name="payment_method" value="bank_transfer">
            <input type="hidden" name="organization_id" value="<?= $organization_id ?>">
            <label>Reference Number:</label>
            <input type="text" name="reference_number" required>
            <label>Amount (₱):</label>
            <input type="number" name="amount" step="0.01" required>
            <label>Upload Proof of Bank Transfer:</label>
            <input type="file" name="uploaded_proof" accept="image/*" required>
            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn-success">Submit Donation</button>
                <button type="button" class="btn-secondary" onclick="closeModal('bankModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- 🧺 Drop-off Donation Modal -->
<div id="dropoffModal" class="modal">
    <div class="modal-content">
        <h3>Donate Items (Drop-off)</h3>
        <?php if (!empty($organization['dropoff_location'])): ?>
            <p><strong>Drop-off Location:</strong><br><?= htmlspecialchars($organization['dropoff_location']) ?></p>
            <?php if (!empty($organization['dropoff_instructions'])): ?>
                <p><em><?= htmlspecialchars($organization['dropoff_instructions']) ?></em></p>
            <?php endif; ?>
        <?php else: ?>
            <p style="color:#dc3545;">No drop-off location provided by this organization.</p>
        <?php endif; ?>

        <form method="POST" action="process_donation.php">
            <input type="hidden" name="payment_method" value="item_donation">
            <input type="hidden" name="organization_id" value="<?= $organization_id ?>">

            <label>Item Category:</label>
            <select name="item_category" required>
                <option value="">-- Select Category --</option>
                <option value="Clothes">Clothes</option>
                <option value="Food">Food</option>
                <option value="Books">Books</option>
                <option value="Hygiene Kits">Hygiene Kits</option>
                <option value="Others">Others</option>
            </select>

            <label>Item Description:</label>
            <textarea name="item_description" rows="3" required placeholder="Describe what you're donating..."></textarea>

            <label>Quantity:</label>
            <input type="number" name="quantity" min="1" required>

            <label>Preferred Drop-off Date:</label>
            <input type="date" name="dropoff_date" required>

            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn-success">Submit Drop-off</button>
                <button type="button" class="btn-secondary" onclick="closeModal('dropoffModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>


    <!-- 📅 Past Events -->
    <div class="section">
        <h2>Past Events</h2>
        <table>
            <tr><th>Title</th><th>Date</th><th>Description</th></tr>
            <?php if ($past_events->num_rows > 0): ?>
                <?php while ($row = $past_events->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="3" class="text-center">No past events found.</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- 📢 Current & Upcoming Events -->
    <div class="section">
        <h2>Current & Upcoming Events</h2>
        <table>
            <tr><th>Title</th><th>Date</th><th>Status</th><th>Description</th></tr>
            <?php if ($current_events->num_rows > 0): ?>
                <?php while ($row = $current_events->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-center">No current or upcoming events found.</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- 💰 Donation History -->
    <div class="section">
        <h2>Your Donations</h2>
        <table>
            <tr><th>Method</th><th>Amount</th><th>Reference No.</th><th>Status</th><th>Date</th></tr>
            <?php if ($donation_result->num_rows > 0): ?>
                <?php while ($don = $donation_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($don['method']) ?></td>
                        <td>₱<?= number_format($don['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($don['reference_number']) ?></td>
                        <td><?= htmlspecialchars($don['status']) ?></td>
                        <td><?= htmlspecialchars($don['created_at']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center">No donations yet.</td></tr>
            <?php endif; ?>
        </table>
    </div>
    <!-- 🎁 Item Donations -->
<div class="section">
    <h2>Your Item Donations</h2>
    <table>
        <tr>
            <th>Category</th>
            <th>Description</th>
            <th>Quantity</th>
            <th>Drop-off Date</th>
            <th>Status</th>
            <th>Date Submitted</th>
        </tr>
        <?php
        $item_stmt = $conn->prepare("
            SELECT item_category, item_description, quantity, dropoff_date, status, created_at
            FROM dropoff_donations
            WHERE user_id = ? AND organization_id = ?
            ORDER BY created_at DESC
        ");
        $item_stmt->bind_param('ii', $user_id, $organization_id);
        $item_stmt->execute();
        $item_result = $item_stmt->get_result();

        if ($item_result->num_rows > 0):
            while ($item = $item_result->fetch_assoc()):
        ?>
            <tr>
                <td><?= htmlspecialchars($item['item_category']) ?></td>
                <td><?= htmlspecialchars($item['item_description']) ?></td>
                <td><?= htmlspecialchars($item['quantity']) ?></td>
                <td><?= htmlspecialchars($item['dropoff_date']) ?></td>
                <td><?= htmlspecialchars($item['status']) ?></td>
                <td><?= htmlspecialchars($item['created_at']) ?></td>
            </tr>
        <?php
            endwhile;
        else:
        ?>
            <tr><td colspan="6" class="text-center">No item donations yet.</td></tr>
        <?php endif; ?>
    </table>
</div>

</div>

<script>
function openModal(id){ document.getElementById(id).style.display = 'flex'; }
function closeModal(id){ document.getElementById(id).style.display = 'none'; }
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
