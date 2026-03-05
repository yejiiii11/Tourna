<?php
require_once "session_bootstrap.php";
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'athlete';
$sports = [
    ['name' => 'Volleyball', 'icon' => 'fa-volleyball-ball'],
    ['name' => 'Basketball', 'icon' => 'fa-basketball-ball'],
    ['name' => 'Badminton', 'icon' => 'fa-badminton'],
    ['name' => 'Table Tennis', 'icon' => 'fa-table-tennis'],
    ['name' => 'Swimming', 'icon' => 'fa-swimmer'],
    ['name' => 'Arnis', 'icon' => 'fa-crossed-swords'],
    ['name' => 'Cycling', 'icon' => 'fa-bicycle'],
    ['name' => 'Tennis', 'icon' => 'fa-tennis-ball'],
    ['name' => 'Archery', 'icon' => 'fa-bow-arrow'],
];

function getSportLink($role, $sportName) {
    $encodedSport = urlencode($sportName);
    if ($role === 'athlete') {
        return "browse_tournaments.php?sport={$encodedSport}";
    }
    return "create_tournament.php?sport={$encodedSport}";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-p8Uhty22XkR4znkbN6w0+jZ8pO0ZL2wMUgGx+2qQPtjc6L3W7llhLj6Xb5zB4sga7zBxsAh3cY3bIvG/7oifCw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --orange: #e97817;
            --orange-dark: #c85c00;
            --cream: #fffaf4;
            --ink: #2b1c11;
            --line: #e6d8c6;
        }
        * { box-sizing: border-box; }
        body {
            font-family: "Manrope", "Segoe UI", sans-serif;
            background: var(--cream);
            margin: 0;
            color: var(--ink);
        }

        .topbar {
            background: linear-gradient(180deg, #ec8a2d 0%, #de7316 100%);
            border-bottom: 1px solid rgba(0,0,0,0.08);
            padding: 10px 20px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .brand {
            font-family: "Bebas Neue", sans-serif;
            font-size: 28px;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .top-search {
            flex: 1;
            max-width: 540px;
            position: relative;
        }
        .top-search input {
            width: 100%;
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 999px;
            padding: 9px 38px 9px 14px;
            background: #fff5e9;
            color: #452a18;
            font: inherit;
        }
        .top-search i {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #c36517;
            font-size: 13px;
        }
        .top-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            white-space: nowrap;
        }
        .top-actions a {
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
        }
        .pill {
            padding: 5px 10px;
            border-radius: 999px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.35);
            font-size: 12px;
            font-weight: 700;
        }

        .container {
            max-width: 1120px;
            margin: 0 auto;
            padding: 34px 20px 48px;
        }

        .welcome-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            margin-bottom: 20px;
        }
        .welcome-title {
            margin: 0;
            font-family: "Bebas Neue", sans-serif;
            letter-spacing: 1px;
            font-size: 44px;
            line-height: 0.95;
        }
        .role-actions a {
            display: inline-flex;
            align-items: center;
            margin-right: 8px;
            margin-bottom: 8px;
            padding: 9px 12px;
            border-radius: 9px;
            border: 1px solid #f0bf8e;
            background: #fff;
            color: #7a400d;
            font-size: 13px;
            text-decoration: none;
            font-weight: 700;
        }
        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            margin-bottom: 14px;
        }
        .section-head h3 {
            margin: 0;
            font-family: "Bebas Neue", sans-serif;
            font-size: 42px;
            letter-spacing: 1px;
            color: #be5b0a;
        }
        .sports-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .sport-card {
            background: linear-gradient(180deg, #ee8a2a 0%, #dd7316 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 182px;
            padding: 22px;
            border-radius: 14px;
            border: 1px solid #d56609;
            box-shadow: 0 10px 18px rgba(190, 91, 10, 0.18);
            text-align: center;
            text-decoration: none;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .sport-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 24px rgba(187, 86, 6, 0.26);
        }
        .sport-card i {
            font-size: 38px;
            color: #2d1607;
        }
        .sport-card span {
            margin-top: 12px;
            color: #2b1304;
            font-family: "Bebas Neue", sans-serif;
            letter-spacing: 1px;
            font-size: 24px;
            line-height: 1;
        }
        @media (max-width: 880px) {
            .sports-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 620px) {
            .topbar { flex-wrap: wrap; }
            .top-search { order: 3; max-width: none; width: 100%; }
            .sports-grid { grid-template-columns: 1fr; }
            .welcome-title { font-size: 34px; }
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="brand"><i class="fas fa-trophy"></i> TOURNAMEET</div>
    <div class="top-search">
        <input type="text" placeholder="Search tournaments, sports...">
        <i class="fas fa-search"></i>
    </div>
    <div class="top-actions">
        <span class="pill"><?php echo htmlspecialchars(strtoupper($role)); ?></span>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="welcome-line">
        <h1 class="welcome-title">SPORT CATEGORIES</h1>
        <div class="role-actions">
            <?php if ($role === 'admin'): ?>
                <a href="admin_panel.php">Admin Panel</a>
            <?php endif; ?>
            <?php if ($role === 'organizer' || $role === 'admin'): ?>
                <a href="create_tournament.php">Create Tournament</a>
                <a href="my_tournaments.php">My Tournaments</a>
            <?php endif; ?>
            <?php if ($role === 'athlete' || $role === 'admin'): ?>
                <a href="browse_tournaments.php">Browse All</a>
                <a href="joined_tournaments.php">My Joined</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($role === 'athlete' || $role === 'organizer' || $role === 'admin'): ?>
    <div class="section-head">
        <h3>TOURNAMENTS</h3>
        <div>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></div>
    </div>
    <div class="sports-grid">
        <?php foreach ($sports as $sport): ?>
            <a href="<?php echo htmlspecialchars(getSportLink($role, $sport['name'])); ?>" class="sport-card">
                <i class="fas <?php echo htmlspecialchars($sport['icon']); ?>"></i>
                <span><?php echo htmlspecialchars($sport['name']); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
