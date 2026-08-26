const CURRENT_FILE = window.location.pathname.split('/').pop() || 'users.php';

// ===== LIVE SEARCH LOGIC =====
(function () {
    const searchInput   = document.getElementById('searchInput');
    const roleFilter     = document.getElementById('roleFilter');
    const searchSpinner  = document.getElementById('searchSpinner');
    const tbody          = document.getElementById('usersTableBody');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationNav  = document.getElementById('paginationNav');
    const filterForm     = document.getElementById('userFilterForm');

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
        params.set('action', 'search_users');
        params.set('search', searchInput.value.trim());
        params.set('role', roleFilter.value);
        params.set('p', page);
        return `${CURRENT_FILE}?${params.toString()}`;
    }

    function rowHtml(u, labels) {
        const statusClass = u.is_active ? 'status-active' : 'status-inactive';
        const statusLabel = u.is_active ? labels.active : labels.inactive;
        const dotClass = u.is_online ? 'online-dot' : 'offline-dot';
        const dotTitle = u.is_online ? labels.online : labels.offline;

        let idCell;
        if (u.is_staff) {
            idCell = `<span class="text-muted small">${esc(labels.staff)}</span>`;
        } else {
            const vClass = u.is_verified ? 'text-success' : 'text-warning';
            const vIcon  = u.is_verified ? 'bi-check-circle-fill' : 'bi-clock-history';
            const vText  = u.is_verified ? labels.verified : labels.pending;
            idCell = `<span class="small fw-bold cursor-pointer ${vClass}" onclick="openVerificationModal(${u.id}, '${esc(u.first_name + ' ' + u.last_name)}')">
                        <i class="bi ${vIcon}"></i> ${esc(vText)}
                      </span>`;
        }

        const activateBtn = !u.is_active
            ? `<button type="button" class="btn btn-sm btn-outline-success border-0" onclick="quickAction(${u.id}, 'activate')">${esc(labels.activate)}</button>`
            : '';

        // Build a plain object (mirrors PHP $user array) for the editUser() JS function
        const userObj = {
            id: u.id, first_name: u.first_name, last_name: u.last_name,
            username: u.username, email: u.email, role: u.role, phone: u.phone
        };

        return `<tr>
            <td>
                <div class="d-flex align-items-center">
                    <div class="${dotClass}" title="${esc(dotTitle)}"></div>
                    <div>
                        <div class="fw-bold">${esc(u.first_name + ' ' + u.last_name)}</div>
                        <div class="text-muted small">${esc(u.email)} | @${esc(u.username)}</div>
                    </div>
                </div>
            </td>
            <td><span class="badge bg-secondary text-uppercase" style="font-size: 0.65rem;">${esc(u.role)}</span></td>
            <td><span class="badge px-3 ${statusClass}">${esc(statusLabel)}</span></td>
            <td>${idCell}</td>
            <td class="text-center d-print-none">
                <button type="button" class="btn btn-sm btn-outline-dark" onclick="viewLogs(${u.id}, '${esc(u.first_name + ' ' + u.last_name)}')"><i class="bi bi-clock-history"></i></button>
                <button type="button" class="btn btn-sm btn-light border" onclick='editUser(${JSON.stringify(userObj)})'><i class="bi bi-pencil-square"></i> ${esc(labels.edit)}</button>
                ${activateBtn}
            </td>
        </tr>`;
    }

    function renderPagination(data) {
        const { page, totalPages } = data;
        const items = [];
        const item = (label, targetPage, disabled, active) => `
            <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${targetPage}">${label}</a>
            </li>`;

        items.push(item('<i class="bi bi-chevron-double-left"></i>', 1, page <= 1, false));
        items.push(item('<i class="bi bi-chevron-left"></i> <span class="pg-label">Prev</span>', page - 1, page <= 1, false));
        const start = Math.max(1, page - 2);
        const end = Math.min(totalPages, page + 2);
        for (let i = start; i <= end; i++) {
            items.push(item(i, i, false, page === i));
        }
        items.push(item('<span class="pg-label">Next</span> <i class="bi bi-chevron-right"></i>', page + 1, page >= totalPages, false));
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
        const { totalUsers, offset, limit, labels } = data;
        const from = totalUsers > 0 ? offset + 1 : 0;
        const to = Math.min(offset + limit, totalUsers);
        paginationInfo.innerHTML = `${esc(labels.showing)} <strong>${from}</strong> ${esc(labels.to)}
            <strong>${to}</strong> ${esc(labels.of)}
            <strong>${totalUsers}</strong> ${esc(labels.users)}`;
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

                if (!data.users.length) {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">No matching users found.</td></tr>`;
                } else {
                    tbody.innerHTML = data.users.map(u => rowHtml(u, data.labels)).join('');
                }
                renderInfo(data);
                renderPagination(data);

                // Reflect state in the URL for shareable/bookmarkable links, without reloading
                const qp = new URLSearchParams();
                if (searchInput.value.trim()) qp.set('search', searchInput.value.trim());
                if (roleFilter.value) qp.set('role', roleFilter.value);
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

    roleFilter.addEventListener('change', () => doSearch(1));

    // "Apply Filter" button still works, just without a full page reload
    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(debounceTimer);
        doSearch(1);
    });
})();

