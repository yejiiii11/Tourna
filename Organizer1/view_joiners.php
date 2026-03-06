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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Joiners</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --orange: #F47B20;
            --orange-dark: #D96210;
            --orange-light: #FFF0E6;
            --orange-mid: #fdb97d;
            --white: #FFFFFF;
            --shadow: 0 2px 16px rgba(244,123,32,0.18);
        }
        body { background:#fafafa; font-family:'DM Sans',sans-serif; min-height:100vh; color:#1a1a1a; }
        nav {
            position: sticky; top: 0; z-index: 200; background: var(--white);
            border-bottom: 2px solid var(--orange); box-shadow: var(--shadow);
            height: 64px; display:grid; grid-template-columns:1fr 1fr 1fr; align-items:center; padding:0 32px; gap:12px;
        }
        .nav-left { display:flex; align-items:center; gap:10px; }
        .logo-icon { width:38px; height:38px; border-radius:50%; background:var(--orange-light); border:2px solid var(--orange); display:flex; align-items:center; justify-content:center; }
        .brand { font-family:'Bebas Neue',sans-serif; font-size:1.7rem; letter-spacing:2.5px; color:var(--orange); line-height:1; }
        .nav-center { display:flex; justify-content:center; }
        .search-wrap { position:relative; width:100%; max-width:340px; }
        .search-wrap input { width:100%; height:40px; border:2px solid var(--orange); border-radius:50px; padding:0 44px 0 18px; background:var(--orange-light); outline:none; }
        .search-wrap button { position:absolute; right:6px; top:50%; transform:translateY(-50%); width:30px; height:30px; border:none; border-radius:50%; background:var(--orange); display:flex; align-items:center; justify-content:center; }
        .nav-right { display:flex; justify-content:flex-end; align-items:center; gap:8px; }
        .nav-icon-btn { background:var(--orange-light); border:1.5px solid var(--orange); border-radius:50%; width:38px; height:38px; display:flex; align-items:center; justify-content:center; color:var(--orange); text-decoration:none; }
        .nav-icon-btn:hover { background:var(--orange); }
        .nav-icon-btn:hover svg { stroke:#fff; }

        .container { max-width:1120px; margin:0 auto; padding:28px 20px 34px; }
        .title { font-family:'Bebas Neue',sans-serif; letter-spacing:2px; font-size:3rem; color:var(--orange); line-height:.95; margin-bottom:8px; }
        .sub { margin-bottom:16px; color:#666; }
        .kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:12px; }
        .kpi { background:#fff; border:1.5px solid #f0e0d0; border-radius:10px; padding:12px; box-shadow:0 2px 12px rgba(244,123,32,0.08); }
        .kpi .k { font-size:11px; color:#7a5535; text-transform:uppercase; letter-spacing:.8px; }
        .kpi .v { font-size:26px; font-weight:800; }
        .warn { padding:10px 12px; border-radius:10px; margin-bottom:10px; font-size:13px; }
        .warn.full { background:#fff1df; color:#8a5700; border:1px solid #f1c78f; }
        .warn.closed { background:#fdecec; color:#932525; border:1px solid #efc1c1; }
        .actions { margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; }
        .btn { display:inline-block; text-decoration:none; background:#fff; border:1px solid #e2b68f; color:#9b4f0a; border-radius:8px; padding:8px 10px; font-weight:700; font-size:12px; cursor:pointer; }
        .btn.primary { background:linear-gradient(120deg,var(--orange),var(--orange-dark)); color:#fff; border:none; }
        .card { background:#fff; border:1.5px solid #f0e0d0; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(244,123,32,0.08); }
        .bulk { padding:10px 12px; border-bottom:1px solid #f2e6d9; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid #f3e6d8; font-size:13px; vertical-align:top; }
        th { background:#fff6ee; color:#8b4b16; font-size:11px; text-transform:uppercase; letter-spacing:.8px; }
        tr:last-child td { border-bottom:none; }
        .muted { color:#7a634f; }
        .empty { background:#fff; border:1.5px dashed #f0c9a3; border-radius:12px; padding:16px; color:#a46b3e; }
        .status { display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; }
        .status.ok { background:#e7f8ed; color:#176e3b; }
        .status.pending { background:#fff4de; color:#845700; }
        .status.bad { background:#fdecec; color:#962727; }
        .status.wait { background:#eef3ff; color:#2f52a1; }
        .conflict { color:#9b2e2e; font-size:11px; font-weight:700; }
        @media (max-width:900px) { .kpis { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:700px) { nav { grid-template-columns:auto 1fr auto; padding:0 12px; } .container { padding:24px 16px 60px; } }
    </style>
</head>
<body>
<nav>
    <div class="nav-left">
        <div class="logo-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h12v6a6 6 0 0 1-12 0V2Z"/><path d="M6 4H3a2 2 0 0 0-2 2v1a4 4 0 0 0 4 4h1"/><path d="M18 4h3a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4h-1"/><line x1="12" y1="14" x2="12" y2="18"/><path d="M8 22h8"/><line x1="8" y1="18" x2="16" y2="18"/></svg>
        </div>
        <span class="brand">TournaMeet</span>
    </div>
    <div class="nav-center">
        <div class="search-wrap">
            <input type="text" id="joinerSearch" placeholder="Search athletes, team, members...">
            <button aria-label="Search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button>
        </div>
    </div>
    <div class="nav-right">
        <a class="nav-icon-btn" href="dashboard.php" title="Dashboard"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><path d="M9 21V12h6v9"/></svg></a>
        <a class="nav-icon-btn" href="my_tournaments.php" title="My Tournaments"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></a>
        <a class="nav-icon-btn" href="logout.php" title="Logout"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    </div>
</nav>
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
        <a class="btn primary" href="payment.php?id=<?php echo intval($tournament['id']); ?>">View Tournament Page</a>
        <a class="btn" href="view_joiners.php?id=<?php echo intval($tournament['id']); ?>&export=approved_csv">Export Approved CSV</a>
    </div>

    <?php if (!$joiners || $joiners->num_rows === 0): ?>
        <div class="empty">No athletes have joined this tournament yet.</div>
    <?php else: ?>
        <form method="POST" class="card">
            <?php echo csrf_input(); ?>
            <div class="bulk">
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
                <tbody id="joinersBody">
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
                        <tr data-search="<?php echo htmlspecialchars(strtolower(($j['athlete_username'] ?? '') . ' ' . ($j['team_name'] ?? '') . ' ' . ($j['members'] ?? ''))); ?>">
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

const joinerSearch = document.getElementById('joinerSearch');
if (joinerSearch) {
    joinerSearch.addEventListener('input', function () {
        const q = joinerSearch.value.trim().toLowerCase();
        document.querySelectorAll('#joinersBody tr').forEach(function (tr) {
            const text = (tr.getAttribute('data-search') || '').toLowerCase();
            tr.style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
        });
    });
}
</script>
</body>
</html>
