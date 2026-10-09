<?php
/**
 * includes/footer.php
 * -----------------------------------------------------------
 * PHASE: Shared Page Layout (bottom half)
 *
 * WHAT THIS FILE IS FOR:
 * Closes whichever layout header.php opened (sidebar shell for
 * logged-in users, or the simple guest layout) and renders the
 * shared confirmation modal + toast notification containers
 * that script.js controls. Must always be paired with a
 * matching includes/header.php on the same page.
 * -----------------------------------------------------------
 */
$useSidebarLayout = $GLOBALS['useSidebarLayout'] ?? false;
?>

<?php if ($useSidebarLayout): ?>

      </main>

      <footer class="app-footer">
        <span>&copy; <?= date('Y') ?> Covered Court Reservation System</span>
        <span class="footer-dot">&middot;</span>
        <span>Colegio de Los Baños</span>
      </footer>
    </div><!-- /.app-main -->
  </div><!-- /.app-shell -->

<?php else: ?>

  </main>

  <footer class="site-footer mt-5">
    <div class="container">
      <div class="row gy-4">
        <div class="col-md-4">
          <div class="footer-brand">
            <img src="<?= BASE_URL ?>assets/images/logo.jpg" alt="School Logo" class="footer-logo">
            Covered Court Reservation
          </div>
          <p class="footer-text">Online booking for the school's covered court — reserve, get approved, and you're set.</p>
        </div>
        <div class="col-6 col-md-2">
          <div class="footer-heading">For Users</div>
          <ul class="footer-links">
            <li><a href="<?= BASE_URL ?>login.php">Login</a></li>
            <li><a href="<?= BASE_URL ?>register.php">Register</a></li>
          </ul>
        </div>
        <div class="col-6 col-md-2">
          <div class="footer-heading">System</div>
          <ul class="footer-links">
            <li><a href="<?= BASE_URL ?>register.php">Get Started</a></li>
          </ul>
        </div>
        <div class="col-md-4">
          <div class="footer-heading">About</div>
          <p class="footer-text">Bookable Mon-Fri evenings and all day on weekends, across two independently-scheduled courts.</p>
        </div>
      </div>
      <div class="footer-bottom">
        <span>&copy; <?= date('Y') ?> Covered Court Reservation System</span>
        <span class="footer-dot">&middot;</span>
        <span>Colegio de Los Baños</span>
      </div>
    </div>
  </footer>

<?php endif; ?>

<!-- ===================================================================
     Shared confirmation modal (replaces native confirm()).
     Any button/form with data-confirm="message" text is intercepted
     by script.js, which shows this modal instead. See script.js.
     =================================================================== -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content confirm-modal">
      <div class="modal-body text-center pt-4">
        <div class="confirm-icon"><i class="bi bi-question-circle"></i></div>
        <p id="confirmModalMessage" class="mb-0 mt-2"></p>
      </div>
      <div class="modal-footer border-0 justify-content-center pb-4">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="confirmModalCancel">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmModalAccept">Yes, Continue</button>
      </div>
    </div>
  </div>
</div>

<!-- ===================================================================
     Shared toast container (replaces native alert()).
     Call showToast('message', 'success'|'error'|'info') from any page.
     =================================================================== -->
<div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="true"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/script.js?v=<?= filemtime(__DIR__ . '/../assets/js/script.js') ?>"></script>
</body>
</html>
