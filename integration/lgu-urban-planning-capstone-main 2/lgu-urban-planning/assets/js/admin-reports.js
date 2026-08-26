/**
 * admin-reports.js
 * Client-side behaviour for the Reports & Analytics page.
 * Extracted from index.php — all PHP-derived data is read from data-*
 * attributes on the relevant DOM elements instead of being inlined here,
 * so this file can be served as a plain static asset.
 */

/* ============================================================
   LIVE CLOCK BADGE
   Reads settings from data-* attributes on #reportTime:
     data-use12h ("true"/"false"), data-timezone (IANA tz string)
   ============================================================ */
(function () {
    var timeEl = document.getElementById('reportTime');
    if (!timeEl) return;

    var use12h   = timeEl.dataset.use12h === 'true';
    var timezone = timeEl.dataset.timezone || 'Asia/Manila';

    function tick() {
        timeEl.textContent =
            new Intl.DateTimeFormat('en-PH', {
                timeZone: timezone,
                hour: '2-digit', minute: '2-digit', second: '2-digit',
                hour12: use12h
            }).format(new Date());
    }
    tick();
    setInterval(tick, 1000);
})();

/* ============================================================
   EXPORT / PRINT VERIFICATION LOGIC (mirrors admin/users.php)
   togglePasswordVisibility() and openExportModal() are referenced
   from inline onclick="" attributes elsewhere on the page, so they
   stay as top-level (global) function declarations.
   ============================================================ */
const _exportModalEl = document.getElementById('exportVerifyModal');
let _exportType = '', _exportTable = '', _exportUrl = '';

function _elmt(id) { return document.getElementById(id); }

function togglePasswordVisibility(inputId, eyeId) {
    const input = document.getElementById(inputId);
    const eye = document.getElementById(eyeId);
    if (!input || !eye) return;
    if (input.type === "password") {
        input.type = "text";
        eye.classList.replace("bi-eye-slash", "bi-eye");
    } else {
        input.type = "password";
        eye.classList.replace("bi-eye", "bi-eye-slash");
    }
}

function _resetExportModal() {
    var pwd = _elmt('exportPassword');
    if (pwd) {
        pwd.value = '';
        pwd.type  = 'password';
        pwd.classList.remove('is-invalid');
    }
    var reason = _elmt('exportReason');
    if (reason) reason.classList.remove('is-invalid');
    // Reason select's value can't be cleared via classList; reset separately.
    if (reason) reason.value = '';

    var eyeIcon = _elmt('exportEyeIcon');
    if (eyeIcon) eyeIcon.className = 'bi bi-eye-slash';

    var verifyBtn = _elmt('exportVerifyBtn');
    if (verifyBtn) verifyBtn.disabled = false;

    var spinner = _elmt('exportBtnSpinner');
    if (spinner) spinner.classList.add('d-none');

    var btnIcon = _elmt('exportBtnIcon');
    if (btnIcon) btnIcon.classList.remove('d-none');

    var alertBox = _elmt('exportVerifyAlert');
    if (alertBox) alertBox.style.display = 'none';
}

(function () {
    var reasonEl = _elmt('exportReason');
    if (reasonEl) {
        reasonEl.addEventListener('change', function () {
            if (this.value) this.classList.remove('is-invalid');
        });
    }
    var passwordEl = _elmt('exportPassword');
    if (passwordEl) {
        passwordEl.addEventListener('input', function () {
            if (this.value.trim()) this.classList.remove('is-invalid');
        });
    }
})();

function _setBtnLoading(on) {
    var verifyBtn = _elmt('exportVerifyBtn');
    if (verifyBtn) verifyBtn.disabled = on;
    var spinner = _elmt('exportBtnSpinner');
    if (spinner) spinner.classList.toggle('d-none', !on);
    var btnIcon = _elmt('exportBtnIcon');
    if (btnIcon) btnIcon.classList.toggle('d-none', on);
}

function _showToast(msg, type) {
    var toastEl   = _elmt('exportToast');
    var toastMsg  = _elmt('exportToastMsg');
    var toastIcon = _elmt('exportToastIcon');
    if (!toastEl || !toastMsg || !toastIcon) return;

    var config = {
        warning: { bg: 'bg-warning',  text: 'text-dark',  icon: 'bi-exclamation-triangle-fill' },
        danger:  { bg: 'bg-danger',   text: 'text-white', icon: 'bi-x-circle-fill'              },
        success: { bg: 'bg-success',  text: 'text-white', icon: 'bi-check-circle-fill'          },
        info:    { bg: 'bg-info',     text: 'text-dark',  icon: 'bi-info-circle-fill'           }
    };
    var c = config[type] || config['info'];

    toastEl.className = 'toast align-items-center border-0 shadow ' + c.bg + ' ' + c.text;
    toastIcon.className = 'bi ' + c.icon;
    toastMsg.innerText = msg;

    var bsToast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3500 });
    bsToast.show();
}

