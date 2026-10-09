<?php
/**
 * my_reservations.php
 * -----------------------------------------------------------
 * PHASE: Reservation History (User)
 *
 * WHAT THIS FILE IS FOR:
 * Full list of the logged-in user's own reservations, with a
 * status filter (All/Pending/Approved/Rejected/Cancelled/
 * Completed) and a Cancel action. The cancel logic only ever
 * touches a reservation that is both owned by this user AND
 * still "pending" — enforced directly in the SQL WHERE clause,
 * so a user can never cancel someone else's booking or one
 * that's already been approved/rejected.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/config/config.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . 'admin/dashboard.php'); exit; }

$userId = $_SESSION['user_id'];

// ---------------------------------------------------------------
// AKSYON: I-CANCEL ANG SARILING RESERVATION
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cancelId = (int) $_POST['cancel_id'];

    // MAHALAGANG PROTEKSYON: Nakalagay lahat ng tatlong condition
    // (id, user_id, AT status='pending') sa loob ng SQL WHERE clause
    // mismo — hindi lang sa PHP if-statement. Ibig sabihin, KAHIT
    // MAG-TANGKANG magpadala ng ibang tao ng ID ng reservation ng
    // IBANG user (hal. sa pamamagitan ng pag-edit ng URL), HINDI
    // magtatagumpay dahil hindi tutugma ang "user_id = ?" condition.
    // Ganito rin, kung "approved" na ang isang reservation, hindi
    // na ito pwedeng i-cancel dito (kailangan mag-message sa admin).
    $stmt = $pdo->prepare(
        "UPDATE reservations
         SET status = 'cancelled'
         WHERE id = ? AND user_id = ? AND status = 'pending'"
    );
    $stmt->execute([$cancelId, $userId]);

    // rowCount() ang nagsasabi kung ilang row ang na-apektuhan ng
    // UPDATE. Kung 0, ibig sabihin walang natugmang row — baka
    // hindi kanya ang reservation, o hindi na "pending"
    if ($stmt->rowCount() > 0) {
        $_SESSION['flash_success'] = 'Reservation cancelled.';
    } else {
        $_SESSION['flash_error'] = 'That reservation can no longer be cancelled.';
    }
    header('Location: ' . BASE_URL . 'my_reservations.php');
    exit;
}

// ---------------------------------------------------------------
// STATUS FILTER (mga tab sa itaas: All/Pending/Approved/atbp.)
// ---------------------------------------------------------------
$filter = $_GET['status'] ?? 'all';
$validStatuses = ['all', 'pending', 'approved', 'rejected', 'cancelled', 'completed'];
if (!in_array($filter, $validStatuses, true)) $filter = 'all';

if ($filter === 'all') {
    // Ipakita LAHAT ng reservations ng user na ito
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE user_id = ? ORDER BY reservation_date DESC, start_time DESC");
    $stmt->execute([$userId]);
} else {
    // Ipakita lang ang mga tumutugma sa napiling status
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE user_id = ? AND status = ? ORDER BY reservation_date DESC, start_time DESC");
    $stmt->execute([$userId, $filter]);
}
$reservations = $stmt->fetchAll();

$pageTitle = 'My Reservations';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-heading history-heading">
  <div><span class="page-kicker">YOUR BOOKINGS</span><h1>My Reservations</h1><p>Review request status and manage pending bookings.</p></div>
  <a href="<?= BASE_URL ?>reserve.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New reservation</a>
</div>

<ul class="nav nav-pills mb-3 reservation-filters">
  <?php foreach ($validStatuses as $s): ?>
    <li class="nav-item">
      <a class="nav-link <?= $filter === $s ? 'active' : '' ?>" href="?status=<?= $s ?>"><?= ucfirst($s) ?></a>
    </li>
  <?php endforeach; ?>
</ul>

<div class="card p-3 reservation-table-card">
  <div class="table-responsive">
    <table class="table table-rounded align-middle mb-0">
      <thead>
        <tr>
          <th>Code</th><th>Court</th><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($reservations)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No reservations found.</td></tr>
        <?php else: ?>
          <?php foreach ($reservations as $r): ?>
          <tr>
            <td><code><?= clean($r['reservation_code']) ?></code></td>
            <td><span class="badge bg-primary"><?= clean($r['court']) ?></span></td>
            <td><?= formatDate($r['reservation_date']) ?></td>
            <td><?= formatTime($r['start_time']) ?> - <?= formatTime($r['end_time']) ?></td>
            <td><?= clean($r['purpose']) ?></td>
            <td><span class="badge <?= statusBadgeClass($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
            <td class="text-nowrap">
              <a href="<?= BASE_URL ?>reservation_details.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
              <?php if ($r['status'] === 'pending'): ?>
                <form method="POST" class="d-inline" data-confirm="Cancel this reservation? This cannot be undone.">
                  <input type="hidden" name="cancel_id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
