<?php

function ensureOrganizerSchema(mysqli $conn): void
{
    $conn->query("ALTER TABLE tournaments ADD COLUMN registration_deadline DATETIME NULL AFTER event_time");
    $conn->query("ALTER TABLE tournaments ADD COLUMN is_closed TINYINT(1) NOT NULL DEFAULT 0 AFTER slots");
    $conn->query("ALTER TABLE tournaments ADD COLUMN organizer_note TEXT NULL AFTER requirements");
    $conn->query("ALTER TABLE tournaments ADD COLUMN views_count INT NOT NULL DEFAULT 0 AFTER organizer_note");

    $conn->query("CREATE TABLE IF NOT EXISTS tournament_registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT NOT NULL,
        athlete_username VARCHAR(50) NOT NULL,
        team_name VARCHAR(120) DEFAULT NULL,
        members TEXT,
        status ENUM('pending','approved','rejected','waitlisted') NOT NULL DEFAULT 'pending',
        attendance_status ENUM('unknown','attended','no_show') NOT NULL DEFAULT 'unknown',
        reviewed_at DATETIME NULL,
        reviewed_by VARCHAR(50) NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_tournament_athlete (tournament_id, athlete_username),
        KEY idx_tournament_status (tournament_id, status),
        CONSTRAINT fk_registration_tournament
            FOREIGN KEY (tournament_id) REFERENCES tournaments(id)
            ON DELETE CASCADE
    )");

    $conn->query("ALTER TABLE tournament_registrations ADD COLUMN status ENUM('pending','approved','rejected','waitlisted') NOT NULL DEFAULT 'pending' AFTER members");
    $conn->query("ALTER TABLE tournament_registrations ADD COLUMN attendance_status ENUM('unknown','attended','no_show') NOT NULL DEFAULT 'unknown' AFTER status");
    $conn->query("ALTER TABLE tournament_registrations ADD COLUMN reviewed_at DATETIME NULL AFTER attendance_status");
    $conn->query("ALTER TABLE tournament_registrations ADD COLUMN reviewed_by VARCHAR(50) NULL AFTER reviewed_at");
    $conn->query("CREATE INDEX idx_tournament_status ON tournament_registrations (tournament_id, status)");
}

