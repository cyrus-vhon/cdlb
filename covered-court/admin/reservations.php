<?php
/**
 * admin/reservations.php
 * -----------------------------------------------------------
 * PHASE: Reservation Approval (Admin)
 *
 * WHAT THIS FILE IS FOR:
 * The full, searchable/filterable list of every reservation in
 * the system — filter by status, court, date, or search by
 * name/code/purpose. Read-only list; clicking "View" goes to
 * reservation_view.php where the actual approve/reject/cancel
 * actions happen.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Kunin ang mga filter na pinili ng admin (galing sa URL, hal.
// ?status=pending&court=Court+1). Kung wala, "all" (lahat) ang default.
$status = $_GET['status'] ?? 'all';
$date   = $_GET['date'] ?? '';
$search = trim($_GET['q'] ?? '');
$courtFilter = $_GET['court'] ?? 'all';

$validStatuses = ['all', 'pending', 'approved', 'rejected', 'cancelled', 'completed'];
if (!in_array($status, $validStatuses, true)) $status = 'all'; // proteksyon laban sa invalid na value

$courts = getCourtList();
if ($courtFilter !== 'all' && !in_array($courtFilter, $courts, true)) $courtFilter = 'all';

// ---------------------------------------------------------------
// PAANO GINAGAWA ANG QUERY: "WHERE 1=1" bilang panimula
// Ito ay isang trick para mas madaling magdagdag ng mga karagdagang
// "AND" na condition sa ibaba — hindi na natin kailangan i-check
// kung ito ba yung UNANG condition (na dapat "WHERE" ang gamitin)
// o hindi (na dapat "AND"). Palagi na lang tayong "AND" gagamitin.
// ---------------------------------------------------------------
$sql = "SELECT r.*, u.full_name, u.email FROM reservations r
        JOIN users u ON u.id = r.user_id
        WHERE 1=1";
$params = [];

// Idinaragdag lang ang bawat filter KUNG meron talagang pinili
// ang admin (hindi "all" o blangko). Ito rin ang dahilan kung
// bakit "prepared statement" (may "?" placeholders) ang gamit —
// para proteksyon laban sa SQL injection.
if ($status !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $status;
}
if ($courtFilter !== 'all') {
    $sql .= " AND r.court = ?";
    $params[] = $courtFilter;
}
if ($date !== '') {
    $sql .= " AND r.reservation_date = ?";
    $params[] = $date;
}
if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR r.reservation_code LIKE ? OR r.purpose LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql .= " ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$pageTitle = 'All Reservations';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-heading admin-reservations-heading">
  <div><span class="page-kicker">BOOKING MANAGEMENT</span><h1>Reservations</h1><p>Search bookings and review their current status.</p></div>
</div>

<form method="GET" class="card p-3 mb-3 reservation-filter-card">
  <div class="row g-2 align-items-end">
    <div class="col-md-2">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach ($validStatuses as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Court</label>
      <select name="court" class="form-select">
        <option value="all" <?= $courtFilter === 'all' ? 'selected' : '' ?>>All Courts</option>
        <?php foreach ($courts as $c): ?>
          <option value="<?= clean($c) ?>" <?= $courtFilter === $c ? 'selected' : '' ?>><?= clean($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Date</label>
      <input type="date" name="date" class="form-control" value="<?= clean($date) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Search (name, code, purpose)</label>
      <input type="text" name="q" class="form-control" value="<?= clean($search) ?>">
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100" type="submit">Filter</button>
    </div>
  </div>
</form>

<div class="card p-3 reservation-table-card">
  <div class="table-responsive">
    <table class="table table-rounded align-middle mb-0">
      <thead><tr><th>Name</th><th>Code</th><th>Court</th><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php if (empty($reservations)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No matching reservations.</td></tr>
        <?php else: ?>
          <?php foreach ($reservations as $r): ?>
          <tr>
            <td><?= clean($r['full_name']) ?><br><small class="text-muted"><?= clean($r['email']) ?></small></td>
            <td><code><?= clean($r['reservation_code']) ?></code></td>
            <td><span class="badge bg-primary"><?= clean($r['court']) ?></span></td>
            <td><?= formatDate($r['reservation_date']) ?></td>
            <td><?= formatTime($r['start_time']) ?> - <?= formatTime($r['end_time']) ?></td>
            <td><?= clean($r['purpose']) ?></td>
            <td><span class="badge <?= statusBadgeClass($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
            <td><a href="reservation_view.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
