// Toggle between view and edit modes
function enableEdit() {
    document.querySelectorAll('#profileForm .view-mode').forEach(el => el.classList.add('d-none'));
    document.querySelectorAll('#profileForm .edit-mode').forEach(el => el.classList.remove('d-none'));
    document.getElementById('editBtn').classList.add('d-none');
}

function cancelEdit() {
    document.querySelectorAll('#profileForm .view-mode').forEach(el => el.classList.remove('d-none'));
    document.querySelectorAll('#profileForm .edit-mode').forEach(el => el.classList.add('d-none'));
    document.getElementById('editBtn').classList.remove('d-none');
}

// Auto-submit avatar form on file select
function submitAvatarForm() {
    document.getElementById('avatarForm').submit();
}

// Show/hide password
function togglePw(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

// Password strength meter
function checkStrength(val) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 8)              score++;
    if (/[A-Z]/.test(val))            score++;
    if (/[0-9]/.test(val))            score++;
    if (/[^A-Za-z0-9]/.test(val))     score++;

    const levels = [
        { w: '25%',  bg: '#ef4444', text: 'Weak',      color: '#ef4444' },
        { w: '50%',  bg: '#f97316', text: 'Fair',       color: '#f97316' },
        { w: '75%',  bg: '#eab308', text: 'Good',       color: '#eab308' },
        { w: '100%', bg: '#22c55e', text: 'Strong',     color: '#22c55e' },
    ];
    const lvl = levels[Math.max(0, score - 1)];
    fill.style.width      = val.length ? lvl.w  : '0%';
    fill.style.background = val.length ? lvl.bg : 'transparent';
    label.textContent     = val.length ? lvl.text : '';
    label.style.color     = val.length ? lvl.color : '';
}

// Confirm password match hint
document.getElementById('confirmPw').addEventListener('input', function () {
    const hint = document.getElementById('matchHint');
    if (this.value && this.value !== document.getElementById('newPw').value) {
        hint.classList.remove('d-none');
    } else {
        hint.classList.add('d-none');
    }
});

// Client-side password validation before submit
function validatePasswordForm() {
    const newPw     = document.getElementById('newPw').value;
    const confirmPw = document.getElementById('confirmPw').value;
    if (newPw !== confirmPw) {
        document.getElementById('matchHint').classList.remove('d-none');
        return false;
    }
    if (newPw.length < 8) {
        alert('Password must be at least 8 characters.');
        return false;
    }
    return true;
}

// If returning from a password error, switch to password tab automatically.
// If returning from a profile error, re-enable edit mode.
// Reads window.PROFILE_CONFIG, set inline in profile.php from PHP $errors/$_POST state.
(function () {
    const config = window.PROFILE_CONFIG;
    if (!config) return;

    if (config.showPasswordError) {
        document.addEventListener('DOMContentLoaded', function () {
            const tab = document.getElementById('password-tab');
            if (tab) tab.click();
        });
    }

    if (config.showProfileError) {
        document.addEventListener('DOMContentLoaded', enableEdit);
    }
})();
