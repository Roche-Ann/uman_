// ── Toast helper ──────────────────────────────────────────────────────────────
function showToast(message, type, duration) {
    type     = type     || 'warning';
    duration = duration || 3500;
    var icons = { warning: 'bi bi-exclamation-circle-fill text-warning',
                  error:   'bi bi-x-circle-fill text-danger',
                  info:    'bi bi-info-circle-fill text-info',
                  success: 'bi bi-check-circle-fill text-success' };
    var container = document.getElementById('toastContainer');
    var toast = document.createElement('div');
    toast.className = 'settings-toast toast-' + type;
    toast.innerHTML =
        '<i class="' + (icons[type] || icons.warning) + ' toast-icon"></i>' +
        '<span>' + message + '</span>' +
        '<button class="toast-close" aria-label="Dismiss">&times;</button>';
    toast.querySelector('.toast-close').addEventListener('click', function () { dismissToast(toast); });
    container.appendChild(toast);
    // Trigger enter animation
    requestAnimationFrame(function () {
        requestAnimationFrame(function () { toast.classList.add('toast-show'); });
    });
    var timer = setTimeout(function () { dismissToast(toast); }, duration);
    toast._timer = timer;
}
function dismissToast(toast) {
    clearTimeout(toast._timer);
    toast.classList.remove('toast-show');
    toast.addEventListener('transitionend', function () { toast.remove(); }, { once: true });
}

// ── Centered confirmation dialog ──────────────────────────────────────────────
function showConfirmDialog(title, message, onConfirm) {
    var overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    overlay.innerHTML =
        '<div class="confirm-dialog">' +
            '<div class="confirm-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>' +
            '<div class="confirm-title">' + title + '</div>' +
            '<div class="confirm-message">' + message + '</div>' +
            '<div class="confirm-actions">' +
                '<button class="btn btn-outline-secondary confirm-cancel-btn">Cancel</button>' +
                '<button class="btn btn-danger confirm-ok-btn">Yes, proceed</button>' +
            '</div>' +
        '</div>';
    document.body.appendChild(overlay);
    requestAnimationFrame(function () {
        requestAnimationFrame(function () { overlay.classList.add('show'); });
    });
    function closeDialog() {
        overlay.classList.remove('show');
        overlay.addEventListener('transitionend', function () { overlay.remove(); }, { once: true });
    }
    overlay.querySelector('.confirm-ok-btn').addEventListener('click', function () {
        closeDialog();
        if (typeof onConfirm === 'function') onConfirm();
    });
    overlay.querySelector('.confirm-cancel-btn').addEventListener('click', closeDialog);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeDialog(); });
    document.addEventListener('keydown', function escHandler(e) {
        if (e.key === 'Escape') { closeDialog(); document.removeEventListener('keydown', escHandler); }
    });
}

// ── Deletion form ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    // Show / hide password toggle
    var delToggleBtn  = document.getElementById('toggleDeletionPwd');
    var delPwdField   = document.getElementById('deletionPassword');
    var delToggleIcon = document.getElementById('toggleDeletionPwdIcon');
    if (delToggleBtn && delPwdField) {
        delToggleBtn.addEventListener('click', function () {
            var isPassword = delPwdField.type === 'password';
            delPwdField.type = isPassword ? 'text' : 'password';
            delToggleIcon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    }

    var deletionForm      = document.getElementById('deletionForm');
    var deletionConfirmed = false;
    if (deletionForm) {
        deletionForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var reason = document.getElementById('deletionReason').value;
            var pwd    = delPwdField ? delPwdField.value.trim() : '';

            // Always validate fields regardless of confirmation state
            if (!reason && !pwd) {
                showToast('Please select a reason and enter your password.', 'warning');
                document.getElementById('deletionReason').focus();
                deletionConfirmed = false;
                return;
            }
            if (!reason) {
                showToast('Please select a reason for account deletion.', 'warning');
                document.getElementById('deletionReason').focus();
                deletionConfirmed = false;
                return;
            }
            if (!pwd) {
                showToast('Please enter your password to confirm.', 'warning');
                if (delPwdField) delPwdField.focus();
                deletionConfirmed = false;
                return;
            }

            // All fields valid — if user already confirmed, submit to PHP
            if (deletionConfirmed) {
                deletionConfirmed = false;
                deletionForm.submit();
                return;
            }

            // Show centered confirmation dialog
            showConfirmDialog(
                'Delete Account?',
                'Are you sure you want to request account deletion? This cannot be undone and all your data will be permanently removed.',
                function () {
                    deletionConfirmed = true;
                    deletionForm.requestSubmit();
                }
            );
        });
    }
});

