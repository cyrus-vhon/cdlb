<?php
/**
 * slots_data.php
 * -----------------------------------------------------------
 * PHASE: Reservation System (AJAX data endpoint)
 *
 * JSON endpoint used by assets/js/calendar.js. Once a person
 * clicks a day on the month calendar, this returns the hourly
 * available/reserved breakdown for that single day.
 *
 * The court now follows a fixed WEEKLY schedule (see
 * config.php): Mon-Fri 6PM-9PM, Sat-Sun 7AM-9PM. Each hour slot
 * can hold either ONE exclusive-sport booking (Basketball or
 * Volleyball, full-court) OR UP TO TWO shareable-sport bookings
 * (Badminton/Pickleball, half-court each, any combination).
 *
 * GET params:
 *   date  = "YYYY-MM-DD"  (required)
 *   court = "Court 1" | "Court 2"  (defaults to Court 1)
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/config/config.php';
requireLogin();

header('Content-Type: application/json');

// Kailangan ng valid na petsa (YYYY-MM-DD format), kung wala o mali
// ang format, agad na ihihinto ang proseso at magpapadala ng error
$date = $_GET['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date.']);
    exit;
}

$courts = getCourtList();
$court  = $_GET['court'] ?? $courts[0];
if (!in_array($court, $courts, true)) {
    $court = $courts[0];
}

// HAKBANG 1: Tingnan muna kung SARADO (court closure) ang petsang
// ito. Kung sarado, hindi na natin kailangan pang tingnan ang
// oras-per-oras na schedule — sasabihin lang natin sa frontend
// na "closed" ito.
$isClosed = isDateClosed($pdo, $date);
$closureReason = $isClosed ? getClosureReason($pdo, $date) : null;
$isOpen = !$isClosed;

[$openHour, $closeHour] = getCourtScheduleHours($date);

// HAKBANG 2: Gumawa ng listahan ng bawat oras-per-oras na "slot"
// mula sa open hour papunta sa close hour ng ARAW NA ITO (depende
// kung weekday o weekend), at tingnan kung anong mga sport ang
// naka-book na dito, puno na ba, at anong mga sport pa ang pwede.
$slots = [];
if ($isOpen) {
    $isToday = ($date === date('Y-m-d'));
    $nowTime = date('H:i:s');

    for ($h = $openHour; $h < $closeHour; $h++) {
        $slotStart = sprintf('%02d:00:00', $h);
        $slotEnd   = sprintf('%02d:00:00', $h + 1);

        // Kung petsa ngayon (today) ang pinipili, at lumipas na ang
        // oras na ito (natapos na ang slot bago pa man ngayon),
        // hindi na dapat ito ma-book kahit pa "available" ang sport
        // capacity — lumipas na talaga ang oras. Ito ang sagot sa
        // "bakit ko pa nabook yung 7-8PM kung tapos na 'yon".
        $isPast = $isToday && $slotEnd <= $nowTime;

        if ($isPast) {
            $state = ['bookings' => [], 'full' => true, 'available_sports' => []];
        } else {
            $state = getSlotState($pdo, $court, $date, $slotStart, $slotEnd);
        }

        $bookingsOut = [];
        foreach ($state['bookings'] as $b) {
            $entry = [
                'sport'  => $b['sport'],
                'status' => $b['status'], // "pending" o "approved"
            ];
            // PRIVACY: Ipinapakita lang ang pangalan ng nag-book
            // KUNG admin ang tumitingin. Regular user lang, sport
            // at status na lang ang makikita nila.
            if (isAdmin()) {
                $stmt = $pdo->prepare(
                    "SELECT u.full_name FROM reservations r
                     JOIN users u ON u.id = r.user_id WHERE r.id = ?"
                );
                $stmt->execute([$b['id']]);
                $extra = $stmt->fetch();
                if ($extra) {
                    $entry['reserved_by'] = $extra['full_name'];
                }
            }
            $bookingsOut[] = $entry;
        }

        $slots[] = [
            'start'            => $slotStart,
            'end'              => $slotEnd,
            'start_label'      => formatTime($slotStart),
            'end_label'        => formatTime($slotEnd),
            'bookings'         => $bookingsOut,
            'full'             => $state['full'],
            'available_sports' => $state['available_sports'],
            'past'             => $isPast,
        ];
    }
}

echo json_encode([
    'date'            => $date,
    'date_label'      => formatDate($date), // hal. "August 30, 2026"
    'court'           => $court,
    'is_open'         => $isOpen,
    'closure_reason'  => $closureReason,
    'open_hour'       => $openHour,
    'close_hour'      => $closeHour,
    'is_weekend'      => isWeekendDate($date),
    'sports'          => getSportsForCourt($court),
    'slots'           => $slots,
]);
