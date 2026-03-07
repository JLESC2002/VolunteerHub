<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Login</title>
    <link rel="stylesheet" href="../styles/login_styles.css">
</head>
<body>
<div class="container">
    <h2>Volunteer Login</h2>
    <form method="POST">
        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <input type="hidden" name="role" value="Volunteer">
        <button type="submit">Login</button>
    </form>
    </form>
 <div class="footer-link">
 <a href="../index.php">Back to Home</a>
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
    setcookie("session_token", $response_data['session_token'], time() + 86400, "/", "", false, true);

    // Show popup first, then redirect
    echo "<script>
        alert('Welcome, Volunteer! You have successfully logged in.');
        window.location.href = '" . $response_data["redirect"] . "';
    </script>";
    exit();
}

        } else {
            echo "<script>alert('" . $response_data["message"] . "');</script>";
        }
    }
?>