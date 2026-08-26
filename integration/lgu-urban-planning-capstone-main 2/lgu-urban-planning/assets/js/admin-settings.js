// Live announcement preview — always visible, falls back to example when empty
// Reads window.SETTINGS_CONFIG, set inline in settings.php from PHP translation
// and POST-error state.
function updatePreview(text) {
    const previewFallback = (window.SETTINGS_CONFIG && window.SETTINGS_CONFIG.previewFallback) || '';
    const previewText = document.getElementById('previewText');
    const charCount   = document.getElementById('charCount');
    previewText.textContent = text.trim() ? text.trim() : previewFallback;
    charCount.textContent = text.length;
}

// Update preview banner color when type changes
// Use a precise regex so only the colour token (e.g. "alert-info") is swapped,
// never the bare "alert" class or any other class that starts with "alert-".
document.getElementById('announcementType').addEventListener('change', function () {
    const preview  = document.getElementById('announcementPreview');
    const newType  = this.value;
    // Remove any existing alert-{color} class then add the new one
    const stripped = preview.className.replace(/\balert-(info|warning|success|danger)\b/g, '').trim();
    preview.className = stripped + ' alert-' + newType;
});

// Prevent Bootstrap's Alert component from ever closing the preview element
(function () {
    var preview = document.getElementById('announcementPreview');
    if (preview) {
        preview.addEventListener('close.bs.alert', function (e) { e.preventDefault(); });
    }
})();

// Re-open correct tab after POST error
(function () {
    const config = window.SETTINGS_CONFIG;
    if (!config || !config.errorAction) return;

    document.addEventListener('DOMContentLoaded', function () {
        const tabMap = {
            'save_announcement': 'tab-announcement',
            'save_locale':       'tab-locale',
            'save_permissions':  'tab-permissions',
        };
        const targetId = tabMap[config.errorAction];
        if (targetId) {
            const tabBtn = document.querySelector('[data-bs-target="#' + targetId + '"]');
            if (tabBtn) tabBtn.click();
        }
    });
})();

// ===== BACKUP EXPORT VERIFICATION =====
(function () {
    var _backupModalEl = document.getElementById('backupVerifyModal');
    if (!_backupModalEl) return; // guard: element must exist

    function _showSettingsToast(msg, type) {
        var toastEl   = document.getElementById('settingsToast');
        var toastMsg  = document.getElementById('settingsToastMsg');
        var toastIcon = document.getElementById('settingsToastIcon');
        if (!toastEl) return;
        var config = {
            warning: { bg: 'bg-warning', text: 'text-dark',  icon: 'bi-exclamation-triangle-fill' },
            danger:  { bg: 'bg-danger',  text: 'text-white', icon: 'bi-x-circle-fill'             },
            success: { bg: 'bg-success', text: 'text-white', icon: 'bi-check-circle-fill'         },
            info:    { bg: 'bg-info',    text: 'text-dark',  icon: 'bi-info-circle-fill'          }
        };
        var c = config[type] || config['info'];
        toastEl.className   = 'toast align-items-center border-0 shadow ' + c.bg + ' ' + c.text;
        toastIcon.className = 'bi ' + c.icon;
        toastMsg.innerText  = msg;
        bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3500 }).show();
    }

    function _showBackupAlert(msg, type) {
        var el = document.getElementById('backupVerifyAlert');
        if (!el) return;
        el.style.display = 'none';
        el.innerHTML = '';
        void el.offsetHeight;
        el.className = 'alert alert-' + type + ' small py-2 mb-3';
        el.innerText = msg;
        el.style.display = 'block';
    }

    function _hideBackupAlert() {
        var el = document.getElementById('backupVerifyAlert');
        if (!el) return;
        el.style.display = 'none';
        el.className = 'alert small py-2 mb-3';
        el.innerText = '';
    }

    function _setBackupBtnLoading(on) {
        var btn     = document.getElementById('backupVerifyBtn');
        var spinner = document.getElementById('backupBtnSpinner');
        var icon    = document.getElementById('backupBtnIcon');
        if (btn)     btn.disabled = on;
        if (spinner) spinner.classList.toggle('d-none', !on);
        if (icon)    icon.classList.toggle('d-none', on);
    }

    function _resetBackupModal() {
        var pw     = document.getElementById('backupPassword');
        var reason = document.getElementById('backupReason');
        var eye    = document.getElementById('backupEyeIcon');
        if (pw)     { pw.value = ''; pw.type = 'password'; }
        if (reason) reason.value = '';
        if (eye)    eye.className = 'bi bi-eye-slash';
        _setBackupBtnLoading(false);
        _hideBackupAlert();
    }

    window.toggleBackupPasswordVisibility = function () {
        var input = document.getElementById('backupPassword');
        var eye   = document.getElementById('backupEyeIcon');
        if (!input || !eye) return;
        if (input.type === 'password') {
            input.type = 'text';
            eye.classList.replace('bi-eye-slash', 'bi-eye');
        } else {
            input.type = 'password';
            eye.classList.replace('bi-eye', 'bi-eye-slash');
        }
    };

    window.openBackupExportModal = function () {
        _resetBackupModal();
        bootstrap.Modal.getOrCreateInstance(_backupModalEl).show();
    };

    function submitBackupVerification() {
        var password = (document.getElementById('backupPassword').value || '').trim();
        var reason   = document.getElementById('backupReason').value;

        if (!reason) { _showSettingsToast('Please select a purpose for this download.', 'warning'); return; }
        if (!password) { _showSettingsToast('Please enter your admin password to continue.', 'warning'); return; }

        _setBackupBtnLoading(true);
        _hideBackupAlert();

        var basePath   = window.location.pathname.replace(/\/[^/]+$/, '/');
        var verifyPath = basePath + 'verify_action.php';
        var fd = new FormData();
        fd.append('password',    password);
        fd.append('reason',      reason);
        fd.append('export_type', 'SQL_BACKUP');
        fd.append('table_name',  'database_backup');

        fetch(verifyPath, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (res) { if (!res.ok) throw new Error('Server error ' + res.status); return res.json(); })
            .then(function (data) {
                if (!data.success) {
                    _setBackupBtnLoading(false);
                    _showBackupAlert(data.message || 'Incorrect password. Download denied.', 'danger');
                    return;
                }
                _showBackupAlert('Verification successful. Starting download...', 'success');
                var downloadUrl = window.location.pathname + '?export=backup&export_token=' + encodeURIComponent(data.token);
                var iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = downloadUrl;
                document.body.appendChild(iframe);
                setTimeout(function () {
                    document.body.removeChild(iframe);
                    _setBackupBtnLoading(false);
                    bootstrap.Modal.getOrCreateInstance(_backupModalEl).hide();
                }, 3000);
            })
            .catch(function () {
                _setBackupBtnLoading(false);
                _showBackupAlert('Network error. Please try again.', 'danger');
            });
    }

    document.getElementById('backupVerifyBtn').addEventListener('click', submitBackupVerification);

    _backupModalEl.addEventListener('hide.bs.modal', function () {
        var f = _backupModalEl.querySelector(':focus'); if (f) f.blur();
    });
    _backupModalEl.addEventListener('hidden.bs.modal', _hideBackupAlert);
})();
// ===== END BACKUP VERIFICATION =====
