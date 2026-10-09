<?php
/**
 * admin/dashboard.php
 * -----------------------------------------------------------
 * PHASE: Admin Dashboard
 *
 * WHAT THIS FILE IS FOR:
 * The admin's home screen — system-wide counts (total, pending,
 * approved, rejected, cancelled, completed) and the 8 most
 * recent reservations across ALL users, with a quick link to
 * open each one for approval.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// ---------------------------------------------------------------
// KUNIN ANG BILANG NG RESERVATIONS PER STATUS (para sa stat cards)
// Isang query lang ito na kumukuha ng "GROUP BY status" — ibig
// sabihin ibinabalik nito ang bilang PER GRUPO (hal. "pending: 12,
// approved: 85"), hindi kailangan ng magkakahiwalay na query bawat
// status.
// ---------------------------------------------------------------
$stmt = $pdo->query("SELECT status, COUNT(*) AS total FROM reservations GROUP BY status");
// Naglagay muna tayo ng "0" bilang default sa bawat status, para
// kahit walang laman ang isang status (hal. wala pang "rejected"),
// hindi magkaroon ng error sa pag-display, "0" lang ang lalabas
$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0, 'completed' => 0];
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['total'];
}
$totalReservations = array_sum($counts); // idinagdag lahat para makuha ang grand total

// ---- Pinaka-huling 8 reservations mula sa LAHAT ng users ----
$recent = $pdo->query(
    "SELECT r.*, u.full_name FROM reservations r
     JOIN users u ON u.id = r.user_id
     ORDER BY r.created_at DESC LIMIT 8"
)->fetchAll();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-heading admin-dashboard-heading">
  <div><span class="page-kicker">COURT MANAGEMENT</span><h1>Admin Dashboard</h1><p>Reservation activity and status overview.</p></div>
  <a href="reservations.php?status=pending" class="btn btn-primary"><i class="bi bi-inbox me-1"></i> Review requests</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-lg-2">
    <div class="stat-card blue"><div class="stat-number"><?= $totalReservations ?></div><div>Total</div></div>
  </div>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="stat-card amber"><div class="stat-number"><?= $counts['pending'] ?></div><div>Pending</div></div>
  </div>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="stat-card green"><div class="stat-number"><?= $counts['approved'] ?></div><div>Approved</div></div>
  </div>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="stat-card red"><div class="stat-number"><?= $counts['rejected'] ?></div><div>Rejected</div></div>
  </div>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="stat-card gray"><div class="stat-number"><?= $counts['cancelled'] ?></div><div>Cancelled</div></div>
  </div>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="stat-card gray"><div class="stat-number"><?= $counts['completed'] ?></div><div>Completed</div></div>
  </div>
</div>

<div class="section-heading-row">
  <div><span class="page-kicker">LATEST ACTIVITY</span><h2>Recent reservations</h2></div>
  <a href="reservations.php" class="section-heading-link">View all <i class="bi bi-arrow-right"></i></a>
</div>

<div class="card p-3 reservation-table-card">
  <div class="table-responsive">
    <table class="table table-rounded align-middle mb-0">
      <thead><tr><th>Name</th><th>Court</th><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No reservations yet.</td></tr>
        <?php else: ?>
          <?php foreach ($recent as $r): ?>
          <tr>
            <td><?= clean($r['full_name']) ?></td>
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
