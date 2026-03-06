<?php
require 'config.php';
$id = 0;
if (isset($_GET['id'])) {
$id = intval($_GET['id']);
}
if (!$id) {
http_response_code(400);
echo json_encode(array('error' => 'Missing id'));
exit;
}
$stmt = $pdo->prepare("SELECT
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
NULL AS image_url,
t.is_closed,
t.registration_deadline
FROM tournaments t
LEFT JOIN (
SELECT tournament_id, SUM(CASE WHEN status IN ('pending','approved') THEN 1 ELSE 0 END) AS active_count
FROM tournament_registrations
GROUP BY tournament_id
) r ON r.tournament_id = t.id
WHERE t.id = :id");
$stmt->execute(array(':id' => $id));
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
http_response_code(404);
echo json_encode(array('error' => 'Tournament not found'));
exit;
}
echo json_encode(array('data' => $row));
