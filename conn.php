<?php 
    $conn = mysqli_connect('localhost', 'root', '');
    $db = mysqli_select_db($conn,'volunteerhub');

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
?>