function tournamentIsOwnedBy(mysqli $conn, int $tournamentId, string $username, bool $isAdmin): bool
{
    if ($isAdmin) {
        return true;
    }

    $stmt = $conn->prepare("SELECT id FROM tournaments WHERE id=? AND created_by=? LIMIT 1");
    $stmt->bind_param("is", $tournamentId, $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    $stmt->close();
    return $ok;
}

function getTournamentById(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT * FROM tournaments WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

function getTournamentCapacityStats(mysqli $conn, int $tournamentId): array
{
    $stmt = $conn->prepare("SELECT
        SUM(CASE WHEN status IN ('pending','approved') THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN status='waitlisted' THEN 1 ELSE 0 END) AS waitlisted_count,
        COUNT(*) AS total_count
        FROM tournament_registrations WHERE tournament_id=?");
    $stmt->bind_param("i", $tournamentId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : [];
    $stmt->close();

    return [
        'active_count' => intval($row['active_count'] ?? 0),
        'approved_count' => intval($row['approved_count'] ?? 0),
        'pending_count' => intval($row['pending_count'] ?? 0),
        'rejected_count' => intval($row['rejected_count'] ?? 0),
        'waitlisted_count' => intval($row['waitlisted_count'] ?? 0),
        'total_count' => intval($row['total_count'] ?? 0),
    ];
}

function isTournamentPast(array $tournament): bool
{
    if (empty($tournament['event_date'])) {
        return false;
    }
    return strtotime($tournament['event_date'] . ' 23:59:59') < time();
}

function deadlinePassed(array $tournament): bool
{
    if (empty($tournament['registration_deadline'])) {
        return false;
    }
    return strtotime($tournament['registration_deadline']) < time();
}

function autoCloseTournamentIfNeeded(mysqli $conn, array $tournament): array
{
    $id = intval($tournament['id'] ?? 0);
    if ($id <= 0) {
        return $tournament;
    }

    $slots = intval($tournament['slots'] ?? 0);
    $isClosed = intval($tournament['is_closed'] ?? 0) === 1;
    $stats = getTournamentCapacityStats($conn, $id);
    $isFull = ($slots > 0 && $stats['active_count'] >= $slots);
    $deadlineDone = deadlinePassed($tournament);

    if (!$isClosed && ($isFull || $deadlineDone)) {
        $stmt = $conn->prepare("UPDATE tournaments SET is_closed=1 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $tournament['is_closed'] = 1;
    }

    return $tournament;
}

function incrementTournamentViewOnce(mysqli $conn, int $tournamentId): void
{
    if (!isset($_SESSION['tournament_views'])) {
        $_SESSION['tournament_views'] = [];
    }

    if (in_array($tournamentId, $_SESSION['tournament_views'], true)) {
        return;
    }

    $stmt = $conn->prepare("UPDATE tournaments SET views_count = views_count + 1 WHERE id=?");
    $stmt->bind_param("i", $tournamentId);
    $stmt->execute();
    $stmt->close();

    $_SESSION['tournament_views'][] = $tournamentId;
}

function parseMemberLines(string $members): array
{
    $lines = preg_split('/\r\n|\r|\n/', trim($members));
    $clean = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $clean[] = $line;
        }
    }
    return $clean;
}

function hasDuplicateMemberNames(string $members): bool
{
    $lines = parseMemberLines($members);
    if (count($lines) <= 1) {
        return false;
    }

    $lowered = array_map(static function ($v) {
        return strtolower($v);
    }, $lines);

    return count($lowered) !== count(array_unique($lowered));
}

function sanitizeTournamentPayload(array $input): array
{
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $sport = trim($input['sport'] ?? '');
    $date = trim($input['date'] ?? '');
    $time = trim($input['time'] ?? '');
    $location = trim($input['location'] ?? '');
    $registrationFee = floatval($input['registration_fee'] ?? 0);
    $prizePool = floatval($input['prize_pool'] ?? 0);
    $slots = intval($input['slots'] ?? 0);
    $requirements = trim($input['requirements'] ?? '');
    $deadline = trim($input['registration_deadline'] ?? '');
    $organizerNote = trim($input['organizer_note'] ?? '');

    $errors = [];
    if ($title === '' || strlen($title) > 150) {
        $errors[] = 'Title is required and must be 150 characters or less.';
    }
    if ($description === '') {
        $errors[] = 'Description is required.';
    }
    if ($sport === '') {
        $errors[] = 'Sport is required.';
    }
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $errors[] = 'A valid event date is required.';
    }
    if ($time !== '' && !preg_match('/^\d{2}:\d{2}$/', $time)) {
        $errors[] = 'Invalid event time format.';
    }
    if ($location === '' || strlen($location) > 255) {
        $errors[] = 'Location is required and must be 255 characters or less.';
    }
    if ($registrationFee < 0 || $prizePool < 0) {
        $errors[] = 'Fees and prize pool cannot be negative.';
    }
    if ($slots < 1) {
        $errors[] = 'Slots must be at least 1.';
    }
    if ($deadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $deadline)) {
        $errors[] = 'Registration deadline format is invalid.';
    }

    $deadlineSql = null;
    if ($deadline !== '') {
        $deadlineSql = str_replace('T', ' ', $deadline) . ':00';
        if ($date !== '' && strtotime($deadlineSql) > strtotime($date . ' 23:59:59')) {
            $errors[] = 'Registration deadline must be before or on the event date.';
        }
    }

    return [
        'data' => [
            'title' => $title,
            'description' => $description,
            'sport' => $sport,
            'date' => $date,
            'time' => $time,
            'location' => $location,
            'registration_fee' => $registrationFee,
            'prize_pool' => $prizePool,
            'slots' => $slots,
            'requirements' => $requirements,
            'registration_deadline' => $deadlineSql,
            'organizer_note' => $organizerNote,
        ],
        'errors' => $errors,
    ];
}

function statusLabelClass(string $status): string
{
    $map = [
        'approved' => 'ok',
        'pending' => 'pending',
        'rejected' => 'bad',
        'waitlisted' => 'wait',
    ];

    return $map[$status] ?? 'pending';
}
