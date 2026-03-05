<?php
require 'config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
http_response_code(405);
echo json_encode(array('error' => 'Method not allowed'));
exit;
}
$body = json_decode(file_get_contents('php://input'), true);
$tid = intval($body['tournament_id']);
$name = trim($body['name']);
$email = trim($body['email']);
if (!$tid || !$name || !$email) {
http_response_code(400);
echo json_encode(array('error' => 'Missing required fields'));
exit;
}
$stmt = $pdo->prepare("SELECT slots_total, slots_taken FROM tournaments WHERE id = :id");
$stmt->execute(array(':id' => $tid));
$t = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$t) {
http_response_code(404);
echo json_encode(array('error' => 'Tournament not found'));
exit;
}
if ($t['slots_taken'] >= $t['slots_total']) {
http_response_code(409);
echo json_encode(array('error' => 'This tournament is already full'));
exit;
}
$stmt = $pdo->prepare("SELECT id FROM registrations WHERE tournament_id = :tid AND email = :email");
$stmt->execute(array(':tid' => $tid, ':email' => $email));
if ($stmt->fetch()) {
http_response_code(409);
echo json_encode(array('error' => 'This email is already registered for this tournament'));
exit;
}
$stmt = $pdo->prepare("INSERT INTO registrations (tournament_id, name, email) VALUES (:tid, :name, :email)");
$stmt->execute(array(':tid' => $tid, ':name' => $name, ':email' => $email));
$pdo->prepare("UPDATE tournaments SET slots_taken = slots_taken + 1 WHERE id = :id")->execute(array(':id' => $tid));
echo json_encode(array('success' => true));