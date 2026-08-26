var _pendingData   = null;   // the modal data object for the pending action
var _pendingAction = null;   // 'approve' or 'reject'

// ── Open message modal ────────────────────────────────────────────────────────
function openMessage(data) {
    _pendingData = data;

    document.getElementById('messageModalLabel').textContent = data.subject || '(no subject)';

    var metaParts = ['From: ' + data.sender];
    if (data.appNumber) metaParts.push('Application: ' + data.appNumber);
    metaParts.push(data.date);
    document.getElementById('modalMeta').textContent = metaParts.join('  ·  ');

    document.getElementById('modalBody').textContent = data.message;

    // Deletion panel
    var panel   = document.getElementById('deletionPanel');
    var actions = document.getElementById('deletionActions');
    var hint    = document.getElementById('deletionPanelHint');

    if (data.isDeletion && data.isAdmin) {
        panel.classList.remove('d-none');
        actions.innerHTML = '';
        hint.style.display = '';

        if (data.prefVal === '2') {
            actions.innerHTML = '<span class="badge bg-success px-3 py-2" style="font-size:0.85rem;"><i class="bi bi-check-circle me-1"></i>Already Approved</span>';
            hint.style.display = 'none';
        } else if (data.prefVal === '0') {
            actions.innerHTML = '<span class="badge bg-secondary px-3 py-2" style="font-size:0.85rem;"><i class="bi bi-x-circle me-1"></i>Already Rejected</span>';
            hint.style.display = 'none';
        } else {
            actions.innerHTML =
                '<button class="btn btn-sm btn-success px-3 me-2" onclick="showActionToast(\'approve\')">'
                + '<i class="bi bi-check-circle me-1"></i>Approve Deletion</button>'
                + '<button class="btn btn-sm btn-outline-danger px-3" onclick="showActionToast(\'reject\')">'
                + '<i class="bi bi-x-circle me-1"></i>Reject Request</button>';
        }
    } else {
        panel.classList.add('d-none');
    }

    // Footer buttons
    var qStr = '&filter=' + encodeURIComponent(data.filter)
             + (data.q    ? '&q='    + encodeURIComponent(data.q)    : '')
             + (data.page ? '&page=' + encodeURIComponent(data.page) : '');
    var btns = '';
    if (!data.isRead) {
        btns += '<a href="?mark_read=' + data.id + qStr + '" class="btn btn-sm btn-outline-primary">'
              + '<i class="bi bi-envelope-open me-1"></i>Mark as Read</a>';
    }
    btns += '<a href="?delete=' + data.id + qStr + '" class="btn btn-sm btn-outline-danger"'
          + ' onclick="return confirm(\'Delete this message? This cannot be undone.\')">'
          + '<i class="bi bi-trash me-1"></i>Delete</a>';
    document.getElementById('modalFooterLeft').innerHTML = btns;

    // Silently mark as read
    if (!data.isRead) {
        fetch('?mark_read=' + data.id + '&filter=' + encodeURIComponent(data.filter), { redirect: 'manual' })
            .then(function () {
                var row = document.querySelector('.msg-row[data-id="' + data.id + '"]');
                if (row) {
                    row.classList.remove('unread');
                    row.classList.add('read');
                    var dot = row.querySelector('.unread-dot');
                    if (dot) dot.remove();
                    var nb = row.querySelector('.badge.bg-primary');
                    if (nb) nb.remove();
                }
            });
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('messageModal')).show();
}

// ── Action toast ──────────────────────────────────────────────────────────────
function showActionToast(action) {
    _pendingAction = action;
    var toast = document.getElementById('actionToast');

    document.getElementById('toastApproveView').classList.toggle('d-none', action !== 'approve');
    document.getElementById('toastRejectView').classList.toggle('d-none',  action !== 'reject');

    if (action === 'reject') {
        document.getElementById('rejectReasonInput').value = '';
    }

    toast.classList.add('show');
    document.getElementById('actionToastBackdrop').classList.add('show');
    // Focus textarea for reject
    if (action === 'reject') {
        setTimeout(function () {
            document.getElementById('rejectReasonInput').focus();
        }, 50);
    }
}

function closeActionToast() {
    document.getElementById('actionToast').classList.remove('show');
    document.getElementById('actionToastBackdrop').classList.remove('show');
    _pendingAction = null;
}

