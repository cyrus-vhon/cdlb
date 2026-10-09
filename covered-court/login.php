<?php
/**
 * login.php
 * -----------------------------------------------------------
 * PHASE: Accounts & Authentication
 *
 * WHAT THIS FILE IS FOR:
 * Handles login for BOTH regular users and admins — one form,
 * one flow. After checking email/password with password_verify(),
 * it looks at the user's "role" column to decide whether to send
 * them to the user dashboard or the admin dashboard.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/config/config.php';

// Kung naka-login na ang user, hindi na kailangan pa nila makita ang
// login form — direkta na lang sila ipadala sa tamang dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . (isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'));
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    } else {
        // Hanapin ang user gamit ang email na na-type
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // password_verify() ang gumagawa ng comparison — hindi natin
        // direktang kinukumpara ang password na na-type sa "hash" na
        // naka-save sa database, ang built-in na function ito ang
        // gumagawa ng tamang paraan ng pag-verify
        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid email or password.';
        } elseif ($user['status'] === 'blocked') {
            // Hindi papayagang mag-login kahit tama ang password,
            // kung na-block na ang account ng admin
            $errors[] = 'Your account has been blocked. Please contact the administrator.';
        } else {
            // TAGUMPAY ANG LOGIN — i-save ang impormasyon ng user sa
            // session, para maalala ng system kung sino ang naka-login
            // habang nagba-browse sila sa ibang pages.
            session_regenerate_id(true); // panseguridad: bagong session ID kada login
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role']; // "user" o "admin"

            // Ipadala sa tamang dashboard depende sa role
            header('Location: ' . BASE_URL . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
            exit;
        }
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-layout">
  <section class="auth-story" aria-label="Welcome back">
    <span class="auth-kicker"><i class="bi bi-shield-check me-2"></i>COURT RESERVATION</span>
    <h1>Welcome back.</h1>
    <p>Sign in to check your bookings, follow a request, or reserve your next time on court.</p>
    <div class="auth-story-note"><span class="auth-note-icon"><i class="bi bi-calendar2-check"></i></span><span><strong>Your bookings, in one place</strong><small>See reservation dates and approval status from your account.</small></span></div>
    <div class="auth-story-decoration" aria-hidden="true"><span></span><span></span><span></span></div>
  </section>

  <div class="card auth-card">
    <div class="auth-form-heading"><span class="auth-form-icon"><i class="bi bi-person-check"></i></span><div><span class="page-kicker">YOUR ACCOUNT</span><h2>Sign in</h2><p>Enter your account details to continue.</p></div></div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= clean($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <div class="mb-3">
      <label class="form-label">Email Address</label>
      <input type="email" name="email" class="form-control" value="<?= clean($email) ?>" autocomplete="email" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Sign in <i class="bi bi-arrow-right ms-1"></i></button>
  </form>

  <p class="text-center mt-3 mb-0">
    New to the reservation system? <a href="<?= BASE_URL ?>register.php">Create an account</a>
  </p>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
