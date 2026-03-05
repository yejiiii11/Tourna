<?php
require_once "session_bootstrap.php";
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['organizer','admin'])) {
    header('Location: login.php');
    exit;
}
verify_csrf_or_die();

include "config.php";
require_once "organizer_helpers.php";

ensureOrganizerSchema($conn);

$sportFromQuery = trim($_POST['sport'] ?? '');
if ($sportFromQuery === '' && isset($_POST['sport_manual'])) {
    $_POST['sport'] = trim($_POST['sport_manual']);
}

$payload = sanitizeTournamentPayload($_POST);
$data = $payload['data'];
$errors = $payload['errors'];

if (!empty($errors)) {
    echo "Validation failed:<br>" . htmlspecialchars(implode(' | ', $errors));
    exit;
}

$createdBy = $_SESSION['username'];
$stmt = $conn->prepare("INSERT INTO tournaments
(title, description, sport, event_date, event_time, registration_deadline, location, registration_fee, prize_pool, slots, requirements, organizer_note, is_closed, created_by)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
$stmt->bind_param(
    "sssssssddisss",
    $data['title'],
    $data['description'],
    $data['sport'],
    $data['date'],
    $data['time'],
    $data['registration_deadline'],
    $data['location'],
    $data['registration_fee'],
    $data['prize_pool'],
    $data['slots'],
    $data['requirements'],
    $data['organizer_note'],
    $createdBy
);

if ($stmt->execute()) {
    header("Location: my_tournaments.php?msg=created");
    exit;
}

echo "Error: " . htmlspecialchars($stmt->error ?: $conn->error);
$stmt->close();
?>
