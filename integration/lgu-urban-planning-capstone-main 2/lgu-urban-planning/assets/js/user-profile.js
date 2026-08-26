// ── Toast helper ──────────────────────────────────────────────────────────────
function showToast(message, type, duration) {
    type     = type     || 'warning';
    duration = duration || 3500;
    var icons = {
        warning: 'bi bi-exclamation-circle-fill text-warning',
        error:   'bi bi-x-circle-fill text-danger',
        success: 'bi bi-check-circle-fill text-success'
    };
    var container = document.getElementById('toastContainer');
    var toast = document.createElement('div');
    toast.className = 'profile-toast toast-' + type;
    toast.innerHTML =
        '<i class="' + (icons[type] || icons.warning) + ' toast-icon"></i>' +
        '<span>' + message + '</span>' +
        '<button class="toast-close" aria-label="Dismiss">&times;</button>';
    toast.querySelector('.toast-close').addEventListener('click', function () { dismissToast(toast); });
    container.appendChild(toast);
    requestAnimationFrame(function () {
        requestAnimationFrame(function () { toast.classList.add('toast-show'); });
    });
    toast._timer = setTimeout(function () { dismissToast(toast); }, duration);
}
function dismissToast(toast) {
    clearTimeout(toast._timer);
    toast.classList.remove('toast-show');
    toast.addEventListener('transitionend', function () { toast.remove(); }, { once: true });
}

// ── Toggle between view and edit modes ────────────────────────────────────────
function enableEdit() {
    document.querySelectorAll('#profileForm .view-mode').forEach(function (el) { el.classList.add('d-none'); });
    document.querySelectorAll('#profileForm .edit-mode').forEach(function (el) { el.classList.remove('d-none'); });
    document.getElementById('editBtn').classList.add('d-none');
}
function cancelEdit() {
    document.querySelectorAll('#profileForm .view-mode').forEach(function (el) { el.classList.remove('d-none'); });
    document.querySelectorAll('#profileForm .edit-mode').forEach(function (el) { el.classList.add('d-none'); });
    document.getElementById('editBtn').classList.remove('d-none');
}

// ── Auto-submit avatar form on file select ────────────────────────────────────
function submitAvatarForm() {
    document.getElementById('avatarForm').submit();
}

// ── Show/hide password ────────────────────────────────────────────────────────
function togglePw(id, btn) {
    var input = document.getElementById(id);
    var icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

// ── Password strength meter ───────────────────────────────────────────────────
function checkStrength(val) {
    var fill  = document.getElementById('strengthFill');
    var label = document.getElementById('strengthLabel');
    var score = 0;
    if (val.length >= 8)            score++;
    if (/[A-Z]/.test(val))          score++;
    if (/[0-9]/.test(val))          score++;
    if (/[^A-Za-z0-9]/.test(val))   score++;
    var levels = [
        { w: '25%',  bg: '#ef4444', text: 'Weak',   color: '#ef4444' },
        { w: '50%',  bg: '#f97316', text: 'Fair',   color: '#f97316' },
        { w: '75%',  bg: '#eab308', text: 'Good',   color: '#eab308' },
        { w: '100%', bg: '#22c55e', text: 'Strong', color: '#22c55e' },
    ];
    var lvl = levels[Math.max(0, score - 1)];
    fill.style.width      = val.length ? lvl.w   : '0%';
    fill.style.background = val.length ? lvl.bg  : 'transparent';
    label.textContent     = val.length ? lvl.text : '';
    label.style.color     = val.length ? lvl.color : '';
}

document.addEventListener('DOMContentLoaded', function () {
    var config = window.USER_PROFILE_CONFIG || {};

    // ── Confirm password match hint ───────────────────────────────────────────
    document.getElementById('confirmPw').addEventListener('input', function () {
        var hint = document.getElementById('matchHint');
        if (this.value && this.value !== document.getElementById('newPw').value) {
            hint.classList.remove('d-none');
        } else {
            hint.classList.add('d-none');
        }
    });

    // ── Profile form: JS validation before submit ─────────────────────────────
    var profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function (e) {
            var fullName = profileForm.querySelector('[name="full_name"]').value.trim();
            var email    = profileForm.querySelector('[name="email"]').value.trim();
            if (!fullName) {
                e.preventDefault();
                showToast(config.errFullName, 'warning');
                profileForm.querySelector('[name="full_name"]').focus();
                return;
            }
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                e.preventDefault();
                showToast(config.errEmailInvalid, 'warning');
                profileForm.querySelector('[name="email"]').focus();
                return;
            }
        });
    }

    // ── Password form: JS validation before submit ────────────────────────────
    var passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function (e) {
            var currentPw = document.getElementById('currentPw').value;
            var newPw     = document.getElementById('newPw').value;
            var confirmPw = document.getElementById('confirmPw').value;

            if (!currentPw) {
                e.preventDefault();
                showToast('Please enter your current password.', 'warning');
                document.getElementById('currentPw').focus();
                return;
            }
            if (!newPw) {
                e.preventDefault();
                showToast('Please enter a new password.', 'warning');
                document.getElementById('newPw').focus();
                return;
            }
            if (newPw.length < 8) {
                e.preventDefault();
                showToast(config.errPwTooShort, 'warning');
                document.getElementById('newPw').focus();
                return;
            }
            if (!/[A-Z]/.test(newPw) || !/[a-z]/.test(newPw) || !/[0-9]/.test(newPw)) {
                e.preventDefault();
                showToast(config.errPwComplexity, 'warning');
                document.getElementById('newPw').focus();
                return;
            }
            if (newPw !== confirmPw) {
                e.preventDefault();
                document.getElementById('matchHint').classList.remove('d-none');
                showToast(config.pwNoMatch, 'warning');
                document.getElementById('confirmPw').focus();
                return;
            }
        });
    }

    // ── Show server-side feedback as toasts on page load ──────────────────────
    if (config.successMessage) {
        showToast(config.successMessage, 'success', 5000);
    }
    if (Array.isArray(config.errorMessages)) {
        config.errorMessages.forEach(function (msg) {
            showToast(msg, 'error');
        });
    }

    // ── If returning from a password error, switch to password tab ────────────
    if (config.showPasswordError) {
        var passwordTab = document.getElementById('password-tab');
        if (passwordTab) passwordTab.click();
    }

    // ── If returning from a profile error, re-enable edit mode ───────────────
    if (config.showProfileError) {
        enableEdit();
    }

});
