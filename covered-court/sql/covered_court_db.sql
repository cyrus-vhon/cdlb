-- =====================================================
-- Covered Court Reservation System
-- Database: covered_court_db
--
-- WHAT THIS FILE IS FOR:
-- Creates the whole database from scratch. Import this once
-- in phpMyAdmin (Import tab) to set up all 3 tables the system
-- needs. See the MIGRATION NOTES at the bottom if you already
-- have an older version of this database imported.
-- =====================================================

CREATE DATABASE IF NOT EXISTS covered_court_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE covered_court_db;

-- ---------------------------------------------------
-- Table: users
-- PHASE: Accounts & Authentication
-- Holds both regular users (students/faculty) and admins.
-- Distinguished by the "role" column, not a separate table.
-- ---------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,          -- always a password_hash(), never plain text
    role ENUM('user','admin') NOT NULL DEFAULT 'user',
    status ENUM('active','blocked') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: reservations
-- PHASE: Reservation System (core of the whole app)
-- One row = one booking request, from submission all the
-- way through approval/rejection/completion. The "court"
-- column lets Court 1 and Court 2 keep independent schedules
-- — a booking on one never blocks the other.
--
-- "purpose" now holds a CONTROLLED sport value (not free text),
-- chosen from a dropdown that depends on the court:
--   Court 1: Basketball, Volleyball
--   Court 2: Volleyball, Badminton, Pickleball
-- Basketball and Volleyball are "exclusive" sports (need the
-- whole court). Badminton and Pickleball are "shareable" — up
-- to 2 of them (any combination of the two) can be booked in
-- the same hour on Court 2, since each only needs a half-court.
-- See includes/functions.php (getSportsForCourt, canBookSlot)
-- for the actual conflict rules.
-- ---------------------------------------------------
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_code VARCHAR(30) NOT NULL UNIQUE,   -- e.g. CCR-2026-A82F91, assigned at submission time
    user_id INT NOT NULL,
    court ENUM('Court 1','Court 2') NOT NULL DEFAULT 'Court 1',
    reservation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    purpose ENUM('Basketball','Volleyball','Badminton','Pickleball') NOT NULL,
    participants INT NOT NULL,
    notes VARCHAR(255) NULL,
    status ENUM('pending','approved','rejected','cancelled','completed') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservations_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_date (reservation_date),
    INDEX idx_status (status),
    INDEX idx_court (court)
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: court_closures
-- PHASE: Court Availability Management (Admin)
--
-- THIS IS A BLACKLIST, NOT A WHITELIST.
-- The court now follows a fixed WEEKLY schedule by default
-- (see WEEKDAY_OPEN_HOUR / WEEKEND_OPEN_HOUR in config.php):
--   Mon-Fri: 6:00 PM - 9:00 PM (after classes)
--   Sat-Sun: 7:00 AM - 9:00 PM (no classes on weekends)
-- Every date is OPEN for reservation per that schedule UNLESS
-- it appears in this table — i.e. a day the admin has
-- explicitly CLOSED the court (e.g. "Bagyo", typhoon signal,
-- class suspension) for the students' safety. Admin can add a
-- closure same-day, even last minute. Adding a closure
-- automatically cancels any reservations already made for
-- that date — see admin/court_closures.php.
-- ---------------------------------------------------
CREATE TABLE court_closures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    closure_date DATE NOT NULL UNIQUE,
    reason VARCHAR(255) NULL,               -- e.g. "Bagyo", "Signal No. 2"
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- No admin seed row here on purpose.
-- After importing this file, open create_admin.php in your browser
-- ONCE to safely generate your first admin account (it creates a
-- correct password_hash() for your PHP build), then delete that file.
-- ---------------------------------------------------

-- =====================================================
-- MIGRATION NOTES (only needed if upgrading an existing,
-- already-imported database — skip these if this is a
-- brand new install, the CREATE TABLE statements above
-- already include everything).
-- =====================================================

-- If your existing "reservations" table has no "court" column yet:
--
-- ALTER TABLE reservations
--   ADD COLUMN court ENUM('Court 1','Court 2') NOT NULL DEFAULT 'Court 1' AFTER user_id,
--   ADD INDEX idx_court (court);

-- If your existing database still has "blocked_dates" (the old
-- blacklist system) and/or QR-related columns/tables, switch to
-- the new whitelist system and drop the QR feature entirely:
--
-- CREATE TABLE IF NOT EXISTS no_class_dates (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     class_date DATE NOT NULL UNIQUE,
--     reason VARCHAR(255) NULL,
--     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
-- ) ENGINE=InnoDB;
--
-- DROP TABLE IF EXISTS blocked_dates;
-- DROP TABLE IF EXISTS verification_logs;
-- ALTER TABLE reservations DROP COLUMN qr_code;

-- If your existing database still has "no_class_dates" (the
-- whitelist system) and/or a free-text "purpose" column, switch
-- to the weekly-schedule + court_closures + fixed-sport design:
--
-- CREATE TABLE IF NOT EXISTS court_closures (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     closure_date DATE NOT NULL UNIQUE,
--     reason VARCHAR(255) NULL,
--     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
-- ) ENGINE=InnoDB;
--
-- -- Carry over old whitelist dates as reference/history if you want,
-- -- otherwise just drop it — the weekly schedule replaces it:
-- DROP TABLE IF EXISTS no_class_dates;
--
-- -- purpose used to be free text (e.g. "Basketball Practice"); it's
-- -- now a fixed sport value chosen from a dropdown. Any existing rows
-- -- with a free-text purpose that isn't exactly one of the 4 sport
-- -- names below must be manually corrected BEFORE running this ALTER,
-- -- or the ALTER will fail:
-- ALTER TABLE reservations
--   MODIFY COLUMN purpose ENUM('Basketball','Volleyball','Badminton','Pickleball') NOT NULL;
