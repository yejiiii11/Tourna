-- ============================================================
--  Ball Sports Tournament App — MySQL Schema & Seed Data
-- ============================================================

-- 1. DATABASE
CREATE DATABASE IF NOT EXISTS ball_sports;
USE ball_sports;

-- ============================================================
-- 2. TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS sports (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name      VARCHAR(100) NOT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS organizers (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name      VARCHAR(150) NOT NULL,
  email     VARCHAR(200),
  phone     VARCHAR(30),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tournaments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(200)   NOT NULL,
  sport_id      INT UNSIGNED   NOT NULL,
  organizer_id  INT UNSIGNED   NOT NULL,
  location      VARCHAR(255)   NOT NULL,
  date          DATE           NOT NULL,
  start_time    TIME           NOT NULL,
  slots_total   SMALLINT UNSIGNED NOT NULL DEFAULT 16,
  slots_taken   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  prize         VARCHAR(50)    NOT NULL COMMENT 'e.g. ₱50,000',
  entry_fee     VARCHAR(100)   NOT NULL COMMENT 'e.g. ₱500 / team',
  format        VARCHAR(100)   NOT NULL COMMENT 'e.g. Single Elimination',
  description   TEXT,
  image_url     VARCHAR(500),
  is_active     TINYINT(1)     NOT NULL DEFAULT 1,
  created_at    DATETIME       DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_tournament_sport      FOREIGN KEY (sport_id)     REFERENCES sports(id),
  CONSTRAINT fk_tournament_organizer  FOREIGN KEY (organizer_id) REFERENCES organizers(id),
  CONSTRAINT chk_slots                CHECK (slots_taken <= slots_total)
);

CREATE TABLE IF NOT EXISTS registrations (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tournament_id   INT UNSIGNED NOT NULL,
  participant_name VARCHAR(150) NOT NULL,
  participant_email VARCHAR(200),
  registered_at   DATETIME DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_reg_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id)
);

-- ============================================================
-- 3. INDEXES
-- ============================================================

CREATE INDEX idx_tournament_sport     ON tournaments(sport_id);
CREATE INDEX idx_tournament_date      ON tournaments(date);
CREATE INDEX idx_tournament_active    ON tournaments(is_active);
CREATE INDEX idx_registration_tourn   ON registrations(tournament_id);

-- ============================================================
-- 4. SEED DATA
-- ============================================================

INSERT INTO sports (name) VALUES
  ('Basketball'),
  ('Football'),
  ('Volleyball'),
  ('Baseball'),
  ('Tennis');

INSERT INTO organizers (name, email, phone) VALUES
  ('Rico Santos',    'rico@ballsports.ph',  '09171234567'),
  ('Jessa Reyes',    'jessa@ballsports.ph', '09181234567'),
  ('Mark Dela Cruz', 'mark@ballsports.ph',  '09191234567'),
  ('Ben Villanueva', 'ben@ballsports.ph',   '09201234567'),
  ('Ana Lim',        'ana@ballsports.ph',   '09211234567'),
  ('DJ Tolentino',   'dj@ballsports.ph',    '09221234567');

INSERT INTO tournaments
  (name, sport_id, organizer_id, location, date, start_time, slots_total, slots_taken, prize, entry_fee, format, description, image_url)
