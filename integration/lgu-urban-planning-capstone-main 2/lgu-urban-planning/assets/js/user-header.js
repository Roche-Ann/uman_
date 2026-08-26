/**
 * user-header.js
 * Client-side behaviour for the shared applicant-portal header/sidebar.
 * Extracted from header.php. None of this logic depends on PHP-derived
 * data, so it can be served as a plain static asset.
 *
 * Load order matters: this file must load AFTER user.js, since the
 * sidebar-toggle patch below wraps window.toggleSidebar as defined there.
 */

/* ============================================================
   MOBILE SIDEBAR OVERLAY TOGGLE
   Patches whatever toggleSidebar() user.js defines.
   ============================================================ */
(function () {
    const _original = window.toggleSidebar;
    window.toggleSidebar = function () {
        if (_original) _original();
        const sidebar  = document.getElementById('sidebar');
        const overlay  = document.getElementById('sidebarOverlay');
        if (!sidebar || !overlay) return;
        /* On mobile (<= 768px) use .show; on desktop keep original collapse logic */
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('active');
        }
    };
})();

/* ============================================================
   LOGIN PRELOADER
   Only relevant when #infra-preloader is present in the DOM
   (rendered once, right after a successful login).
   ============================================================ */
(function () {
    var MIN_DISPLAY_MS = 2800;
    var start = Date.now();
    window.addEventListener('load', function () {
        var elapsed = Date.now() - start;
        var wait = Math.max(0, MIN_DISPLAY_MS - elapsed);
        setTimeout(function () {
            var el = document.getElementById('infra-preloader');
            if (el) el.classList.add('is-hidden');
        }, wait);
    });
})();

/* ============================================================
   LOGOUT CONFIRMATION OVERLAY
   Intercepts logout links and shows a branded overlay before
   navigating away.
   ============================================================ */
(function () {
    var logoutLinks = document.querySelectorAll('a[href$="logout.php"]');
    var logoutOverlay = document.getElementById('logout-overlay');

    logoutLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var href = link.getAttribute('href');

            if (logoutOverlay) {
                logoutOverlay.classList.add('show');
                // Force a reflow so the opacity transition actually plays
                void logoutOverlay.offsetWidth;
                logoutOverlay.classList.add('visible');
            }

            setTimeout(function () {
                window.location.href = href;
            }, 1200);
        });
    });
})();
