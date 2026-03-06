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
$activeCount = 0;

while ($result && ($row = $result->fetch_assoc())) {
    $row = autoCloseTournamentIfNeeded($conn, $row);
    $stats = getTournamentCapacityStats($conn, intval($row['id']));
    $slots = intval($row['slots'] ?? 0);
    $isFull = ($slots > 0 && $stats['active_count'] >= $slots);
    $isClosed = intval($row['is_closed'] ?? 0) === 1;
    $isActive = !$isClosed && !deadlinePassed($row) && !isTournamentPast($row);

    $row['_stats'] = $stats;
    $row['_is_full'] = $isFull;
    $row['_is_active'] = $isActive;
    $tournaments[] = $row;

    $totalApplicants += $stats['total_count'];
    if ($isActive) {
        $activeCount++;
    }
}

$totalTournaments = count($tournaments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Tournaments</title>
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
    position: sticky; top: 0; z-index: 200;
    background: var(--white); border-bottom: 2px solid var(--orange); box-shadow: var(--shadow);
    height: 64px; display: grid; grid-template-columns: 1fr 1fr 1fr; align-items: center; padding: 0 32px; gap: 12px;
  }
  .nav-left { display:flex; align-items:center; gap:10px; }
  .logo-icon { width:38px; height:38px; border-radius:50%; background:var(--orange-light); border:2px solid var(--orange); display:flex; align-items:center; justify-content:center; }
  .brand { font-family:'Bebas Neue',sans-serif; font-size:1.7rem; letter-spacing:2.5px; color:var(--orange); line-height:1; }
  .nav-center { display:flex; justify-content:center; }
  .search-wrap { position:relative; width:100%; max-width:340px; }
  .search-wrap input { width:100%; height:40px; border:2px solid var(--orange); border-radius:50px; padding:0 44px 0 18px; background:var(--orange-light); outline:none; }
  .search-wrap button { position:absolute; right:6px; top:50%; transform:translateY(-50%); background:var(--orange); border:none; border-radius:50%; width:30px; height:30px; display:flex; align-items:center; justify-content:center; }
  .nav-right { display:flex; justify-content:flex-end; align-items:center; gap:8px; }
  .nav-icon-btn { background:var(--orange-light); border:1.5px solid var(--orange); border-radius:50%; width:38px; height:38px; display:flex; align-items:center; justify-content:center; color:var(--orange); text-decoration:none; }
  .nav-icon-btn:hover { background:var(--orange); color:#fff; }
  .nav-icon-btn:hover svg { stroke:#fff; }
  main { max-width:1100px; margin:0 auto; padding:40px 32px 80px; }
  .page-header { margin-bottom:24px; display:flex; justify-content:space-between; align-items:flex-end; gap:12px; }
  .page-header h1 { font-family:'Bebas Neue',sans-serif; font-size:clamp(2rem,5vw,3.5rem); color:var(--orange); letter-spacing:3px; line-height:1; margin-bottom:4px; }
  .page-header p { font-size:0.92rem; color:#888; font-weight:500; }
  .summary { display:flex; gap:8px; flex-wrap:wrap; }
  .summary span { background:#fff3e6; color:#995300; border:1px solid #ffd5ad; border-radius:999px; padding:6px 10px; font-size:.78rem; font-weight:700; }
  .cards-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:22px; }
  .card {
    background:var(--white); border-radius:14px; border:1.5px solid #f0e0d0; box-shadow:0 2px 12px rgba(244,123,32,0.08);
    display:flex; flex-direction:column; padding:20px; position:relative; overflow:hidden;
  }
  .card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg,var(--orange),var(--orange-mid)); }
  .card-top { margin-bottom:8px; }
  .chip { display:inline-block; background:var(--orange-light); color:#a65300; border-radius:999px; padding:3px 10px; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; }
  .title { font-family:'Bebas Neue',sans-serif; font-size:1.4rem; letter-spacing:1.5px; color:#222; margin:8px 0; line-height:1.1; }
  .meta { font-size:.82rem; color:#666; margin-bottom:5px; }
  .status-row { margin:8px 0 12px; display:flex; gap:6px; flex-wrap:wrap; }
  .status { border-radius:999px; padding:4px 8px; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.8px; }
  .status.live { background:#e7f8ed; color:#176e3b; }
  .status.closed { background:#fdecec; color:#952e2e; }
  .status.full { background:#fff2de; color:#8a5a00; }
  .actions { margin-top:auto; display:grid; grid-template-columns:1fr 1fr; gap:8px; }
  .actions a, .actions button {
    border:none; border-radius:8px; padding:9px 10px; text-align:center; text-decoration:none; font-size:.78rem; font-weight:700; cursor:pointer;
  }
  .actions .primary { background:var(--orange); color:#fff; }
  .actions .outline { background:#fff; color:#a65300; border:1px solid #f0c9a3; }
  .empty { padding:40px; background:#fff; border:1.5px dashed #f0c9a3; border-radius:14px; color:#a46b3e; }
  @media (max-width:1099px) { .cards-grid { grid-template-columns:repeat(2,1fr); } nav { padding:0 20px; } }
  @media (max-width:700px) { .cards-grid { grid-template-columns:1fr; } nav { grid-template-columns:auto 1fr auto; padding:0 12px; } main { padding:24px 16px 60px; } }
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
      <input type="text" id="searchInput" placeholder="Search my tournaments...">
      <button aria-label="Search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button>
    </div>
  </div>
  <div class="nav-right">
    <a class="nav-icon-btn" href="dashboard.php" title="Dashboard"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><path d="M9 21V12h6v9"/></svg></a>
    <a class="nav-icon-btn" href="create_tournament.php" title="Create"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg></a>
    <a class="nav-icon-btn" href="logout.php" title="Logout"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
  </div>
</nav>

<main>
  <div class="page-header">
    <div>
      <h1>My Tournaments</h1>
      <p>Manage your created tournaments and joiners</p>
    </div>
    <div class="summary">
      <span>Total: <?php echo $totalTournaments; ?></span>
      <span>Active: <?php echo $activeCount; ?></span>
      <span>Applicants: <?php echo $totalApplicants; ?></span>
    </div>
  </div>

  <?php if ($totalTournaments > 0): ?>
    <div class="cards-grid" id="cardsGrid">
      <?php foreach ($tournaments as $row): ?>
        <?php $stats = $row['_stats']; ?>
        <article class="card" data-search="<?php echo htmlspecialchars(strtolower(($row['title'] ?? '') . ' ' . ($row['sport'] ?? '') . ' ' . ($row['location'] ?? ''))); ?>">
          <div class="card-top">
            <span class="chip"><?php echo htmlspecialchars($row['sport'] ?: 'General'); ?></span>
            <h3 class="title"><?php echo htmlspecialchars($row['title']); ?></h3>
            <p class="meta"><?php echo htmlspecialchars($row['event_date']); ?> | <?php echo htmlspecialchars($row['location'] ?: 'TBA Venue'); ?></p>
            <p class="meta"><?php echo intval($stats['total_count']); ?> joins | <?php echo intval($stats['approved_count']); ?> approved | <?php echo intval($row['views_count'] ?? 0); ?> views</p>
          </div>
          <div class="status-row">
            <?php if ($row['_is_active']): ?><span class="status live">Active</span><?php else: ?><span class="status closed">Closed</span><?php endif; ?>
            <?php if ($row['_is_full']): ?><span class="status full">Full</span><?php endif; ?>
          </div>
          <div class="actions">
            <a class="primary" href="edit_tournament.php?id=<?php echo intval($row['id']); ?>">Edit</a>
            <a class="outline" href="view_joiners.php?id=<?php echo intval($row['id']); ?>">Joiners</a>
            <a class="outline" href="view_joiners.php?id=<?php echo intval($row['id']); ?>&export=approved_csv">Export CSV</a>
            <a class="outline" href="payment.php?id=<?php echo intval($row['id']); ?>">View Page</a>

            <form method="POST" action="tournament_actions.php">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="id" value="<?php echo intval($row['id']); ?>">
              <input type="hidden" name="action" value="<?php echo intval($row['is_closed']) === 1 ? 'reopen' : 'close'; ?>">
              <button class="outline" type="submit"><?php echo intval($row['is_closed']) === 1 ? 'Reopen' : 'Close'; ?></button>
            </form>
            <form method="POST" action="tournament_actions.php" onsubmit="return confirm('Delete this tournament? This cannot be undone.');">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="id" value="<?php echo intval($row['id']); ?>">
              <input type="hidden" name="action" value="delete">
              <button class="outline" type="submit">Delete</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty">No tournaments created yet.</div>
  <?php endif; ?>
</main>

<script>
  const searchInput = document.getElementById('searchInput');
  const cards = Array.prototype.slice.call(document.querySelectorAll('#cardsGrid .card'));
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      const q = searchInput.value.trim().toLowerCase();
      cards.forEach(function (card) {
        const text = (card.getAttribute('data-search') || '').toLowerCase();
        card.style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
      });
    });
  }
</script>
</body>
</html>
