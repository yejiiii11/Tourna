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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_tournaments.php');
    exit;
}

$id = intval($_POST['id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$username = $_SESSION['username'];
$isAdmin = $_SESSION['role'] === 'admin';

if ($id <= 0 || !tournamentIsOwnedBy($conn, $id, $username, $isAdmin)) {
    die('Access denied.');
}

if ($action === 'close') {
    $stmt = $conn->prepare("UPDATE tournaments SET is_closed=1 WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
} elseif ($action === 'reopen') {
    $t = getTournamentById($conn, $id);
    if ($t && !isTournamentPast($t)) {
        $stmt = $conn->prepare("UPDATE tournaments SET is_closed=0 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
} elseif ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM tournaments WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: my_tournaments.php');
exit;
?>
