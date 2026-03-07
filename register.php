<?php
include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $name, $email, $password, $role);

    if ($stmt->execute()) {
        echo "<script>alert('Registered Successfully'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Error: Registration Failed!'); window.location.href='register.php';</script>";
    }

    $stmt->close();
}
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Volunteer Registration</title>
    <link rel="stylesheet" href="styles/login_styles.css">
</head>
<body>

<div class="container">
    <h2>Registration</h2>
    <form method="POST" action="register.php">
    <label for="name">Full Name</label>
    <input type="text" name="name" id="name" required>

    <label for="email">Email</label>
    <input type="email" name="email" id="email" required>

    <label for="password">Password</label>
    <input type="password" name="password" id="password" required>

    <label for="confirm_password">Confirm Password</label>
    <input type="password" name="confirm_password" id="confirm_password" required>

    <label for="role">Register as:</label>
<select name="role" id="role" required>
    <option value="">Select Role</option>
    <option value="Volunteer">Volunteer</option>
    <option value="Admin">Admin</option>
</select>


    <button type="submit">Register</button>
</form>


    <div class="footer-link">
        <a href="index.php">← Back to Home</a>
    </div>
</div>

</body>
</html>
