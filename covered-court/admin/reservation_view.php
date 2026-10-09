<?php
/**
 * admin/reservation_view.php
 * -----------------------------------------------------------
 * PHASE: Reservation Approval (Admin)
 *
 * WHAT THIS FILE IS FOR:
 * Shows one reservation's full details and lets the admin
 * Approve, Reject, Cancel, or mark it Completed. This is the
 * single place where a reservation's status actually changes.
 *
 * When the admin cancels a reservation, they can type a reason
 * (e.g. "Court needed for a school event") — this gets appended
 * to the reservation's notes, which the student can see on their
 * own reservation details page, so they know WHY it was cancelled
 * instead of just seeing a status change with no explanation.
 *
 * Before approving, we re-check for a scheduling conflict
 * (canBookSlot) in case something changed between when the
 * student submitted the request and now — this keeps the
 * sport-aware double-booking guarantee solid even at approval time.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);

// Kinukuha ang buong detalye ng reservation, kasama na yung
// pangalan/email ng nag-request (galing sa users table via JOIN)
function loadReservation(PDO $pdo, $id) {
    $stmt = $pdo->prepare(
        "SELECT r.*, u.full_name, u.email FROM reservations r
         JOIN users u ON u.id = r.user_id WHERE r.id = ?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch();
}

$r = loadReservation($pdo, $id);
if (!$r) {
    $_SESSION['flash_error'] = 'Reservation not found.';
    header('Location: ' . BASE_URL . 'admin/reservations.php');
    exit;
}

// ---------------------------------------------------------------
// ITO YUNG HUMAHAWAK SA MGA ACTION NG ADMIN: approve / reject / cancel / complete
// Isang POST request lang ang dumarating dito, at yung "action"
// field ang nagsasabi kung ano talaga ang gustong gawin ng admin.
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve' && $r['status'] === 'pending') {
        // MAHALAGANG HAKBANG: Bago talaga i-approve, chine-check muna
        // ULIT natin (canBookSlot) kung available pa rin ang sport na
        // ito. Bakit kailangan pang i-recheck kahit na-check na noong
        // pag-submit? Dahil posibleng may nangyari sa pagitan ng
        // pag-submit at ngayon (hal. inaprubahan muna ng admin yung
        // ibang request sa parehong oras). Ito ang tinatawag na
        // "race condition" — dalawang bagay na parehong "pending"
        // pero magkasalungat, kaya kailangan i-verify ulit bago
        // talaga tuluyang ma-confirm.
        if (!canBookSlot($pdo, $r['court'], $r['reservation_date'], $r['start_time'], $r['end_time'], $r['purpose'], $r['id'])) {
            $_SESSION['flash_error'] = 'Cannot approve: this schedule now conflicts with another approved reservation.';
        } else {
            $stmt = $pdo->prepare("UPDATE reservations SET status = 'approved' WHERE id = ?");
            $stmt->execute([$r['id']]);
            $_SESSION['flash_success'] = 'Reservation approved.';
        }
    } elseif ($action === 'reject' && $r['status'] === 'pending') {
        // Simpleng pagpalit ng status sa "rejected" — walang ibang
        // check na kailangan dahil hindi naman ito nagre-reserve ng slot.
        // Kinukuha rin natin ang "reason" (kung meron) at idinaragdag sa
        // notes, para malaman ng estudyante kung bakit hindi na-approve
        // ang request nila.
        $rejectReason = trim($_POST['reject_reason'] ?? '');
        $rejectNote = $rejectReason !== ''
            ? '(Rejected by admin: ' . $rejectReason . ')'
            : '(Rejected by admin.)';

        $stmt = $pdo->prepare(
            "UPDATE reservations
             SET status = 'rejected',
                 notes = CONCAT(COALESCE(notes, ''), CASE WHEN notes IS NULL OR notes = '' THEN '' ELSE ' ' END, ?)
             WHERE id = ?"
        );
        $stmt->execute([$rejectNote, $r['id']]);
        $_SESSION['flash_success'] = 'Reservation rejected.';
    } elseif ($action === 'cancel' && in_array($r['status'], ['pending', 'approved'], true)) {
        // Pwedeng i-cancel ng admin ang isang reservation kahit
        // "pending" o "approved" pa ito (hindi puwede kung
        // "completed" o "rejected" na — wala nang saysay i-cancel yun)
        //
        // Kinukuha rin natin ang "reason" na inilagay ng admin
        // (kung meron) at idinaragdag sa notes ng reservation, para
        // makita ng estudyante KUNG BAKIT ito na-cancel — hindi
        // lang basta nagbago ang status nang walang paliwanag.
        $cancelReason = trim($_POST['cancel_reason'] ?? '');
        $cancelNote = $cancelReason !== ''
            ? '(Cancelled by admin: ' . $cancelReason . ')'
            : '(Cancelled by admin.)';

        $stmt = $pdo->prepare(
            "UPDATE reservations
             SET status = 'cancelled',
                 notes = CONCAT(COALESCE(notes, ''), CASE WHEN notes IS NULL OR notes = '' THEN '' ELSE ' ' END, ?)
             WHERE id = ?"
        );
        $stmt->execute([$cancelNote, $r['id']]);
        $_SESSION['flash_success'] = 'Reservation cancelled.';
    } elseif ($action === 'complete' && $r['status'] === 'approved') {
        // Ginagamit ito para markahan na tapos na ang isang
        // reservation (lumipas na yung oras/petsa), para sa
        // record-keeping at reports
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'completed' WHERE id = ?");
        $stmt->execute([$r['id']]);
        $_SESSION['flash_success'] = 'Reservation marked as completed.';
    }

    // Pagkatapos ng kahit anong action, ibinabalik natin ang admin
    // sa parehong page para makita nila ang bagong status
    header('Location: ' . BASE_URL . 'admin/reservation_view.php?id=' . $r['id']);
    exit;
}

$pageTitle = 'Reservation #' . $r['id'];
require_once __DIR__ . '/../includes/header.php';
?>

<a href="reservations.php" class="d-inline-block mb-3">&larr; Back to Reservations</a>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card p-4">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <h4 class="fw-bold mb-0">Reservation Details</h4>
        <span class="badge fs-6 <?= statusBadgeClass($r['status']) ?>"><?= ucfirst($r['status']) ?></span>
      </div>
      <table class="table table-borderless mb-0">
        <tr><th style="width:160px;">Requested By</th><td><?= clean($r['full_name']) ?> (<?= clean($r['email']) ?>)</td></tr>
        <tr><th>Reservation Code</th><td><code><?= clean($r['reservation_code']) ?></code></td></tr>
        <tr><th>Court</th><td><span class="badge bg-primary"><?= clean($r['court']) ?></span></td></tr>
        <tr><th>Date</th><td><?= formatDate($r['reservation_date']) ?></td></tr>
        <tr><th>Time</th><td><?= formatTime($r['start_time']) ?> - <?= formatTime($r['end_time']) ?></td></tr>
        <tr><th>Purpose</th><td><?= clean($r['purpose']) ?></td></tr>
        <tr><th>Participants</th><td><?= (int) $r['participants'] ?></td></tr>
        <?php if (!empty($r['notes'])): ?><tr><th>Notes</th><td><?= clean($r['notes']) ?></td></tr><?php endif; ?>
        <tr><th>Submitted</th><td><?= date('F j, Y g:i A', strtotime($r['created_at'])) ?></td></tr>
      </table>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card p-4">
      <h5 class="fw-bold mb-3">Actions</h5>

      <?php if ($r['status'] === 'pending'): ?>
        <form method="POST" class="mb-2" data-confirm="Approve this reservation?">
          <input type="hidden" name="action" value="approve">
          <button type="submit" class="btn btn-success w-100">
            <i class="bi bi-check-circle"></i> Approve
          </button>
        </form>
        <form method="POST" data-confirm="Reject this reservation? The user will be notified via their dashboard.">
          <input type="hidden" name="action" value="reject">
          <label class="form-label small text-muted mb-1">Reason for rejection (optional, shown to the student)</label>
          <textarea name="reject_reason" class="form-control mb-2" rows="2" placeholder="e.g. Conflicts with a school event"></textarea>
          <button type="submit" class="btn btn-outline-danger w-100">
            <i class="bi bi-x-circle"></i> Reject
          </button>
        </form>
      <?php elseif ($r['status'] === 'approved'): ?>
        <form method="POST" class="mb-2" data-confirm="Mark this reservation as completed? This means the court time has already taken place.">
          <input type="hidden" name="action" value="complete">
          <button type="submit" class="btn btn-primary w-100">Mark as Completed</button>
        </form>
        <form method="POST" data-confirm="Cancel this approved reservation?">
          <input type="hidden" name="action" value="cancel">
          <label class="form-label small text-muted mb-1">Reason for cancellation (optional, shown to the student)</label>
          <textarea name="cancel_reason" class="form-control mb-2" rows="2" placeholder="e.g. Court needed for a school event"></textarea>
          <button type="submit" class="btn btn-outline-danger w-100">
            Cancel Reservation
          </button>
        </form>
      <?php else: ?>
        <p class="text-muted mb-0">No further actions available for a <?= $r['status'] ?> reservation.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
