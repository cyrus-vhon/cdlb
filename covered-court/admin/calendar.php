<?php
/**
 * admin/calendar.php
 * -----------------------------------------------------------
 * PHASE: Court Availability Management (Admin)
 *
 * WHAT THIS FILE IS FOR:
 * A full month-view calendar for admins covering ALL users'
 * reservations (not just their own), with a Court 1/Court 2
 * switch. Clicking a day shows exactly who booked what, with
 * their name and purpose visible — a privilege only admins get
 * (see slots_data.php, which hides that detail from regular
 * users for privacy).
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$courts = getCourtList();

$pageTitle = 'Schedule Calendar';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-heading schedule-heading">
  <div><span class="page-kicker">ADMINISTRATION</span><h1>Schedule Calendar</h1><p>Review availability and reservations across both courts.</p></div>
  <span class="schedule-heading-note"><i class="bi bi-calendar3"></i> Monthly view</span>
</div>

<div class="card p-3 mb-4 reservation-courtbar">
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="fw-semibold me-2"><i class="bi bi-geo-alt"></i> Viewing:</span>
    <div class="court-switch" id="adminCourtSwitch">
      <?php foreach ($courts as $i => $c): ?>
        <button type="button" class="court-switch-btn <?= $i === 0 ? 'active' : '' ?>" data-court="<?= clean($c) ?>"><?= clean($c) ?></button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card p-4 reservation-panel admin-calendar-panel">
      <div id="adminCalendar" data-base-url="<?= BASE_URL ?>"></div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card p-4 reservation-panel admin-day-panel">
      <div id="adminDayPanel">
        <p class="text-muted mb-0">Click a day on the calendar to see the full schedule for that day.</p>
      </div>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>assets/js/calendar.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const panel = document.getElementById('adminDayPanel');
  const baseUrl = <?= json_encode(BASE_URL) ?>;
  let currentCourt = <?= json_encode($courts[0]) ?>;
  let selectedIso = null;

  function renderDay(data) {
    if (data.error) {
      panel.innerHTML = '<p class="text-danger mb-0">Could not load the schedule. Please try again.</p>';
      return;
    }

    if (!data.is_open) {
      panel.innerHTML = '<p class="fw-semibold mb-2">' + data.date_label + '</p>' +
        '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle"></i> The court is closed on this date' +
        (data.closure_reason ? ' (' + data.closure_reason + ')' : '') + '.</div>';
      return;
    }

    const scheduleLine = '<p class="text-muted small mb-2">' +
      (data.is_weekend ? 'Weekend hours: 7:00 AM - 9:00 PM' : 'Weekday hours: 6:00 PM - 9:00 PM') + '</p>';

    let html = '<p class="fw-semibold mb-1">' + data.date_label + ' &middot; ' + currentCourt + '</p>' + scheduleLine + '<div class="list-group">';
    data.slots.forEach(function (s) {
      if (s.past) {
        html += '<div class="list-group-item text-muted d-flex justify-content-between align-items-center">' +
                '<span>' + s.start_label + ' - ' + s.end_label + '</span>' +
                '<span class="badge bg-secondary">Past</span></div>';
      } else if (s.bookings.length > 0) {
        const rows = s.bookings.map(function (b) {
          const statusBadge = b.status === 'approved'
            ? '<span class="badge bg-success">Approved</span>'
            : '<span class="badge bg-warning text-dark">Pending</span>';
          return '<div class="d-flex justify-content-between align-items-center">' +
                 '<span>' + (b.reserved_by || '') + ' &middot; ' + b.sport + '</span>' + statusBadge + '</div>';
        }).join('');
        html += '<div class="list-group-item">' +
                '<div class="d-flex justify-content-between align-items-center mb-1">' +
                '<span class="fw-semibold">' + s.start_label + ' - ' + s.end_label + '</span>' +
                (s.full ? '<span class="badge bg-danger">Full</span>' : '<span class="badge bg-warning text-dark">1 slot left</span>') +
                '</div>' + rows + '</div>';
      } else {
        html += '<div class="list-group-item slot-available d-flex justify-content-between align-items-center">' +
                '<span>' + s.start_label + ' - ' + s.end_label + '</span>' +
                '<span class="badge bg-success">Available</span></div>';
      }
    });
    html += '</div>';
    panel.innerHTML = html;
  }

  function loadDay(iso) {
    selectedIso = iso;
    panel.innerHTML = '<p class="text-muted mb-0">Loading schedule…</p>';
    fetch(baseUrl + 'slots_data.php?date=' + iso + '&court=' + encodeURIComponent(currentCourt))
      .then(function (res) { return res.json(); })
      .then(renderDay)
      .catch(function () {
        panel.innerHTML = '<p class="text-danger mb-0">Could not load the schedule. Please try again.</p>';
      });
  }

  const calendarApi = initCourtCalendar('adminCalendar', { court: currentCourt, onDaySelected: loadDay });

  document.querySelectorAll('#adminCourtSwitch .court-switch-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#adminCourtSwitch .court-switch-btn').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      currentCourt = btn.getAttribute('data-court');
      if (calendarApi && calendarApi.setCourt) calendarApi.setCourt(currentCourt);
      if (selectedIso) loadDay(selectedIso);
    });
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
