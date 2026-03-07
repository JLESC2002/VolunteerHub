<?php
include '../conn.php';
include './check_session.php';

/* Only accept POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: volunteers_profile.php');
    exit;
}

$user_id     = $_SESSION['user_id'];
$current_pw  = $_POST['current_password']  ?? '';
$new_pw      = $_POST['new_password']       ?? '';
$confirm_pw  = $_POST['confirm_password']   ?? '';

/* ── Basic validation ──────────────────────────────────────── */
if (empty($current_pw) || empty($new_pw) || empty($confirm_pw)) {
    header('Location: volunteers_profile.php?pw_status=empty');
    exit;
}

if (strlen($new_pw) < 8) {
    header('Location: volunteers_profile.php?pw_status=short');
    exit;
}

if ($new_pw !== $confirm_pw) {
    header('Location: volunteers_profile.php?pw_status=mismatch');
    exit;
}

/* ── Fetch current hashed password ────────────────────────── */
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($current_pw, $user['password'])) {
    header('Location: volunteers_profile.php?pw_status=wrong_current');
    exit;
}

/* ── Update password ───────────────────────────────────────── */
$hashed = password_hash($new_pw, PASSWORD_BCRYPT);
$upd    = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$upd->bind_param("si", $hashed, $user_id);

if ($upd->execute()) {
    $upd->close();
    header('Location: volunteers_profile.php?pw_status=success');
} else {
    $upd->close();
    header('Location: volunteers_profile.php?pw_status=db_error');
}
exit;