function openExportModal(type, table, downloadUrl) {
    // Defensive cleanup FIRST, unconditionally, every time this is called.
    // Never gate opening on a flag/event that might not fire (that's what
    // caused the "2 clicks then nothing works" regression) — instead just
    // make sure no stray backdrop or body-lock state from a previous
    // cycle is left over before showing a fresh modal. Idempotent/safe
    // even if nothing needs cleaning up.
    document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');

    _exportType  = type.toUpperCase();
    _exportTable = table;
    _exportUrl   = downloadUrl ? new URL(downloadUrl, window.location.href).href : null;

    _resetExportModal();

    var isPrint = _exportType === 'PRINT';
    var subtitleEl = _elmt('exportVerifySubtitle');
    if (subtitleEl) {
        subtitleEl.textContent = isPrint
            ? 'Verify your identity to print this report'
            : 'Verify your identity to download this export';
    }
    var sectionTitleEl = _elmt('exportVerifySectionTitle');
    if (sectionTitleEl) {
        sectionTitleEl.innerHTML =
            '<i class="bi ' + (isPrint ? 'bi-printer' : 'bi-file-earmark-arrow-down') + '" id="exportVerifySectionIcon"></i> ' +
            (isPrint ? 'Print Details' : 'Export Details');
    }
    var warningEl = _elmt('exportWarningText');
    if (warningEl) {
        warningEl.textContent = isPrint
            ? 'You are about to print official report records. Please confirm your identity to proceed.'
            : 'You are about to export official report records. Please confirm your identity to proceed.';
    }
    var btnLabelEl = _elmt('exportBtnLabel');
    if (btnLabelEl) btnLabelEl.textContent = isPrint ? 'Verify & Print' : 'Verify & Download';
    var btnIconEl = _elmt('exportBtnIcon');
    if (btnIconEl) btnIconEl.className = isPrint ? 'bi bi-printer me-1' : 'bi bi-download me-1';

    bootstrap.Modal.getOrCreateInstance(_exportModalEl).show();
}

if (_exportModalEl) {
    _exportModalEl.addEventListener('hide.bs.modal', function () {
        var focused = _exportModalEl.querySelector(':focus');
        if (focused) focused.blur();
    });

    _exportModalEl.addEventListener('hidden.bs.modal', function () {
        // Same cleanup on the way out too, as a second safety net — but nothing
        // else depends on this event actually firing anymore.
        document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    });
}

function submitExportVerification() {
    var reasonEl    = _elmt('exportReason');
    var passwordEl  = _elmt('exportPassword');
    if (!reasonEl || !passwordEl) {
        _showToast('The export form is not ready. Please refresh the page and try again.', 'danger');
        return;
    }

    var password    = passwordEl.value.trim();
    var reason      = reasonEl.value;
    var missing     = false;

    reasonEl.classList.remove('is-invalid');
    passwordEl.classList.remove('is-invalid');

    if (!reason) {
        reasonEl.classList.add('is-invalid');
        missing = true;
    }
    if (!password) {
        passwordEl.classList.add('is-invalid');
        missing = true;
    }

    if (missing) {
        if (!reason && !password) {
            _showToast('Please select a purpose and enter your password to continue.', 'warning');
        } else if (!reason) {
            _showToast('Please select a purpose for this export.', 'warning');
        } else {
            _showToast('Please enter your password to continue.', 'warning');
        }
        return;
    }

    _setBtnLoading(true);

    var fd = new FormData();
    fd.append('password',    password);
    fd.append('reason',      reason);
    fd.append('export_type', _exportType);
    fd.append('table_name',  _exportTable);

    fetch('../admin/verify_action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(res) {
            if (!res.ok) throw new Error('Server error: ' + res.status);
            return res.json();
        })
        .then(function(data) {
            if (!data.success) {
                _setBtnLoading(false);
                _showToast(data.message || 'Incorrect password. Export denied.', 'danger');
                return;
            }

            if (_exportType === 'PRINT') {
                _showToast('Verification successful. Opening print dialog...', 'success');
                setTimeout(function() {
                    _setBtnLoading(false);
                    bootstrap.Modal.getOrCreateInstance(_exportModalEl).hide();
                    setTimeout(function() { window.print(); }, 300);
                }, 800);
                return;
            }

            // CSV export — password verified, submit the hidden POST form carrying the report data
            var form = document.getElementById('csvExportForm');
            if (!form) {
                _setBtnLoading(false);
                _showToast('Export form not found on this page. Please refresh and try again.', 'danger');
                return;
            }
            _showToast('Verification successful. Starting download...', 'success');
            var tokenInput = form.querySelector('input[name="export_token"]');
            if (!tokenInput) {
                tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = 'export_token';
                form.appendChild(tokenInput);
            }
            tokenInput.value = data.token;

            setTimeout(function() {
                _setBtnLoading(false);
                bootstrap.Modal.getOrCreateInstance(_exportModalEl).hide();
                form.submit();
            }, 800);
        })
        .catch(function() {
            _setBtnLoading(false);
            _showToast('Network error. Please try again.', 'danger');
        });
}

