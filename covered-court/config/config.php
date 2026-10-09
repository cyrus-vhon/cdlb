<?php
/**
 * config/config.php
 * -----------------------------------------------------------
 * PHASE: Core Configuration (loaded by every single page)
 *
 * WHAT THIS FILE IS FOR:
 * Site-wide settings that every other file depends on: the
 * live URL (BASE_URL), timezone, court operating hours, the
 * session, the global error handler, and it pulls in
 * database.php and functions.php so any page that requires
 * this one file gets everything it needs in one line.
 * -----------------------------------------------------------
 */

// IMPORTANT: must end with a trailing slash "/", and must match
// EXACTLY the address people actually type/click to reach the site
// (including https:// vs http://) — otherwise CSS/JS/images and
// every generated link on the site will point to the wrong place.
$requestHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
$isLocalRequest = (bool) preg_match('/^(localhost|127(?:\.\d{1,3}){3}|\[::1\])(?::\d+)?$/', $requestHost);
define('BASE_URL', $isLocalRequest ? '/covered-court/' : 'http://localhost/covered-court/');

date_default_timezone_set('Asia/Manila');

// -----------------------------------------------------------
// COURT OPERATING HOURS — weekly schedule (regular school days)
// -----------------------------------------------------------
// Mon-Fri: court only opens after classes, 6:00 PM - 9:00 PM.
// Sat-Sun: no classes, so the court is open the whole day,
// 7:00 AM - 9:00 PM.
// This is the DEFAULT schedule for every date. Admins can still
// fully CLOSE a specific date (e.g. bagyo) via the
// court_closures table — see admin/court_closures.php and
// includes/functions.php (getCourtScheduleHours, isDateClosed).
// -----------------------------------------------------------
define('WEEKDAY_OPEN_HOUR', 18);  // 6:00 PM
define('WEEKDAY_CLOSE_HOUR', 21); // 9:00 PM
define('WEEKEND_OPEN_HOUR', 7);   // 7:00 AM
define('WEEKEND_CLOSE_HOUR', 21); // 9:00 PM

// Always start the session before anything else uses it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -----------------------------------------------------------
// GLOBAL SAFETY NET
// Any error/exception that isn't caught closer to where it
// happened (e.g. an unexpected database problem) lands here
// instead of showing raw PHP text to the user.
// The real technical details are written to PHP's error log
// (visible in xampp/php/logs or your Apache error log) so you
// can still debug it, but the visitor just sees a plain,
// friendly page.
// -----------------------------------------------------------
ini_set('display_errors', '0'); // never print raw PHP errors/warnings to the page
error_reporting(E_ALL);         // still log everything

function coveredCourtFriendlyError($message = 'Something went wrong. Please try again.') {
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">'
       . '<title>Something went wrong</title>'
       . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>'
       . '<body class="bg-light"><div class="container py-5" style="max-width:480px;">'
       . '<div class="card p-4 text-center"><h4 class="mb-2">Something went wrong</h4>'
       . '<p class="text-muted mb-3">' . htmlspecialchars($message) . '</p>'
       . '<a href="' . BASE_URL . '" class="btn btn-primary">Return Home</a>'
       . '</div></div></body></html>';
    exit;
}

set_exception_handler(function ($e) {
    error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    coveredCourtFriendlyError('An unexpected error occurred. If this keeps happening, please tell the administrator.');
});

set_error_handler(function ($severity, $message, $file, $line) {
    // Only intercept serious errors; let PHP's normal (non-fatal) notices pass through the log only.
    error_log("PHP error [$severity]: $message in $file:$line");
    if (in_array($severity, [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
        coveredCourtFriendlyError('An unexpected error occurred. If this keeps happening, please tell the administrator.');
    }
    return true; // suppress default PHP handler
});

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';

// "Lazy" auto-expiration: on every page load, cancel any pending
// reservation whose scheduled time has already passed without
// admin approval. See expireStalePendingReservations() in
// includes/functions.php for the full explanation.
expireStalePendingReservations($pdo);
