<?php
include '../conn.php';
include './check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $organization_id = intval($_POST['organization_id']);
    $payment_method = $_POST['payment_method'] ?? '';
    $upload_dir = "../uploads/";

    // ✅ Handle Monetary Donations (GCash / Bank)
    if (in_array($payment_method, ['gcash', 'bank_transfer'])) {

        $reference_number = trim($_POST['reference_number']);
        $amount = floatval($_POST['amount']);

        if (empty($organization_id) || empty($reference_number) || $amount <= 0) {
            header("Location: organization_profile.php?id=$organization_id&error=missing_fields");
            exit;
        }

        // ✅ Handle file upload (Proof of Payment)
        $uploaded_proof = null;
        if (!empty($_FILES['uploaded_proof']['name'])) {
            $file_name = basename($_FILES['uploaded_proof']['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($file_ext, $allowed_ext)) {
                header("Location: organization_profile.php?id=$organization_id&error=invalid_file");
                exit;
            }

            $new_name = $payment_method . "_" . time() . "_" . uniqid() . "." . $file_ext;
            $target_path = $upload_dir . $new_name;

            if (!move_uploaded_file($_FILES['uploaded_proof']['tmp_name'], $target_path)) {
                header("Location: organization_profile.php?id=$organization_id&error=upload_failed");
                exit;
            }

            $uploaded_proof = $new_name;
        }

        // ✅ Insert donation record depending on method
        if ($payment_method === 'gcash') {
            $stmt = $conn->prepare("
                INSERT INTO gcash_donations 
                (user_id, organization_id, reference_number, amount, proof_image, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'Pending', NOW())
            ");
            $stmt->bind_param("iisss", $user_id, $organization_id, $reference_number, $amount, $uploaded_proof);

        } elseif ($payment_method === 'bank_transfer') {
            $stmt = $conn->prepare("
                INSERT INTO bank_payments 
                (user_id, organization_id, reference_number, amount, uploaded_proof, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'Pending', NOW())
            ");
            $stmt->bind_param("iisss", $user_id, $organization_id, $reference_number, $amount, $uploaded_proof);
        }

        if ($stmt->execute()) {
            header("Location: organization_profile.php?id=$organization_id&success=donation_sent");
        } else {
            header("Location: organization_profile.php?id=$organization_id&error=db_insert_failed");
        }
        exit;
    }

    // ✅ Handle Item Donations
    elseif ($payment_method === 'item_donation') {
        $item_category = trim($_POST['item_category']);
        $item_description = trim($_POST['item_description']);
        $quantity = intval($_POST['quantity']);
        $dropoff_date = $_POST['dropoff_date'];

        if (empty($organization_id) || empty($item_category) || empty($item_description) || $quantity <= 0 || empty($dropoff_date)) {
            header("Location: organization_profile.php?id=$organization_id&error=missing_fields");
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO dropoff_donations 
            (user_id, organization_id, item_category, item_description, quantity, dropoff_date, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())
        ");
        // ✅ Fixed bind_param formatting (should match 6 placeholders)
        $stmt->bind_param("iissis", $user_id, $organization_id, $item_category, $item_description, $quantity, $dropoff_date);

        if ($stmt->execute()) {
            header("Location: organization_profile.php?id=$organization_id&success=item_donation_sent");
        } else {
            header("Location: organization_profile.php?id=$organization_id&error=db_insert_failed");
        }
        exit;
    }

    // ❌ Invalid Method
    else {
        header("Location: organization_profile.php?id=$organization_id&error=invalid_method");
        exit;
    }
}
?>
