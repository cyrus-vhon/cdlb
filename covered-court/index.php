<?php
/** Public landing page for the covered court reservation system. */
require_once __DIR__ . '/config/config.php';

if (isAdmin()) {
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit;
} elseif (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$pageTitle = 'Welcome';
require_once __DIR__ . '/includes/header.php';
?>

<section class="landing-hero mb-5" aria-labelledby="landing-title">
  <div class="landing-hero-copy">
    <span class="landing-eyebrow"><i class="bi bi-building me-2"></i>COLEGIO DE LOS BAÑOS</span>
    <h1 id="landing-title">Make room for your next game.</h1>
    <p class="landing-intro">Find a time, choose a court, and send your reservation request online. The school administrator will review it before it’s confirmed.</p>
    <div class="landing-actions">
      <a href="<?= BASE_URL ?>register.php" class="btn btn-primary btn-lg">Request a court <i class="bi bi-arrow-right ms-2"></i></a>
      <a href="<?= BASE_URL ?>login.php" class="btn btn-outline-secondary btn-lg">Sign in</a>
    </div>
    <div class="landing-note"><i class="bi bi-check-circle-fill"></i><span>Free to reserve <span class="landing-note-divider">·</span> Approval required</span></div>
  </div>
  <div class="landing-hero-panel" aria-label="Court schedule overview">
    <div class="court-illustration" aria-hidden="true">
      <div class="court-line court-center"></div><div class="court-line court-circle"></div>
      <div class="court-key court-key-left"></div><div class="court-key court-key-right"></div>
      <i class="bi bi-dribbble court-ball"></i>
    </div>
    <div class="schedule-card">
      <div class="schedule-icon"><i class="bi bi-clock"></i></div>
      <div><span class="schedule-label">WEEKDAY HOURS</span><strong>6:00 PM – 9:00 PM</strong></div>
      <span class="schedule-days">MON–FRI</span>
    </div>
    <div class="schedule-card">
      <div class="schedule-icon"><i class="bi bi-sun"></i></div>
      <div><span class="schedule-label">WEEKEND HOURS</span><strong>7:00 AM – 9:00 PM</strong></div>
      <span class="schedule-days">SAT–SUN</span>
    </div>
    <div class="hero-panel-caption"><span class="availability-dot"></span> Two courts, independently scheduled</div>
  </div>
</section>

<section class="landing-section mb-5" aria-labelledby="courts-heading">
  <div class="landing-section-heading">
    <div><span class="landing-eyebrow">CHOOSE YOUR GAME</span><h2 id="courts-heading">Two courts. Plenty of ways to play.</h2></div>
    <p>Pick the court that fits your activity when you make a request.</p>
  </div>
  <div class="row g-3">
    <div class="col-md-6"><article class="court-card h-100"><div class="court-card-icon"><i class="bi bi-basket2"></i></div><div><span class="court-card-label">COURT 1</span><h3>Basketball &amp; volleyball</h3><p>Reserved for basketball or volleyball bookings.</p></div><span class="court-card-arrow"><i class="bi bi-arrow-up-right"></i></span></article></div>
    <div class="col-md-6"><article class="court-card h-100"><div class="court-card-icon court-card-icon-alt"><i class="bi bi-circle-half"></i></div><div><span class="court-card-label">COURT 2</span><h3>Volleyball, badminton &amp; pickleball</h3><p>Badminton and pickleball bookings may share Court 2. Basketball and volleyball need the full court.</p></div><span class="court-card-arrow"><i class="bi bi-arrow-up-right"></i></span></article></div>
  </div>
</section>

<section class="booking-steps mb-5" aria-labelledby="steps-heading">
  <div class="landing-section-heading">
    <div><span class="landing-eyebrow">HOW IT WORKS</span><h2 id="steps-heading">From request to game time.</h2></div>
    <p>You can follow your request status from your account.</p>
  </div>
  <div class="row g-3">
    <div class="col-md-4"><article class="step-card"><span class="step-number">01</span><i class="bi bi-calendar2-check"></i><h3>Choose a time</h3><p>Check availability and select a court and time slot.</p></article></div>
    <div class="col-md-4"><article class="step-card"><span class="step-number">02</span><i class="bi bi-send"></i><h3>Send your request</h3><p>Submit your booking for the administrator to review.</p></article></div>
    <div class="col-md-4"><article class="step-card"><span class="step-number">03</span><i class="bi bi-bell"></i><h3>Track its status</h3><p>Check My Reservations to see whether it’s approved.</p></article></div>
  </div>
  <p class="booking-help"><i class="bi bi-info-circle me-2"></i>Need to change or cancel an approved booking, or ask about facility rules? Contact the school administrator.</p>
</section>

<section class="landing-cta" aria-label="Start a reservation">
  <div><span class="landing-eyebrow">READY TO PLAY?</span><h2>Find a court and make your request.</h2></div>
  <a href="<?= BASE_URL ?>register.php" class="btn btn-light btn-lg">Get started <i class="bi bi-arrow-right ms-2"></i></a>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
