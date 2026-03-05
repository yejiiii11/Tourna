<?php
require_once "session_bootstrap.php";
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['organizer', 'admin'])) {
    header('Location: login.php');
    exit;
}
include "config.php";
require_once "organizer_helpers.php";

ensureOrganizerSchema($conn);

$creator = $_SESSION['username'];
$isAdmin = $_SESSION['role'] === 'admin';

if ($isAdmin) {
    $result = $conn->query("SELECT * FROM tournaments ORDER BY event_date DESC, event_time DESC, id DESC");
} else {
    $stmt = $conn->prepare("SELECT * FROM tournaments WHERE created_by=? ORDER BY event_date DESC, event_time DESC, id DESC");
    $stmt->bind_param("s", $creator);
    $stmt->execute();
    $result = $stmt->get_result();
}

$tournaments = [];
$totalApplicants = 0;
$totalViews = 0;
$activeCount = 0;
$fullCount = 0;
$repeatAthletesGlobal = 0;

while ($result && ($row = $result->fetch_assoc())) {
    $row = autoCloseTournamentIfNeeded($conn, $row);
    $stats = getTournamentCapacityStats($conn, intval($row['id']));

    $slots = intval($row['slots'] ?? 0);
    $isFull = ($slots > 0 && $stats['active_count'] >= $slots);
    $isClosed = intval($row['is_closed'] ?? 0) === 1;
    $isActive = !$isClosed && !deadlinePassed($row) && !isTournamentPast($row);

    $joins = $stats['total_count'];
    $approved = $stats['approved_count'];
    $views = intval($row['views_count'] ?? 0);
    $conversion = $views > 0 ? round(($joins / $views) * 100, 1) : 0;

    $repeatAthletes = 0;
    $repeatSql = "SELECT COUNT(*) AS c FROM (
        SELECT athlete_username
        FROM tournament_registrations
        WHERE athlete_username IN (
            SELECT athlete_username FROM tournament_registrations WHERE tournament_id=" . intval($row['id']) . "
        )
        GROUP BY athlete_username
        HAVING COUNT(*) > 1
    ) q";
    $repeatRes = $conn->query($repeatSql);
    if ($repeatRes && $repeatRes->num_rows > 0) {
        $repeatAthletes = intval($repeatRes->fetch_assoc()['c']);
    }

    $noShowRate = 0;
    if ($approved > 0) {
        $noShowRes = $conn->query("SELECT SUM(CASE WHEN attendance_status='no_show' THEN 1 ELSE 0 END) AS n FROM tournament_registrations WHERE tournament_id=" . intval($row['id']) . " AND status='approved'");
        $noShowCount = ($noShowRes && $noShowRes->num_rows > 0) ? intval($noShowRes->fetch_assoc()['n']) : 0;
        $noShowRate = round(($noShowCount / $approved) * 100, 1);
    }

    $row['_stats'] = $stats;
    $row['_is_full'] = $isFull;
    $row['_is_active'] = $isActive;
    $row['_conversion'] = $conversion;
    $row['_no_show_rate'] = $noShowRate;
    $row['_repeat_athletes'] = $repeatAthletes;
    $tournaments[] = $row;

    $totalApplicants += $joins;
    $totalViews += $views;
    $repeatAthletesGlobal += $repeatAthletes;
    if ($isActive) {
        $activeCount++;
    }
    if ($isFull) {
        $fullCount++;
    }
}

