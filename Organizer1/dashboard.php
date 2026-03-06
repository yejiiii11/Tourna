<?php
require_once "session_bootstrap.php";
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['organizer', 'admin'], true)) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TournaMeet Organizer Dashboard</title>
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
  body {
    background: #fafafa;
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    color: #1a1a1a;
  }
  nav {
    position: sticky; top: 0; z-index: 200;
    background: var(--white);
    border-bottom: 2px solid var(--orange);
    box-shadow: var(--shadow);
    height: 64px;
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    align-items: center;
    padding: 0 32px;
    gap: 12px;
  }
  .nav-left { display: flex; align-items: center; gap: 10px; }
  .logo-icon {
    width: 38px; height: 38px; border-radius: 50%;
    background: var(--orange-light); border: 2px solid var(--orange);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  }
  .brand {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 1.7rem; letter-spacing: 2.5px;
    color: var(--orange); line-height: 1;
  }
  .nav-center { display: flex; justify-content: center; }
  .search-wrap { position: relative; width: 100%; max-width: 340px; }
  .search-wrap input {
    width: 100%; height: 40px;
    border: 2px solid var(--orange); border-radius: 50px;
    padding: 0 44px 0 18px;
    font-family: 'DM Sans', sans-serif; font-size: 0.9rem; color: #333;
    background: var(--orange-light); outline: none;
    transition: box-shadow 0.2s, background 0.2s;
  }
  .search-wrap input::placeholder { color: #aaa; }
  .search-wrap input:focus { background: var(--white); box-shadow: 0 0 0 3px rgba(244,123,32,0.25); }
  .search-wrap button {
    position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
    background: var(--orange); border: none; border-radius: 50%;
    width: 30px; height: 30px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background 0.2s;
  }
  .search-wrap button:hover { background: var(--orange-dark); }
  .nav-right { display: flex; justify-content: flex-end; align-items: center; gap: 8px; }
  .nav-icon-btn {
    background: var(--orange-light); border: 1.5px solid var(--orange);
    border-radius: 50%; width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background 0.2s, transform 0.15s;
    color: var(--orange); text-decoration: none;
  }
  .nav-icon-btn:hover { background: var(--orange); color: var(--white); transform: scale(1.08); }
  .nav-icon-btn:hover svg { stroke: var(--white); }
  main { max-width: 1100px; margin: 0 auto; padding: 40px 32px 80px; }
  .page-header { margin-bottom: 28px; }
  .page-header h1 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: clamp(2rem, 5vw, 3.5rem);
    color: var(--orange); letter-spacing: 3px; line-height: 1; margin-bottom: 4px;
  }
  .page-header p { font-size: 0.92rem; color: #888; font-weight: 500; }
  .sports-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
  .sport-card {
    background: var(--white);
    border-radius: 14px;
    border: 1.5px solid #f0e0d0;
    box-shadow: 0 2px 12px rgba(244,123,32,0.08);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 32px 20px 24px;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    text-align: center;
    position: relative; overflow: hidden;
    animation: fadeUp 0.4s ease both;
  }
  .sport-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--orange), var(--orange-mid));
    opacity: 0; transition: opacity 0.2s;
  }
  .sport-card:hover { transform: translateY(-4px); box-shadow: 0 8px 28px rgba(244,123,32,0.18); border-color: var(--orange); }
  .sport-card:hover::before { opacity: 1; }
  .sport-card:hover .icon-wrap { background: var(--orange); }
  .sport-card:hover .icon-wrap svg { stroke: var(--white); }
  .sport-card:active { transform: scale(0.985); }
  .icon-wrap {
    width: 72px; height: 72px; border-radius: 50%;
    background: var(--orange-light); border: 2px solid #f0e0d0;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 14px; transition: background 0.2s; flex-shrink: 0;
  }
  .icon-wrap svg { width: 36px; height: 36px; stroke: var(--orange); transition: stroke 0.2s; }
  .sport-name { font-family: 'Bebas Neue', sans-serif; font-size: 1.15rem; letter-spacing: 1.5px; color: #222; margin-bottom: 4px; }
  .sport-sub  { font-size: 0.75rem; color: #c0a090; font-weight: 500; }
  .sport-card:nth-child(1) { animation-delay: 0ms; }
  .sport-card:nth-child(2) { animation-delay: 60ms; }
  .sport-card:nth-child(3) { animation-delay: 120ms; }
  .sport-card:nth-child(4) { animation-delay: 180ms; }
  .sport-card:nth-child(5) { animation-delay: 240ms; }
  .sport-card:nth-child(6) { animation-delay: 300ms; }
  @keyframes fadeUp { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:translateY(0); } }
  @media (max-width: 1099px) { .sports-grid { grid-template-columns: repeat(2, 1fr); } nav { padding: 0 20px; } }
  @media (max-width: 599px) {
    .sports-grid { gap: 14px; } nav { padding: 0 12px; grid-template-columns: auto 1fr auto; }
    main { padding: 24px 16px 60px; } .icon-wrap { width: 60px; height: 60px; }
    .icon-wrap svg { width: 28px; height: 28px; }
  }
</style>
</head>
<body>
<nav>
  <div class="nav-left">
    <div class="logo-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 2h12v6a6 6 0 0 1-12 0V2Z"/>
        <path d="M6 4H3a2 2 0 0 0-2 2v1a4 4 0 0 0 4 4h1"/>
        <path d="M18 4h3a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4h-1"/>
        <line x1="12" y1="14" x2="12" y2="18"/>
        <path d="M8 22h8"/><line x1="8" y1="18" x2="16" y2="18"/>
      </svg>
    </div>
    <span class="brand">TournaMeet</span>
  </div>
  <div class="nav-center">
    <div class="search-wrap">
      <input type="text" id="searchInput" placeholder="Search categories..."/>
      <button aria-label="Search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </button>
    </div>
  </div>
  <div class="nav-right">
    <a class="nav-icon-btn" href="create_tournament.php" title="Create Tournament">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
    </a>
    <a class="nav-icon-btn" href="my_tournaments.php" title="My Tournaments">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    </a>
    <a class="nav-icon-btn" href="logout.php" title="Logout">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </a>
  </div>
</nav>

<main>
  <div class="page-header">
    <h1>Choose a Category</h1>
    <p>Create organizer tournaments by sport type</p>
  </div>

  <div class="sports-grid" id="sportsGrid">
    <button class="sport-card" data-label="Ball Sports" onclick="openSport('Ball Sports')">
      <div class="icon-wrap">
        <svg viewBox="0 0 36 36" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="18" cy="18" r="13"/>
          <line x1="18" y1="5" x2="18" y2="31"/>
          <line x1="5" y1="18" x2="31" y2="18"/>
          <path d="M18 5 C10 8 10 28 18 31" fill="none"/>
          <path d="M18 5 C26 8 26 28 18 31" fill="none"/>
        </svg>
      </div>
      <div class="sport-name">Ball Sports</div>
      <div class="sport-sub">Basketball · Football · Volleyball</div>
    </button>

    <button class="sport-card" data-label="Racket Sports" onclick="openSport('Racket Sports')">
      <div class="icon-wrap">
        <svg viewBox="0 0 36 36" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <ellipse cx="13" cy="12" rx="6" ry="8" transform="rotate(-35 13 12)"/>
          <line x1="17" y1="18" x2="30" y2="32" stroke-width="2.4"/>
          <circle cx="29" cy="10" r="3"/>
        </svg>
      </div>
      <div class="sport-name">Racket Sports</div>
      <div class="sport-sub">Badminton · Tennis · Squash</div>
    </button>

    <button class="sport-card" data-label="Combatives" onclick="openSport('Combatives')">
      <div class="icon-wrap">
        <svg viewBox="0 0 36 36" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 12 Q3 8 9 8 L15 8 Q18 8 18 12 L18 19 Q18 22 15 22 L9 22 Q3 22 3 18 Z"/>
          <path d="M33 12 Q33 8 27 8 L21 8 Q18 8 18 12 L18 19 Q18 22 21 22 L27 22 Q33 22 33 18 Z"/>
        </svg>
      </div>
      <div class="sport-name">Combatives</div>
      <div class="sport-sub">Boxing · MMA · Karate</div>
    </button>

    <button class="sport-card" data-label="Endurance" onclick="openSport('Endurance')">
      <div class="icon-wrap">
        <svg viewBox="0 0 36 36" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="18" cy="6" r="3.5"/>
          <line x1="18" y1="9.5" x2="18" y2="21"/>
          <line x1="18" y1="13" x2="11" y2="16"/>
          <line x1="18" y1="13" x2="25" y2="16"/>
        </svg>
      </div>
      <div class="sport-name">Endurance</div>
      <div class="sport-sub">Running · Cycling · Triathlon</div>
    </button>

    <button class="sport-card" data-label="Precision" onclick="openSport('Precision')">
      <div class="icon-wrap">
        <svg viewBox="0 0 36 36" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="17" cy="20" r="12"/>
          <circle cx="17" cy="20" r="7.5"/>
          <circle cx="17" cy="20" r="3.5"/>
        </svg>
      </div>
      <div class="sport-name">Precision</div>
      <div class="sport-sub">Archery · Shooting · Darts</div>
    </button>

    <button class="sport-card" data-label="E-sports" onclick="openSport('E-sports')">
      <div class="icon-wrap">
        <svg viewBox="0 0 36 36" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 11 C13 11 9 11 7 14 L6 20 C5 23 5 26 6 29"/>
          <path d="M21 11 C23 11 27 11 29 14 L30 20 C31 23 31 26 30 29"/>
        </svg>
      </div>
      <div class="sport-name">E-sports</div>
      <div class="sport-sub">FPS · MOBA · Fighting</div>
    </button>
  </div>
</main>

<script>
  function openSport(name) {
    window.location.href = 'create_tournament.php?sport=' + encodeURIComponent(name);
  }

  const searchInput = document.getElementById('searchInput');
  const cards = Array.prototype.slice.call(document.querySelectorAll('.sport-card'));
  searchInput.addEventListener('input', function () {
    const q = searchInput.value.trim().toLowerCase();
    cards.forEach(function (card) {
      const label = (card.getAttribute('data-label') || '').toLowerCase();
      card.style.display = (q === '' || label.indexOf(q) !== -1) ? '' : 'none';
    });
  });
</script>
</body>
</html>
