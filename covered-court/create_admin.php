<?php
/**
 * create_admin.php
 * -----------------------------------------------------------
 * PHASE: Accounts & Authentication (one-time setup)
 *
 * ONE-TIME SETUP SCRIPT.
 * Run this once in your browser to create your first real admin
 * account with a properly generated password hash for YOUR PHP
 * build, then DELETE THIS FILE. Leaving it on a live server would
 * let anyone create an admin account.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/config/config.php';

$done = false;
$errors = [];
$full_name = $email = '';

// PROTEKSYON: Kung meron nang admin account, hindi na papayagan
// itong gumana ulit — kailangang i-delete na ang file na 'to.
// Ito ang dahilan kung bakit ligtas itong i-leave sa mismong
// project folder pansamantala (hindi kaagad kailangang i-delete
// sa unang segundo), pero dapat pa ring tanggalin sa huli.
$existingAdmin = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($existingAdmin) {
        $errors[] = 'An admin account already exists. Delete this file for security.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';

        if ($full_name === '' || $email === '' || strlen($password) < 6) {
            $errors[] = 'All fields are required and password must be at least 6 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email.';
        }

        // Chine-check muna kung may gumagamit na ng email na ito
        // BAGO natin subukang i-insert — ito ang dahilan kung bakit
        // hindi na sumasabog (fatal error) ang page kapag paulit-ulit
        // subukan gamitin ang parehong email
        if (empty($errors)) {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $errors[] = 'This email is already used by an existing account. Please use a different email address, or ask an admin to promote that account to admin via phpMyAdmin instead.';
            }
        }

        if (empty($errors)) {
            try {
                // 'admin' ang role dito (hindi 'user') — ito lang
                // yung tanging file na gumagawa ng admin account
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (full_name, email, password, role, status)
                     VALUES (?, ?, ?, 'admin', 'active')"
                );
                $stmt->execute([$full_name, $email, $hash]);
                $done = true;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'This email is already used by an existing account. Please use a different email address, or ask an admin to promote that account to admin via phpMyAdmin instead.';
                } else {
                    error_log('create_admin insert failed: ' . $e->getMessage());
                    $errors[] = 'We could not create the admin account right now. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Admin Account | Covered Court Reservation</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(circle at 15% 15%, #2e7d32 0%, #14321a 45%, #0a1a0e 100%);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
    padding: 2rem 1rem;
  }
  .setup-shell {
    width: 100%;
    max-width: 880px;
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.35);
    display: grid;
    grid-template-columns: 1fr 1fr;
  }
  @media (max-width: 767.98px) { .setup-shell { grid-template-columns: 1fr; } }

  .setup-side {
    background: linear-gradient(160deg, #2e7d32 0%, #1b5e20 55%, #0d2412 100%);
    color: #fff;
    padding: 2.75rem 2.25rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
  }
  .setup-side::after {
    content: '';
    position: absolute;
    width: 260px; height: 260px;
    border-radius: 50%;
    background: rgba(255,255,255,0.06);
    right: -80px; bottom: -80px;
  }
  .setup-logo { width: 64px; height: 64px; border-radius: 14px; background: #fff; padding: 6px; object-fit: cover; margin-bottom: 1.25rem; }
  .setup-side h2 { font-weight: 700; font-size: 1.5rem; margin-bottom: 0.6rem; }
  .setup-side p { color: rgba(255,255,255,0.85); font-size: 14px; line-height: 1.6; }
  .setup-steps { list-style: none; padding: 0; margin: 1.5rem 0 0; }
  .setup-steps li { display: flex; gap: 10px; align-items: flex-start; font-size: 13px; color: rgba(255,255,255,0.9); margin-bottom: 0.85rem; }
  .setup-steps i { color: #a5d6a7; margin-top: 2px; }
  .setup-badge { position: relative; z-index: 1; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; background: rgba(255,255,255,0.15); display: inline-block; padding: 4px 12px; border-radius: 999px; margin-bottom: 1rem; width: fit-content; }

  .setup-form { padding: 2.75rem 2.5rem; }
  .setup-form h3 { font-weight: 700; color: #14321a; margin-bottom: 0.25rem; }
  .setup-form .sub { color: #6b7280; font-size: 14px; margin-bottom: 1.75rem; }
  .setup-form label { font-size: 13px; font-weight: 600; color: #14321a; margin-bottom: 0.3rem; }
  .setup-form .form-control {
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    padding: 0.65rem 0.9rem;
    font-size: 14px;
  }
  .setup-form .form-control:focus {
    border-color: #2e7d32;
    box-shadow: 0 0 0 3px rgba(46,125,50,0.14);
  }
  .setup-submit {
    background: linear-gradient(135deg, #2e7d32, #1b5e20);
    border: none;
    color: #fff;
    font-weight: 600;
    padding: 0.75rem;
    border-radius: 10px;
    width: 100%;
    margin-top: 0.5rem;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  .setup-submit:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(27,94,32,0.35); color: #fff; }
  .setup-success-icon { width: 64px; height: 64px; border-radius: 50%; background: #e8f5e9; color: #2e7d32; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 1rem; }
</style>
</head>
<body>

<div class="setup-shell">

  <div class="setup-side">
    <div>
      <span class="setup-badge">One-Time Setup</span>
      <img src="assets/images/logo.jpg" alt="School Logo" class="setup-logo">
      <h2>Welcome to Covered Court Reservation</h2>
      <p>Before anyone can approve bookings or manage users, you need one administrator account. Let's set that up now.</p>

      <ul class="setup-steps">
        <li><i class="bi bi-check-circle-fill"></i> Fill in your name, email, and a password below.</li>
        <li><i class="bi bi-check-circle-fill"></i> We'll securely hash the password before saving it.</li>
        <li><i class="bi bi-check-circle-fill"></i> You'll be redirected to log in as admin.</li>
        <li><i class="bi bi-exclamation-triangle-fill" style="color:#ffe08a;"></i> Delete this file afterward for security.</li>
      </ul>
    </div>
    <p style="font-size:12px; color:rgba(255,255,255,0.6); margin:0;">Colegio de Los Baños &middot; Covered Court Reservation System</p>
  </div>

  <div class="setup-form">
    <?php if ($existingAdmin): ?>
      <div class="setup-success-icon"><i class="bi bi-shield-check"></i></div>
      <h3 class="text-center">Admin Already Set Up</h3>
      <p class="sub text-center">An administrator account already exists for this system.</p>
      <div class="alert alert-warning">For security, please <strong>delete create_admin.php</strong> from your project folder now.</div>
      <a href="login.php" class="setup-submit d-block text-center text-decoration-none">Go to Login</a>

    <?php elseif ($done): ?>
      <div class="setup-success-icon"><i class="bi bi-check-lg"></i></div>
      <h3 class="text-center">Admin Account Created!</h3>
      <p class="sub text-center">You can now log in with the credentials you just set.</p>
      <div class="alert alert-success"><strong>Important:</strong> please delete <code>create_admin.php</code> from your project folder now.</div>
      <a href="login.php" class="setup-submit d-block text-center text-decoration-none">Go to Login</a>

    <?php else: ?>
      <h3>Create Admin Account</h3>
      <p class="sub">This form only works once — it locks itself after your first admin is created.</p>

      <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

      <form method="POST">
        <div class="mb-3">
          <label>Full Name</label>
          <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($full_name) ?>" placeholder="e.g. Juan Dela Cruz" required>
        </div>
        <div class="mb-3">
          <label>Email Address</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" placeholder="admin@school.edu.ph" required>
        </div>
        <div class="mb-3">
          <label>Password</label>
          <input type="password" name="password" class="form-control" minlength="6" placeholder="At least 6 characters" required>
        </div>
        <button class="setup-submit" type="submit">Create Admin Account</button>
      </form>
    <?php endif; ?>
  </div>

</div>

</body>
</html>