$totalTournaments = count($tournaments);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Organizer Control Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin:0; font-family:"Manrope","Segoe UI",sans-serif; background:#fffaf4; color:#2b1c11; }
        .topbar { background:linear-gradient(180deg,#ec8a2d 0%,#de7316 100%); padding:10px 20px; color:#fff; display:flex; justify-content:space-between; align-items:center; }
        .brand { font-family:"Bebas Neue",sans-serif; font-size:30px; letter-spacing:1px; }
        .topbar a { color:#fff; text-decoration:none; margin-left:12px; font-weight:700; font-size:13px; }
        .container { max-width:1180px; margin:0 auto; padding:24px 20px 34px; }
        h1 { margin:0 0 14px; font-family:"Bebas Neue",sans-serif; font-size:52px; color:#be5b0a; letter-spacing:1px; }
        .cards { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:16px; }
        .kpi { background:#fff; border:1px solid #edd2b8; border-radius:12px; padding:12px; }
        .kpi .k { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#8b5f3e; }
        .kpi .v { font-size:26px; font-weight:800; }
        .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(290px,1fr)); gap:14px; }
        .card { background:#fff; border:1px solid #efd8c2; border-radius:12px; box-shadow:0 6px 14px rgba(156,83,17,0.09); }
        .card-top { height:5px; background:linear-gradient(90deg,#f19e47,#e97817); }
        .card-body { padding:12px; }
        .sport { font-size:10px; letter-spacing:1px; text-transform:uppercase; color:#bf6c24; font-weight:700; margin-bottom:4px; }
        .title { margin:0 0 8px; font-family:"Bebas Neue",sans-serif; font-size:30px; line-height:.95; }
        .meta { margin:4px 0; font-size:12px; color:#5d4a3a; }
        .badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; }
        .badge.live { background:#e7f8ed; color:#176e3b; }
        .badge.closed { background:#fdecec; color:#942d2d; }
        .badge.full { background:#fff0de; color:#8b5b00; }
        .quick { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:6px; margin-top:10px; }
        .quick a, .quick button { font:inherit; font-size:11px; font-weight:700; text-align:center; border-radius:8px; padding:8px 6px; border:1px solid #e2b68f; text-decoration:none; color:#9b4f0a; background:#fff; cursor:pointer; }
        .quick .primary { background:linear-gradient(120deg,#ee8f36,#d9690f); color:#fff; border:none; }
        .empty { background:#fff; border:1px dashed #deb995; border-radius:12px; padding:16px; }
        .analytics { margin-top:8px; font-size:12px; color:#594835; background:#fff8f1; border:1px solid #f0d7bf; border-radius:8px; padding:8px; }
        @media (max-width:980px) { .cards { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:620px) { .cards { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="topbar">
    <div class="brand">TOURNAMEET</div>
    <div>
        <a href="dashboard.php">Home</a>
        <a href="create_tournament.php">Create Tournament</a>
        <a href="logout.php">Logout</a>
    </div>
</div>
<div class="container">
    <h1>ORGANIZER CONTROL PANEL</h1>

    <section class="cards">
        <article class="kpi"><div class="k">Total tournaments</div><div class="v"><?php echo $totalTournaments; ?></div></article>
        <article class="kpi"><div class="k">Active</div><div class="v"><?php echo $activeCount; ?></div></article>
        <article class="kpi"><div class="k">Full</div><div class="v"><?php echo $fullCount; ?></div></article>
        <article class="kpi"><div class="k">Total applicants</div><div class="v"><?php echo $totalApplicants; ?></div></article>
    </section>

    <?php if ($totalTournaments > 0): ?>
        <div class="grid">
            <?php foreach($tournaments as $row): ?>
                <?php $stats = $row['_stats']; ?>
                <article class="card">
                    <div class="card-top"></div>
                    <div class="card-body">
                        <div class="sport"><?php echo htmlspecialchars($row['sport'] ?: 'General'); ?></div>
                        <h3 class="title"><?php echo htmlspecialchars($row['title']); ?></h3>
                        <p class="meta"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($row['event_date']); ?></p>
                        <p class="meta"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['location'] ?: 'TBA Venue'); ?></p>
                        <p class="meta"><i class="fas fa-users"></i> <?php echo $stats['total_count']; ?> joins | <?php echo $stats['approved_count']; ?> approved</p>
                        <p class="meta"><i class="fas fa-eye"></i> <?php echo intval($row['views_count'] ?? 0); ?> views</p>

                        <?php if ($row['_is_active']): ?><span class="badge live">Active</span><?php else: ?><span class="badge closed">Closed</span><?php endif; ?>
                        <?php if ($row['_is_full']): ?><span class="badge full">Full</span><?php endif; ?>

                        <div class="analytics">
                            Conversion: <?php echo number_format($row['_conversion'], 1); ?>% (views -> joins -> approved <?php echo $stats['approved_count']; ?>)<br>
                            No-show rate: <?php echo number_format($row['_no_show_rate'], 1); ?>% | Repeat athletes: <?php echo intval($row['_repeat_athletes']); ?>
                        </div>

                        <div class="quick">
                            <a class="primary" href="edit_tournament.php?id=<?php echo intval($row['id']); ?>">Edit</a>
                            <a href="view_joiners.php?id=<?php echo intval($row['id']); ?>">View Joiners</a>
                            <a href="view_joiners.php?id=<?php echo intval($row['id']); ?>&export=approved_csv">Export CSV</a>

                            <form method="POST" action="tournament_actions.php" style="margin:0;">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="id" value="<?php echo intval($row['id']); ?>">
                                <input type="hidden" name="action" value="<?php echo intval($row['is_closed']) === 1 ? 'reopen' : 'close'; ?>">
                                <button type="submit"><?php echo intval($row['is_closed']) === 1 ? 'Reopen' : 'Close Registration'; ?></button>
                            </form>
                            <a href="payment.php?id=<?php echo intval($row['id']); ?>">View Page</a>
                            <form method="POST" action="tournament_actions.php" style="margin:0;" onsubmit="return confirm('Delete this tournament? This cannot be undone.');">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="id" value="<?php echo intval($row['id']); ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">No tournaments created yet.</div>
    <?php endif; ?>
</div>
</body>
</html>