// ===== EXPORT VERIFICATION LOGIC =====
const _exportModalEl = document.getElementById('exportVerifyModal');
let _exportType = '', _exportTable = '', _exportUrl = '';

/* ---- helpers ---- */
function _elmt(id) { return document.getElementById(id); }

function _resetExportModal() {
    _elmt('exportPassword').value   = '';
    _elmt('exportPassword').type    = 'password';
    _elmt('exportReason').value     = '';
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

function _setBtnLoading(on) {
    _elmt('exportVerifyBtn').disabled = on;
    _elmt('exportBtnSpinner').classList.toggle('d-none', !on);
    _elmt('exportBtnIcon').classList.toggle('d-none', on);
}

/* ---- toast notification ---- */
function _showToast(msg, type) {
    var toastEl   = _elmt('exportToast');
    var toastMsg  = _elmt('exportToastMsg');
    var toastIcon = _elmt('exportToastIcon');

    // Map type → Bootstrap bg class + icon
    var config = {
        warning: { bg: 'bg-warning',  text: 'text-dark',  icon: 'bi-exclamation-triangle-fill' },
        danger:  { bg: 'bg-danger',   text: 'text-white', icon: 'bi-x-circle-fill'              },
        success: { bg: 'bg-success',  text: 'text-white', icon: 'bi-check-circle-fill'          },
        info:    { bg: 'bg-info',     text: 'text-dark',  icon: 'bi-info-circle-fill'           }
    };
    var c = config[type] || config['info'];

    // Reset classes, then apply
    toastEl.className = 'toast align-items-center border-0 shadow ' + c.bg + ' ' + c.text;
    toastIcon.className = 'bi ' + c.icon;
    toastMsg.innerText = msg;

    var bsToast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3500 });
    bsToast.show();
}

/* ---- open modal ---- */
function openExportModal(type, table, downloadUrl) {
    _exportType  = type.toUpperCase();
    _exportTable = table;
    _exportUrl   = downloadUrl ? new URL(downloadUrl, window.location.href).href : null;

    _resetExportModal();

    var isPrint = _exportType === 'PRINT';
    _elmt('exportVerifySubtitle').textContent = isPrint
        ? 'Verify your identity to print these records'
        : 'Verify your identity to download this export';
    _elmt('exportVerifySectionTitle').innerHTML =
        '<i class="bi ' + (isPrint ? 'bi-printer' : 'bi-file-earmark-arrow-down') + '" id="exportVerifySectionIcon"></i> ' +
        (isPrint ? 'Print Details' : 'Export Details');
    _elmt('exportWarningText').textContent = isPrint
        ? 'You are about to print sensitive user records. Please confirm your identity to proceed.'
        : window.USERS_CONFIG.exportWarning;
    _elmt('exportBtnLabel').textContent = isPrint
        ? window.USERS_CONFIG.btnVerifyPrint
        : window.USERS_CONFIG.btnVerifyDownload;
    _elmt('exportBtnIcon').className = isPrint ? 'bi bi-printer me-1' : 'bi bi-download me-1';

    bootstrap.Modal.getOrCreateInstance(_exportModalEl).show();
}

