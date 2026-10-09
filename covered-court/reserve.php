<?php
/**
 * reserve.php
 * -----------------------------------------------------------
 * PHASE: Reservation System (core of the whole app)
 *
 * WHAT THIS FILE IS FOR:
 * The booking form itself. Shows a month calendar (color-coded
 * by availability, per court) and lets the user pick a court,
 * date, time, and sport (Purpose). On submit, this file runs
 * ALL the important server-side checks before saving anything:
 * valid date, that the date isn't closed (court_closures), that
 * the time falls within that day's weekly schedule (Mon-Fri
 * 6-9PM, Sat-Sun 7AM-9PM), that the sport is valid for the
 * chosen court, and — most importantly — the sport-aware
 * conflict check (canBookSlot) which allows up to 2 shareable
 * Badminton/Pickleball bookings to share Court 2 at once. None
 * of these checks are optional or skippable from the browser;
 * they all run in PHP, not just JavaScript.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/config/config.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . 'admin/dashboard.php'); exit; }

$courts = getCourtList();
$errors = [];

// Nilalagay muna dito yung mga value ng form (galing sa POST kung
// nag-submit na, o galing sa GET kung galing lang sa calendar click)
// para hindi mawala yung na-type na ng user kapag may error later.
$court             = $_POST['court'] ?? $_GET['court'] ?? $courts[0];
$reservation_date  = $_POST['reservation_date'] ?? $_GET['date'] ?? '';
$start_time        = $_POST['start_time'] ?? '';
$end_time          = $_POST['end_time'] ?? '';
$purpose           = $_POST['purpose'] ?? '';
$participants      = $_POST['participants'] ?? '';
$notes             = $_POST['notes'] ?? '';

if (!in_array($court, $courts, true)) {
    $court = $courts[0]; // fallback sa "Court 1" kung hindi valid ang laman
}

// ---------------------------------------------------------------
// ITO YUNG BUONG PROSESO KAPAG NAG-SUBMIT NA ANG FORM (POST request)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Muling kunin ang mga value, pero ngayon galing na talaga sa
    // sinubmit na form (hindi na sa GET/calendar click)
    $court            = trim($_POST['court'] ?? '');
    $reservation_date = trim($_POST['reservation_date'] ?? '');
    $start_time       = trim($_POST['start_time'] ?? '');
    $end_time         = trim($_POST['end_time'] ?? '');
    $purpose          = trim($_POST['purpose'] ?? '');
    $participants     = trim($_POST['participants'] ?? '');
    $notes            = trim($_POST['notes'] ?? '');

    // Ang <input type="time"> ay nagpapadala ng "HH:MM" (walang
    // seconds). Idinaragdag natin ang ":00" dito mismo, bago pa man
    // gamitin sa kahit anong paghahambing, para consistent lagi ang
    // format (kasama na rin sa pag-INSERT sa database mismo).
    if (preg_match('/^\d{2}:\d{2}$/', $start_time)) {
        $start_time .= ':00';
    }
    if (preg_match('/^\d{2}:\d{2}$/', $end_time)) {
        $end_time .= ':00';
    }

    // ---- HAKBANG 1: Basic na validation (mga simpleng pagsusuri) ----
    $today = date('Y-m-d');

    if (!in_array($court, $courts, true)) {
        $errors[] = 'Please select a valid court.';
    }

    // Dapat tamang format ang petsa (YYYY-MM-DD) at hindi lumipas na
    if ($reservation_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reservation_date)) {
        $errors[] = 'Please select a valid date.';
    } elseif ($reservation_date < $today) {
        $errors[] = 'Reservation date cannot be in the past.';
    }

    // Dapat mas maaga ang start_time kumpara sa end_time
    if ($start_time === '' || $end_time === '') {
        $errors[] = 'Please select both a start time and an end time.';
    } elseif ($start_time >= $end_time) {
        $errors[] = 'Start time must be before end time.';
    } elseif ($reservation_date === $today && $end_time <= date('H:i:s')) {
        // Kahit ma-bypass pa ng user ang JavaScript/UI, hindi pa rin
        // dapat pumasa ang pag-book ng oras na LUMIPAS NA sa
        // kasalukuyang araw (hal. 7-8PM habang 8:15PM na ngayon).
        $errors[] = 'That time has already passed today. Please choose a time that has not started yet.';
    }

    // Dapat isa sa mga valid na sport ng court na ito ang napili
    if ($purpose === '' || !in_array($court, $courts, true) || !isValidSportForCourt($court, $purpose)) {
        $errors[] = 'Please select a valid purpose (sport) for the chosen court.';
    }

    // Dapat numero at hindi zero o negative ang bilang ng participants
    if ($participants === '' || !ctype_digit((string)$participants) || (int)$participants < 1) {
        $errors[] = 'Number of participants must be a positive number.';
    }

    // ---- HAKBANG 2: Court Closure check (bagyo, class suspension) ----
    // Kung na-anunsyo ng admin na SARADO ang court sa petsang ito
    // (court_closures table), hindi na natin kailangang pang tingnan
    // ang oras — bawal talaga mag-reserve sa araw na 'yon.
    if (empty($errors) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $reservation_date) && isDateClosed($pdo, $reservation_date)) {
        $closureReason = getClosureReason($pdo, $reservation_date);
        $errors[] = 'The court is closed on this date' . ($closureReason ? ' (' . $closureReason . ')' : '') . '. Please choose another date.';
    }

    // ---- HAKBANG 3: Dapat nasa loob ng weekly schedule ang oras ----
    // Mon-Fri 6:00 PM - 9:00 PM, Sat-Sun 7:00 AM - 9:00 PM. Tinitignan
    // lang ito kung wala pang error, at hindi sarado ang petsa.
    if (empty($errors) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $reservation_date)) {
        [$openHour, $closeHour] = getCourtScheduleHours($reservation_date);
        $openTime  = sprintf('%02d:00:00', $openHour);
        $closeTime = sprintf('%02d:00:00', $closeHour);
        if ($start_time < $openTime || $end_time > $closeTime) {
            $dayType = isWeekendDate($reservation_date) ? 'weekends (7:00 AM - 9:00 PM)' : 'weekdays (6:00 PM - 9:00 PM)';
            $errors[] = 'That time is outside the reservation hours for this day. The court is only bookable on ' . $dayType . ' for this date.';
        }
    }

    // ---- HAKBANG 4: ANG PINAKA-IMPORTANTENG CHECK — SPORT-AWARE CONFLICT ----
    // Ito yung tumatawag sa canBookSlot() function (nasa functions.php)
    // na siyang nag-che-check kung available pa ang sport na ito sa
    // oras/court na hiniling. Court 1 at ang Volleyball ay exclusive
    // (buong court); Badminton/Pickleball sa Court 2 ay pwedeng
    // magkasabay hanggang 2. Ginagawa ito dito sa PHP (server-side)
    // — hindi lang sa JavaScript — para hindi ito ma-bypass ng user
    // kahit i-disable pa nila ang JavaScript sa browser nila.
    if (empty($errors) && !canBookSlot($pdo, $court, $reservation_date, $start_time, $end_time, $purpose)) {
        $errors[] = 'The selected schedule is unavailable on ' . htmlspecialchars($court) . ' for ' . htmlspecialchars($purpose) . '. Please choose another date, time, court, or sport.';
    }

    // ---- HAKBANG 5: I-SAVE NA SA DATABASE (kung walang natagpuang error) ----
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO reservations
                    (reservation_code, user_id, court, reservation_date, start_time, end_time, purpose, participants, notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
            );
            // Gumagawa muna ng unique reservation code bago i-save
            $tempCode = generateReservationCode($pdo);
            $stmt->execute([
                $tempCode,
                $_SESSION['user_id'], // ID ng kasalukuyang naka-login na user
                $court,
                $reservation_date,
                $start_time,
                $end_time,
                $purpose,
                (int)$participants,
                $notes !== '' ? $notes : null,
            ]);

            $_SESSION['flash_success'] = 'Reservation submitted for ' . $court . '! It is now pending admin approval.';
            header('Location: ' . BASE_URL . 'my_reservations.php');
            exit;
        } catch (PDOException $e) {
            error_log('Reservation insert failed: ' . $e->getMessage());
            $errors[] = 'We could not submit your reservation right now. Please try again.';
        }
    }
}

$pageTitle = 'Reserve the Court';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-heading reservation-heading">
  <div><span class="page-kicker">BOOK A TIME</span><h1>Reserve the Covered Court</h1><p>Choose a date and available time, then send your request for approval.</p></div>
  <span class="free-booking-badge"><i class="bi bi-check-circle-fill"></i> Free to reserve</span>
</div>
<div class="alert alert-info reservation-notice" role="note">
  <strong>Before you submit:</strong> Requests are not confirmed until approved by an administrator. Hours are Monday–Friday, 6:00 PM–9:00 PM, and Saturday–Sunday, 7:00 AM–9:00 PM. Court 1 is for Basketball or Volleyball. Court 2 is for Volleyball, Badminton, or Pickleball; Badminton and Pickleball can share the court. Pending requests can be cancelled under My Reservations. For participant limits, equipment, check-in, facility rules, or changes to an approved booking, contact the school administrator.
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Court selector -->
<div class="card p-3 mb-4 reservation-courtbar">
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="fw-semibold me-2"><i class="bi bi-geo-alt"></i> Choose a Court:</span>
    <div class="court-switch" id="courtSwitch">
      <?php foreach ($courts as $c): ?>
        <button type="button" class="court-switch-btn <?= $c === $court ? 'active' : '' ?>" data-court="<?= clean($c) ?>"><?= clean($c) ?></button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card p-4 reservation-panel reservation-calendar-panel">
      <h2 class="reservation-panel-title"><span class="reservation-step">1</span> Check availability <span id="calendarCourtLabel" class="text-success"><?= clean($court) ?></span></h2>
      <div id="courtCalendar" data-base-url="<?= BASE_URL ?>"></div>
      <small class="text-muted d-block mt-2">“Partially Booked” means some hours are taken and at least one hour still has availability. Select a date, then select an available time to see which sports can be booked for that hour.</small>

      <div id="daySlotsPanel" class="mt-3">
        <p class="text-muted mb-0">Click a day on the calendar to see its hourly schedule.</p>
      </div>
      <small class="text-muted d-block mt-2">Tip: click an available time slot below to auto-fill the time fields. You can still adjust start/end manually (e.g. to book 2 consecutive hours).</small>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card p-4 reservation-panel reservation-form-panel">
      <h2 class="reservation-panel-title"><span class="reservation-step">2</span> Reservation details</h2>
      <form method="POST" id="reservationForm" data-confirm="Submit this reservation request? An admin will need to approve it before it's confirmed.">
        <input type="hidden" name="court" id="court_input" value="<?= clean($court) ?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Court</label>
            <input type="text" class="form-control" id="court_display" value="<?= clean($court) ?>" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Date</label>
            <input type="date" name="reservation_date" id="reservation_date" class="form-control"
                   value="<?= clean($reservation_date) ?>" min="<?= date('Y-m-d') ?>" required readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Start Time</label>
            <input type="time" name="start_time" id="start_time" class="form-control" value="<?= clean($start_time) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">End Time</label>
            <input type="time" name="end_time" id="end_time" class="form-control" value="<?= clean($end_time) ?>" required>
          </div>
          <div class="col-12">
            <small class="text-muted" id="scheduleHint">Mon-Fri: 6:00 PM - 9:00 PM &middot; Sat-Sun: 7:00 AM - 9:00 PM</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Purpose (Sport)</label>
            <select name="purpose" id="purpose_select" class="form-control" required>
              <?php foreach (getSportsForCourt($court) as $sport): ?>
                <option value="<?= clean($sport) ?>" <?= $sport === $purpose ? 'selected' : '' ?>><?= clean($sport) ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted" id="purposeHint"></small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Number of Participants</label>
            <input type="number" name="participants" class="form-control" min="1"
                   value="<?= clean($participants) ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Notes (optional)</label>
            <textarea name="notes" class="form-control" rows="2"><?= clean($notes) ?></textarea>
          </div>
        </div>
        <button type="submit" class="btn btn-primary mt-4 w-100">Submit Reservation</button>
      </form>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>assets/js/calendar.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const panel = document.getElementById('daySlotsPanel');
  const dateInput = document.getElementById('reservation_date');
  const startInput = document.getElementById('start_time');
  const endInput = document.getElementById('end_time');
  const courtInput = document.getElementById('court_input');
  const courtDisplay = document.getElementById('court_display');
  const calendarCourtLabel = document.getElementById('calendarCourtLabel');
  const purposeSelect = document.getElementById('purpose_select');
  const purposeHint = document.getElementById('purposeHint');
  const baseUrl = <?= json_encode(BASE_URL) ?>;

  // Sport choices per court — kailangan magbago ang dropdown na 'to
  // kapag lumipat ng court, dahil magkaiba ang sport list nila.
  const sportsByCourt = {
    'Court 1': ['Basketball', 'Volleyball'],
    'Court 2': ['Volleyball', 'Badminton', 'Pickleball']
  };

  let currentCourt = <?= json_encode($court) ?>;
  let calendarApi = null;
  let selectedIso = <?= json_encode($reservation_date !== '' ? $reservation_date : null) ?>;

  // Pinupuno ang Purpose dropdown depende sa court (o mas mainam pa,
  // depende sa "allowedSports" ng isang partikular na slot na na-click).
  function populatePurposeOptions(allowedSports) {
    const previous = purposeSelect.value;
    purposeSelect.innerHTML = '';
    allowedSports.forEach(function (sport) {
      const opt = document.createElement('option');
      opt.value = sport;
      opt.textContent = sport;
      purposeSelect.appendChild(opt);
    });
    if (allowedSports.includes(previous)) {
      purposeSelect.value = previous;
    }
  }

  function renderSlots(data) {
    if (data.error) {
      panel.innerHTML = '<p class="text-danger mb-0">Could not load the schedule. Please try again.</p>';
      return;
    }

    if (!data.is_open) {
      panel.innerHTML = '<p class="fw-semibold mb-1">' + data.date_label + '</p>' +
        '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle"></i> The court is closed on this date' +
        (data.closure_reason ? ' (' + data.closure_reason + ')' : '') + '.</div>';
      return;
    }

    const scheduleLine = '<p class="text-muted small mb-2">' +
      (data.is_weekend ? 'Weekend hours: 7:00 AM - 9:00 PM' : 'Weekday hours: 6:00 PM - 9:00 PM') + '</p>';

    let html = '<p class="fw-semibold mb-1">' + data.date_label + '</p>' + scheduleLine + '<div class="list-group">';
    data.slots.forEach(function (s) {
      if (s.past) {
        html += '<div class="list-group-item text-muted d-flex justify-content-between align-items-center">' +
                '<span>' + s.start_label + ' - ' + s.end_label + '</span>' +
                '<span class="badge bg-secondary">PAST</span></div>';
      } else if (s.full) {
        const sportsBooked = s.bookings.map(function (b) { return b.sport; }).join(', ');
        html += '<div class="list-group-item slot-taken d-flex justify-content-between align-items-center">' +
                '<span>' + s.start_label + ' - ' + s.end_label + (sportsBooked ? ' (' + sportsBooked + ')' : '') + '</span>' +
                '<span class="badge bg-danger">FULL</span></div>';
      } else if (s.bookings.length > 0) {
        // May 1 shareable booking na (Badminton o Pickleball), pero
        // may isa pang half-court na available.
        const sportsBooked = s.bookings.map(function (b) { return b.sport; }).join(', ');
        html += '<div class="list-group-item slot-available slot-btn d-flex justify-content-between align-items-center" ' +
                'data-available="1" data-start="' + s.start + '" data-end="' + s.end + '" data-sports="' + s.available_sports.join(',') + '">' +
                '<span>' + s.start_label + ' - ' + s.end_label + ' &mdash; ' + sportsBooked + ' booked, 1 half-court slot left</span>' +
                '<span class="badge bg-warning text-dark">1 SLOT LEFT</span></div>';
      } else {
        html += '<div class="list-group-item slot-available slot-btn d-flex justify-content-between align-items-center" ' +
                'data-available="1" data-start="' + s.start + '" data-end="' + s.end + '" data-sports="' + s.available_sports.join(',') + '">' +
                '<span>' + s.start_label + ' - ' + s.end_label + '</span>' +
                '<span class="badge bg-success">AVAILABLE</span></div>';
      }
    });
    html += '</div>';
    panel.innerHTML = html;

    panel.querySelectorAll('.slot-btn[data-available="1"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        startInput.value = btn.getAttribute('data-start').slice(0, 5);
        endInput.value = btn.getAttribute('data-end').slice(0, 5);
        panel.querySelectorAll('.slot-btn').forEach(function (b) { b.classList.remove('selected'); });
        btn.classList.add('selected');

        // I-limit ang Purpose dropdown sa mga sport na pwede pa
        // talaga sa specific na oras na ito (hal. kung may 1 na
        // Badminton booking na, Badminton/Pickleball na lang ang
        // pwede, hindi na Volleyball).
        const allowed = btn.getAttribute('data-sports').split(',').filter(Boolean);
        populatePurposeOptions(allowed.length ? allowed : sportsByCourt[currentCourt]);
        purposeHint.textContent = allowed.length < sportsByCourt[currentCourt].length
          ? 'Limited to ' + allowed.join(' / ') + ' for this time slot (half-court already in use).'
          : '';
      });
    });
  }

  function loadDay(iso) {
    selectedIso = iso;
    dateInput.value = iso;
    panel.innerHTML = '<p class="text-muted mb-0">Loading schedule…</p>';
    fetch(baseUrl + 'slots_data.php?date=' + iso + '&court=' + encodeURIComponent(currentCourt))
      .then(function (res) { return res.json(); })
      .then(renderSlots)
      .catch(function () {
        panel.innerHTML = '<p class="text-danger mb-0">Could not load the schedule. Please try again.</p>';
      });
  }

  calendarApi = initCourtCalendar('courtCalendar', {
    initialDate: selectedIso,
    court: currentCourt,
    onDaySelected: loadDay
  });

  populatePurposeOptions(sportsByCourt[currentCourt]);

  if (selectedIso) {
    loadDay(selectedIso);
  }

  // ---- Court switch buttons ----
  document.querySelectorAll('#courtSwitch .court-switch-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#courtSwitch .court-switch-btn').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');

      currentCourt = btn.getAttribute('data-court');
      courtInput.value = currentCourt;
      courtDisplay.value = currentCourt;
      calendarCourtLabel.textContent = currentCourt;
      purposeHint.textContent = '';
      populatePurposeOptions(sportsByCourt[currentCourt]);

      if (calendarApi && calendarApi.setCourt) {
        calendarApi.setCourt(currentCourt);
      }
      if (selectedIso) {
        loadDay(selectedIso);
      }
    });
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
