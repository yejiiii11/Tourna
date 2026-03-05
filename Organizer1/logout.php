<?php
require_once "session_bootstrap.php";
session_destroy();
header("Location: login.php");
?>