/* ---- close modal on hide ---- */
_exportModalEl.addEventListener('hide.bs.modal', function () {
    var focused = _exportModalEl.querySelector(':focus');
    if (focused) focused.blur();
});

/* ---- main submit ---- */
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

    fetch('verify_action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(res) {
            if (!res.ok) throw new Error('Server error: ' + res.status);
            return res.json();
        })
        .then(function(data) {
            if (!data.success) {
                // Wrong password or other server rejection
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

            // Password verified — trigger download directly via iframe
            _showToast('Verification successful. Starting download...', 'success');
            var sep         = _exportUrl.includes('?') ? '&' : '?';
            var downloadUrl = _exportUrl + sep + 'export_token=' + encodeURIComponent(data.token);

            var iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = downloadUrl;
            document.body.appendChild(iframe);

            setTimeout(function() {
                document.body.removeChild(iframe);
                _setBtnLoading(false);
                bootstrap.Modal.getOrCreateInstance(_exportModalEl).hide();
            }, 3000);
        })
        .catch(function() {
            _setBtnLoading(false);
            _showToast('Network error. Please try again.', 'danger');
        });
}

_elmt('exportVerifyBtn').onclick = submitExportVerification;
// ===== END EXPORT VERIFICATION LOGIC =====

function quickAction(id, action) {
    if (confirm('Change status?')) {
        document.getElementById('qa_user_id').value = id;
        document.getElementById('qa_action').value = action;
        document.getElementById('quickActionForm').submit();
    }
}

function togglePasswordVisibility(inputId, eyeId) {
    const input = document.getElementById(inputId);
    const eye = document.getElementById(eyeId);
    if (input.type === "password") {
        input.type = "text";
        eye.classList.replace("bi-eye-slash", "bi-eye");
    } else {
        input.type = "password";
        eye.classList.replace("bi-eye", "bi-eye-slash");
    }
}

function zoomImage(src) {
    if (src.includes('placehold.co')) return; 
    document.getElementById('fullImagePreview').src = src;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('imageZoomModal')).show();
}

