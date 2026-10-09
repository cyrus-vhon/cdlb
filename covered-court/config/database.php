<?php
/**
 * config/database.php
 * -----------------------------------------------------------
 * PHASE: Core Configuration (database connection)
 *
 * WHAT THIS FILE IS FOR:
 * Opens one PDO connection to MySQL that every page reuses via
 * the shared $pdo variable. Edit DB_USER / DB_PASS if your
 * XAMPP MySQL root user has a password (default XAMPP install:
 * username "root", no password). On live hosting (e.g.
 * InfinityFree), replace all four constants with the values
 * your host gives you.
 * -----------------------------------------------------------
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'covered_court_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            // Throw exceptions on error instead of failing silently
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return rows as associative arrays ($row['column'])
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use real prepared statements (safer against SQL injection)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Never show raw DB errors to end users.
    error_log('Database connection failed: ' . $e->getMessage());
    if (function_exists('coveredCourtFriendlyError')) {
        coveredCourtFriendlyError('We could not connect to the database. Please make sure MySQL is running and config/database.php is correct.');
    } else {
        http_response_code(500);
        die('Database connection failed. Please check that MySQL is running in XAMPP and that config/database.php has the right settings.');
    }
}
