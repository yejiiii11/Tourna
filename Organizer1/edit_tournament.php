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

$row = getTournamentById($conn, $id);
if (!$row) {
    echo "Tournament not found.";
    exit;
}
$row = autoCloseTournamentIfNeeded($conn, $row);
$isPast = isTournamentPast($row);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Tournament</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: "Plus Jakarta Sans", "Segoe UI", sans-serif; background:#fffaf4; margin:0; }
        .container { max-width: 920px; margin: 0 auto; padding: 28px 20px 36px; }
        .card { background:#fff; border:1px solid #f0d4b7; border-radius:14px; box-shadow:0 10px 24px rgba(180,93,17,.1); padding:20px; }
        .row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .input-group { margin-bottom:12px; }
        .input-group label { display:block; margin-bottom:6px; font-weight:600; font-size:13px; }
        .input-group input, .input-group textarea { width:100%; box-sizing:border-box; padding:10px 11px; border:1px solid #d8dde6; border-radius:8px; font:inherit; background:#fff; }
        .actions { display:flex; gap:8px; }
        .btn { display:inline-block; border:none; border-radius:8px; text-decoration:none; padding:10px 14px; font-weight:700; font-size:13px; cursor:pointer; }
        .btn.primary { background:linear-gradient(120deg,#ee8f36,#d9690f); color:#fff; }
        .btn.ghost { background:#fff; color:#9b4f0a; border:1px solid #e3bb96; }
        .warn { border:1px solid #f1c58f; background:#fff7ea; color:#8d510d; padding:10px; border-radius:8px; margin-bottom:10px; }
        .picker-map { height:240px; border:1px solid #d8dde6; border-radius:8px; margin-top:8px; }
        .picker-search { margin-top:8px; }
        .loc-btn { margin-top:8px; border:1px solid #d4d9e1; background:#fff; border-radius:8px; padding:8px 10px; font-weight:700; cursor:pointer; }
        @media (max-width: 700px) { .row { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="navbar">
    <div>Edit Tournament</div>
    <div><a href="my_tournaments.php">Back</a> | <a href="logout.php">Logout</a></div>
</div>
<div class="container">
    <div class="card">
        <h2>Update Tournament Details</h2>
        <?php if ($isPast): ?>
            <div class="warn">This tournament date is in the past. To edit and reopen registration, you must confirm reopen below.</div>
        <?php endif; ?>
        <form action="update_tournament.php" method="POST">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="id" value="<?php echo intval($row['id']); ?>">
            <div class="input-group">
                <label>Sport</label>
                <input type="text" name="sport" value="<?php echo htmlspecialchars($row['sport'] ?? ''); ?>" required>
            </div>
            <div class="input-group">
                <label>Title</label>
                <input type="text" name="title" maxlength="150" value="<?php echo htmlspecialchars($row['title'] ?? ''); ?>" required>
            </div>
            <div class="input-group">
                <label>Description</label>
                <textarea name="description" rows="4" required><?php echo htmlspecialchars($row['description'] ?? ''); ?></textarea>
            </div>
            <div class="row">
                <div class="input-group">
                    <label>Date</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($row['event_date'] ?? ''); ?>" required>
                </div>
                <div class="input-group">
                    <label>Time</label>
                    <input type="time" name="time" value="<?php echo htmlspecialchars(substr($row['event_time'] ?? '', 0, 5)); ?>" required>
                </div>
            </div>
            <div class="input-group">
                <label>Registration Deadline</label>
                <input type="datetime-local" name="registration_deadline" value="<?php echo !empty($row['registration_deadline']) ? htmlspecialchars(str_replace(' ', 'T', substr($row['registration_deadline'], 0, 16))) : ''; ?>" required>
            </div>
            <div class="input-group">
                <label>Location</label>
                <input type="text" id="e_location" name="location" maxlength="255" value="<?php echo htmlspecialchars($row['location'] ?? ''); ?>" required>
                <div class="picker-search">
                    <input type="text" id="e_location_search" placeholder="Search location and press Enter">
                </div>
                <button type="button" class="loc-btn" id="e_use_current_location">Use my current location</button>
                <div id="e_location_map" class="picker-map"></div>
            </div>
            <div class="row">
                <div class="input-group">
                    <label>Registration Fee (PHP)</label>
                    <input type="number" step="0.01" min="0" name="registration_fee" value="<?php echo htmlspecialchars($row['registration_fee'] ?? '0'); ?>" required>
                </div>
                <div class="input-group">
                    <label>Prize Pool (PHP)</label>
                    <input type="number" step="0.01" min="0" name="prize_pool" value="<?php echo htmlspecialchars($row['prize_pool'] ?? '0'); ?>" required>
                </div>
            </div>
            <div class="input-group">
                <label>Slots</label>
                <input type="number" min="1" name="slots" value="<?php echo htmlspecialchars($row['slots'] ?? '1'); ?>" required>
            </div>
            <div class="input-group">
                <label>Requirements (one per line)</label>
                <textarea name="requirements" rows="4"><?php echo htmlspecialchars($row['requirements'] ?? ''); ?></textarea>
            </div>
            <div class="input-group">
                <label>Organizer Note / Announcement</label>
                <textarea name="organizer_note" rows="3"><?php echo htmlspecialchars($row['organizer_note'] ?? ''); ?></textarea>
            </div>
            <div class="input-group">
                <label><input type="checkbox" name="is_closed" value="1" <?php echo intval($row['is_closed'] ?? 0) === 1 ? 'checked' : ''; ?>> Close registration manually</label>
            </div>
            <?php if ($isPast): ?>
                <div class="input-group">
                    <label><input type="checkbox" name="confirm_reopen" value="1"> I confirm reopening/editing a past tournament</label>
                </div>
            <?php endif; ?>
            <div class="actions">
                <button class="btn primary" type="submit">Save Changes</button>
                <a class="btn ghost" href="my_tournaments.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const editLocationInput = document.getElementById('e_location');
const editLocationSearch = document.getElementById('e_location_search');
const editUseCurrentLocationBtn = document.getElementById('e_use_current_location');
const editMap = L.map('e_location_map').setView([14.5995, 120.9842], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(editMap);
let editMarker = null;

function setEditMarker(lat, lng) {
    if (!editMarker) {
        editMarker = L.marker([lat, lng], { draggable: true }).addTo(editMap);
        editMarker.on('dragend', function (e) {
            const pos = e.target.getLatLng();
            reverseEditGeocode(pos.lat, pos.lng);
        });
    } else {
        editMarker.setLatLng([lat, lng]);
    }
    editMap.setView([lat, lng], 15);
}

function reverseEditGeocode(lat, lon) {
    fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lon)
        .then(r => r.json())
        .then(data => {
            if (data && data.display_name) {
                editLocationInput.value = data.display_name;
            }
        })
        .catch(() => {});
}

function searchEditLocation(query) {
    if (!query) return;
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(query))
        .then(r => r.json())
        .then(results => {
            if (results && results.length > 0) {
                const r = results[0];
                setEditMarker(parseFloat(r.lat), parseFloat(r.lon));
                editLocationInput.value = r.display_name;
            }
        })
        .catch(() => {});
}

editMap.on('click', function (e) {
    setEditMarker(e.latlng.lat, e.latlng.lng);
    reverseEditGeocode(e.latlng.lat, e.latlng.lng);
});

editLocationSearch.addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchEditLocation(editLocationSearch.value.trim());
    }
});

editLocationInput.addEventListener('blur', function () {
    if (editLocationInput.value.trim() !== '') {
        searchEditLocation(editLocationInput.value.trim());
    }
});

editUseCurrentLocationBtn.addEventListener('click', function () {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported in this browser.');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        function (pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            setEditMarker(lat, lng);
            reverseEditGeocode(lat, lng);
        },
        function () {
            alert('Unable to access your current location. Please allow location permission.');
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
});

if (editLocationInput.value.trim() !== '') {
    searchEditLocation(editLocationInput.value.trim());
}
</script>
</body>
</html>
