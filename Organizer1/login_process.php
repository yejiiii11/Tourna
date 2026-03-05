<?php
require_once "session_bootstrap.php";
include "config.php";

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT username, password, role FROM users WHERE username=? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();

    if (password_verify($password, $row['password'])) {
        session_regenerate_id(true);
        $_SESSION['username'] = $row['username'];
        $_SESSION['role']     = $row['role'];
        header("Location: dashboard.php");
        exit;
    } else {
        echo "Invalid password!";
    }
} else {
    echo "No user found!";
}

$stmt->close();
$conn->close();
?>
