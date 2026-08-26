// ===== LOGIN PRELOADER =====
// Only relevant when #infra-preloader is present (shown once, right after login).
// Guarded so this is a no-op on pages that don't render the preloader markup.
(function () {
    var MIN_DISPLAY_MS = 2800;
    var start = Date.now();
    window.addEventListener('load', function () {
        var el = document.getElementById('infra-preloader');
        if (!el) return;
        var elapsed = Date.now() - start;
        var wait = Math.max(0, MIN_DISPLAY_MS - elapsed);
        setTimeout(function () {
            el.classList.add('is-hidden');
        }, wait);
    });
})();

// ===== SIDEBAR TOGGLE / MOBILE BEHAVIOR / LOGOUT CONFIRMATION =====
(function () {
    function isMobile() { return window.innerWidth <= 768; }

    function closeMobileSidebar() {
        var s = document.getElementById('sidebar');
        var b = document.getElementById('sidebarBackdrop');
        if (s) s.classList.remove('mobile-open');
        if (b) b.classList.remove('show');
        document.body.classList.remove('sidebar-locked');
    }

    // Override toggleSidebar — runs after admin.js has already set its version
    window.toggleSidebar = function () {
        var sidebar  = document.getElementById('sidebar');
        var backdrop = document.getElementById('sidebarBackdrop');
        if (!sidebar) return;
        if (isMobile()) {
            sidebar.classList.toggle('mobile-open');
            if (backdrop) backdrop.classList.toggle('show');
            // Fallback body-scroll lock for browsers without :has() support
            document.body.classList.toggle('sidebar-locked', sidebar.classList.contains('mobile-open'));
        } else {
            // Desktop: original collapse behaviour
            sidebar.classList.toggle('collapsed');
        }
    };

    window.closeMobileSidebar = closeMobileSidebar;

    // Also re-wire the toggle button directly so onclick attr can't be stale
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('sidebarToggle');
        if (btn) {
            btn.onclick = null; // clear any existing onclick
            btn.addEventListener('click', window.toggleSidebar);
        }

        var backdrop = document.getElementById('sidebarBackdrop');
        if (backdrop) {
            backdrop.addEventListener('click', closeMobileSidebar);
        }

        // Close on nav-link click (mobile only)
        document.querySelectorAll('.sidebar .nav-link:not(.nav-link-toggle)').forEach(function (l) {
            l.addEventListener('click', function () {
                if (isMobile()) closeMobileSidebar();
            });
        });

        // Clean up on resize to desktop
        window.addEventListener('resize', function () {
            if (!isMobile()) closeMobileSidebar();
        });

        // Logout confirmation — intercept the sidebar logout link
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
    });
})();
