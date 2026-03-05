<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "user_system";

// secret string to allow admin creation during registration
$admin_secret = 'letmein_admin';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
