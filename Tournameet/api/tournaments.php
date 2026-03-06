<?php
require 'config.php';
$search = '';
$sort = '';
$category = '';
if (isset($_GET['search'])) { $search = $_GET['search']; }
if (isset($_GET['sort'])) { $sort = $_GET['sort']; }
if (isset($_GET['category'])) { $category = $_GET['category']; }

$categoryAliases = array(
    'ball' => array('ball sports', 'basketball', 'volleyball', 'football', 'futsal', '3x3 basketball', 'beach volleyball', 'baseball', 'softball'),
    'racket' => array('racket sports', 'badminton', 'tennis', 'squash', 'table tennis', 'pickleball'),
    'combatives' => array('combatives', 'boxing', 'mma', 'karate', 'taekwondo', 'judo', 'wrestling', 'muay thai', 'jiu jitsu'),
    'endurance' => array('endurance', 'running', 'marathon', 'cycling', 'triathlon', 'swimming', 'cross country', 'trail running'),
    'precision' => array('precision', 'archery', 'shooting', 'darts', 'bowling', 'golf', 'billiards'),
    'esports' => array('e-sports', 'esports', 'fps', 'moba', 'fighting', 'battle royale', 'rts', 'sports game', 'mobile legends', 'valorant', 'dota 2', 'street fighter'),
);

$sql = "SELECT
t.id,
t.title AS name,
t.description,
t.sport,
t.event_date AS date,
COALESCE(DATE_FORMAT(t.event_time, '%H:%i'), '') AS time,
t.location,
COALESCE(t.registration_fee, 0) AS entry_fee,
COALESCE(t.prize_pool, 0) AS prize,
COALESCE(NULLIF(t.created_by, ''), 'Organizer') AS organizer,
COALESCE(t.slots, 0) AS slots_total,
COALESCE(r.active_count, 0) AS slots_taken,
'Standard' AS format,
NULL AS image_url
FROM tournaments t
LEFT JOIN (
SELECT tournament_id, SUM(CASE WHEN status IN ('pending','approved') THEN 1 ELSE 0 END) AS active_count
FROM tournament_registrations
GROUP BY tournament_id
) r ON r.tournament_id = t.id
WHERE 1=1";

if (isset($categoryAliases[$category])) {
    $quoted = array_map(function ($v) use ($pdo) {
        return $pdo->quote($v);
    }, $categoryAliases[$category]);
    $sql = $sql . " AND LOWER(t.sport) IN (" . implode(',', $quoted) . ")";
}
if ($search) {
$sql = $sql . " AND (t.title LIKE :search OR t.sport LIKE :search OR t.location LIKE :search)";
}
if ($sort === 'az') { $sql = $sql . " ORDER BY t.title ASC"; }
elseif ($sort === 'za') { $sql = $sql . " ORDER BY t.title DESC"; }
elseif ($sort === 'newest') { $sql = $sql . " ORDER BY t.event_date DESC, t.id DESC"; }
elseif ($sort === 'slots') { $sql = $sql . " ORDER BY t.slots DESC"; }
else { $sql = $sql . " ORDER BY t.id DESC"; }
$stmt = $pdo->prepare($sql);
if ($search) {
$likeSearch = '%' . $search . '%';
$stmt->bindValue(':search', $likeSearch);
}
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(array('data' => $data));
