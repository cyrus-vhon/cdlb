<?php
/**
 * calendar_data.php
 * -----------------------------------------------------------
 * PHASE: Reservation System (AJAX data endpoint)
 *
 * JSON endpoint used by assets/js/calendar.js to render the
 * month-view calendar.
 *
 * WEEKLY SCHEDULE (default for every date):
 *   Mon-Fri: 6:00 PM - 9:00 PM
 *   Sat-Sun: 7:00 AM - 9:00 PM
 * A date is only NOT bookable if the admin explicitly CLOSED it
 * (court_closures table — e.g. bagyo). This is the opposite of
 * the old "no-class whitelist" — everything is open by default
 * now, and closures are the exception.
 *
 * GET params:
 *   month = "YYYY-MM"  (defaults to the current month)
 *   court = "Court 1" | "Court 2"  (defaults to Court 1)
 *
 * Response shape:
 * {
 *   "days": {
 *     "2026-08-30": { "status": "closed", "reason": "Bagyo" },
 *     "2026-08-31": { "status": "partial" },
 *     ...
 *   }
 * }
 *
 * status is one of: closed | available | partial | full
 * ("closed" = admin-announced court closure.
 *  past/today-vs-future is computed client-side.)
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/config/config.php';
requireLogin();

header('Content-Type: application/json');

// Kunin ang buwan na kailangan (galing sa calendar.js), o gamitin
// ang kasalukuyang buwan kung wala/mali ang format
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$courts = getCourtList();
$court  = $_GET['court'] ?? $courts[0];
if (!in_array($court, $courts, true)) {
    $court = $courts[0];
}

// Kunin ang unang araw at huling araw ng buwan (para sa SQL query)
$monthStart = $month . '-01';
$monthEnd   = date('Y-m-t', strtotime($monthStart));

// ---------------------------------------------------------------
// HAKBANG 1: Kunin lahat ng court closures sa buwan na ito.
// ---------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT closure_date, reason FROM court_closures WHERE closure_date BETWEEN ? AND ?"
);
$stmt->execute([$monthStart, $monthEnd]);
$closures = [];
foreach ($stmt->fetchAll() as $row) {
    $closures[$row['closure_date']] = $row['reason'];
}

// ---------------------------------------------------------------
// HAKBANG 2: Kunin lahat ng reservations (pending/approved) sa
// buwan na ito, sa court na ito, tapos i-group per date.
// ---------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT reservation_date, start_time, end_time, purpose AS sport
     FROM reservations
     WHERE status IN ('pending','approved')
       AND court = ?
       AND reservation_date BETWEEN ? AND ?"
);
$stmt->execute([$court, $monthStart, $monthEnd]);
$reservationsByDate = [];
foreach ($stmt->fetchAll() as $row) {
    $reservationsByDate[$row['reservation_date']][] = $row;
}

// ---------------------------------------------------------------
// HAKBANG 3: Para sa BAWAT petsa ng buwan, tingnan kung sarado ba
// (closure), at kung hindi, kunin ang status base sa bilang ng
// oras na "puno" (full capacity) laban sa kabuuang bukas na oras
// ng araw na 'yon (depende kung weekday o weekend).
// ---------------------------------------------------------------
$days = [];
$cursor = strtotime($monthStart);
$endTs  = strtotime($monthEnd);

while ($cursor <= $endTs) {
    $iso = date('Y-m-d', $cursor);

    if (isset($closures[$iso])) {
        $days[$iso] = ['status' => 'closed', 'reason' => $closures[$iso]];
        $cursor = strtotime('+1 day', $cursor);
        continue;
    }

    [$openHour, $closeHour] = getCourtScheduleHours($iso);
    $totalHours = $closeHour - $openHour;
    $dayBookings = $reservationsByDate[$iso] ?? [];

    $anyBooked = false;
    $fullHours = 0;

    for ($h = $openHour; $h < $closeHour; $h++) {
        $slotStart = sprintf('%02d:00:00', $h);
        $slotEnd   = sprintf('%02d:00:00', $h + 1);

        // Alin sa mga booking ng araw na ito ang nag-o-overlap sa
        // oras na ito?
        $overlapping = array_values(array_filter($dayBookings, function ($b) use ($slotStart, $slotEnd) {
            return $b['start_time'] < $slotEnd && $b['end_time'] > $slotStart;
        }));

        if (empty($overlapping)) {
            continue; // walang booking dito, hindi "full", hindi "booked"
        }
        $anyBooked = true;

        $hasExclusive = false;
        foreach ($overlapping as $b) {
            if (isExclusiveSport($b['sport'])) {
                $hasExclusive = true;
                break;
            }
        }
        $isFullHour = $hasExclusive || count($overlapping) >= sportMaxConcurrency('Badminton');
        if ($isFullHour) {
            $fullHours++;
        }
    }

    if (!$anyBooked) {
        $status = 'available';
    } elseif ($fullHours >= $totalHours) {
        $status = 'full';
    } else {
        $status = 'partial';
    }

    $days[$iso] = ['status' => $status];
    $cursor = strtotime('+1 day', $cursor);
}

echo json_encode(['days' => $days]);
