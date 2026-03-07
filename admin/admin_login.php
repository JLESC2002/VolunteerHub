<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../styles/login_styles.css">
</head>
<body>
<div class="container">
    <h2>Admin Login</h2>
    <form method="POST">
        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <input type="hidden" name="role" value="Admin">
        <button type="submit">Login</button>

    </form>
 <div class="footer-link">
 <a href="../index.php">Back to Home</a>
    </div>
</div>
</body>
</html>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];
    $role = $_POST["role"];

    $flask_url = "http://localhost:5000/login";
    $data = json_encode(array("email" => $email, "password" => $password, "role" => $role));

    $ch = curl_init($flask_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_status == 200) {
        $response_data = json_decode($result, true);
        if ($response_data["status"] === "success") {
            $session_token = $response_data['session_token'];
            setcookie("session_token", $session_token, time() + 86400, "/", "", false, true);

            include '../conn.php';
            $mysql = $conn->prepare("SELECT id FROM users WHERE session_token = ?");
            $mysql->bind_param("s", $session_token);
            $mysql->execute();
            $result = $mysql->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $admin_id = $user['id'];

                // Check if organization exists
                $org_mysql = $conn->prepare("SELECT id FROM organizations WHERE admin_id = ?");
                $org_mysql->bind_param("i", $admin_id);
                $org_mysql->execute();
                $org_result = $org_mysql->get_result();

                if ($org_result->num_rows > 0) {
                    echo "<script>alert('Login Successful! Welcome Admin.'); window.location.href='admin_dashboard.php';</script>";
                    exit();
                } else {
                    echo "<script>alert('Login Successful! But you need to register your organization first.'); window.location.href='register_organization.php';</script>";
                    exit();
                }
            } else {
                echo "<script>alert('Invalid session. Please login again.'); window.location.href='../index.php';</script>";
            }
        } else {
            echo "<script>alert('" . $response_data["message"] . "');</script>";
        }
    } else {
        echo "<script>alert('Error: Unable to reach the server. Please try again later.');</script>";
    }
}
?>
