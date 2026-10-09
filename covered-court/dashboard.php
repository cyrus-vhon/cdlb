<?php
/**
 * dashboard.php
 * -----------------------------------------------------------
 * PHASE: User Dashboard
 *
 * WHAT THIS FILE IS FOR:
 * The first page a regular (non-admin) user sees after login.
 * Shows quick stat counts (pending/approved/completed) and the
 * 5 most recent reservations, with a shortcut to start a new
 * booking.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/config/config.php';
requireLogin();

if (isAdmin()) {
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];

// ---- Bilangin ang reservations ng LOGGED-IN USER LANG (per status) ----
// Kapansin-pansin: may "WHERE user_id = ?" dito — kaya lang makikita
// ng bawat user ang SARILI nilang bilang, hindi yung sa ibang user
$stmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS total
     FROM reservations
     WHERE user_id = ?
     GROUP BY status"
);
$stmt->execute([$userId]);
$counts = ['pending' => 0, 'approved' => 0, 'completed' => 0, 'rejected' => 0, 'cancelled' => 0];
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['total'];
}

// ---- Recent reservations ----
$stmt = $pdo->prepare(
    "SELECT * FROM reservations WHERE user_id = ? ORDER BY created_at DESC LIMIT 5"
);
$stmt->execute([$userId]);
$recent = $stmt->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-heading user-dashboard-heading">
  <div><span class="page-kicker">YOUR COURT BOOKINGS</span><h1>Welcome, <?= clean($_SESSION['full_name']) ?></h1><p>Here’s the latest on your reservations.</p></div>
  <a href="<?= BASE_URL ?>reserve.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New reservation</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card amber">
      <div class="stat-number"><?= $counts['pending'] ?></div>
      <div>Pending</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card green">
      <div class="stat-number"><?= $counts['approved'] ?></div>
      <div>Approved</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card blue">
      <div class="stat-number"><?= $counts['completed'] ?></div>
      <div>Completed</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card gray">
      <div class="stat-number"><?= $counts['rejected'] + $counts['cancelled'] ?></div>
      <div>Rejected / Cancelled</div>
    </div>
  </div>
</div>

<div class="section-heading-row">
  <div><span class="page-kicker">YOUR ACTIVITY</span><h2>Recent reservations</h2></div>
  <a href="<?= BASE_URL ?>my_reservations.php" class="section-heading-link">View all <i class="bi bi-arrow-right"></i></a>
</div>

<div class="card p-3 reservation-table-card">
  <div class="table-responsive">
    <table class="table table-rounded align-middle mb-0">
      <thead>
        <tr>
          <th>Code</th>
          <th>Court</th>
          <th>Date</th>
          <th>Time</th>
          <th>Purpose</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No reservations yet. Click "New Reservation" to get started.</td></tr>
        <?php else: ?>
          <?php foreach ($recent as $r): ?>
          <tr>
            <td><code><?= clean($r['reservation_code']) ?></code></td>
            <td><span class="badge bg-primary"><?= clean($r['court']) ?></span></td>
            <td><?= formatDate($r['reservation_date']) ?></td>
            <td><?= formatTime($r['start_time']) ?> - <?= formatTime($r['end_time']) ?></td>
            <td><?= clean($r['purpose']) ?></td>
            <td><span class="badge <?= statusBadgeClass($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
            <td><a href="<?= BASE_URL ?>reservation_details.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="mt-3"><a href="<?= BASE_URL ?>my_reservations.php">View full reservation history &rarr;</a></p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
