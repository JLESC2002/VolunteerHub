<?php
session_start();
include '../conn.php';

if (isset($_COOKIE['session_token'])) {
    $session_token = $_COOKIE['session_token'];

    $query = $conn->prepare("SELECT id, name, role FROM users WHERE session_token = ?");
    $query->bind_param("s", $session_token);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
    } else {
        setcookie("session_token", "", time() - 3600, "/"); // Delete invalid session token
        session_destroy();
        header("Location: ../index.php");
        exit();
    }
} else {
    header("Location: ../index.php");
    exit();
}
?>
