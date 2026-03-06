<?php
$host = 'localhost';
$dbname = 'user_system';
$user = 'root';
$pass = '';
try {
$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
http_response_code(500);
echo json_encode(array('error' => 'DB connection failed: ' . $e->getMessage()));
exit;
}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
