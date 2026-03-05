<?php
include "config.php";

$username = $_POST['username'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
// default role to athlete if somehow missing
$role = isset($_POST['role']) ? $_POST['role'] : 'athlete';
// sanitize role to prevent invalid values
if (!in_array($role, ['athlete','organizer','admin'])) {
    $role = 'athlete';
}

// if registering as admin, verify code
if ($role === 'admin') {
    $provided = isset($_POST['admin_code']) ? $_POST['admin_code'] : '';
    include_once "config.php"; // to get $admin_secret
    if ($provided !== $admin_secret) {
        // invalid code, revert to athlete and ignore the attempt
        $role = 'athlete';
    }
}

$sql = "INSERT INTO users (username, email, password, role)
        VALUES ('$username', '$email', '$password', '$role')";

if ($conn->query($sql) === TRUE) {
    // successful registration, send user to login page
    header('Location: login.php');
    exit;
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
