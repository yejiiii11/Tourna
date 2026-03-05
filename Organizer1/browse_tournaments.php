<?php
require_once "session_bootstrap.php";
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
include "config.php";
require_once "organizer_helpers.php";

ensureOrganizerSchema($conn);

$currentUser = $conn->real_escape_string($_SESSION['username']);
$sportFilter = isset($_GET['sport']) ? trim($_GET['sport']) : '';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_asc';

$whereParts = [];
if ($sportFilter !== '') {
    $sportEscaped = $conn->real_escape_string($sportFilter);
    $whereParts[] = "t.sport = '$sportEscaped'";
}
if ($search !== '') {
    $searchEscaped = $conn->real_escape_string($search);
    $whereParts[] = "(t.title LIKE '%$searchEscaped%' OR t.description LIKE '%$searchEscaped%' OR t.location LIKE '%$searchEscaped%')";
}
$whereClause = empty($whereParts) ? '' : ' WHERE ' . implode(' AND ', $whereParts);

$orderBy = "t.event_date ASC, t.event_time ASC";
if ($sort === 'date_desc') { $orderBy = "t.event_date DESC, t.event_time DESC"; }
elseif ($sort === 'title_asc') { $orderBy = "t.title ASC"; }
elseif ($sort === 'title_desc') { $orderBy = "t.title DESC"; }