function openVerificationModal(userId, name) {
    document.getElementById('v_user_id').value = userId;
    document.getElementById('v_name').innerText = name;
    document.getElementById('v_loading').style.display = 'block';
    document.getElementById('v_content').style.display = 'none';
    document.getElementById('v_footer').style.display = 'none';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('verificationModal')).show();

    fetch(`${CURRENT_FILE}?action=get_verification&user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                const placeholder = 'https://placehold.co/400x300?text=No+Image'; 
                document.getElementById('img_front').src = data.id_front ? data.id_front : placeholder;
                document.getElementById('img_back').src = data.id_back ? data.id_back : placeholder;
                document.getElementById('v_decision').value = data.is_verified ? 'approve' : 'reject';
                
                const selectReason = document.getElementById('v_rejection_reason');
                const customText = document.getElementById('v_custom_reason');
                
                if (data.rejection_reason) {
                    let exists = Array.from(selectReason.options).some(opt => opt.value === data.rejection_reason);
                    if (exists) {
                        selectReason.value = data.rejection_reason;
                        customText.style.display = 'none';
                    } else {
                        selectReason.value = 'Other';
                        customText.value = data.rejection_reason;
                        customText.style.display = 'block';
                    }
                }
                toggleRejectionBox(document.getElementById('v_decision').value);
                document.getElementById('v_loading').style.display = 'none';
                document.getElementById('v_content').style.display = 'block';
                document.getElementById('v_footer').style.display = 'flex';
            }
        });
}

function toggleRejectionBox(val) {
    document.getElementById('rejection_box').style.display = (val === 'reject') ? 'block' : 'none';
}

function checkOtherReason(val) {
    document.getElementById('v_custom_reason').style.display = (val === 'Other') ? 'block' : 'none';
}

function editUser(u) {
    document.getElementById('e_id').value = u.id;
    document.getElementById('e_fname').value = u.first_name;
    document.getElementById('e_lname').value = u.last_name;
    document.getElementById('e_username').value = u.username;
    document.getElementById('e_email').value = u.email;
    document.getElementById('e_phone').value = u.phone || '';
    document.getElementById('e_role').value = u.role;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('editUserModal')).show();
}

function _statusBadgeClass(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'approved') return 'bg-success text-white';
    if (s === 'submitted') return 'bg-primary text-white';
    if (s === 'rejected') return 'bg-danger text-white';
    return 'bg-light text-dark border';
}

function viewLogs(userId, userName) {
    document.getElementById('log_user_name').innerText = userName;
    const content = document.getElementById('logs_content');
    content.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>`;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('logsModal')).show();
    
    fetch(`${CURRENT_FILE}?action=get_history&user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                let appRows = (data.applications || []).map(app => 
                    `<tr><td><b>${app.application_number}</b></td><td>${app.project_name}</td><td><span class="badge ${_statusBadgeClass(app.status)}">${app.status}</span></td><td>${app.created_at}</td></tr>`
                ).join('') || '<tr><td colspan="4" class="text-center">No applications.</td></tr>';
                
                content.innerHTML = `
                    <div class="form-section">
                        <div class="form-section-title"><i class="bi bi-graph-up"></i> Account Overview</div>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="stat-box">
                                    <i class="bi bi-box-arrow-in-right"></i>
                                    <div>
                                        <div class="stat-label">Last Login</div>
                                        <div class="stat-value">${data.last_login}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-box">
                                    <i class="bi bi-file-earmark-text"></i>
                                    <div>
                                        <div class="stat-label">Applications</div>
                                        <div class="stat-value">${data.app_count}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-section-title"><i class="bi bi-list-check"></i> Submitted Applications</div>
                        <div class="table-responsive"><table class="table table-sm">
                            <thead><tr><th>ID</th><th>Project</th><th>Status</th><th>Date</th></tr></thead>
                            <tbody>${appRows}</tbody>
                        </table></div>
                    </div>`;
            }
        });
}

function checkStrength(password, barId) {
    let s = 0;
    if (password.length >= 8) s += 25;
    if (password.match(/[a-z]/)) s += 25;
    if (password.match(/[A-Z]/)) s += 25;
    if (password.match(/[0-9]/)) s += 25;
    let bar = document.getElementById(barId);
    if (bar) {
        bar.style.width = s + "%";
        bar.style.backgroundColor = s <= 50 ? "#dc3545" : (s <= 75 ? "#ffc107" : "#198754");
    }
}

// ===== TOAST-BASED FORM VALIDATION (Create / Edit User) =====
// Replaces the native browser "Please fill out this field." tooltip with
// the same toast notification used by the export flow, so feedback is
// consistent across the page.
function _validateRequiredFields(form) {
    const fields = form.querySelectorAll('[required]');
    let firstInvalid = null;

    fields.forEach(function (field) {
        const invalid = !field.value || !field.value.trim();
        field.classList.toggle('is-invalid', invalid);
        if (invalid && !firstInvalid) firstInvalid = field;
    });

    return firstInvalid;
}

function _wireToastValidation(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', function (e) {
        const firstInvalid = _validateRequiredFields(form);
        if (firstInvalid) {
            e.preventDefault();
            _showToast('Please fill out all required fields.', 'warning');
            firstInvalid.focus();
        }
    });

    // Clear the red state as soon as the user starts fixing that field
    form.querySelectorAll('[required]').forEach(function (field) {
        field.addEventListener('input', function () {
            if (field.value && field.value.trim()) field.classList.remove('is-invalid');
        });
        field.addEventListener('change', function () {
            if (field.value && field.value.trim()) field.classList.remove('is-invalid');
        });
    });
}

_wireToastValidation('createUserForm');
_wireToastValidation('editUserForm');
