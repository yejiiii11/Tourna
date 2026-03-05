<?php
// ============================================================
//  test.php — Run this FIRST to diagnose connection issues
//  Visit: http://localhost/ball-sports/test.php
// ============================================================

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
  <title>Ball Sports — Diagnostic</title>
  <style>
    body { font-family: sans-serif; max-width: 700px; margin: 40px auto; padding: 0 20px; background: #fafafa; }
    h1 { color: #F47B20; }
    .check { display: flex; align-items: flex-start; gap: 14px; padding: 14px 18px; border-radius: 10px; margin-bottom: 12px; }
    .ok   { background: #f0fff4; border: 1.5px solid #6fcf97; }
    .fail { background: #fff0f0; border: 1.5px solid #e05555; }
    .warn { background: #fffbeb; border: 1.5px solid #f6c942; }
    .icon { font-size: 1.4rem; flex-shrink: 0; margin-top: 2px; }
    .label { font-weight: 700; font-size: 0.95rem; margin-bottom: 3px; }
    .detail { font-size: 0.82rem; color: #555; line-height: 1.5; }
    code { background: #f0f0f0; padding: 1px 6px; border-radius: 4px; font-size: 0.82rem; }
    h2 { margin-top: 32px; font-size: 1rem; color: #888; text-transform: uppercase; letter-spacing: 1px; }
  </style>
</head>
<body>
<h1>🔧 Diagnostic Check</h1>
<p style="color:#888; margin-bottom:24px;">If you can see this page, Apache is working correctly.</p>

<?php

// ── 1. PHP Running ───────────────────────────────────────────
echo '<div class="check ok">
  <div class="icon">✅</div>
  <div>
    <div class="label">PHP is running</div>
    <div class="detail">Version: ' . phpversion() . '</div>
  </div>
</div>';

// ── 2. MySQLi Extension ──────────────────────────────────────
if (extension_loaded('mysqli')) {
  echo '<div class="check ok">
    <div class="icon">✅</div>
    <div>
      <div class="label">MySQLi extension is loaded</div>
      <div class="detail">PHP can talk to MySQL.</div>
    </div>
  </div>';
} else {
  echo '<div class="check fail">
    <div class="icon">❌</div>
    <div>
      <div class="label">MySQLi extension is NOT loaded</div>
      <div class="detail">
        Open <code>C:/xampp/php/php.ini</code>, find
        <code>;extension=mysqli</code> and remove the semicolon,
        then restart Apache.
      </div>
    </div>
  </div>';
}

// ── 3. MySQL Connection ──────────────────────────────────────
$conn = @new mysqli('localhost', 'root', '', '');
if ($conn->connect_error) {
  echo '<div class="check fail">
    <div class="icon">❌</div>
    <div>
      <div class="label">Cannot connect to MySQL</div>
      <div class="detail">
        Error: <code>' . htmlspecialchars($conn->connect_error) . '</code><br>
        Make sure MySQL is started in the XAMPP Control Panel.
      </div>
    </div>
  </div>';
} else {
  echo '<div class="check ok">
    <div class="icon">✅</div>
    <div>
      <div class="label">MySQL connection successful</div>
      <div class="detail">Connected as <code>root@localhost</code></div>
    </div>
  </div>';

  // ── 4. Database Exists ─────────────────────────────────────
  $db = @$conn->select_db('ball_sports');
  if (!$db) {
    echo '<div class="check fail">
      <div class="icon">❌</div>
      <div>
        <div class="label">Database <code>ball_sports</code> not found</div>
        <div class="detail">
          Go to <a href="http://localhost/phpmyadmin" target="_blank">phpMyAdmin</a>,
          click <strong>SQL</strong>, paste the contents of
          <code>tournaments_schema.sql</code> and click <strong>Go</strong>.
        </div>
      </div>
    </div>';
  } else {
    echo '<div class="check ok">
      <div class="icon">✅</div>
      <div>
        <div class="label">Database <code>ball_sports</code> found</div>
      </div>
    </div>';

    // ── 5. Tables Exist ──────────────────────────────────────
    $tables = ['sports', 'organizers', 'tournaments', 'registrations'];
    $missing = [];
    foreach ($tables as $tbl) {
      $r = $conn->query("SHOW TABLES LIKE '$tbl'");
      if ($r->num_rows === 0) $missing[] = $tbl;
    }

    if ($missing) {
      echo '<div class="check fail">
        <div class="icon">❌</div>
        <div>
          <div class="label">Missing tables: <code>' . implode(', ', $missing) . '</code></div>
          <div class="detail">
            Re-run <code>tournaments_schema.sql</code> in
            <a href="http://localhost/phpmyadmin" target="_blank">phpMyAdmin</a>.
          </div>
        </div>
      </div>';
    } else {
      echo '<div class="check ok">
        <div class="icon">✅</div>
        <div>
          <div class="label">All tables exist</div>
          <div class="detail"><code>' . implode(', ', $tables) . '</code></div>
        </div>
      </div>';
    }

    // ── 6. Row Count ─────────────────────────────────────────
    $res   = $conn->query("SELECT COUNT(*) AS cnt FROM tournaments");
    $count = $res ? $res->fetch_assoc()['cnt'] : 0;
    if ($count == 0) {
      echo '<div class="check warn">
        <div class="icon">⚠️</div>
        <div>
          <div class="label">Tournaments table is empty</div>
          <div class="detail">
            The schema was imported but seed data is missing.<br>
            Re-run <code>tournaments_schema.sql</code> — it includes
            <code>INSERT</code> statements at the bottom.
          </div>
        </div>
      </div>';
    } else {
      echo '<div class="check ok">
        <div class="icon">✅</div>
        <div>
          <div class="label">Seed data is present</div>
          <div class="detail"><code>' . $count . '</code> tournament(s) in the database.</div>
        </div>
      </div>';
    }
  }
  $conn->close();
}

// ── 7. API Files Exist ───────────────────────────────────────
$apiFiles = [
  'api/config.php',
  'api/tournaments.php',
  'api/tournament.php',
  'api/register.php',
];
$apiMissing = [];
foreach ($apiFiles as $f) {
  if (!file_exists(__DIR__ . '/' . $f)) $apiMissing[] = $f;
}

if ($apiMissing) {
  echo '<div class="check fail">
    <div class="icon">❌</div>
    <div>
      <div class="label">Missing API files</div>
      <div class="detail">
        These files were not found:<br><code>' . implode('<br>', $apiMissing) . '</code><br><br>
        Make sure you copied the <code>api/</code> folder into
        <code>C:/xampp/htdocs/ball-sports/</code>
      </div>
    </div>
  </div>';
} else {
  echo '<div class="check ok">
    <div class="icon">✅</div>
    <div>
      <div class="label">All API files found</div>
      <div class="detail"><code>api/config.php</code>, <code>api/tournaments.php</code>,
      <code>api/tournament.php</code>, <code>api/register.php</code></div>
    </div>
  </div>';
}

// ── 8. CORS / Live API Test ──────────────────────────────────
echo '<h2>Next Step</h2>';
echo '<div class="check ok">
  <div class="icon">🚀</div>
  <div>
    <div class="label">Ready to test the live API?</div>
    <div class="detail">
      <a href="api/tournaments.php" target="_blank">Open api/tournaments.php</a> — it should return JSON.<br>
      If everything above is ✅ and the API returns JSON, open
      <a href="index.html">index.html</a> and it will work.
    </div>
  </div>
</div>';
?>

</body>
</html>