// ── Submit deletion action via fetch ──────────────────────────────────────────
function submitDeletionAction(action) {
    if (!_pendingData) return;

    var approveBtn = document.getElementById('toastApproveConfirm');
    if (approveBtn && action === 'approve') {
        approveBtn.disabled = true;
        approveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';
    }

    var formData = new FormData();
    formData.append('ajax_deletion_action', action);
    formData.append('target_user_id',       _pendingData.targetUid);
    formData.append('msg_id',               _pendingData.id);
    if (action === 'reject') {
        formData.append('reject_reason', document.getElementById('rejectReasonInput').value.trim());
    }

    fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        body:   formData
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        closeActionToast();

        if (res.ok) {
            // Update the deletion panel inside the modal to show done state
            var actions = document.getElementById('deletionActions');
            var hint    = document.getElementById('deletionPanelHint');
            if (action === 'approve') {
                actions.innerHTML = '<span class="badge bg-success px-3 py-2" style="font-size:0.85rem;"><i class="bi bi-check-circle me-1"></i>Approved</span>';
            } else {
                actions.innerHTML = '<span class="badge bg-secondary px-3 py-2" style="font-size:0.85rem;"><i class="bi bi-x-circle me-1"></i>Rejected</span>';
            }
            hint.style.display = 'none';

            // Remove deletion badge from the list row
            var row = document.querySelector('.msg-row[data-id="' + _pendingData.id + '"]');
            if (row) {
                var delBadge = row.querySelector('.badge.bg-danger');
                if (delBadge) delBadge.remove();
            }

            showResultToast(
                action === 'approve'
                    ? 'Account deletion approved. The user has been deactivated and notified.'
                    : 'Deletion request rejected. A message has been sent to the user.',
                action === 'approve' ? 'success' : 'warning'
            );
        } else {
            showResultToast(res.error || 'Something went wrong. Please try again.', 'danger');
            // Re-enable approve button if failed
            if (approveBtn) {
                approveBtn.disabled = false;
                approveBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Yes, Approve';
            }
        }
    })
    .catch(function () {
        closeActionToast();
        showResultToast('Network error. Please check your connection and try again.', 'danger');
    });
}

// ── Result toast ──────────────────────────────────────────────────────────────
function showResultToast(message, type) {
    var el   = document.getElementById('resultToast');
    var body = document.getElementById('resultToastBody');

    var icons = { success: 'bi-check-circle-fill', warning: 'bi-exclamation-triangle-fill', danger: 'bi-x-circle-fill' };
    var icon  = icons[type] || 'bi-info-circle-fill';

    el.className = 'toast result-toast align-items-center border-0 text-white bg-' + type;
    body.innerHTML = '<i class="bi ' + icon + ' me-2 fs-5"></i>' + message;

    var bsToast = bootstrap.Toast.getOrCreateInstance(el, { delay: 5000 });
    bsToast.show();
}

// ── Set data-id on rows (for mark-as-read update) ─────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.msg-row').forEach(function (row) {
        try {
            var d = JSON.parse(row.dataset.msg);
            if (d && d.id) row.setAttribute('data-id', d.id);
        } catch(e) {}
    });

    // Close action toast on backdrop click
    document.getElementById('actionToastBackdrop').addEventListener('click', function () {
        closeActionToast();
    });

    // Close action toast on outside click
    document.addEventListener('click', function (e) {
        var toast = document.getElementById('actionToast');
        if (toast.classList.contains('show') && !toast.contains(e.target)) {
            // Only close if the click isn't on one of the modal action buttons
            var btns = document.getElementById('deletionActions');
            if (btns && btns.contains(e.target)) return;
            closeActionToast();
        }
    });
});

// ── Searchable User Dropdown ──────────────────────────────────────────────────
(function () {
    const dropdown  = document.getElementById('userDropdown');
    const trigger   = document.getElementById('userDropdownTrigger');
    const menu      = document.getElementById('userDropdownMenu');
    const search    = document.getElementById('userSearch');
    const options   = document.querySelectorAll('.searchable-select-option');
    const noResults = document.getElementById('userNoResults');
    const label     = document.getElementById('userDropdownLabel');
    const hidden    = document.getElementById('receiverId');

    if (!dropdown) return;

    // Open / close
    trigger.addEventListener('click', function () {
        dropdown.classList.toggle('open');
        if (dropdown.classList.contains('open')) {
            search.value = '';
            filterOptions('');
            setTimeout(() => search.focus(), 50);
        }
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target)) dropdown.classList.remove('open');
    });

    // Close on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') dropdown.classList.remove('open');
    });

    // Live filter
    search.addEventListener('input', function () {
        filterOptions(this.value.trim().toLowerCase());
    });

    function filterOptions(q) {
        let visible = 0;
        options.forEach(function (opt) {
            const match = opt.dataset.label.toLowerCase().includes(q);
            opt.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        noResults.style.display = visible === 0 ? 'block' : 'none';
    }

    // Select option
    options.forEach(function (opt) {
        opt.addEventListener('click', function () {
            options.forEach(o => o.classList.remove('active'));
            this.classList.add('active');
            hidden.value        = this.dataset.value;
            label.textContent   = this.dataset.label;
            label.classList.remove('text-muted');
            dropdown.classList.remove('open');
        });
    });
})();

