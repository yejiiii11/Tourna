-- Run these statements in your MySQL server (e.g. via phpMyAdmin) to prepare the database.

CREATE DATABASE IF NOT EXISTS user_system;
USE user_system;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','organizer','athlete') NOT NULL DEFAULT 'athlete',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    sport VARCHAR(100),
    event_date DATE,
    event_time TIME NULL,
    registration_deadline DATETIME NULL,
    location VARCHAR(255),
    registration_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    prize_pool DECIMAL(12,2) NOT NULL DEFAULT 0,
    slots INT NOT NULL DEFAULT 0,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    requirements TEXT,
    organizer_note TEXT NULL,
    views_count INT NOT NULL DEFAULT 0,
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tournament_registrations (
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
);

INSERT IGNORE INTO users (username, email, password, role) VALUES
('admin','admin@example.com', '', 'admin');
