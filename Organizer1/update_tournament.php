<?php
require_once "session_bootstrap.php";
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['organizer', 'admin'])) {
    header('Location: login.php');
    exit;
}
verify_csrf_or_die();

include "config.php";
require_once "organizer_helpers.php";

ensureOrganizerSchema($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    echo "Invalid request.";
    exit;
}

$id = intval($_POST['id']);
$username = $_SESSION['username'];
$isAdmin = $_SESSION['role'] === 'admin';
if (!tournamentIsOwnedBy($conn, $id, $username, $isAdmin)) {
    echo "Tournament not found or access denied.";
    exit;
}

$current = getTournamentById($conn, $id);
if (!$current) {
    echo "Tournament not found.";
    exit;
}

$payload = sanitizeTournamentPayload($_POST);
$data = $payload['data'];
$errors = $payload['errors'];

if (isTournamentPast($current) && !isset($_POST['confirm_reopen'])) {
    $errors[] = 'This tournament is in the past. Tick reopen confirmation to continue editing.';
}

if (!empty($errors)) {
    echo "Validation failed:<br>" . htmlspecialchars(implode(' | ', $errors));
    exit;
}

$isClosed = isset($_POST['is_closed']) ? 1 : 0;

$stmt = $conn->prepare("UPDATE tournaments SET
    title=?, description=?, sport=?, event_date=?, event_time=?, registration_deadline=?,
    location=?, registration_fee=?, prize_pool=?, slots=?, requirements=?, organizer_note=?, is_closed=?
    WHERE id=?");
$stmt->bind_param(
    "sssssssddissii",
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
    $isClosed,
    $id
);

if ($stmt->execute()) {
    $updated = getTournamentById($conn, $id);
    if ($updated) {
        autoCloseTournamentIfNeeded($conn, $updated);
    }
    header("Location: my_tournaments.php?msg=updated");
    exit;
}

echo "Error updating tournament: " . htmlspecialchars($stmt->error ?: $conn->error);
$stmt->close();
?>