VALUES
  (
    'City Basketball Cup', 1, 1,
    'Manila Sports Complex',
    '2025-03-15', '08:00:00', 16, 10,
    '₱50,000', '₱500 / team', 'Single Elimination',
    'The annual City Basketball Cup returns! Open to all amateur and semi-pro teams in the Metro Manila area. Expect fast-paced games, fierce competition, and a chance to bring home the championship trophy and prize money.',
    'https://images.unsplash.com/photo-1546519638405-a2700178fcd7?w=900&q=80'
  ),
  (
    'Angeles Football League', 2, 2,
    'Clark Football Field, Pampanga',
    '2025-03-22', '07:30:00', 12, 4,
    '₱30,000', '₱300 / player', 'Round Robin',
    'A full-season football league hosted at the world-class Clark Football Field. Teams from Central Luzon compete over 6 weeks of thrilling matches. Registration includes jerseys and player insurance.',
    'https://images.unsplash.com/photo-1489944440615-453fc2b6a9a9?w=900&q=80'
  ),
  (
    'Summer Volleyball Open', 3, 3,
    'SM Arena, Pampanga',
    '2025-04-05', '09:00:00', 8, 8,
    '₱20,000', '₱200 / player', 'Double Elimination',
    'The hottest volleyball event of the summer! Indoor courts available. All skill levels welcome. Food stalls and live music throughout the event weekend. Categories for men, women, and mixed.',
    'https://images.unsplash.com/photo-1612872087720-bb876e2e67d1?w=900&q=80'
  ),
  (
    'Metro Baseball Classic', 4, 4,
    'Rizal Memorial Baseball Stadium',
    '2025-04-12', '06:00:00', 10, 2,
    '₱40,000', '₱400 / team', 'Single Elimination',
    'One of the longest-running baseball classics in the Philippines. Held at the historic Rizal Memorial Stadium, this tournament brings together the best amateur baseball teams from across the country.',
    'https://images.unsplash.com/photo-1566577739112-5180d4bf9390?w=900&q=80'
  ),
  (
    'Regional Tennis Open', 5, 5,
    'Pampanga Sports Center',
    '2025-05-01', '08:30:00', 32, 18,
    '₱60,000', '₱600 / player', 'Bracket Draw',
    'Singles and doubles categories for men, women, and mixed. Professional courts with floodlights for evening matches. Seeded draws based on national ranking. Medals and trophies for top 3.',
    'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?w=900&q=80'
  ),
  (
    'Pampanga 3x3 Streetball', 1, 6,
    'Angeles City Barangay Court',
    '2025-05-10', '15:00:00', 20, 15,
    '₱15,000', '₱150 / team', '3x3 Bracket',
    'Street-style 3x3 basketball — raw, fast, and exciting. Open barangay courts, loud music, and the best streetball players in Angeles City. Show up and hoop!',
    'https://images.unsplash.com/photo-1503415786848-f1fbdc52c0b1?w=900&q=80'
  );

-- ============================================================
-- 5. USEFUL QUERIES
-- ============================================================

-- Get all active tournaments with sport & organizer info
-- (this is what your API endpoint should run)
SELECT
  t.id,
  t.name,
  s.name          AS sport,
  o.name          AS organizer,
  t.location,
  t.date,
  TIME_FORMAT(t.start_time, '%h:%i %p') AS time,
  t.slots_total,
  t.slots_taken,
  t.prize,
  t.entry_fee,
  t.format,
  t.description,
  t.image_url
FROM tournaments t
JOIN sports     s ON s.id = t.sport_id
JOIN organizers o ON o.id = t.organizer_id
WHERE t.is_active = 1
ORDER BY t.date ASC;

-- Get a single tournament by ID
SELECT
  t.id,
  t.name,
  s.name          AS sport,
  o.name          AS organizer,
  t.location,
  t.date,
  TIME_FORMAT(t.start_time, '%h:%i %p') AS time,
  t.slots_total,
  t.slots_taken,
  t.prize,
  t.entry_fee,
  t.format,
  t.description,
  t.image_url
FROM tournaments t
JOIN sports     s ON s.id = t.sport_id
JOIN organizers o ON o.id = t.organizer_id
WHERE t.id = ?   -- bind your tournament ID here
  AND t.is_active = 1;

-- Register a participant and increment slots_taken (run both in a transaction)
START TRANSACTION;

INSERT INTO registrations (tournament_id, participant_name, participant_email)
VALUES (?, ?, ?);   -- bind: tournament_id, name, email

UPDATE tournaments
SET slots_taken = slots_taken + 1
WHERE id = ? AND slots_taken < slots_total;  -- bind: tournament_id

COMMIT;