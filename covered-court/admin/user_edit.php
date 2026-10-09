<?php
/**
 * admin/user_edit.php
 * -----------------------------------------------------------
 * PHASE: User Management (Admin)
 *
 * WHAT THIS FILE IS FOR:
 * Lets an admin update a specific user's name, email, or reset
 * their password (leave the password field blank to keep their
 * current one). Checks that the new email isn't already used by
 * a different account before saving.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

// Hanapin ang user na ie-edit — dapat "user" role lang (hindi
// pwedeng i-edit dito ang ibang admin account)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
$stmt->execute([$id]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    $_SESSION['flash_error'] = 'User not found.';
    header('Location: ' . BASE_URL . 'admin/users.php');
    exit;
}

$errors = [];
$full_name = $targetUser['full_name'];
$email     = $targetUser['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    // Kung may na-type sa password field, dapat 6+ characters ito.
    // Kung WALANG na-type (blangko), okay lang — ibig sabihin
    // gusto lang panatilihin ng admin ang lumang password
    if ($new_password !== '' && strlen($new_password) < 6) {
        $errors[] = 'New password must be at least 6 characters (or leave it blank to keep the current password).';
    }

    // Siguraduhing walang IBANG account na gumagamit na ng bagong
    // email — "id != ?" para hindi masabing "duplicate" yung sarili
    // niyang existing email
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $id]);
        if ($check->fetch()) {
            $errors[] = 'Another account is already using that email address.';
        }
    }

    if (empty($errors)) {
        try {
            // May DALAWANG magkaibang UPDATE query dito, depende
            // kung nagpapalit ng password o hindi:
            if ($new_password !== '') {
                // May bagong password — i-hash muna bago i-save,
                // saka i-update lahat kasama ang password
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ? AND role = 'user'"
                );
                $stmt->execute([$full_name, $email, $hash, $id]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE users SET full_name = ?, email = ? WHERE id = ? AND role = 'user'"
                );
                $stmt->execute([$full_name, $email, $id]);
            }

            $_SESSION['flash_success'] = 'User account updated successfully.';
            header('Location: ' . BASE_URL . 'admin/users.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'Another account is already using that email address.';
            } else {
                error_log('Admin user update failed: ' . $e->getMessage());
                $errors[] = 'We could not save these changes right now. Please try again.';
            }
        }
    }
}

$pageTitle = 'Edit User';
require_once __DIR__ . '/../includes/header.php';

$initials = strtoupper(substr(trim($targetUser['full_name']), 0, 1));
?>

<a href="users.php" class="d-inline-block mb-3">&larr; Back to Users</a>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card p-4 text-center">
      <div class="user-avatar mx-auto mb-3"><?= clean($initials) ?></div>
      <h5 class="fw-bold mb-1"><?= clean($targetUser['full_name']) ?></h5>
      <p class="text-muted mb-2"><?= clean($targetUser['email']) ?></p>
      <span class="badge <?= $targetUser['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
        <?= ucfirst($targetUser['status']) ?>
      </span>
      <hr>
      <table class="table table-borderless text-start mb-0">
        <tr><th class="text-muted small">Joined</th><td><?= date('M j, Y', strtotime($targetUser['created_at'])) ?></td></tr>
        <tr><th class="text-muted small">Role</th><td>Student / Faculty (User)</td></tr>
      </table>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card p-4">
      <h5 class="fw-bold mb-3">Edit Account Details</h5>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" data-confirm="Save these changes to this user's account?">
        <input type="hidden" name="id" value="<?= (int) $targetUser['id'] ?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= clean($full_name) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= clean($email) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">New Password <span class="text-muted fw-normal">(optional)</span></label>
            <input type="password" name="new_password" class="form-control" minlength="6" placeholder="Leave blank to keep current password">
            <small class="text-muted">Only fill this in if you want to reset the user's password.</small>
          </div>
        </div>
        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary">Save Changes</button>
          <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
