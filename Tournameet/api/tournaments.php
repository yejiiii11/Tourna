<?php
require 'config.php';
$search = '';
$sort = '';
$category = '';
if (isset($_GET['search'])) { $search = $_GET['search']; }
if (isset($_GET['sort'])) { $sort = $_GET['sort']; }
if (isset($_GET['category'])) { $category = $_GET['category']; }
$sql = "SELECT * FROM tournaments WHERE 1=1";
if ($category === 'ball') {
$sql = $sql . " AND sport IN ('Basketball', 'Volleyball', 'Football', 'Futsal', '3x3 Basketball', 'Beach Volleyball', 'Baseball', 'Softball')";
}
if ($category === 'racket') {
$sql = $sql . " AND sport IN ('Badminton', 'Tennis', 'Squash', 'Table Tennis', 'Pickleball')";
}
if ($category === 'combatives') {
$sql = $sql . " AND sport IN ('Boxing', 'MMA', 'Karate', 'Taekwondo', 'Judo', 'Wrestling', 'Muay Thai', 'Jiu Jitsu')";
}
if ($category === 'endurance') {
$sql = $sql . " AND sport IN ('Running', 'Marathon', 'Cycling', 'Triathlon', 'Swimming', 'Cross Country', 'Trail Running')";
}
if ($category === 'precision') {
$sql = $sql . " AND sport IN ('Archery', 'Shooting', 'Darts', 'Bowling', 'Golf', 'Billiards')";
}
if ($category === 'esports') {
$sql = $sql . " AND sport IN ('FPS', 'MOBA', 'Fighting', 'Battle Royale', 'RTS', 'Sports Game', 'Mobile Legends', 'Valorant', 'DOTA 2', 'Street Fighter')";
}
if ($search) {
$sql = $sql . " AND (name LIKE :search OR sport LIKE :search OR location LIKE :search)";
}
if ($sort === 'az') { $sql = $sql . " ORDER BY name ASC"; }
elseif ($sort === 'za') { $sql = $sql . " ORDER BY name DESC"; }
elseif ($sort === 'newest') { $sql = $sql . " ORDER BY date DESC"; }
elseif ($sort === 'slots') { $sql = $sql . " ORDER BY slots_total DESC"; }
else { $sql = $sql . " ORDER BY id ASC"; }
$stmt = $pdo->prepare($sql);
if ($search) {
$likeSearch = '%' . $search . '%';
$stmt->bindValue(':search', $likeSearch);
}
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(array('data' => $data));