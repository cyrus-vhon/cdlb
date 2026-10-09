<?php
/**
 * register.php
 * -----------------------------------------------------------
 * PHASE: Accounts & Authentication
 *
 * WHAT THIS FILE IS FOR:
 * Lets a student/faculty create a new account. Every new
 * account is created with role='user' (never 'admin') and
 * status='active'. Passwords are hashed with password_hash()
 * before being saved — never stored as plain text.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$errors = [];
$full_name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    // ---- HAKBANG 1: Validation ng mga input ----
    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password and confirmation do not match.';
    }

    // ---- HAKBANG 2: Siguraduhin na wala pang gumagamit ng email na ito ----
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    // ---- HAKBANG 3: Gawin ang account (kung walang error) ----
    if (empty($errors)) {
        try {
            // MAHALAGA: hindi natin isino-save ang password mismo,
            // kundi ang "hash" nito — isang paraan ng pag-encrypt
            // na hindi na-babalik pa sa orihinal na password. Kahit
            // makuha ng may masamang balak ang database, hindi nila
            // makikita ang totoong password ng user.
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, email, password, role, status)
                 VALUES (?, ?, ?, 'user', 'active')"
            );
            // Palaging 'user' ang role dito (hindi 'admin') — ang
            // pag-gawa ng admin account ay sa create_admin.php lang
            // pwedeng gawin, hindi dito sa normal na registration
            $stmt->execute([$full_name, $email, $hash]);

            $_SESSION['flash_success'] = 'Account created successfully. Please log in.';
            header('Location: ' . BASE_URL . 'login.php');
            exit;
        } catch (PDOException $e) {
            // Error code "23000" = duplicate entry — posibleng
            // may sumunod na nag-register ng parehong email habang
            // tayo ay nagcheck (napaka-bihirang mangyari, pero
            // possible pa rin)
            if ($e->getCode() === '23000') {
                $errors[] = 'An account with that email already exists.';
            } else {
                $errors[] = 'We could not create your account right now. Please try again.';
                error_log('Register insert failed: ' . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-layout auth-layout-register">
  <section class="auth-story" aria-label="Create your reservation account">
    <span class="auth-kicker"><i class="bi bi-building me-2"></i>COLEGIO DE LOS BAÑOS</span>
    <h1>Your next game starts here.</h1>
    <p>Create an account to check court availability and send a reservation request online.</p>
    <div class="auth-benefits">
      <div><i class="bi bi-check-circle-fill"></i><span><strong>Free to reserve</strong><small>No reservation fee.</small></span></div>
      <div><i class="bi bi-calendar3"></i><span><strong>Two court schedules</strong><small>Choose the court and time that work for your game.</small></span></div>
      <div><i class="bi bi-person-check"></i><span><strong>Track your request</strong><small>Check your reservation status after submitting.</small></span></div>
    </div>
    <div class="auth-story-decoration" aria-hidden="true"><span></span><span></span><span></span></div>
  </section>

  <div class="card auth-card">
    <div class="auth-form-heading"><span class="auth-form-icon"><i class="bi bi-person-plus"></i></span><div><span class="page-kicker">GET STARTED</span><h2>Create an account</h2><p>Fill in your details to register.</p></div></div>

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
      <label class="form-label">Full Name</label>
      <input type="text" name="full_name" class="form-control" value="<?= clean($full_name) ?>" autocomplete="name" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Email Address</label>
      <input type="email" name="email" class="form-control" value="<?= clean($email) ?>" autocomplete="email" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" minlength="6" autocomplete="new-password" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Confirm Password</label>
      <input type="password" name="confirm_password" class="form-control" minlength="6" autocomplete="new-password" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Create account <i class="bi bi-arrow-right ms-1"></i></button>
  </form>

  <p class="text-center mt-3 mb-0">
    Already registered? <a href="<?= BASE_URL ?>login.php">Sign in</a>
  </p>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
