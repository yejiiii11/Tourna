<?php
require_once "session_bootstrap.php";
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['athlete','admin','organizer'])) {
    header('Location: login.php');
    exit;
}
include "config.php";
require_once "organizer_helpers.php";

ensureOrganizerSchema($conn);

if (!isset($_GET['id'])) {
    echo "Invalid tournament.";
    exit;
}

$id = intval($_GET['id']);
$tournament = getTournamentById($conn, $id);
if (!$tournament) {
    echo "Tournament not found.";
    exit;
}
$tournament = autoCloseTournamentIfNeeded($conn, $tournament);
incrementTournamentViewOnce($conn, $id);

$username = $_SESSION['username'];
$canJoin = in_array($_SESSION['role'], ['athlete', 'admin'], true);
$message = '';
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_tournament']) && $canJoin) {
    verify_csrf_or_die();

    $teamName = trim($_POST['team_name'] ?? '');
    $members = trim($_POST['members'] ?? '');

    if (strlen($teamName) > 120) {
        $message = 'Team name is too long (max 120 characters).';
        $messageType = 'error';
    } elseif (strlen($members) > 3000) {
        $message = 'Members field is too long.';
        $messageType = 'error';
    } else {
        $latest = getTournamentById($conn, $id);
        if (!$latest) {
            $message = 'Tournament no longer exists.';
            $messageType = 'error';
        } else {
            $latest = autoCloseTournamentIfNeeded($conn, $latest);
            $isClosed = intval($latest['is_closed'] ?? 0) === 1;
            $deadlineDone = deadlinePassed($latest);
            $slots = intval($latest['slots'] ?? 0);
            $stats = getTournamentCapacityStats($conn, $id);
            $isFull = ($slots > 0 && $stats['active_count'] >= $slots);

            if ($isClosed || $deadlineDone || $isFull) {
                $message = 'Registration is closed for this tournament.';
                $messageType = 'error';
            } else {
                $existsStmt = $conn->prepare("SELECT id, status FROM tournament_registrations WHERE tournament_id=? AND athlete_username=? LIMIT 1");
                $existsStmt->bind_param("is", $id, $username);
                $existsStmt->execute();
                $existsRes = $existsStmt->get_result();
                $existing = ($existsRes && $existsRes->num_rows > 0) ? $existsRes->fetch_assoc() : null;
                $existsStmt->close();

                if ($existing) {
                    $existingStatus = $existing['status'] ?? 'pending';
                    if (in_array($existingStatus, ['rejected', 'waitlisted'], true)) {
                        $updateStmt = $conn->prepare("UPDATE tournament_registrations
                            SET team_name=?, members=?, status='pending', attendance_status='unknown', reviewed_at=NULL, reviewed_by=NULL
                            WHERE id=?");
                        $updateStmt->bind_param("ssi", $teamName, $members, $existing['id']);
                        if ($updateStmt->execute()) {
                            $message = 'Registration resubmitted. Status: pending review.';
                            $messageType = 'ok';
                        } else {
                            $message = 'Could not resubmit registration: ' . $updateStmt->error;
                            $messageType = 'error';
                        }
                        $updateStmt->close();
                    } else {
                        $message = 'You already joined this tournament.';
                        $messageType = 'warn';
                    }
                } else {
                    $stmt = $conn->prepare("INSERT INTO tournament_registrations (tournament_id, athlete_username, team_name, members, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmt->bind_param("isss", $id, $username, $teamName, $members);
                    if ($stmt->execute()) {
                        $message = 'Registration submitted. Status: pending review.';
                        $messageType = 'ok';
                    } else {
                        $message = 'Could not submit registration: ' . $stmt->error;
                        $messageType = 'error';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

$stats = getTournamentCapacityStats($conn, $id);
$slots = intval($tournament['slots'] ?? 0);
$isFull = ($slots > 0 && $stats['active_count'] >= $slots);
$isClosed = intval($tournament['is_closed'] ?? 0) === 1 || deadlinePassed($tournament);

$alreadyJoined = false;
$teamNameSaved = '';
$membersSaved = '';
$myStatus = '';
$myAttendance = '';
$detailStmt = $conn->prepare("SELECT team_name, members, status, attendance_status FROM tournament_registrations WHERE tournament_id=? AND athlete_username=? LIMIT 1");
$detailStmt->bind_param("is", $id, $username);
$detailStmt->execute();
$detailRes = $detailStmt->get_result();
if ($detailRes && $detailRes->num_rows > 0) {
    $alreadyJoined = true;
    $saved = $detailRes->fetch_assoc();
    $teamNameSaved = $saved['team_name'] ?? '';
    $membersSaved = $saved['members'] ?? '';
    $myStatus = $saved['status'] ?? 'pending';
    $myAttendance = $saved['attendance_status'] ?? 'unknown';
}
$detailStmt->close();

$mapQuery = !empty($tournament['location']) ? urlencode($tournament['location']) : urlencode($tournament['title']);
$displayPrizePool = floatval($tournament['prize_pool'] ?? 0);
if ($displayPrizePool <= 0) {
    $displayPrizePool = floatval($tournament['registration_fee'] ?? 0) * max(1, $slots);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Join - <?php echo htmlspecialchars($tournament['title']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --ink:#23170f; --surface:#fff; --line:#e8d8c7; --accent:#e97817; --accent-deep:#c85c00; }
        * { box-sizing: border-box; }
        body { margin:0; color:var(--ink); font-family:"Manrope","Segoe UI",sans-serif; background:#fffaf4; }
        .topbar { background:linear-gradient(180deg,#ec8a2d 0%,#de7316 100%); padding:10px 20px; color:#fff; display:flex; justify-content:space-between; align-items:center; }
        .brand { font-family:"Bebas Neue",sans-serif; font-size:28px; letter-spacing:1px; }
        .top-links a { color:#fff; text-decoration:none; margin-left:14px; font-weight:700; font-size:13px; }
        .shell { max-width:1160px; margin:0 auto; padding:22px 20px 34px; }
        .hero { height:250px; border-radius:14px; overflow:hidden; margin-bottom:14px; border:1px solid #dbb594; }
        .hero iframe { width:100%; height:100%; border:0; }
        .grid { display:grid; grid-template-columns:minmax(0,1.12fr) minmax(300px,.88fr); gap:16px; }
        .panel { background:var(--surface); border:1px solid var(--line); border-radius:12px; padding:14px; }
        .section-title { margin:0 0 10px; font-family:"Bebas Neue",sans-serif; letter-spacing:1px; font-size:27px; color:#a95410; }
        .box { border:1px solid #e6d9ca; border-radius:9px; background:#fff; padding:10px; margin-bottom:8px; }
        .status { margin:0 0 12px; padding:10px 12px; border-radius:10px; font-weight:700; font-size:14px; }
        .status.ok { background:#e8f7ee; color:#16743f; }
        .status.warn { background:#fff4de; color:#875a00; }
        .status.error { background:#fde8e8; color:#972727; }
        .field { margin-bottom:10px; }
        .field label { display:block; margin-bottom:5px; font-size:12px; text-transform:uppercase; letter-spacing:.8px; font-weight:700; }
        .field input, .field textarea { width:100%; padding:10px 11px; border-radius:8px; border:1px solid #d7c8b8; font:inherit; }
        .confirm-btn { width:100%; border:none; border-radius:8px; padding:12px 14px; font-weight:700; color:#fff; background:linear-gradient(120deg,var(--accent),var(--accent-deep)); cursor:pointer; }
        .confirm-btn[disabled] { background:#b8bec8; cursor:not-allowed; }
        .joined-box { border:1px dashed #aac9b5; border-radius:12px; background:#f1fbf5; padding:12px; }
        .notice { border:1px dashed #d2b396; border-radius:10px; padding:10px; background:#fff8f0; margin-top:10px; white-space:pre-wrap; }
        @media (max-width:980px) { .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<header class="topbar">
    <div class="brand">TOURNAMEET</div>
    <div class="top-links">
        <a href="dashboard.php">Home</a>
        <a href="browse_tournaments.php">Tournaments</a>
        <a href="logout.php">Logout</a>
    </div>
</header>

<main class="shell">
    <section class="hero">
        <iframe src="https://www.google.com/maps?q=<?php echo $mapQuery; ?>&output=embed" allowfullscreen></iframe>
    </section>

    <div class="grid">
        <section class="panel">
            <h3 class="section-title"><?php echo htmlspecialchars($tournament['title']); ?></h3>
            <div class="box"><strong>Sport:</strong> <?php echo htmlspecialchars($tournament['sport'] ?: 'General'); ?></div>
            <div class="box"><strong>Date:</strong> <?php echo htmlspecialchars($tournament['event_date']); ?> <?php if (!empty($tournament['event_time'])): ?><?php echo htmlspecialchars(date('g:i A', strtotime($tournament['event_time']))); ?><?php endif; ?></div>
            <div class="box"><strong>Deadline:</strong> <?php echo !empty($tournament['registration_deadline']) ? htmlspecialchars($tournament['registration_deadline']) : 'Not set'; ?></div>
            <div class="box"><strong>Location:</strong> <?php echo htmlspecialchars($tournament['location'] ?: 'TBA Venue'); ?></div>
            <div class="box"><strong>Entry Fee:</strong> PHP <?php echo number_format(floatval($tournament['registration_fee'] ?? 0), 2); ?> | <strong>Prize Pool:</strong> PHP <?php echo number_format($displayPrizePool, 2); ?></div>
            <div class="box"><strong>Capacity:</strong> <?php echo $stats['active_count']; ?> / <?php echo $slots; ?> active applicants</div>
            <p><?php echo nl2br(htmlspecialchars($tournament['description'] ?? '')); ?></p>

            <?php if (!empty($tournament['requirements'])): ?>
                <h4>Requirements</h4>
                <p><?php echo nl2br(htmlspecialchars($tournament['requirements'])); ?></p>
            <?php endif; ?>

            <?php if (!empty($tournament['organizer_note'])): ?>
                <h4>Organizer Announcement</h4>
                <div class="notice"><?php echo htmlspecialchars($tournament['organizer_note']); ?></div>
            <?php endif; ?>
        </section>

        <aside class="panel">
            <?php if (!empty($message)): ?>
                <div class="status <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!$canJoin): ?>
                <div class="joined-box">Organizers can view details here. Only athletes can submit registrations.</div>
            <?php elseif ($alreadyJoined): ?>
                <div class="joined-box">
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($myStatus); ?></p>
                    <p><strong>Attendance:</strong> <?php echo htmlspecialchars($myAttendance); ?></p>
                    <p><strong>Team Name:</strong> <?php echo htmlspecialchars($teamNameSaved ?: 'N/A'); ?></p>
                    <p><strong>Members:</strong><br><?php echo nl2br(htmlspecialchars($membersSaved ?: 'N/A')); ?></p>
                </div>
            <?php else: ?>
                <form method="POST">
                    <?php echo csrf_input(); ?>
                    <div class="field">
                        <label>Team Name</label>
                        <input type="text" name="team_name" maxlength="120" placeholder="Enter team name">
                    </div>
                    <div class="field">
                        <label>Members</label>
                        <textarea name="members" rows="4" placeholder="One member per line"></textarea>
                    </div>
                    <button class="confirm-btn" type="submit" name="join_tournament" <?php echo ($isFull || $isClosed) ? 'disabled' : ''; ?>>
                        <?php echo ($isFull || $isClosed) ? 'Registration Closed' : 'Submit Registration'; ?>
                    </button>
                </form>
            <?php endif; ?>
        </aside>
    </div>
</main>
</body>
</html>
