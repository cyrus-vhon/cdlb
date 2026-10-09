/**
 * assets/js/script.js
 * Client-side convenience only. The PHP backend re-validates
 * everything (never trust the browser for security checks).
 *
 * This file also provides two shared UI helpers used across the
 * whole site instead of native browser popups:
 *   - data-confirm="message"  -> nice modal instead of confirm()
 *   - showToast(message, type) -> toast instead of alert()
 */

document.addEventListener('DOMContentLoaded', function () {

  // Fade out before ordinary same-site page navigation. Skip modified
  // clicks, downloads, new tabs, hash links, and links handled by confirms.
  document.addEventListener('click', function (event) {
    const clickedElement = event.target instanceof Element ? event.target : event.target.parentElement;
    const link = clickedElement && clickedElement.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (link.target || link.hasAttribute('download') || link.hasAttribute('data-confirm-action')) return;

    let destination;
    try { destination = new URL(link.href, window.location.href); } catch (_) { return; }
    if (destination.origin !== window.location.origin) return;
    if (destination.pathname === window.location.pathname && destination.search === window.location.search) return;

    event.preventDefault();
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const delay = reducedMotion ? 1 : 360;
    document.body.classList.add('page-leaving');
    window.setTimeout(function () { window.location.assign(destination.href); }, delay);
  });
  window.addEventListener('pageshow', function () {
    document.body.classList.remove('page-leaving');
  });

  // ---- Sidebar mobile toggle (off-canvas drawer) -----------------
  const sidebar = document.getElementById('appSidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarBackdrop = document.getElementById('sidebarBackdrop');

  function openSidebar() {
    if (sidebar) sidebar.classList.add('open');
    if (sidebarBackdrop) sidebarBackdrop.classList.add('open');
  }
  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (sidebarBackdrop) sidebarBackdrop.classList.remove('open');
  }
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function () {
      if (sidebar && sidebar.classList.contains('open')) closeSidebar();
      else openSidebar();
    });
  }
  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', closeSidebar);
  }
  // Close the drawer automatically after tapping a nav link (mobile UX)
  document.querySelectorAll('.sidebar .side-link:not(.side-link-logout)').forEach(function (link) {
    link.addEventListener('click', closeSidebar);
  });

  // ---- Staggered entrance animation for cards -------------------
  // Purely visual polish; safe no-op if the browser prefers reduced
  // motion (the CSS animation itself is gated behind that media query).
  var animatable = document.querySelectorAll('main .card, main .stat-card');
  animatable.forEach(function (el, i) {
    el.classList.add('fade-in-up');
    el.style.animationDelay = Math.min(i * 45, 300) + 'ms';
  });

  // ---- Reservation form: basic front-end validation -------------
  const form = document.getElementById('reservationForm');
  if (form) {
    const dateInput  = document.getElementById('reservation_date');
    const startInput = document.getElementById('start_time');
    const endInput   = document.getElementById('end_time');

    if (dateInput) {
      const today = new Date().toISOString().split('T')[0];
      dateInput.setAttribute('min', today);
    }

    form.addEventListener('submit', function (e) {
      const errors = [];

      if (dateInput && dateInput.value) {
        const today = new Date().toISOString().split('T')[0];
        if (dateInput.value < today) {
          errors.push('Reservation date cannot be in the past.');
        }
      }

      if (startInput && endInput && startInput.value && endInput.value) {
        if (startInput.value >= endInput.value) {
          errors.push('Start time must be before end time.');
        }
      }

      if (errors.length) {
        e.preventDefault();
        e.stopImmediatePropagation(); // don't also trigger the confirm modal below
        showFormError(errors.join(' '));
      }
    });
  }

  function showFormError(message) {
    let box = document.getElementById('jsFormError');
    if (!box) {
      box = document.createElement('div');
      box.id = 'jsFormError';
      box.className = 'alert alert-danger mt-2';
      form.prepend(box);
    }
    box.textContent = message;
  }

  // ---- Availability grid: clicking an available slot fills the --
  // ---- start_time / end_time fields on the reservation form -----
  document.querySelectorAll('.slot-btn[data-available="1"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const start = btn.getAttribute('data-start');
      const end   = btn.getAttribute('data-end');
      const startInput = document.getElementById('start_time');
      const endInput   = document.getElementById('end_time');
      if (startInput) startInput.value = start;
      if (endInput)   endInput.value = end;

      document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
    });
  });

  // =================================================================
  // CONFIRMATION MODAL
  // Any <form> or <button> with data-confirm="Some question?" is
  // intercepted here. Instead of the browser's native confirm()
  // popup, we show a styled Bootstrap modal. If the user accepts,
  // we submit the original form (or click the original button)
  // programmatically.
  // =================================================================
  const confirmModalEl = document.getElementById('confirmModal');
  const confirmMessage = document.getElementById('confirmModalMessage');
  const confirmAccept  = document.getElementById('confirmModalAccept');
  let confirmModal = null;
  let pendingAction = null; // function to run if the user accepts

  if (confirmModalEl && window.bootstrap) {
    confirmModal = new bootstrap.Modal(confirmModalEl);
  }

  function requestConfirmation(message, onAccept) {
    if (!confirmModal) {
      // Fallback (bootstrap failed to load for some reason)
      if (window.confirm(message)) onAccept();
      return;
    }
    confirmMessage.textContent = message || 'Are you sure you want to continue?';
    pendingAction = onAccept;
    confirmModal.show();
  }

  if (confirmAccept) {
    confirmAccept.addEventListener('click', function () {
      const action = pendingAction;
      pendingAction = null;
      confirmModal.hide();
      if (typeof action === 'function') action();
    });
  }

  // Intercept forms that ask for confirmation
  document.querySelectorAll('form[data-confirm]').forEach(function (frm) {
    // Remembers which button actually triggered the submit (e.g. the
    // "Announce No-Class Day" button, whose name="add_date" value="1"
    // the PHP backend depends on to know which action was requested).
    let lastSubmitter = null;

    frm.addEventListener('submit', function (e) {
      if (frm.dataset.confirmed === 'true') return; // already accepted, let it through
      e.preventDefault();
      lastSubmitter = e.submitter || null;
      requestConfirmation(frm.getAttribute('data-confirm'), function () {
        frm.dataset.confirmed = 'true';
        // IMPORTANT: frm.submit() does NOT include the name/value of
        // the button that was clicked (e.g. add_date=1, remove_id),
        // so the PHP side would never see isset($_POST['add_date'])
        // and silently do nothing. requestSubmit(submitter) submits
        // "as if" that same button were clicked, keeping its
        // name/value in the POST data.
        if (typeof frm.requestSubmit === 'function') {
          frm.requestSubmit(lastSubmitter);
        } else {
          frm.submit(); // very old browser fallback
        }
      });
    });
  });

  // Intercept standalone buttons/links that ask for confirmation
  // (e.g. a button that triggers a JS action rather than a form submit)
  document.querySelectorAll('[data-confirm-action]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      const message = el.getAttribute('data-confirm-action');
      const targetFn = el.getAttribute('data-confirm-fn');
      requestConfirmation(message, function () {
        if (targetFn && typeof window[targetFn] === 'function') {
          window[targetFn]();
        } else if (el.tagName === 'A' && el.href) {
          window.location.href = el.href;
        }
      });
    });
  });

  // =================================================================
  // TOAST NOTIFICATIONS
  // Replaces native alert(). Usage: showToast('Saved!', 'success')
  // Types: 'success' | 'error' | 'info'
  // =================================================================
  window.showToast = function (message, type) {
    type = type || 'info';
    const stack = document.getElementById('toastStack');
    if (!stack) { console.log(message); return; }

    const icon = type === 'success' ? 'bi-check-circle-fill'
               : type === 'error'   ? 'bi-x-circle-fill'
               : 'bi-info-circle-fill';

    const toast = document.createElement('div');
    toast.className = 'app-toast app-toast-' + type;
    toast.innerHTML = '<i class="bi ' + icon + '"></i><span></span><button type="button" class="app-toast-close" aria-label="Close">&times;</button>';
    toast.querySelector('span').textContent = message;

    stack.appendChild(toast);
    requestAnimationFrame(function () { toast.classList.add('show'); });

    function remove() {
      toast.classList.remove('show');
      setTimeout(function () { toast.remove(); }, 200);
    }
    toast.querySelector('.app-toast-close').addEventListener('click', remove);
    setTimeout(remove, 4500);
  };

  // Auto-convert server-rendered flash alerts (.alert-success / .alert-danger
  // printed by includes/header.php) into toasts instead of static banners.
  document.querySelectorAll('.alert[data-flash]').forEach(function (el) {
    const type = el.dataset.flash === 'success' ? 'success' : 'error';
    window.showToast(el.textContent.trim(), type);
    el.remove();
  });

});