(function () {
    var verifyBtn = _elmt('exportVerifyBtn');
    if (verifyBtn) verifyBtn.onclick = submitExportVerification;
})();
// ===== END EXPORT / PRINT VERIFICATION LOGIC =====

/* ============================================================
   ANALYTICS CHARTS (Chart.js)
   Reads aggregated chart data from data-* attributes on
   .analytics-section (all JSON-encoded by PHP, parsed here):
     data-status, data-month-labels, data-month-values,
     data-brgy-labels, data-brgy-values,
     data-inspector-labels, data-inspector-values,
     data-yoy-current, data-yoy-prev,
     data-current-year, data-prev-year
   ============================================================ */
document.addEventListener('DOMContentLoaded', function() {
    var section = document.querySelector('.analytics-section');
    if (!section) return;

    function parseJSON(str, fallback) {
        if (!str) return fallback;
        try { return JSON.parse(str); } catch (e) { return fallback; }
    }

    const statusValues     = parseJSON(section.dataset.status, [0, 0, 0]);
    const monthLabels      = parseJSON(section.dataset.monthLabels, []);
    const monthValues      = parseJSON(section.dataset.monthValues, []);
    const brgyLabels       = parseJSON(section.dataset.brgyLabels, []);
    const brgyValues       = parseJSON(section.dataset.brgyValues, []);
    const inspectorLabels  = parseJSON(section.dataset.inspectorLabels, []);
    const inspectorValues  = parseJSON(section.dataset.inspectorValues, []);
    const yoyCurrent       = parseInt(section.dataset.yoyCurrent, 10) || 0;
    const yoyPrev          = parseInt(section.dataset.yoyPrev, 10) || 0;
    const currentYearStr   = section.dataset.currentYear || '';
    const prevYearStr      = section.dataset.prevYear || '';
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

    if (isDark) {
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.scale.grid.color = 'rgba(255, 255, 255, 0.1)';
    }

    // New YoY Comparison Chart
    new Chart(document.getElementById('yoyGrowthChart'), {
        type: 'bar',
        data: {
            labels: [prevYearStr, currentYearStr],
            datasets: [{
                label: 'Total Applications',
                data: [yoyPrev, yoyCurrent],
                backgroundColor: isDark ? ['#475569', '#10b981'] : ['#94a3b8', '#10b981'],
                borderRadius: 8
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    new Chart(document.getElementById('permitDoughnutChart'), {
        type: 'doughnut',
        data: {
            labels: ['Approved', 'Rejected', 'Pending'],
            datasets: [{
                data: statusValues,
                backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
                hoverOffset: 10
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('revenueBarChart'), {
        type: 'bar',
        data: {
            labels: monthLabels,
            datasets: [{
                label: 'Applications',
                data: monthValues,
                backgroundColor: '#3b82f6',
                borderRadius: 5
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    new Chart(document.getElementById('barangayHorizontalChart'), {
        type: 'bar',
        data: {
            labels: brgyLabels.length ? brgyLabels : ['No Data'],
            datasets: [{
                label: 'Project Count',
                data: brgyValues.length ? brgyValues : [0],
                backgroundColor: '#6366f1',
                borderRadius: 5
            }]
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    new Chart(document.getElementById('inspectorWorkloadChart'), {
        type: 'bar',
        data: {
            labels: inspectorLabels.length ? inspectorLabels : ['No Data'],
            datasets: [{
                label: 'Inspections Assigned',
                data: inspectorValues.length ? inspectorValues : [0],
                backgroundColor: '#f59e0b',
                borderRadius: 5
            }]
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
});
