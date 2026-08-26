// ── AUDIT_T is now defined inline in audit-logs.php (see bottom of that file) ──
// ── before this script is loaded, so it's available here as a global. ─────────

const CURRENT_FILE = window.location.pathname.split('/').pop() || 'audit-logs.php';

// ===== LIVE SEARCH LOGIC (mirrors admin/users.php) =====
(function () {
    const searchInput    = document.getElementById('searchInput');
    const dateFromFilter = document.getElementById('dateFromFilter');
    const dateToFilter   = document.getElementById('dateToFilter');
    const searchSpinner  = document.getElementById('searchSpinner');
    const tbody          = document.getElementById('auditTableBody');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationNav  = document.getElementById('paginationNav');
    const filterForm     = document.getElementById('auditFilterForm');

    let debounceTimer = null;
    let currentPage   = 1;
    let requestSeq    = 0; // guards against out-of-order responses

    function esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function buildUrl(page) {
        const params = new URLSearchParams();
        params.set('ajax', 'search_logs');
        params.set('action', searchInput.value.trim());
        params.set('date_from', dateFromFilter.value);
        params.set('date_to', dateToFilter.value);
        params.set('p', page);
        return `${CURRENT_FILE}?${params.toString()}`;
    }

    function severityBadge(severity, labels) {
        const config = {
            critical: { cls: 'bg-danger text-white',  icon: 'bi-exclamation-octagon', label: labels.severity_critical },
            warning:  { cls: 'bg-warning text-dark',   icon: 'bi-exclamation-triangle', label: labels.severity_warning },
            info:     { cls: 'bg-info text-white',     icon: 'bi-info-circle',          label: labels.severity_info }
        };
        const c = config[severity] || config.info;
        return `<span class="badge ${c.cls} border-0 shadow-sm px-2 py-1"><i class="bi ${c.icon} me-1"></i>${esc(c.label)}</span>`;
    }

    function rowHtml(log, labels) {
        const refCell = log.entity_type
            ? `${esc(log.entity_type)} <span class="text-secondary fw-bold">#${esc(log.entity_id)}</span>`
            : `<span class="text-muted opacity-50">-</span>`;

        return `<tr onclick="showLogDetails(this)"
            data-user="${esc(log.user)}"
            data-action="${esc(log.action)}"
            data-time="${esc(log.time)}"
            data-details="${esc(log.details)}"
            data-ip="${esc(log.ip)}"
            data-agent="${esc(log.agent)}">
            <td class="ps-4">${severityBadge(log.severity, labels)}</td>
            <td class="small text-secondary">${esc(log.time)}</td>
            <td><div class="fw-bold text-primary small">${esc(log.user)}</div></td>
            <td><span class="badge bg-light text-dark border fw-normal px-2 py-1">${esc(log.action)}</span></td>
            <td class="small font-monospace text-muted">${esc(log.ip)}</td>
            <td class="small text-muted">${refCell}</td>
        </tr>`;
    }

    function renderPagination(data) {
        const { page, totalPages, labels } = data;
        const items = [];
        const item = (label, targetPage, disabled, active) => `
            <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${targetPage}">${label}</a>
            </li>`;

        items.push(item('<i class="bi bi-chevron-double-left"></i>', 1, page <= 1, false));
        items.push(item(esc(labels.prev), page - 1, page <= 1, false));
        const start = Math.max(1, page - 2);
        const end = Math.min(totalPages, page + 2);
        for (let i = start; i <= end; i++) {
            items.push(item(i, i, false, page === i));
        }
        items.push(item(esc(labels.next), page + 1, page >= totalPages, false));
        items.push(item('<i class="bi bi-chevron-double-right"></i>', totalPages, page >= totalPages, false));

        paginationNav.innerHTML = items.join('');
        paginationNav.querySelectorAll('a.page-link').forEach(a => {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                const p = parseInt(this.dataset.page, 10);
                if (!isNaN(p) && p >= 1) doSearch(p);
            });
        });
    }

    function renderInfo(data) {
        const { totalLogs, offset, limit, labels } = data;
        const from = totalLogs > 0 ? offset + 1 : 0;
        const to = Math.min(offset + limit, totalLogs);
        paginationInfo.innerHTML = `${esc(labels.showing)} <strong>${from}</strong> ${esc(labels.to)}
            <strong>${to}</strong> ${esc(labels.of)}
            <strong>${totalLogs}</strong> ${esc(labels.entries)}`;
    }

    function doSearch(page) {
        currentPage = page || 1;
        const seq = ++requestSeq;
        searchSpinner.style.display = 'inline-block';

        fetch(buildUrl(currentPage))
            .then(res => res.json())
            .then(data => {
                if (seq !== requestSeq) return; // stale response, ignore
                if (!data.success) return;

                if (!data.rows.length) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted small italic">${esc(data.labels.no_records)}</td></tr>`;
                } else {
                    tbody.innerHTML = data.rows.map(log => rowHtml(log, data.labels)).join('');
                }
                renderInfo(data);
                renderPagination(data);

                // Reflect state in the URL for shareable/bookmarkable links, without reloading
                const qp = new URLSearchParams();
                if (searchInput.value.trim()) qp.set('action', searchInput.value.trim());
                if (dateFromFilter.value) qp.set('date_from', dateFromFilter.value);
                if (dateToFilter.value) qp.set('date_to', dateToFilter.value);
                if (currentPage > 1) qp.set('p', currentPage);
                const newUrl = window.location.pathname + (qp.toString() ? '?' + qp.toString() : '');
                history.replaceState(null, '', newUrl);
            })
            .catch(() => { /* silently ignore network hiccups */ })
            .finally(() => { searchSpinner.style.display = 'none'; });
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => doSearch(1), 350);
    });

    // Date range picker — single clickable field showing "from - to" (mirrors applications.php)
    const dateRangeInput = document.getElementById('auditDateRangeInput');
    const clearDateBtn   = document.getElementById('auditClearDateRange');

    if (dateRangeInput && window.flatpickr) {
        const initialDates = [];
        if (dateFromFilter.value) initialDates.push(dateFromFilter.value);
        if (dateToFilter.value)   initialDates.push(dateToFilter.value);

        const dateRangePicker = flatpickr(dateRangeInput, {
            mode: 'range',
            dateFormat: 'M j, Y',
            defaultDate: initialDates.length ? initialDates : undefined,
            onClose: function (selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    dateFromFilter.value = instance.formatDate(selectedDates[0], 'Y-m-d');
                    dateToFilter.value   = instance.formatDate(selectedDates[1], 'Y-m-d');
                    clearDateBtn.classList.remove('d-none');
                    clearTimeout(debounceTimer);
                    doSearch(1);
                }
            }
        });

        clearDateBtn.addEventListener('click', function () {
            dateRangePicker.clear();
            dateFromFilter.value = '';
            dateToFilter.value   = '';
            clearDateBtn.classList.add('d-none');
            clearTimeout(debounceTimer);
            doSearch(1);
        });
    }

    // "Apply Filters" button still works, just without a full page reload
    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(debounceTimer);
        doSearch(1);
    });
})();

