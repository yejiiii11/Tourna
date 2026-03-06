<?php
require 'config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
http_response_code(405);
echo json_encode(array('error' => 'Method not allowed'));
exit;
}
$body = json_decode(file_get_contents('php://input'), true);
$tid = intval($body['tournament_id'] ?? 0);
$name = trim($body['name'] ?? '');
$email = trim($body['email'] ?? '');
$team = trim($body['team'] ?? '');
$age = trim($body['age'] ?? '');
$gender = trim($body['gender'] ?? '');
$phone = trim($body['phone'] ?? '');
$notes = trim($body['notes'] ?? '');
if (!$tid || !$name || !$email) {
http_response_code(400);
echo json_encode(array('error' => 'Missing required fields'));
exit;
}
$pdo->exec("CREATE TABLE IF NOT EXISTS tournament_registrations (
id INT AUTO_INCREMENT PRIMARY KEY,
tournament_id INT NOT NULL,
athlete_username VARCHAR(50) NOT NULL,
team_name VARCHAR(120) DEFAULT NULL,
members TEXT,
status ENUM('pending','approved','rejected','waitlisted') NOT NULL DEFAULT 'pending',
attendance_status ENUM('unknown','attended','no_show') NOT NULL DEFAULT 'unknown',
reviewed_at DATETIME NULL,
reviewed_by VARCHAR(50) NULL,
joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
UNIQUE KEY uniq_tournament_athlete (tournament_id, athlete_username),
KEY idx_tournament_status (tournament_id, status)
)");

$stmt = $pdo->prepare("SELECT id, slots, is_closed, registration_deadline FROM tournaments WHERE id = :id");
$stmt->execute(array(':id' => $tid));
$t = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$t) {
    http_response_code(404);
    echo json_encode(array('error' => 'Tournament not found'));
    exit;
}

$deadlinePassed = false;
if (!empty($t['registration_deadline'])) {
    $deadlinePassed = strtotime($t['registration_deadline']) < time();
}
if (intval($t['is_closed']) === 1 || $deadlinePassed) {
    http_response_code(409);
    echo json_encode(array('error' => 'Registration is closed for this tournament'));
    exit;
}

$countStmt = $pdo->prepare("SELECT
SUM(CASE WHEN status IN ('pending','approved') THEN 1 ELSE 0 END) AS active_count
FROM tournament_registrations WHERE tournament_id = :tid");
$countStmt->execute(array(':tid' => $tid));
$activeCount = intval($countStmt->fetchColumn() ?: 0);
$totalSlots = intval($t['slots'] ?? 0);
if ($totalSlots > 0 && $activeCount >= $totalSlots) {
    http_response_code(409);
    echo json_encode(array('error' => 'This tournament is already full'));
    exit;
}

$athleteKey = mb_substr($email, 0, 50);
$lines = array('Name: ' . $name);
if ($age !== '') { $lines[] = 'Age: ' . $age; }
if ($gender !== '') { $lines[] = 'Gender: ' . $gender; }
if ($phone !== '') { $lines[] = 'Phone: ' . $phone; }
if ($notes !== '') { $lines[] = 'Notes: ' . $notes; }
$members = implode("\n", $lines);

$existingStmt = $pdo->prepare("SELECT id, status FROM tournament_registrations WHERE tournament_id = :tid AND athlete_username = :athlete LIMIT 1");
$existingStmt->execute(array(':tid' => $tid, ':athlete' => $athleteKey));
$existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $existingStatus = $existing['status'] ?? 'pending';
    if (in_array($existingStatus, array('rejected', 'waitlisted'), true)) {
        $updateStmt = $pdo->prepare("UPDATE tournament_registrations
        SET team_name = :team_name, members = :members, status = 'pending', attendance_status = 'unknown', reviewed_at = NULL, reviewed_by = NULL
        WHERE id = :id");
        $updateStmt->execute(array(
            ':team_name' => $team,
            ':members' => $members,
            ':id' => intval($existing['id'])
        ));
        echo json_encode(array('success' => true));
        exit;
    }
    http_response_code(409);
    echo json_encode(array('error' => 'This email is already registered for this tournament'));
    exit;
}

$insertStmt = $pdo->prepare("INSERT INTO tournament_registrations (tournament_id, athlete_username, team_name, members, status)
VALUES (:tid, :athlete, :team_name, :members, 'pending')");
$insertStmt->execute(array(
    ':tid' => $tid,
    ':athlete' => $athleteKey,
    ':team_name' => $team,
    ':members' => $members
));

echo json_encode(array('success' => true));
