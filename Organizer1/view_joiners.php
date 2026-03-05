<?php
require_once "session_bootstrap.php";
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['organizer', 'admin'])) {
    header('Location: login.php');
    exit;
}
include "config.php";
require_once "organizer_helpers.php";

ensureOrganizerSchema($conn);

if (!isset($_GET['id'])) {
    echo "Invalid request.";
    exit;
}

$id = intval($_GET['id']);
$username = $_SESSION['username'];
$isAdmin = $_SESSION['role'] === 'admin';
if (!tournamentIsOwnedBy($conn, $id, $username, $isAdmin)) {
    echo "Tournament not found or access denied.";
    exit;
}

$tournament = getTournamentById($conn, $id);
if (!$tournament) {
    echo "Tournament not found.";
    exit;
}
$tournament = autoCloseTournamentIfNeeded($conn, $tournament);

if (isset($_GET['export']) && $_GET['export'] === 'approved_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="approved_joiners_tournament_' . $id . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Athlete Username', 'Team Name', 'Members', 'Status', 'Attendance', 'Joined At']);

    $stmt = $conn->prepare("SELECT athlete_username, team_name, members, status, attendance_status, joined_at
        FROM tournament_registrations WHERE tournament_id=? AND status='approved' ORDER BY joined_at DESC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        fputcsv($out, [$row['athlete_username'], $row['team_name'], preg_replace('/\s+/', ' | ', $row['members'] ?? ''), $row['status'], $row['attendance_status'], $row['joined_at']]);
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $action = trim($_POST['bulk_action'] ?? '');
    $selected = $_POST['selected'] ?? [];

    if (is_array($selected) && !empty($selected) && in_array($action, ['approve', 'reject', 'waitlist', 'attended', 'no_show'], true)) {
        $stats = getTournamentCapacityStats($conn, $id);
        $slots = intval($tournament['slots'] ?? 0);
        $approvedCount = $stats['approved_count'];

        foreach ($selected as $sid) {
            $regId = intval($sid);
            if ($regId <= 0) {
                continue;
            }

            if ($action === 'approve') {
                if ($slots > 0 && $approvedCount >= $slots) {
                    // If full, move to waitlisted instead of approving.
                    $stmt = $conn->prepare("UPDATE tournament_registrations SET status='waitlisted', reviewed_at=NOW(), reviewed_by=? WHERE id=? AND tournament_id=?");
                    $stmt->bind_param("sii", $username, $regId, $id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare("UPDATE tournament_registrations SET status='approved', reviewed_at=NOW(), reviewed_by=? WHERE id=? AND tournament_id=?");
                    $stmt->bind_param("sii", $username, $regId, $id);
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) {
                        $approvedCount++;
                    }
                    $stmt->close();
                }
            } elseif ($action === 'reject') {
                $stmt = $conn->prepare("UPDATE tournament_registrations SET status='rejected', reviewed_at=NOW(), reviewed_by=? WHERE id=? AND tournament_id=?");
                $stmt->bind_param("sii", $username, $regId, $id);
                $stmt->execute();
                $stmt->close();
            } elseif ($action === 'waitlist') {
                $stmt = $conn->prepare("UPDATE tournament_registrations SET status='waitlisted', reviewed_at=NOW(), reviewed_by=? WHERE id=? AND tournament_id=?");
                $stmt->bind_param("sii", $username, $regId, $id);
                $stmt->execute();
                $stmt->close();
            } elseif ($action === 'attended' || $action === 'no_show') {
                $attendance = $action === 'attended' ? 'attended' : 'no_show';
                $stmt = $conn->prepare("UPDATE tournament_registrations SET attendance_status=? WHERE id=? AND tournament_id=? AND status='approved'");
                $stmt->bind_param("sii", $attendance, $regId, $id);
                $stmt->execute();
                $stmt->close();
            }
        }

        $tournament = getTournamentById($conn, $id);
        if ($tournament) {
            autoCloseTournamentIfNeeded($conn, $tournament);
        }
    }

    header('Location: view_joiners.php?id=' . $id);
    exit;
}

$stats = getTournamentCapacityStats($conn, $id);
$slots = intval($tournament['slots'] ?? 0);
$isFull = ($slots > 0 && $stats['active_count'] >= $slots);

$joinersSql = "SELECT id, athlete_username, team_name, members, status, attendance_status, reviewed_at, reviewed_by, joined_at
               FROM tournament_registrations
               WHERE tournament_id=$id
               ORDER BY FIELD(status,'pending','approved','waitlisted','rejected'), joined_at DESC";
$joiners = $conn->query($joinersSql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tournament Joiners</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { margin:0; font-family:"Manrope","Segoe UI",sans-serif; background:#fffaf4; color:#2b1c11; }
        .navbar { background:linear-gradient(180deg,#ec8a2d 0%,#de7316 100%); color:#fff; padding:12px 18px; display:flex; justify-content:space-between; align-items:center; }
        .navbar a { color:#fff; text-decoration:none; margin-left:12px; font-weight:700; font-size:13px; }
        .container { max-width:1120px; margin:0 auto; padding:22px 18px 30px; }
        .title { margin:0; font-family:"Bebas Neue",sans-serif; letter-spacing:1px; font-size:44px; color:#b9590e; line-height:.95; }
        .sub { margin:8px 0 16px; color:#654b37; }
        .kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:12px; }
        .kpi { background:#fff; border:1px solid #ecd5bf; border-radius:10px; padding:10px; }
        .kpi .k { font-size:11px; color:#7a5535; text-transform:uppercase; letter-spacing:.8px; }
        .kpi .v { font-size:22px; font-weight:800; }
        .warn { padding:8px 10px; border-radius:8px; margin-bottom:10px; font-size:13px; }
        .warn.full { background:#fff1df; color:#8a5700; border:1px solid #f1c78f; }
        .warn.closed { background:#fdecec; color:#932525; border:1px solid #efc1c1; }
        .card { background:#fff; border:1px solid #ecd5bf; border-radius:12px; overflow:hidden; box-shadow:0 8px 20px rgba(156,83,17,.08); }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid #f3e6d8; font-size:13px; vertical-align:top; }
        th { background:#fff1e3; color:#8b4b16; font-size:11px; text-transform:uppercase; letter-spacing:.8px; }
        tr:last-child td { border-bottom:none; }
        .muted { color:#7a634f; }
        .empty { background:#fff; border:1px dashed #deb995; border-radius:12px; padding:16px; }
        .actions { margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; }
        .btn { display:inline-block; text-decoration:none; background:#fff; border:1px solid #e2b68f; color:#9b4f0a; border-radius:8px; padding:8px 10px; font-weight:700; font-size:12px; }
        .status { display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; }
        .status.ok { background:#e7f8ed; color:#176e3b; }
        .status.pending { background:#fff4de; color:#845700; }
        .status.bad { background:#fdecec; color:#962727; }
        .status.wait { background:#eef3ff; color:#2f52a1; }
        .conflict { color:#9b2e2e; font-size:11px; font-weight:700; }
        @media (max-width:900px) { .kpis { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    </style>
</head>
<body>
<div class="navbar">
    <div>Tournament Joiners</div>
    <div>
        <a href="my_tournaments.php">My Tournaments</a>
        <a href="logout.php">Logout</a>
    </div>
</div>
<div class="container">
    <h1 class="title"><?php echo htmlspecialchars($tournament['title']); ?></h1>
    <p class="sub">
        <?php echo htmlspecialchars($tournament['sport'] ?: 'General'); ?> |
        <?php echo htmlspecialchars($tournament['event_date']); ?> |
        Organizer: <?php echo htmlspecialchars($tournament['created_by']); ?>
    </p>

    <div class="kpis">
        <div class="kpi"><div class="k">Total applicants</div><div class="v"><?php echo $stats['total_count']; ?></div></div>
        <div class="kpi"><div class="k">Approved</div><div class="v"><?php echo $stats['approved_count']; ?></div></div>
        <div class="kpi"><div class="k">Pending</div><div class="v"><?php echo $stats['pending_count']; ?></div></div>
        <div class="kpi"><div class="k">Waitlisted</div><div class="v"><?php echo $stats['waitlisted_count']; ?></div></div>
    </div>

    <?php if ($isFull): ?><div class="warn full">Conflict flag: Tournament capacity is full. Extra approvals will be moved to waitlisted.</div><?php endif; ?>
    <?php if (intval($tournament['is_closed'] ?? 0) === 1): ?><div class="warn closed">Registration is currently closed.</div><?php endif; ?>

    <div class="actions">
        <a class="btn" href="payment.php?id=<?php echo intval($tournament['id']); ?>">View Tournament Page</a>
        <a class="btn" href="view_joiners.php?id=<?php echo intval($tournament['id']); ?>&export=approved_csv">Export Approved CSV</a>
    </div>

    <?php if (!$joiners || $joiners->num_rows === 0): ?>
        <div class="empty">No athletes have joined this tournament yet.</div>
    <?php else: ?>
        <form method="POST" class="card">
            <?php echo csrf_input(); ?>
            <div style="padding:10px 12px; border-bottom:1px solid #f2e6d9; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <strong>Bulk actions:</strong>
                <button class="btn" type="submit" name="bulk_action" value="approve">Approve</button>
                <button class="btn" type="submit" name="bulk_action" value="reject">Reject</button>
                <button class="btn" type="submit" name="bulk_action" value="waitlist">Waitlist</button>
                <button class="btn" type="submit" name="bulk_action" value="attended">Mark Attended</button>
                <button class="btn" type="submit" name="bulk_action" value="no_show">Mark No-show</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="check_all"></th>
                        <th>Athlete</th>
                        <th>Team Name</th>
                        <th>Members</th>
                        <th>Status</th>
                        <th>Attendance</th>
                        <th>Joined At</th>
                        <th>Flags</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($j = $joiners->fetch_assoc()): ?>
                        <?php
                            $status = $j['status'] ?: 'pending';
                            $statusClass = statusLabelClass($status);
                            $flags = [];
                            if (hasDuplicateMemberNames($j['members'] ?? '')) {
                                $flags[] = 'Duplicate member names';
                            }
                            if ($isFull && $status === 'pending') {
                                $flags[] = 'Full capacity';
                            }
                        ?>
                        <tr>
                            <td><input type="checkbox" name="selected[]" value="<?php echo intval($j['id']); ?>"></td>
                            <td><?php echo htmlspecialchars($j['athlete_username']); ?></td>
                            <td><?php echo htmlspecialchars($j['team_name'] ?: 'N/A'); ?></td>
                            <td class="muted"><?php echo nl2br(htmlspecialchars($j['members'] ?: 'N/A')); ?></td>
                            <td><span class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                            <td><?php echo htmlspecialchars($j['attendance_status'] ?: 'unknown'); ?></td>
                            <td><?php echo htmlspecialchars($j['joined_at']); ?></td>
                            <td>
                                <?php if (empty($flags)): ?>
                                    <span class="muted">-</span>
                                <?php else: ?>
                                    <span class="conflict"><?php echo htmlspecialchars(implode(' | ', $flags)); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </form>
    <?php endif; ?>
</div>
<script>
const checkAll = document.getElementById('check_all');
if (checkAll) {
    checkAll.addEventListener('change', function () {
        document.querySelectorAll('input[name="selected[]"]').forEach(function (cb) { cb.checked = checkAll.checked; });
    });
}
</script>
</body>
</html>
