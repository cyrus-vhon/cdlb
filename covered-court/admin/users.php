<?php
/**
 * admin/users.php
 * -----------------------------------------------------------
 * PHASE: User Management (Admin)
 *
 * WHAT THIS FILE IS FOR:
 * List of all regular (non-admin) accounts, with search and a
 * Block/Unblock toggle. A blocked user's status becomes
 * 'blocked' in the database, which login.php checks and refuses
 * to let them sign in. An admin can never block their own
 * account (checked explicitly below) to avoid accidentally
 * locking themselves out.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// ---------------------------------------------------------------
// AKSYON: I-BLOCK O I-UNBLOCK ANG ISANG USER
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $targetId = (int) $_POST['user_id'];
    $action   = $_POST['action'];

    // PROTEKSYON: hindi pwedeng i-block ng admin ang sarili niyang
    // account — kung mangyari ito, hindi na makaka-login yung admin
    // kahit sarili niyang account
    if ($targetId === (int) $_SESSION['user_id']) {
        $_SESSION['flash_error'] = 'You cannot block your own account.';
    } elseif (in_array($action, ['block', 'unblock'], true)) {
        // Kapag "block", nagiging 'blocked' ang status. Kapag
        // 'blocked' ito, chine-check ito ng login.php at hindi
        // papayagang mag-login ang user kahit tama ang password nila
        $newStatus = $action === 'block' ? 'blocked' : 'active';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'user'");
        $stmt->execute([$newStatus, $targetId]);
        $_SESSION['flash_success'] = 'User status updated.';
    }
    header('Location: ' . BASE_URL . 'admin/users.php');
    exit;
}

// Search functionality — kung may sinearch, ihahanap sa pangalan
// o email; kung wala, ipapakita lahat ng users
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare(
        "SELECT * FROM users WHERE role = 'user' AND (full_name LIKE ? OR email LIKE ?) ORDER BY created_at DESC"
    );
    $like = "%$search%";
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC");
}
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-heading"><div><span class="page-kicker">ACCOUNT ADMINISTRATION</span><h1>Manage Users</h1><p>Find accounts and review their access status.</p></div></div>

<form method="GET" class="mb-3">
  <div class="input-group" style="max-width:400px;">
    <input type="text" name="q" class="form-control" placeholder="Search by name or email" value="<?= clean($search) ?>">
    <button class="btn btn-outline-primary" type="submit">Search</button>
  </div>
</form>

<div class="card p-3 reservation-table-card">
  <div class="table-responsive">
    <table class="table table-rounded align-middle mb-0">
      <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Joined</th><th>Action</th></tr></thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">No users found.</td></tr>
        <?php else: ?>
          <?php foreach ($users as $u): ?>
          <?php $initials = strtoupper(substr(trim($u['full_name']), 0, 1)); ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="user-avatar user-avatar-sm"><?= clean($initials) ?></div>
                <?= clean($u['full_name']) ?>
              </div>
            </td>
            <td><?= clean($u['email']) ?></td>
            <td>
              <span class="badge <?= $u['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                <?= ucfirst($u['status']) ?>
              </span>
            </td>
            <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
            <td class="text-nowrap">
              <a href="user_edit.php?id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
              <form method="POST" class="d-inline" data-confirm="<?= $u['status'] === 'active' ? 'Block this user? They will not be able to log in until unblocked.' : 'Unblock this user? They will be able to log in again.' ?>">
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <?php if ($u['status'] === 'active'): ?>
                  <input type="hidden" name="action" value="block">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Block</button>
                <?php else: ?>
                  <input type="hidden" name="action" value="unblock">
                  <button type="submit" class="btn btn-sm btn-outline-success">Unblock</button>
                <?php endif; ?>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