// ===== EXPORT VERIFICATION LOGIC =====
const _exportModalEl = document.getElementById('exportVerifyModal');
let _exportType = '', _exportTable = '', _exportUrl = '';

/* ---- shared helper ---- */
function _elmt(id) { return document.getElementById(id); }

/* ---- shared toggle password visibility ---- */
function togglePasswordVisibility(inputId, eyeId) {
    var input = document.getElementById(inputId);
    var eye   = document.getElementById(eyeId);
    if (input.type === 'password') {
        input.type = 'text';
        eye.classList.replace('bi-eye-slash', 'bi-eye');
    } else {
        input.type = 'password';
        eye.classList.replace('bi-eye', 'bi-eye-slash');
    }
}

/* ---- shared toast ---- */
function _showToast(msg, type) {
    var toastEl   = _elmt('auditToast');
    var toastMsg  = _elmt('auditToastMsg');
    var toastIcon = _elmt('auditToastIcon');
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

/* ---- export: reset modal ---- */
function _resetExportModal() {
    _elmt('exportPassword').value    = '';
    _elmt('exportPassword').type     = 'password';
    _elmt('exportReason').value      = '';
    _elmt('exportPassword').classList.remove('is-invalid');
    _elmt('exportReason').classList.remove('is-invalid');
    _elmt('exportEyeIcon').className = 'bi bi-eye-slash';
    _elmt('exportVerifyBtn').disabled = false;
    _elmt('exportBtnSpinner').classList.add('d-none');
    _elmt('exportBtnIcon').classList.remove('d-none');
}

_elmt('exportReason').addEventListener('change', function () {
    if (this.value) this.classList.remove('is-invalid');
});
_elmt('exportPassword').addEventListener('input', function () {
    if (this.value.trim()) this.classList.remove('is-invalid');
});

/* ---- export: open modal ---- */
function openExportModal(type, table, downloadUrl) {
    _exportType  = type.toUpperCase();
    _exportTable = table;
    _exportUrl   = downloadUrl ? new URL(downloadUrl, window.location.href).href : null;

    _resetExportModal();

    var isPrint = _exportType === 'PRINT';
    _elmt('exportVerifySubtitle').textContent = isPrint
        ? AUDIT_T.print_subtitle
        : 'Verify your identity to download this export';
    _elmt('exportVerifySectionTitle').innerHTML =
        '<i class="bi ' + (isPrint ? 'bi-printer' : 'bi-file-earmark-arrow-down') + '" id="exportVerifySectionIcon"></i> ' +
        (isPrint ? 'Print Details' : 'Export Details');
    _elmt('exportWarningText').textContent = isPrint
        ? AUDIT_T.print_warning
        : AUDIT_T.export_warning;
    _elmt('exportBtnLabel').textContent = isPrint
        ? AUDIT_T.btn_verify_print
        : AUDIT_T.btn_verify_download;
    _elmt('exportBtnIcon').className = isPrint ? 'bi bi-printer me-1' : 'bi bi-download me-1';

    bootstrap.Modal.getOrCreateInstance(_exportModalEl).show();
}

/* ---- clean up on close ---- */
_exportModalEl.addEventListener('hide.bs.modal', function () {
    var f = _exportModalEl.querySelector(':focus'); if (f) f.blur();
});

/* ---- export: loading state ---- */
function _setExportBtnLoading(on) {
    _elmt('exportVerifyBtn').disabled = on;
    _elmt('exportBtnSpinner').classList.toggle('d-none', !on);
    _elmt('exportBtnIcon').classList.toggle('d-none', on);
}

/* ---- export: submit ---- */
function submitExportVerification() {
    var password    = _elmt('exportPassword').value.trim();
    var reason      = _elmt('exportReason').value;
    var reasonEl    = _elmt('exportReason');
    var passwordEl  = _elmt('exportPassword');
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
            _showToast(AUDIT_T.export_select_reason, 'warning');
        } else {
            _showToast(AUDIT_T.export_enter_password, 'warning');
        }
        return;
    }

    _setExportBtnLoading(true);

    var basePath   = window.location.pathname.replace(/\/[^/]+$/, '/');
    var verifyPath = basePath + 'verify_action.php';
    var fd = new FormData();
    fd.append('password',    password);
    fd.append('reason',      reason);
    fd.append('export_type', _exportType);
    fd.append('table_name',  _exportTable);

    fetch(verifyPath, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(res) { if (!res.ok) throw new Error('Server error: ' + res.status); return res.json(); })
        .then(function(data) {
            if (!data.success) {
                _setExportBtnLoading(false);
                _showToast(data.message || AUDIT_T.export_select_reason, 'danger');
                return;
            }

            if (_exportType === 'PRINT') {
                _showToast(AUDIT_T.print_success, 'success');
                setTimeout(function() {
                    _setExportBtnLoading(false);
                    bootstrap.Modal.getOrCreateInstance(_exportModalEl).hide();
                    setTimeout(function() { window.print(); }, 300);
                }, 800);
                return;
            }

            _showToast(AUDIT_T.export_success, 'success');
            var sep         = _exportUrl.includes('?') ? '&' : '?';
            var downloadUrl = _exportUrl + sep + 'export_token=' + encodeURIComponent(data.token);
            var iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = downloadUrl;
            document.body.appendChild(iframe);
            setTimeout(function() {
                document.body.removeChild(iframe);
                _setExportBtnLoading(false);
                bootstrap.Modal.getOrCreateInstance(_exportModalEl).hide();
            }, 3000);
        })
        .catch(function() {
            _setExportBtnLoading(false);
            _showToast(AUDIT_T.export_network_error, 'danger');
        });
}

