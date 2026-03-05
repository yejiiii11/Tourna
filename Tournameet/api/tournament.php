<?php
require 'config.php';
$id = 0;
if (isset($_GET['id'])) {
$id = intval($_GET['id']);
}
if (!$id) {
http_response_code(400);
echo json_encode(array('error' => 'Missing id'));
exit;
}
$stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = :id");
$stmt->execute(array(':id' => $id));
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
http_response_code(404);
echo json_encode(array('error' => 'Tournament not found'));
exit;
}
echo json_encode(array('data' => $row));