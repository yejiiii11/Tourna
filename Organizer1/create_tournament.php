<?php
require_once "session_bootstrap.php";
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['organizer','admin'])) {
    header('Location: login.php');
    exit;
}

$selectedSport = isset($_GET['sport']) ? trim(urldecode($_GET['sport'])) : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Tournament</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family:"Plus Jakarta Sans","Segoe UI",sans-serif; background:linear-gradient(180deg,#fff4e8 0%,#f7f8fb 55%,#eef2f8 100%); margin:0; }
        .container { max-width: 1320px; margin:0 auto; padding:32px 22px 40px; }
        .layout { display:grid; grid-template-columns:minmax(530px,1.1fr) minmax(380px,.9fr); gap:20px; }
        .card { background:#fff; border:1px solid #ffd9b0; border-radius:16px; box-shadow:0 14px 30px rgba(255,140,0,.1); padding:22px; }
        .input-group { margin-bottom:12px; }
        label { display:block; margin-bottom:5px; font-size:13px; font-weight:700; }
        input, textarea { width:100%; box-sizing:border-box; border:1px solid #d4d9e1; border-radius:10px; padding:10px 11px; font:inherit; }
        input:focus, textarea:focus { outline:none; border-color:#ff8c00; box-shadow:0 0 0 3px rgba(255,140,0,.16); }
        .row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .btn { border:none; border-radius:10px; padding:12px 18px; font-weight:700; color:#fff; background:linear-gradient(120deg,#ff8c00,#ff6a00); cursor:pointer; }
        .sub { color:#4f5663; margin-top:0; }
        .preview-card h3 { margin-top:0; }
        .preview-chip { display:inline-block; border-radius:999px; padding:4px 8px; font-size:11px; font-weight:700; background:#fff1df; color:#a65300; }
        .preview-title { font-size:32px; margin:6px 0 10px; line-height:1; }
        .preview-note { white-space:pre-wrap; border:1px dashed #dac7b2; border-radius:10px; padding:10px; background:#fffaf3; color:#4f4439; }
        .muted { color:#656d7b; font-size:12px; }
        .map-wrap { border-radius:12px; overflow:hidden; border:1px solid #e3e8f0; height:280px; background:#eef2f7; }
        .picker-map { height:240px; border:1px solid #d4d9e1; border-radius:10px; margin-top:8px; }
        .picker-search { margin-top:8px; }
        .picker-search input { width:100%; }
        .loc-btn { margin-top:8px; border:1px solid #d4d9e1; background:#fff; border-radius:8px; padding:8px 10px; font-weight:700; cursor:pointer; }
        .sport-tag { display:inline-block; background:#fff1df; color:#a65300; padding:7px 12px; border-radius:999px; font-weight:700; margin-bottom:10px; }
        @media (max-width:1020px) { .layout { grid-template-columns:1fr; } }
        @media (max-width:700px) { .row { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="navbar">
    <div>Create Tournament</div>
    <div><a href="dashboard.php">Back</a> | <a href="logout.php">Logout</a></div>
</div>
<div class="container">
    <div class="layout">
        <section class="card">
            <h2 style="margin:0 0 8px;">Enter Tournament Details</h2>
            <p class="sub">Use complete details and publish only when the preview is correct.</p>
            <?php if ($selectedSport !== ''): ?>
                <div class="sport-tag">Sport: <?php echo htmlspecialchars($selectedSport); ?></div>
            <?php endif; ?>

            <form action="save_tournament.php" method="POST" id="tournamentForm">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="sport" value="<?php echo htmlspecialchars($selectedSport); ?>">

                <div class="input-group">
                    <label>Title</label>
                    <input type="text" id="f_title" name="title" maxlength="150" required>
                </div>
                <div class="input-group">
                    <label>Description</label>
                    <textarea id="f_description" name="description" rows="4" required></textarea>
                </div>
                <div class="input-group">
                    <label>Sport</label>
                    <input type="text" id="f_sport" name="sport_manual" value="<?php echo htmlspecialchars($selectedSport); ?>" required>
                    <div class="muted">If the selected sport is empty, type it here.</div>
                </div>
                <div class="row">
                    <div class="input-group">
                        <label>Date</label>
                        <input type="date" id="f_date" name="date" required>
                    </div>
                    <div class="input-group">
                        <label>Time</label>
                        <input type="time" id="f_time" name="time" required>
                    </div>
                </div>
                <div class="input-group">
                    <label>Registration Deadline</label>
                    <input type="datetime-local" id="f_deadline" name="registration_deadline" required>
                </div>
                <div class="input-group">
                    <label>Location / Venue</label>
                    <input type="text" id="f_location" name="location" maxlength="255" required>
                    <div class="picker-search">
                        <input type="text" id="location_search" placeholder="Search location and press Enter">
                    </div>
                    <button type="button" class="loc-btn" id="use_current_location">Use my current location</button>
                    <div id="location_map" class="picker-map"></div>
                    <div class="muted">Tip: click map or search a place to auto-fill venue.</div>
                </div>
                <div class="row">
                    <div class="input-group">
                        <label>Registration Fee (PHP)</label>
                        <input type="number" id="f_fee" name="registration_fee" step="0.01" min="0" value="0" required>
                    </div>
                    <div class="input-group">
                        <label>Prize Pool (PHP)</label>
                        <input type="number" id="f_prize" name="prize_pool" step="0.01" min="0" value="0" required>
                    </div>
                </div>
                <div class="input-group">
                    <label>Slots</label>
                    <input type="number" id="f_slots" name="slots" min="1" required>
                </div>
                <div class="input-group">
                    <label>Requirements (one per line)</label>
                    <textarea id="f_requirements" name="requirements" rows="3"></textarea>
                </div>
                <div class="input-group">
                    <label>Organizer Note / Announcement</label>
                    <textarea id="f_note" name="organizer_note" rows="3" placeholder="Visible in tournament page and joiner list"></textarea>
                </div>
                <button class="btn" type="submit">Publish Tournament</button>
            </form>
        </section>

        <aside class="card preview-card">
            <h3>Preview Before Publish</h3>
            <div class="map-wrap"><iframe id="preview_map" width="100%" height="100%" frameborder="0" src="https://www.google.com/maps?q=Philippines&output=embed"></iframe></div>
            <div style="margin-top:12px;">
                <span class="preview-chip" id="p_sport">SPORT</span>
                <h4 class="preview-title" id="p_title">Tournament Title</h4>
                <p id="p_desc">Description preview</p>
                <p><strong>Date:</strong> <span id="p_date">-</span></p>
                <p><strong>Deadline:</strong> <span id="p_deadline">-</span></p>
                <p><strong>Venue:</strong> <span id="p_location">-</span></p>
                <p><strong>Fees:</strong> PHP <span id="p_fee">0.00</span> | Prize Pool: PHP <span id="p_prize">0.00</span></p>
                <p><strong>Slots:</strong> <span id="p_slots">-</span></p>
                <div class="preview-note" id="p_note">No organizer note yet.</div>
            </div>
        </aside>
    </div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const form = document.getElementById('tournamentForm');

function get(id){ return document.getElementById(id); }
function sync() {
    const sport = get('f_sport').value || 'General';
    get('p_sport').textContent = sport.toUpperCase();
    get('p_title').textContent = get('f_title').value || 'Tournament Title';
    get('p_desc').textContent = get('f_description').value || 'Description preview';
    get('p_date').textContent = (get('f_date').value || '-') + (get('f_time').value ? ' ' + get('f_time').value : '');
    get('p_deadline').textContent = get('f_deadline').value || '-';
    get('p_location').textContent = get('f_location').value || '-';
    get('p_fee').textContent = (parseFloat(get('f_fee').value || 0)).toFixed(2);
    get('p_prize').textContent = (parseFloat(get('f_prize').value || 0)).toFixed(2);
    get('p_slots').textContent = get('f_slots').value || '-';
    get('p_note').textContent = get('f_note').value.trim() || 'No organizer note yet.';

    const mapQ = encodeURIComponent(get('f_location').value || 'Philippines');
    get('preview_map').src = 'https://www.google.com/maps?q=' + mapQ + '&output=embed';
}

['input','change'].forEach(evt => {
    form.addEventListener(evt, function(e){ if(e.target && e.target.id && e.target.id.startsWith('f_')) sync(); });
});

const locationInput = get('f_location');
const locationSearch = get('location_search');
const useCurrentLocationBtn = get('use_current_location');
const pickerMap = L.map('location_map').setView([14.5995, 120.9842], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(pickerMap);
let pickerMarker = null;

function setMarker(lat, lng) {
    if (!pickerMarker) {
        pickerMarker = L.marker([lat, lng], { draggable: true }).addTo(pickerMap);
        pickerMarker.on('dragend', function (e) {
            const pos = e.target.getLatLng();
            reverseGeocode(pos.lat, pos.lng);
        });
    } else {
        pickerMarker.setLatLng([lat, lng]);
    }
    pickerMap.setView([lat, lng], 15);
}

function reverseGeocode(lat, lon) {
    fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lon)
        .then(r => r.json())
        .then(data => {
            if (data && data.display_name) {
                locationInput.value = data.display_name;
                sync();
            }
        })
        .catch(() => {});
}

function searchGeocode(query) {
    if (!query) return;
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(query))
        .then(r => r.json())
        .then(results => {
            if (results && results.length > 0) {
                const r = results[0];
                setMarker(parseFloat(r.lat), parseFloat(r.lon));
                locationInput.value = r.display_name;
                sync();
            }
        })
        .catch(() => {});
}

pickerMap.on('click', function (e) {
    setMarker(e.latlng.lat, e.latlng.lng);
    reverseGeocode(e.latlng.lat, e.latlng.lng);
});

locationSearch.addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchGeocode(locationSearch.value.trim());
    }
});

locationInput.addEventListener('blur', function () {
    if (locationInput.value.trim() !== '') {
        searchGeocode(locationInput.value.trim());
    }
});

useCurrentLocationBtn.addEventListener('click', function () {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported in this browser.');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        function (pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            setMarker(lat, lng);
            reverseGeocode(lat, lng);
        },
        function () {
            alert('Unable to access your current location. Please allow location permission.');
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
});

sync();
</script>
</body>
</html>
