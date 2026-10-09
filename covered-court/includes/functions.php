<?php
/**
 * includes/functions.php
 * -----------------------------------------------------------
 * PHASE: Core Configuration (mga reusable na function)
 *
 * PARA SAAN ITO:
 * Dito nakatago yung mga maliliit na function na ginagamit
 * paulit-ulit sa buong system — pag-check kung naka-login,
 * paglilinis ng input, yung double-booking check, yung
 * "walang pasok" whitelist check, at iba pang formatting.
 * Once lang ito ni-require ng config.php, kaya kahit saang
 * file basta na-require yung config.php, automatic na
 * available na lahat ng functions dito.
 * -----------------------------------------------------------
 */

/**
 * Nililinis ang text na galing sa user bago ito ipakita sa page.
 * Ginagamit ito PARATI bago i-print ang kahit anong input ng user
 * (pangalan, purpose, notes, atbp.) — para maiwasan ang XSS attack
 * (kung saan may maglalagay ng <script> code sa isang text field).
 */
function clean($value) {
    return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** True kung naka-login ang user (may laman ang session variable). */
function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

/** True kung admin ang naka-login (base sa "role" na naka-save sa session). */
function isAdmin() {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * PROTEKSYON: Ginagamit ito sa itaas ng mga pahina na dapat
 * naka-login lang ang makapag-access (hal. dashboard.php, reserve.php).
 * Kung hindi naka-login, agad ibabalik sa login.php.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

/**
 * PROTEKSYON: Ginagamit sa lahat ng admin/*.php pages. Dalawang
 * check ang ginagawa: (1) naka-login ba, at (2) admin ba talaga
 * yung naka-login. Kung hindi admin, ibabalik sa normal na
 * user dashboard (hindi login.php, dahil naka-login naman siya,
 * "user" lang siya hindi "admin").
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}

/**
 * Gumagawa ng unique na reservation code, hal. CCR-2026-A82F91.
 * Paano ito gumagana: gumagawa muna ng random na code, tapos
 * chine-check sa database kung meron nang parehong code. Kung
 * meron na pala (bihira mangyari, pero posible), uulitin lang
 * ang buong proseso hanggang sa makahanap ng talagang unique.
 */
function generateReservationCode(PDO $pdo) {
    do {
        $year   = date('Y');
        $random = strtoupper(bin2hex(random_bytes(3))); // 6 na random na letra/numero
        $code   = "CCR-{$year}-{$random}";

        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE reservation_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch()); // ulitin kung meron nang gumagamit ng code na 'to

    return $code;
}

/**
 * Listahan ng mga court na pwedeng i-reserve. Kung magkakaroon
 * pa ng ikatlong court balang araw, dito lang idadagdag.
 */
function getCourtList() {
    return ['Court 1', 'Court 2'];
}

/**
 * ================================================================
 * WEEKLY SCHEDULE — anong oras bukas ang court sa petsang ito
 * ================================================================
 * Mon-Fri: 6:00 PM - 9:00 PM lang (pagkatapos ng klase).
 * Sat-Sun: 7:00 AM - 9:00 PM (buong araw, dahil walang pasok).
 * Ibinabalik ang [openHour, closeHour] bilang integer (24-hour).
 * Ito ang DEFAULT schedule — hiwalay dito ang tanong kung
 * CLOSED ba ang buong petsa (see isDateClosed()).
 */
function getCourtScheduleHours($date) {
    $dow = (int) date('N', strtotime($date)); // 1=Mon ... 7=Sun
    if ($dow >= 6) { // Saturday o Sunday
        return [WEEKEND_OPEN_HOUR, WEEKEND_CLOSE_HOUR];
    }
    return [WEEKDAY_OPEN_HOUR, WEEKDAY_CLOSE_HOUR];
}

/** True kung "Sat" o "Sun" ang petsang ito. */
function isWeekendDate($date) {
    return ((int) date('N', strtotime($date))) >= 6;
}

/**
 * Listahan ng mga sport na pwedeng piliin bilang "Purpose" depende
 * sa court. Ginagamit ito sa dropdown ng reserve.php AT sa
 * server-side validation (huwag basta tumanggap ng kahit anong
 * text — dapat kasama sa listahang ito para sa court na pinili).
 */
function getSportsForCourt($court) {
    if ($court === 'Court 1') {
        return ['Basketball', 'Volleyball'];
    }
    return ['Volleyball', 'Badminton', 'Pickleball']; // Court 2
}

/** True kung valid na combination ang court + sport na 'to. */
function isValidSportForCourt($court, $sport) {
    return in_array($sport, getSportsForCourt($court), true);
}

/**
 * "EXCLUSIVE" na sport (Basketball, Volleyball) = kailangan ang
 * BUONG court, kaya isa lang ang pwedeng naka-book sa oras na 'yon,
 * kahit anong sport pa ang gustong i-book ng iba.
 *
 * "SHAREABLE" na sport (Badminton, Pickleball) = half-court lang
 * ang kailangan, kaya hanggang 2 (kahit anong kombinasyon ng
 * dalawang ito) ang pwedeng magkasabay sa parehong oras.
 */
function sportMaxConcurrency($sport) {
    return in_array($sport, ['Badminton', 'Pickleball'], true) ? 2 : 1;
}

function isExclusiveSport($sport) {
    return sportMaxConcurrency($sport) === 1;
}

/**
 * ================================================================
 * PINAKA-IMPORTANTENG FUNCTION: kunin ang "state" ng isang oras
 * (start-end) sa isang court+petsa — anong mga sport ang naka-book
 * na, puno na ba, at anong mga sport pa ang pwedeng i-book dito.
 * ================================================================
 * Ito ang tunay na "double-booking prevention" ngayon — pinapalitan
 * nito ang lumang isSlotTaken(), dahil hindi na simpleng "taken o
 * hindi" ang tanong (lalo na sa Court 2 kung saan pwedeng magkasabay
 * ang 2 shareable na sport).
 *
 * Panuntunan:
 *   - Kung may EXCLUSIVE na sport (Basketball/Volleyball) na naka-book
 *     na dito -> PUNO na ang buong oras, wala nang ibang pwedeng
 *     i-dagdag na sport (exclusive man o shareable).
 *   - Kung wala pang naka-book -> lahat ng valid na sport sa court
 *     na 'yon ay pwede pa.
 *   - Kung may 1 shareable na sport na (Badminton o Pickleball) naka-
 *     book na, at wala pang exclusive -> pwede pa ang IKALAWANG
 *     shareable booking (Badminton man o Pickleball), pero HINDI na
 *     pwede ang exclusive sport (kasya lang half-court na natitira).
 *   - Kung 2 na shareable ang naka-book -> puno na rin, wala nang
 *     ibang pwede.
 *
 * Ibinabalik: ['bookings' => [...], 'full' => bool, 'available_sports' => [...]]
 */
function getSlotState(PDO $pdo, $court, $date, $startTime, $endTime, $ignoreId = null) {
    $sql = "SELECT id, purpose AS sport, status FROM reservations
            WHERE reservation_date = ?
              AND court = ?
              AND status IN ('pending','approved')
              AND start_time < ?
              AND end_time > ?";
    $params = [$date, $court, $endTime, $startTime];

    if ($ignoreId !== null) {
        $sql .= " AND id != ?";
        $params[] = $ignoreId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();

    $allSports = getSportsForCourt($court);

    // May exclusive na sport na ba dito (Basketball o Volleyball)?
    foreach ($bookings as $b) {
        if (isExclusiveSport($b['sport'])) {
            return ['bookings' => $bookings, 'full' => true, 'available_sports' => []];
        }
    }

    // Wala pang naka-book -> lahat ng sport ng court na 'to ay pwede pa
    if (empty($bookings)) {
        return ['bookings' => $bookings, 'full' => false, 'available_sports' => $allSports];
    }

    // Lahat ng naka-book dito ay shareable (Badminton/Pickleball lang,
    // dahil na-check na natin sa itaas na walang exclusive)
    $shareableCount = count($bookings);
    $shareableCap = sportMaxConcurrency('Badminton'); // parehong 2 ang cap ng Badminton/Pickleball

    if ($shareableCount >= $shareableCap) {
        return ['bookings' => $bookings, 'full' => true, 'available_sports' => []];
    }

    // 1 pa lang ang shareable na naka-book -> pwede pa ang IKALAWANG
    // shareable (Badminton man o Pickleball), pero hindi na ang exclusive
    $stillAvailable = array_values(array_filter($allSports, function ($s) {
        return !isExclusiveSport($s);
    }));

    return ['bookings' => $bookings, 'full' => false, 'available_sports' => $stillAvailable];
}

/**
 * Simpleng true/false na tanong: pwede bang i-book ang sport na
 * ito, sa court/petsa/oras na ito? Ginagamit ito sa reserve.php
 * (bago i-save) at sa admin/reservation_view.php (bago i-approve —
 * muling che-check kung nag-iba ang schedule sa pagitan ng
 * pag-submit at ngayon).
 *
 * $ignoreId — ginagamit kapag ang sarili mismong reservation ay
 * hindi dapat "mag-collide sa sarili niya" sa re-check bago
 * i-approve.
 */
function canBookSlot(PDO $pdo, $court, $date, $startTime, $endTime, $sport, $ignoreId = null) {
    $state = getSlotState($pdo, $court, $date, $startTime, $endTime, $ignoreId);
    return in_array($sport, $state['available_sports'], true);
}

/**
 * ================================================================
 * COURT CLOSURE CHECK (bagyo, class suspension, atbp.)
 * ================================================================
 * Bukas ang court sa BAWAT petsa base sa weekly schedule
 * (getCourtScheduleHours) BY DEFAULT. Ang function na ito ang
 * nagsasabi kung mayroong EXPLICIT na closure ang admin para sa
 * petsang ito (hal. dahil sa bagyo) — kung meron, SARADO ang
 * buong court sa araw na 'yon, kahit Mon-Fri man o weekend.
 */
function isDateClosed(PDO $pdo, $date) {
    $stmt = $pdo->prepare("SELECT id FROM court_closures WHERE closure_date = ?");
    $stmt->execute([$date]);
    return (bool) $stmt->fetch();
}

/**
 * Kinukuha ang "reason" (dahilan) ng isang court closure, kung
 * meron. Halimbawa: "Bagyo", "Signal No. 2". Ginagamit ito para
 * ipakita sa calendar kung bakit sarado ang court sa araw na 'yon.
 */
function getClosureReason(PDO $pdo, $date) {
    $stmt = $pdo->prepare("SELECT reason FROM court_closures WHERE closure_date = ?");
    $stmt->execute([$date]);
    $row = $stmt->fetch();
    return $row['reason'] ?? null;
}

/**
 * ================================================================
 * AUTO-CANCEL: pending reservation na hindi na-approve BEFORE
 * lumipas ang oras nito
 * ================================================================
 * Senaryo: nag-book ang estudyante ng Sept 9, 7:00 PM - 8:00 PM.
 * Kung hindi ito na-approve ng admin bago pa man dumating ang
 * 8:00 PM ng Sept 9, wala nang saysay ang request na 'yon —
 * lumipas na ang oras, kaya dapat awtomatikong mag-cancel ito sa
 * halip na manatiling "pending" magpakailanman.
 *
 * "Approved" na reservations ay HINDI kasama dito — pagdating ng
 * oras, tungkulin ng admin na markahan itong "Completed" (sa
 * admin/reservation_view.php), hindi ito basta na-lang
 * awtomatikong nagbabago.
 *
 * WALANG CRON JOB ANG SIMPLENG APP NA ITO, kaya sa halip na
 * umasa sa isang scheduled task na tumatakbo sa background,
 * tinatawag natin ang function na ito sa simula pa lang ng
 * BAWAT page load (config.php) — "lazy expiration". Napaka-mura
 * lang ng query nito (isang UPDATE base sa index), kaya walang
 * mararamdamang epekto sa bilis ng site.
 */
function expireStalePendingReservations(PDO $pdo) {
    $today = date('Y-m-d');
    $now   = date('H:i:s');

    $stmt = $pdo->prepare(
        "UPDATE reservations
         SET status = 'cancelled',
             notes = CONCAT(COALESCE(notes, ''), CASE WHEN notes IS NULL OR notes = '' THEN '' ELSE ' ' END, '(Auto-cancelled: not approved before the reservation time passed.)')
         WHERE status = 'pending'
           AND (reservation_date < ? OR (reservation_date = ? AND end_time <= ?))"
    );
    $stmt->execute([$today, $today, $now]);
    return $stmt->rowCount();
}

/**
 * ================================================================
 * AUTO-CANCEL: kapag na-anunsyo ang isang court closure
 * ================================================================
 * Senaryo: may mga estudyanteng nag-reserve na sa isang petsa,
 * pero bigla na-anunsyo ng admin na sarado ang court sa araw na
 * 'yon (hal. bagyo) — para sa safety ng mga estudyante, bawal na
 * silang pumunta sa school. Kapag nag-add ang admin ng entry sa
 * court_closures (sa admin/court_closures.php), tatawagin ang
 * function na ito para AWTOMATIKONG I-CANCEL lahat ng existing
 * reservations sa araw na 'yon.
 *
 * Idinadagdag din natin ang isang note sa bawat cancelled
 * reservation, para malaman ng user kung bakit bigla na-cancel
 * ang booking nila.
 *
 * Ibinabalik ang bilang ng reservations na na-cancel, para
 * maipakita sa admin ang confirmation message (hal. "3
 * reservation(s) ang na-auto-cancel").
 */
function autoCancelReservationsForDate(PDO $pdo, $date) {
    $stmt = $pdo->prepare(
        "UPDATE reservations
         SET status = 'cancelled',
             notes = CONCAT(COALESCE(notes, ''), CASE WHEN notes IS NULL OR notes = '' THEN '' ELSE ' ' END, '(Auto-cancelled: court closed on this date.)')
         WHERE reservation_date = ? AND status IN ('pending','approved')"
    );
    $stmt->execute([$date]);
    return $stmt->rowCount(); // ilang reservations ang na-apektuhan
}

/** Ginagawang "8:00 AM" style ang oras na galing sa database ("08:00:00"). */
function formatTime($time24) {
    return date('g:i A', strtotime($time24));
}

/** Ginagawang "August 30, 2026" style ang petsa galing sa database ("2026-08-30"). */
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

/**
 * Ibinabalik ang tamang Bootstrap badge color base sa status ng
 * reservation — dilaw (pending), berde (approved), pula (rejected),
 * kulay-abo (cancelled), asul (completed).
 */
function statusBadgeClass($status) {
    switch ($status) {
        case 'pending':   return 'bg-warning text-dark';
        case 'approved':  return 'bg-success';
        case 'rejected':  return 'bg-danger';
        case 'cancelled': return 'bg-secondary';
        case 'completed': return 'bg-primary';
        default:          return 'bg-secondary';
    }
}

/** Kinukuha ang IP address ng gumagamit (para sa mga logs/records). */
function getClientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Kinukuha ang unang letra ng pangalan ng user, para gamitin
 * bilang "avatar" na bilog na may letra (hal. "Juan Dela Cruz"
 * -> "J"). Ginagamit ito sa sidebar at sa listahan ng users.
 */
function initials($name) {
    $name = trim($name ?? '');
    return $name === '' ? '?' : strtoupper(substr($name, 0, 1));
}
