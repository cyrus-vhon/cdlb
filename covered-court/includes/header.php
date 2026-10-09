<?php
/**
 * includes/header.php
 * -----------------------------------------------------------
 * PHASE: Shared Page Layout (top half)
 *
 * WHAT THIS FILE IS FOR:
 * The shared page shell every page opens with. Include this
 * AFTER requiring config/config.php. A page can set $pageTitle
 * before including this file.
 *
 * Logged-in users (student/faculty or admin) get a left sidebar
 * layout. Guests (landing/login/register) get a simple top bar.
 * footer.php checks $GLOBALS['useSidebarLayout'] to close the
 * right matching tags, so header.php and footer.php must always
 * be used as a pair on the same page.
 * -----------------------------------------------------------
 */
$pageTitle = $pageTitle ?? 'Covered Court Reservation';
$useSidebarLayout = isLoggedIn();
$GLOBALS['useSidebarLayout'] = $useSidebarLayout;

function navItem($url, $icon, $label, $badge = null) {
    $current = basename($_SERVER['PHP_SELF']);
    $active = (basename($url) === $current) ? 'active' : '';
    echo '<a class="side-link ' . $active . '" href="' . BASE_URL . $url . '">';
    echo '<i class="bi ' . $icon . '"></i><span>' . $label . '</span>';
    if ($badge) echo '<span class="side-link-badge">' . $badge . '</span>';
    echo '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= clean($pageTitle) ?> | Covered Court Reservation</title>
<link rel="icon" type="image/jpeg" href="<?= BASE_URL ?>assets/images/logo.jpg">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>

<?php if ($useSidebarLayout): ?>

  <div class="app-shell">

    <!-- ============== SIDEBAR ============== -->
    <aside class="sidebar" id="appSidebar">
      <div class="sidebar-brand">
        <img src="<?= BASE_URL ?>assets/images/logo.jpg" alt="School Logo" class="sidebar-logo">
        <div>
          <div class="sidebar-brand-title">Covered Court</div>
          <div class="sidebar-brand-sub">Reservation System</div>
        </div>
      </div>

      <nav class="sidebar-nav">
        <?php if (isAdmin()): ?>
          <div class="side-section-label">Admin</div>
          <?php navItem('admin/dashboard.php', 'bi-speedometer2', 'Dashboard'); ?>
          <?php navItem('admin/calendar.php', 'bi-calendar3', 'Calendar'); ?>
          <?php navItem('admin/reservations.php', 'bi-list-check', 'Reservations'); ?>
          <?php navItem('admin/users.php', 'bi-people', 'Users'); ?>
          <?php navItem('admin/court_closures.php', 'bi-calendar2-x', 'Court Closures'); ?>
          <?php navItem('admin/reports.php', 'bi-graph-up', 'Reports'); ?>
        <?php else: ?>
          <div class="side-section-label">Menu</div>
          <?php navItem('dashboard.php', 'bi-speedometer2', 'Dashboard'); ?>
          <?php navItem('reserve.php', 'bi-calendar-plus', 'Reserve Court'); ?>
          <?php navItem('my_reservations.php', 'bi-calendar-check', 'My Reservations'); ?>
        <?php endif; ?>
      </nav>

      <div class="sidebar-footer">
        <div class="sidebar-user">
          <span class="user-avatar-sm"><?= clean(initials($_SESSION['full_name'])) ?></span>
          <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= clean($_SESSION['full_name']) ?></div>
            <div class="sidebar-user-role"><?= isAdmin() ? 'Administrator' : 'Student / Faculty' ?></div>
          </div>
        </div>
        <a href="<?= BASE_URL ?>logout.php" class="side-link side-link-logout" data-confirm-action="Are you sure you want to log out?">
          <i class="bi bi-box-arrow-right"></i><span>Logout</span>
        </a>
      </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- ============== MAIN CONTENT ============== -->
    <div class="app-main">
      <header class="app-topbar">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu"><i class="bi bi-list"></i></button>
        <div class="app-topbar-title"><?= clean($pageTitle) ?></div>
        <?php if (isAdmin()): ?><span class="badge bg-success">Admin</span><?php endif; ?>
      </header>

      <main class="app-content">

<?php else: /* ---------------- guest top bar ---------------- */ ?>

  <nav class="navbar navbar-expand-lg guest-navbar sticky-top">
    <div class="container">
      <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>index.php">
        <img src="<?= BASE_URL ?>assets/images/logo.jpg" alt="School Logo" class="guest-navbar-logo">
        Covered Court Reservation
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto align-items-lg-center">
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>login.php">Login</a></li>
          <li class="nav-item"><a class="btn btn-primary btn-sm ms-lg-2" href="<?= BASE_URL ?>register.php">Register</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <main class="container py-4">

<?php endif; ?>

<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success alert-dismissible fade show" data-flash="success" role="status">
    <?= clean($_SESSION['flash_success']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show" data-flash="error" role="alert">
    <?= clean($_SESSION['flash_error']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>
