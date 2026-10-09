<?php
/**
 * reservation_details.php
 * -----------------------------------------------------------
 * PHASE: Reservation History (User)
 *
 * WHAT THIS FILE IS FOR:
 * Shows one of the logged-in user's own reservations in full
 * detail — code, court, date/time, purpose, and current status.
 * A user can only ever open their OWN reservation; this is
 * enforced directly in the SQL WHERE clause below (id AND
 * user_id), not just by hiding the link elsewhere.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/config/config.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . 'admin/dashboard.php'); exit; }

$id = (int) ($_GET['id'] ?? 0);

// PROTEKSYON: Makikita lang ng user ang SARILI niyang reservation.
// Kahit baguhin niya ang "id" sa URL para subukang tingnan ang
// reservation ng ibang tao, hindi ito lalabas dahil kasama sa
// WHERE clause ang "user_id = $_SESSION['user_id']" (yung sariling
// ID niya, hindi kayang palitan sa URL).
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$r = $stmt->fetch();

if (!$r) {
    $_SESSION['flash_error'] = 'Reservation not found.';
    header('Location: ' . BASE_URL . 'my_reservations.php');
    exit;
}

$pageTitle = 'Reservation Details';
require_once __DIR__ . '/includes/header.php';
?>

<a href="<?= BASE_URL ?>my_reservations.php" class="detail-back-link d-inline-block mb-3"><i class="bi bi-arrow-left me-1"></i> Back to My Reservations</a>

<div class="card p-4 reservation-detail-card">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <h4 class="fw-bold mb-0">Reservation Details</h4>
    <span class="badge fs-6 <?= statusBadgeClass($r['status']) ?>"><?= ucfirst($r['status']) ?></span>
  </div>

  <table class="table table-borderless mb-0">
    <tr><th style="width:180px;">Reservation Code</th><td><code><?= clean($r['reservation_code']) ?></code></td></tr>
    <tr><th>Court</th><td><span class="badge bg-primary"><?= clean($r['court']) ?></span></td></tr>
    <tr><th>Date</th><td><?= formatDate($r['reservation_date']) ?></td></tr>
    <tr><th>Time</th><td><?= formatTime($r['start_time']) ?> - <?= formatTime($r['end_time']) ?></td></tr>
    <tr><th>Purpose</th><td><?= clean($r['purpose']) ?></td></tr>
    <tr><th>Participants</th><td><?= (int) $r['participants'] ?></td></tr>
    <?php if (!empty($r['notes'])): ?>
      <tr><th>Notes</th><td><?= clean($r['notes']) ?></td></tr>
    <?php endif; ?>
    <tr><th>Submitted</th><td><?= date('F j, Y g:i A', strtotime($r['created_at'])) ?></td></tr>
  </table>

  <?php if ($r['status'] === 'approved'): ?>
    <div class="alert alert-success mt-3 mb-0">This reservation is approved. Please show this reservation code at the court entrance if asked. Contact the school administrator to request a change or cancellation.</div>
  <?php elseif ($r['status'] === 'pending'): ?>
    <div class="alert alert-warning mt-3 mb-0">This request is awaiting administrator approval and is not confirmed yet. You can cancel a pending request from My Reservations.</div>
  <?php elseif ($r['status'] === 'rejected'): ?>
    <div class="alert alert-danger mt-3 mb-0">This request was rejected. Check the Notes above for a reason, if one was provided. Contact the school administrator if you need more information.</div>
  <?php elseif ($r['status'] === 'cancelled'): ?>
    <div class="alert alert-secondary mt-3 mb-0">This reservation was cancelled.</div>
  <?php elseif ($r['status'] === 'completed'): ?>
    <div class="alert alert-secondary mt-3 mb-0">This reservation has already taken place.</div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
