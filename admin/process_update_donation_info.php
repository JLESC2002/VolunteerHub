<?php
// admin/process_update_donation_info.php
include '../conn.php';
include './check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $org_id = intval($_POST['organization_id'] ?? 0);

    // Validate org_id
    if ($org_id <= 0) {
        header("Location: admin_profile.php?error=invalid_org");
        exit;
    }

    // Basic Info fields
    $org_name = trim($_POST['org_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Existing fields
    $gcash_name = trim($_POST['gcash_name'] ?? '');
    $gcash_number = trim($_POST['gcash_number'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_account_name = trim($_POST['bank_account_name'] ?? '');
    $bank_account_number = trim($_POST['bank_account_number'] ?? '');
    $dropoff_location = trim($_POST['dropoff_location'] ?? '');
    $dropoff_instructions = trim($_POST['dropoff_instructions'] ?? '');

    // NEW: facebook link
    $facebook_link = trim($_POST['facebook_link'] ?? '');
    if ($facebook_link === '') $facebook_link = null; // allow NULL in DB

    $upload_dir = "../uploads/";

    // Handle GCash QR (existing code)...
    $gcash_qr = null;
    if (!empty($_FILES['gcash_qr']['name'])) {
        $file_name = basename($_FILES['gcash_qr']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg','jpeg','png'];
        if (in_array($file_ext, $allowed_ext)) {
            $new_name = "gcash_qr_" . time() . "_" . uniqid() . "." . $file_ext;
            $target_path = $upload_dir . $new_name;
            if (move_uploaded_file($_FILES['gcash_qr']['tmp_name'], $target_path)) {
                $gcash_qr = $new_name;
            }
        }
    }

    // NEW: handle organization logo upload
    $logo_name = null;
    if (!empty($_FILES['logo']['name'])) {
        $file_name = basename($_FILES['logo']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg','jpeg','png','gif'];
        if (in_array($file_ext, $allowed_ext)) {
            $new_logo = "org_logo_" . $org_id . "_" . time() . "_" . uniqid() . "." . $file_ext;
            $target_logo = $upload_dir . $new_logo;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_logo)) {
                $logo_name = $new_logo;

                // Optionally delete existing logo file to save space
                $oldQ = $conn->prepare("SELECT logo FROM organizations WHERE id = ?");
                $oldQ->bind_param("i", $org_id);
                $oldQ->execute();
                $oldRes = $oldQ->get_result();
                if ($oldRes && $row = $oldRes->fetch_assoc()) {
                    if (!empty($row['logo']) && file_exists($upload_dir . $row['logo'])) {
                        @unlink($upload_dir . $row['logo']);
                    }
                }
            }
        }
    }

    // Check organization exists
    $check = $conn->prepare("SELECT id FROM organizations WHERE id = ?");
    $check->bind_param("i", $org_id);
    $check->execute();
    $checkResult = $check->get_result();

    if ($checkResult->num_rows === 0) {
        header("Location: admin_profile.php?error=org_not_found");
        exit;
    }

    // Build UPDATE statement dynamically to include optional fields
    $fields = [];
    $types  = "";
    $values = [];

    // Basic info fields (always update if provided)
    if ($org_name) {
        $fields[] = "name = ?";
        $types .= "s";
        $values[] = $org_name;
    }

    if ($location !== '') {
        $fields[] = "location = ?";
        $types .= "s";
        $values[] = $location;
    }

    if ($contact_email !== '') {
        $fields[] = "contact_email = ?";
        $types .= "s";
        $values[] = $contact_email;
    }

    if ($contact_phone !== '') {
        $fields[] = "contact_phone = ?";
        $types .= "s";
        $values[] = $contact_phone;
    }

    if ($description !== '') {
        $fields[] = "description = ?";
        $types .= "s";
        $values[] = $description;
    }

    // always update donation fields (existing)
    $fields[] = "gcash_name = ?";
    $types .= "s";
    $values[] = $gcash_name;

    $fields[] = "gcash_number = ?";
    $types .= "s";
    $values[] = $gcash_number;

    if ($gcash_qr) {
        $fields[] = "gcash_qr = ?";
        $types .= "s";
        $values[] = $gcash_qr;
    }

    $fields[] = "bank_name = ?";
    $types .= "s";
    $values[] = $bank_name;

    $fields[] = "bank_account_name = ?";
    $types .= "s";
    $values[] = $bank_account_name;

    $fields[] = "bank_account_number = ?";
    $types .= "s";
    $values[] = $bank_account_number;

    $fields[] = "dropoff_location = ?";
    $types .= "s";
    $values[] = $dropoff_location;

    $fields[] = "dropoff_instructions = ?";
    $types .= "s";
    $values[] = $dropoff_instructions;

    // NEW: facebook_link
    $fields[] = "facebook_link = ?";
    $types .= "s";
    $values[] = $facebook_link;

    // NEW: logo
    if ($logo_name) {
        $fields[] = "logo = ?";
        $types .= "s";
        $values[] = $logo_name;
    }

    // finalize
    $sql = "UPDATE organizations SET " . implode(", ", $fields) . " WHERE id = ?";
    $types .= "i";
    $values[] = $org_id;

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        header("Location: admin_profile.php?error=db_prepare");
        exit;
    }

    // bind params dynamically
    $stmt->bind_param($types, ...$values);
    if ($stmt->execute()) {
        header("Location: admin_profile.php?success=donation_updated");
        exit;
    } else {
        header("Location: admin_profile.php?error=update_failed");
        exit;
    }

} else {
    header("Location: admin_profile.php");
    exit;
}