// ── Live search (AJAX — searches ALL messages across all pages) ───────────────
(function () {
    const input      = document.getElementById('liveSearchInput');
    const clearBtn   = document.getElementById('clearSearchBtn');
    const listBody   = document.getElementById('msgListBody');
    const pagination = document.getElementById('msgPagination');
    if (!input || !listBody) return;

    // Grab the active filter from the URL
    function getFilter() {
        return new URLSearchParams(window.location.search).get('filter') || 'all';
    }

    let debounceTimer = null;
    let currentQ      = '';

    // ── Render a single message row from JSON data ────────────────────────────
    function renderRow(d) {
        const isDeletion = d.isDeletion;
        const isUnread   = !d.isRead;
        let rowClass     = isUnread ? (isDeletion ? 'deletion-unread' : 'unread') : 'read';
        let borderColor  = isDeletion ? '#dc3545' : (isUnread ? '#0d6efd' : '#dee2e6');

        const dot    = isUnread ? `<span class="unread-dot" style="background:${isDeletion ? '#dc3545' : '#0d6efd'};"></span>` : '';
        const appBadge = d.appNumber ? `<span class="badge bg-info text-dark ms-1">${escHtml(d.appNumber)}</span>` : '';
        const delBadge = isDeletion  ? `<span class="badge bg-danger ms-1"><i class="bi bi-person-x me-1"></i>Deletion</span>` : '';
        const newBadge = (isUnread && !isDeletion) ? `<span class="badge bg-primary ms-1">New</span>` : '';

        const safeData = JSON.stringify(d).replace(/'/g, "&#39;");

        return `<div class="msg-row ${rowClass}"
                     style="border-left:5px solid ${borderColor} !important;"
                     data-msg='${safeData}'
                     data-id="${d.id}"
                     onclick="openMessage(JSON.parse(this.dataset.msg))"
                     role="button" tabindex="0"
                     onkeydown="if(event.key==='Enter')openMessage(JSON.parse(this.dataset.msg))">
            <div class="msg-meta">
                <div class="msg-sender">
                    ${dot}<strong>${escHtml(d.sender)}</strong>${appBadge}${delBadge}${newBadge}
                </div>
                <small class="text-muted">${escHtml(d.date)}</small>
            </div>
            <div class="msg-subject">${escHtml(d.subject || '(no subject)')}</div>
            <div class="msg-preview">${escHtml(d.preview)}<span class="click-hint"><i class="bi bi-eye me-1"></i>Read more</span></div>
        </div>`;
    }

    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function showLoading() {
        listBody.innerHTML = '<div class="p-4 text-center text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Searching…</div>';
    }

    function showResults(results, q) {
        if (results.length === 0) {
            listBody.innerHTML = '<div class="p-5 text-center text-muted"><i class="bi bi-search fs-2 d-block mb-2 opacity-50"></i>No messages found for <strong>' + escHtml(q) + '</strong>.</div>';
            return;
        }
        listBody.innerHTML = results.map(renderRow).join('');
        // Show result count
        const info = document.createElement('p');
        info.className = 'text-center text-muted mt-2 mb-0';
        info.style.fontSize = '0.78rem';
        info.textContent = results.length + ' result' + (results.length !== 1 ? 's' : '') + ' found across all pages';
        listBody.appendChild(info);
    }

    function restorePage() {
        window.location.reload();
    }

    function doSearch(q) {
        currentQ = q;
        if (!q) {
            // Clear — reload to restore paginated view
            restorePage();
            return;
        }
        showLoading();
        if (pagination) pagination.style.display = 'none';

        const url = `?ajax_search=1&q=${encodeURIComponent(q)}&filter=${encodeURIComponent(getFilter())}`;
        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (data.ok) showResults(data.results, q);
                else listBody.innerHTML = '<div class="p-4 text-center text-danger">Search failed. Please try again.</div>';
            })
            .catch(() => {
                listBody.innerHTML = '<div class="p-4 text-center text-danger">Network error. Please try again.</div>';
            });
    }

    input.addEventListener('input', function () {
        const q = this.value.trim();
        if (clearBtn) clearBtn.style.display = q ? '' : 'none';
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => doSearch(q), 350);
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            clearBtn.style.display = 'none';
            restorePage();
        });
    }
})();
