<?php
/**
 * admin/court_closures.php
 * -----------------------------------------------------------
 * PHASE: Court Availability Management (Admin)
 *
 * WHAT THIS FILE IS FOR:
 * The court now follows a fixed WEEKLY schedule by default
 * (Mon-Fri 6-9PM, Sat-Sun 7AM-9PM — see config.php). Every date
 * is OPEN for reservation per that schedule UNLESS it appears
 * in the court_closures table below — i.e. a day the admin has
 * explicitly CLOSED the court, usually for student safety
 * (e.g. "Bagyo", typhoon signal, sudden class suspension).
 *
 * This is intentionally the OPPOSITE of the old "no-class
 * whitelist" system — we are not whitelisting the few days the
 * court is usable anymore; we are blacklisting the rare days it
 * is NOT (on top of the normal weekly schedule).
 *
 * SAME-DAY ANNOUNCEMENTS:
 * Admin can add today's date at any time — e.g. a storm is
 * announced at night and classes are suspended for tomorrow.
 * As soon as the admin saves it here, the court is immediately
 * closed for that date.
 *
 * AUTO-CANCEL ON CLOSE:
 * The moment a closure is added, every pending/approved
 * reservation already made for that date is automatically
 * cancelled, since the court is no longer usable that day.
 *
 * REOPENING:
 * If the admin removes a closure (e.g. the suspension was
 * lifted), the date simply reverts to the normal weekly
 * schedule — no reservations need to be restored since none
 * survive a closure.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$errors = [];

// ---------------------------------------------------------------
// AKSYON 1: MAG-ANUNSYO NG COURT CLOSURE (hal. dahil sa bagyo)
// Dito idinaragdag ng admin ang isang bagong entry sa court_closures
// table. Sa sandaling ma-save ito, agad na SASARADO ang araw na ito
// sa calendar ng lahat ng estudyante, at awtomatikong mac-cancel
// ang anumang existing na reservation sa petsang 'yon.
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_closure'])) {
    $date   = trim($_POST['closure_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $errors[] = 'Please select a valid date.';
    } elseif ($date < date('Y-m-d')) {
        // Bawal mag-anunsyo ng closure sa petsang lumipas na
        $errors[] = 'Cannot close a date in the past.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO court_closures (closure_date, reason) VALUES (?, ?)");
            $stmt->execute([$date, $reason !== '' ? $reason : null]);

            // Awtomatikong i-cancel ang lahat ng existing reservations
            // sa petsang ito, dahil sarado na ang court sa araw na 'yon
            $cancelledCount = autoCancelReservationsForDate($pdo, $date);

            $_SESSION['flash_success'] = 'Court closure announced. The court is now closed on this date.'
                . ($cancelledCount > 0 ? " {$cancelledCount} existing reservation(s) on that date were automatically cancelled." : '');
            header('Location: ' . BASE_URL . 'admin/court_closures.php');
            exit;
        } catch (PDOException $e) {
            // Error code "23000" = duplicate entry (pareho ang petsa
            // sa isa nang existing na entry sa database)
            if ($e->getCode() === '23000') {
                $errors[] = 'That date has already been closed.';
            } else {
                error_log('court_closures insert failed: ' . $e->getMessage());
                $errors[] = 'We could not save this right now. Please try again.';
            }
        }
    }
}

// ---------------------------------------------------------------
// AKSYON 2: TANGGALIN ANG ISANG CLOSURE (buksan ulit ang petsang 'yon)
// Babalik na lang ang petsang ito sa normal na weekly schedule.
// Walang kailangang i-restore na reservation dahil wala namang
// nakaligtas na reservation sa isang closed na petsa.
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    $removeId = (int) $_POST['remove_id'];

    $stmt = $pdo->prepare("DELETE FROM court_closures WHERE id = ?");
    $stmt->execute([$removeId]);

    $_SESSION['flash_success'] = 'Court closure removed. This date now follows the normal weekly schedule again.';
    header('Location: ' . BASE_URL . 'admin/court_closures.php');
    exit;
}

// Kunin lahat ng naka-anunsyo nang closure, pinaka-unang petsa
// muna ang lalabas (ORDER BY closure_date ASC)
$closures = $pdo->query("SELECT * FROM court_closures ORDER BY closure_date ASC")->fetchAll();

$pageTitle = 'Court Closures';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-heading closure-heading"><div><span class="page-kicker">SCHEDULE MANAGEMENT</span><h1>Announce a Court Closure</h1><p>
  The court follows the regular weekly schedule by default —
  <strong>Mon-Fri 6:00 PM - 9:00 PM</strong>, <strong>Sat-Sun 7:00 AM - 9:00 PM</strong>.
  Use this page only to CLOSE the court on a specific date (e.g. bagyo, class suspension) for student safety.
  Any existing reservations on that date will be automatically cancelled.
</p></div></div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= clean($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card p-4 reservation-panel">
      <h5 class="fw-bold mb-3">Close a Date</h5>
      <form method="POST" data-confirm="Close the court on this date? Students will no longer be able to reserve on this date, and any existing reservations on it will be automatically cancelled.">
        <div class="mb-3">
          <label class="form-label">Date</label>
          <input type="date" name="closure_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
          <small class="text-muted">Same-day is allowed — you can close today's date right away.</small>
        </div>
        <div class="mb-3">
          <label class="form-label">Reason (optional)</label>
          <input type="text" name="reason" class="form-control" placeholder="e.g. Bagyo, Signal No. 2, Class Suspension">
        </div>
        <button type="submit" name="add_closure" value="1" class="btn btn-danger w-100">Close the Court</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card p-3 reservation-table-card">
      <div class="table-responsive">
        <table class="table table-rounded align-middle mb-0">
          <thead><tr><th>Date</th><th>Reason</th><th></th></tr></thead>
          <tbody>
            <?php if (empty($closures)): ?>
              <tr><td colspan="3" class="text-center text-muted py-4">No closures announced. The court follows the normal weekly schedule on every date.</td></tr>
            <?php else: ?>
              <?php foreach ($closures as $c): ?>
              <tr>
                <td><?= formatDate($c['closure_date']) ?></td>
                <td><?= clean($c['reason'] ?? '—') ?></td>
                <td>
                  <form method="POST" data-confirm="Reopen this date? It will follow the normal weekly schedule again.">
                    <input type="hidden" name="remove_id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Reopen</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