// ── Export Data Modal: show/hide password ────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var config = window.USER_SETTINGS_CONFIG || {};

    // Show / hide password toggle
    var toggleBtn  = document.getElementById('toggleExportPwd');
    var pwdField   = document.getElementById('exportPassword');
    var toggleIcon = document.getElementById('toggleExportPwdIcon');
    if (toggleBtn && pwdField) {
        toggleBtn.addEventListener('click', function () {
            var isPassword = pwdField.type === 'password';
            pwdField.type  = isPassword ? 'text' : 'password';
            toggleIcon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    }

    // Reset modal fields and re-enable button on close
    var exportModal = document.getElementById('exportDataModal');
    if (exportModal) {
        exportModal.addEventListener('hidden.bs.modal', function () {
            var form = document.getElementById('exportDataForm');
            if (form) form.reset();
            var btn = document.getElementById('exportSubmitBtn');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-file-earmark-arrow-down me-1"></i>' + (config.exportConfirmBtnLabel || ''); }
            if (pwdField) pwdField.type = 'password';
            if (toggleIcon) toggleIcon.className = 'bi bi-eye';
        });
    }

    // Validate export form with toasts before submit
    var exportForm = document.getElementById('exportDataForm');
    if (exportForm) {
        exportForm.addEventListener('submit', function (e) {
            var reason = document.getElementById('exportReason').value;
            var pwd    = (pwdField ? pwdField.value.trim() : '');

            if (!reason && !pwd) {
                e.preventDefault();
                showToast('Please select a reason and enter your password.', 'warning');
                document.getElementById('exportReason').focus();
                return;
            }
            if (!reason) {
                e.preventDefault();
                showToast('Please select a reason for exporting your data.', 'warning');
                document.getElementById('exportReason').focus();
                return;
            }
            if (!pwd) {
                e.preventDefault();
                showToast('Please enter your current password to confirm.', 'warning');
                if (pwdField) pwdField.focus();
                return;
            }

            // All good — show spinner
            var btn = document.getElementById('exportSubmitBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Exporting…';
            }
        });
    }

});

// ── Server-side feedback after a POST (errors / success / re-open state) ──────
document.addEventListener('DOMContentLoaded', function () {
    var config = window.USER_SETTINGS_CONFIG || {};

    if (config.errorAction) {
        var tabMap = {
            'request_deletion': 'tab-notifications',
            'save_display': 'tab-display',
            'export_data': 'tab-notifications',
        };
        var targetId = tabMap[config.errorAction];
        if (targetId) {
            var tabBtn = document.querySelector('[data-bs-target="#' + targetId + '"]');
            if (tabBtn) tabBtn.click();
        }

        // Re-open the export modal automatically if that action failed
        if (config.showExportModalError) {
            var modalEl = document.getElementById('exportDataModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                new bootstrap.Modal(modalEl).show();
            }
        }

        // Show server-side errors as toasts
        if (Array.isArray(config.errorMessages)) {
            config.errorMessages.forEach(function (msg) {
                showToast(msg, 'error');
            });
        }
    }

    if (config.successMessage) {
        showToast(config.successMessage, 'success', 5000);
    }
});