_elmt('exportVerifyBtn').onclick = submitExportVerification;
// ===== END VERIFICATION LOGIC =====


// ===== LOG DETAIL MODAL =====
function showLogDetails(row) {
    var user    = row.getAttribute('data-user');
    var action  = row.getAttribute('data-action');
    var time    = row.getAttribute('data-time');
    var details = row.getAttribute('data-details');
    var ip      = row.getAttribute('data-ip');
    var agent   = row.getAttribute('data-agent');

    var displayDevice  = 'Unknown Device';
    var displayBrowser = 'Unknown Browser';

    if      (agent.includes('Windows NT 10.0')) displayDevice = 'Windows 10/11 Desktop';
    else if (agent.includes('Android'))          displayDevice = 'Android Mobile';
    else if (agent.includes('iPhone'))           displayDevice = 'iPhone/iOS';
    else if (agent.includes('Macintosh'))        displayDevice = 'Mac Desktop';

    if      (agent.includes('Chrome') && !agent.includes('Edg'))    displayBrowser = 'Google Chrome';
    else if (agent.includes('Edg'))                                  displayBrowser = 'Microsoft Edge';
    else if (agent.includes('Firefox'))                              displayBrowser = 'Mozilla Firefox';
    else if (agent.includes('Safari') && !agent.includes('Chrome')) displayBrowser = 'Apple Safari';

    document.getElementById('modalTitle').innerText        = action;
    document.getElementById('modalUser').innerText         = user;
    document.getElementById('modalTime').innerText         = time;
    document.getElementById('modalIP').innerText           = ip;
    document.getElementById('modalAgentDisplay').innerText = displayDevice + ' (' + displayBrowser + ')';
    document.getElementById('modalAgentRaw').innerText     = agent;
    document.getElementById('modalDetails').innerText      = details ? details : AUDIT_T.no_changes;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('logModal')).show();
}