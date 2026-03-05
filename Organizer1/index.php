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
    <title>Tournameet</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-p8Uhty22XkR4znkbN6w0+jZ8pO0ZL2wMUgGx+2qQPtjc6L3W7llhLj6Xb5zB4sga7zBxsAh3cY3bIvG/7oifCw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="landing-body">

    <div class="landing-container" style="max-width:400px; text-align:center;">
        <h1 style="font-size:36px; margin-bottom:0;">Tournameet</h1>
        <p style="margin:5px 0 30px; font-style:italic;font-size:14px;">Where athletes meet opportunities</p>

        <!-- email style input and continue button to mimic screenshot -->
        <div style="margin-bottom:20px;">
            <input type="email" placeholder="Enter your email" style="width:100%; padding:12px 15px; border-radius:8px; border:1px solid #ccc;">
        </div>
        <a href="register.php" class="btn" style="width:100%; background:#000; color:#fff;">Continue</a>

        <div style="margin-top:20px;">
            <button class="btn" style="width:100%; background:#fff; color:#000; margin-bottom:10px;">
                <i class="fab fa-google" style="margin-right:8px;"></i>Continue with Google
            </button>
            <button class="btn" style="width:100%; background:#fff; color:#000;">
                <i class="fab fa-apple" style="margin-right:8px;"></i>Continue with Apple
            </button>
        </div>
        <div style="margin-top:20px;">
            <a href="login.php" class="btn btn-outline" style="width:100%;">Login instead</a>
        </div>
    </div>

</body>
</html>