$result = $conn->query("SELECT t.*,
    (SELECT COUNT(*) FROM tournament_registrations tr WHERE tr.tournament_id=t.id) AS joined_count,
    (SELECT COUNT(*) FROM tournament_registrations tr WHERE tr.tournament_id=t.id AND tr.athlete_username='$currentUser') AS joined_by_me,
    (SELECT status FROM tournament_registrations tr WHERE tr.tournament_id=t.id AND tr.athlete_username='$currentUser' LIMIT 1) AS my_status
    FROM tournaments t
    $whereClause
    ORDER BY $orderBy");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Browse Tournaments</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin:0; font-family:"Manrope","Segoe UI",sans-serif; background:#fffaf4; color:#2b1c11; }
        .topbar { background:linear-gradient(180deg,#ec8a2d 0%,#de7316 100%); padding:10px 20px; color:#fff; display:flex; justify-content:space-between; align-items:center; gap:12px; }
        .brand { font-family:"Bebas Neue",sans-serif; font-size:28px; letter-spacing:1px; }
        .topbar a { color:#fff; text-decoration:none; font-weight:700; margin-left:12px; font-size:13px; }
        .container { max-width:1180px; margin:0 auto; padding:26px 20px 36px; }
        .controls { display:grid; grid-template-columns:auto 1fr auto auto; gap:10px; align-items:center; margin-bottom:18px; }
        .home-btn { border:1px solid #d86c11; background:#f3953b; color:#fff; text-decoration:none; border-radius:999px; padding:7px 11px; font-size:12px; font-weight:700; }
        .search-wrap { position:relative; }
        .search-wrap input { width:100%; border-radius:999px; border:1px solid #dbb594; background:#fff; padding:10px 40px 10px 14px; font:inherit; }
        .search-wrap i { position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#ca6712; font-size:13px; }
        .title-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; gap:12px; }
        .title-row h1 { margin:0; font-family:"Bebas Neue",sans-serif; letter-spacing:1px; font-size:54px; line-height:0.95; color:#be5b0a; }
        .cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:14px; }
        .card { background:#fff; border-radius:12px; border:1px solid #efd8c2; overflow:hidden; box-shadow:0 6px 14px rgba(156,83,17,0.09); display:flex; flex-direction:column; }
        .card-top { height:5px; background:linear-gradient(90deg,#f19e47,#e97817); }
        .card-body { padding:12px 12px 10px; flex:1; display:flex; flex-direction:column; }
        .sport { font-size:10px; letter-spacing:1px; text-transform:uppercase; color:#bf6c24; font-weight:700; margin-bottom:3px; }
        .title { margin:0 0 8px; font-family:"Bebas Neue",sans-serif; letter-spacing:.8px; font-size:29px; line-height:.95; }
        .meta { margin:2px 0; font-size:12px; color:#5d4a3a; }
        .bottom { margin-top:auto; padding-top:10px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .btn { border:none; border-radius:8px; padding:7px 10px; font-size:11px; font-weight:700; text-decoration:none; background:linear-gradient(120deg,#ee8f36,#d9690f); color:#fff; }
        .pill { border-radius:8px; padding:6px 9px; font-size:11px; font-weight:700; }
        .pill.joined { background:#e6f7ec; color:#1d7b46; }
        .pill.full { background:#fdeaea; color:#9a2a2a; }
        .pill.closed { background:#f1eef8; color:#50317f; }
        .empty { background:#fff; border:1px dashed #deb995; padding:18px; border-radius:12px; }
        @media (max-width:780px) { .controls { grid-template-columns:1fr; } .title-row h1 { font-size:44px; } }
    </style>
</head>
<body>
<div class="topbar">
    <div class="brand">TOURNAMEET</div>
    <div>
        <a href="dashboard.php">Home</a>
        <a href="joined_tournaments.php">My Joined</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <form method="GET" class="controls">
        <a href="dashboard.php" class="home-btn"><i class="fas fa-home"></i> HOME</a>
        <div class="search-wrap">
            <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search tournaments...">
            <i class="fas fa-search"></i>
        </div>
        <div>
            <?php if ($sportFilter !== ''): ?>Sport: <?php echo htmlspecialchars($sportFilter); ?><?php else: ?>All Sports<?php endif; ?>
        </div>
        <select name="sort" onchange="this.form.submit()">
            <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Date asc</option>
            <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Date desc</option>
            <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Title A-Z</option>
            <option value="title_desc" <?php echo $sort === 'title_desc' ? 'selected' : ''; ?>>Title Z-A</option>
        </select>
        <?php if ($sportFilter !== ''): ?>
            <input type="hidden" name="sport" value="<?php echo htmlspecialchars($sportFilter); ?>">
        <?php endif; ?>
    </form>

    <div class="title-row">
        <h1>TOURNAMENTS</h1>
        <div><?php echo $result ? $result->num_rows : 0; ?> Results</div>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
        <div class="cards">
        <?php while($row = $result->fetch_assoc()): ?>
            <?php
                $row = autoCloseTournamentIfNeeded($conn, $row);
                $slots = intval($row['slots'] ?? 0);
                $joinedCount = intval($row['joined_count'] ?? 0);
                $joinedByMe = intval($row['joined_by_me'] ?? 0) > 0;
                $isClosed = intval($row['is_closed'] ?? 0) === 1 || deadlinePassed($row);
                $isFull = ($slots > 0 && $joinedCount >= $slots);
            ?>
            <article class="card">
                <div class="card-top"></div>
                <div class="card-body">
                    <div class="sport"><?php echo htmlspecialchars($row['sport'] ?: 'General'); ?></div>
                    <h3 class="title"><?php echo htmlspecialchars($row['title']); ?></h3>
                    <p class="meta"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['location'] ?: 'TBA Venue'); ?></p>
                    <p class="meta"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($row['event_date']); ?></p>
                    <p class="meta"><i class="fas fa-users"></i> <?php echo $joinedCount; ?><?php if ($slots > 0): ?> / <?php echo $slots; ?><?php endif; ?></p>
                    <div class="bottom">
                        <?php if ($joinedByMe): ?>
                            <span class="pill joined"><?php echo htmlspecialchars($row['my_status'] ?: 'joined'); ?></span>
                        <?php elseif ($isClosed): ?>
                            <span class="pill closed">Closed</span>
                        <?php elseif ($isFull): ?>
                            <span class="pill full">Full</span>
                        <?php else: ?>
                            <a href="payment.php?id=<?php echo intval($row['id']); ?>" class="btn">View &amp; Join</a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty">No tournaments found for this filter.</div>
    <?php endif; ?>
</div>
</body>
</html>
