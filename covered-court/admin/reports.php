<?php
/**
 * admin/reports.php
 * -----------------------------------------------------------
 * PHASE: Reports (Admin)
 *
 * WHAT THIS FILE IS FOR:
 * Summary reporting — filter reservations by date range
 * (today/this week/this month/custom) and by status, then see
 * totals, the most-used dates, the most common purposes, and
 * the full matching list. Has a Print button for a physical/PDF
 * copy (browser's own print-to-PDF).
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$range  = $_GET['range'] ?? 'month';
$status = $_GET['status'] ?? 'all';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';

$validRanges   = ['today', 'week', 'month', 'custom'];
$validStatuses = ['all', 'approved', 'rejected', 'cancelled', 'completed', 'pending'];
if (!in_array($range, $validRanges, true))   $range = 'month';
if (!in_array($status, $validStatuses, true)) $status = 'all';

// ---------------------------------------------------------------
// KUNIN ANG TAMANG "FROM" AT "TO" NA PETSA, DEPENDE SA PINILING RANGE
// Ang "range" ay maaaring "today", "week", "month", o "custom".
// Ang gawain dito ay i-convert ang salitang 'yon papunta sa
// aktwal na dalawang petsa (from/to) na gagamitin natin sa query.
// ---------------------------------------------------------------
switch ($range) {
    case 'today':
        $from = $to = date('Y-m-d'); // parehong petsa lang — ngayong araw
        break;
    case 'week':
        // "monday this week" / "sunday this week" ay built-in na
        // PHP phrase na kinukuha ang petsa ng Lunes/Linggo ng
        // kasalukuyang linggo
        $from = date('Y-m-d', strtotime('monday this week'));
        $to   = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $from = date('Y-m-01'); // unang araw ng kasalukuyang buwan
        $to   = date('Y-m-t');  // huling araw ng kasalukuyang buwan ("t" = "last day")
        break;
    case 'custom':
        // Kung "custom" ang pinili, gagamitin natin yung mismong
        // petsa na na-type ng admin. Pero kung blangko pala ito
        // (hal. binago lang nila yung range dropdown pero hindi
        // pa nag-fill ng petsa), babalik na lang tayo sa "this month"
        // bilang default para may laman pa rin
        if ($from === '' || $to === '') {
            $from = date('Y-m-01');
            $to   = date('Y-m-t');
        }
        break;
}

// Kunin lahat ng reservations sa loob ng napiling date range
$sql = "SELECT r.*, u.full_name FROM reservations r
        JOIN users u ON u.id = r.user_id
        WHERE r.reservation_date BETWEEN ? AND ?";
$params = [$from, $to];

if ($status !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY r.reservation_date DESC, r.start_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ---------------------------------------------------------------
// GUMAWA NG SUMMARY STATS GALING SA MGA NAKUHANG ROWS
// Hindi na tayo bumalik pa sa database para sa mga sumusunod na
// bilang — ginagamit na lang natin yung $rows na nakuha na natin
// sa itaas, tapos binibilang natin manu-mano gamit ang PHP.
// ---------------------------------------------------------------
$total = count($rows);

$dateCounts = [];    // bilang ng reservations PER PETSA
$purposeCounts = []; // bilang ng reservations PER PURPOSE (hal. "Basketball": 18)
foreach ($rows as $r) {
    $dateCounts[$r['reservation_date']] = ($dateCounts[$r['reservation_date']] ?? 0) + 1;
    $purposeCounts[$r['purpose']] = ($purposeCounts[$r['purpose']] ?? 0) + 1;
}
arsort($dateCounts);
arsort($purposeCounts);
$topDates    = array_slice($dateCounts, 0, 5, true);
$topPurposes = array_slice($purposeCounts, 0, 5, true);

$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-heading no-print">
  <div><span class="page-kicker">ANALYTICS</span><h1>Reports</h1><p>Review reservation activity for a selected date range.</p></div>
  <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
</div>

<form method="GET" class="card p-3 mb-3 no-print reservation-filter-card">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Range</label>
      <select name="range" class="form-select" onchange="document.getElementById('customRow').style.display = this.value==='custom' ? 'flex' : 'none';">
        <option value="today"  <?= $range === 'today' ? 'selected' : '' ?>>Today</option>
        <option value="week"   <?= $range === 'week' ? 'selected' : '' ?>>This Week</option>
        <option value="month"  <?= $range === 'month' ? 'selected' : '' ?>>This Month</option>
        <option value="custom" <?= $range === 'custom' ? 'selected' : '' ?>>Custom Range</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach ($validStatuses as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-5 d-flex gap-2" id="customRow" style="<?= $range === 'custom' ? 'display:flex;' : 'display:none;' ?>">
      <div class="flex-fill">
        <label class="form-label">From</label>
        <input type="date" name="from" class="form-control" value="<?= clean($from) ?>">
      </div>
      <div class="flex-fill">
        <label class="form-label">To</label>
        <input type="date" name="to" class="form-control" value="<?= clean($to) ?>">
      </div>
    </div>
    <div class="col-md-1">
      <button class="btn btn-primary w-100" type="submit">Go</button>
    </div>
  </div>
</form>

<p class="text-muted">Showing results from <strong><?= formatDate($from) ?></strong> to <strong><?= formatDate($to) ?></strong></p>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card blue"><div class="stat-number"><?= $total ?></div><div>Total Reservations</div></div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 h-100">
      <h6 class="fw-bold">Most Used Dates</h6>
      <?php if (empty($topDates)): ?><p class="text-muted mb-0">No data.</p><?php endif; ?>
      <ul class="list-unstyled mb-0">
        <?php foreach ($topDates as $d => $c): ?>
          <li class="d-flex justify-content-between"><span><?= formatDate($d) ?></span><span class="badge bg-primary"><?= $c ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 h-100">
      <h6 class="fw-bold">Most Common Purposes</h6>
      <?php if (empty($topPurposes)): ?><p class="text-muted mb-0">No data.</p><?php endif; ?>
      <ul class="list-unstyled mb-0">
        <?php foreach ($topPurposes as $p => $c): ?>
          <li class="d-flex justify-content-between"><span><?= clean($p) ?></span><span class="badge bg-primary"><?= $c ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<div class="card p-3">
  <h6 class="fw-bold mb-3">Reservation History</h6>
  <div class="table-responsive">
    <table class="table table-rounded align-middle mb-0">
      <thead><tr><th>Name</th><th>Code</th><th>Court</th><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No reservations in this range.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= clean($r['full_name']) ?></td>
            <td><code><?= clean($r['reservation_code']) ?></code></td>
            <td><span class="badge bg-primary"><?= clean($r['court']) ?></span></td>
            <td><?= formatDate($r['reservation_date']) ?></td>
            <td><?= formatTime($r['start_time']) ?> - <?= formatTime($r['end_time']) ?></td>
            <td><?= clean($r['purpose']) ?></td>
            <td><span class="badge <?= statusBadgeClass($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
@media print {
  .no-print, nav, footer { display: none !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
