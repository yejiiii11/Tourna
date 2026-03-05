<?php
require_once "session_bootstrap.php";
if (isset($_SESSION['username'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tournameet Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="form-body">

<div class="card" style="max-width:360px;">
    <h2>Welcome Back</h2>

    <form action="login_process.php" method="POST">

        <div class="input-group">
            <label>Username</label>
            <input type="text" name="username" required>
        </div>

        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit" class="form-btn">Login</button>

    </form>

    <div class="link">
        Don’t have an account? <a href="register.php">Register here</a>
    </div>
</div>

</body>
</html>
