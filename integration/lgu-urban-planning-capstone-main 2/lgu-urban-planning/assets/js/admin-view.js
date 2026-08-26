/**
 * admin-view.js
 * Client-side behaviour for the Application Details (Staff View) page.
 * Extracted from view.php — all PHP-derived data is read from data-*
 * attributes on the relevant DOM elements instead of being inlined here,
 * so this file can be served as a plain static asset.
 */

/* ============================================================
   TOAST NOTIFICATIONS (error / success banners on page load)
   ============================================================ */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var toastError = document.getElementById('toastError');
        if (toastError) new bootstrap.Toast(toastError).show();

        var toastSuccess = document.getElementById('toastSuccess');
        if (toastSuccess) new bootstrap.Toast(toastSuccess).show();
    });
})();

/* ============================================================
   WORKFLOW STATUS PREREQUISITE CHECK
   Reads flags/messages from data-* attributes on #confirmWorkflowBtn:
     data-has-technical, data-has-zoning, data-is-compliant,
     data-msg-tech, data-msg-zone, data-msg-noncompliant
   ============================================================ */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('confirmWorkflowBtn');
        if (!btn) return;
        var form = btn.closest('form');
        if (!form) return;

        var hasTechnical = btn.dataset.hasTechnical === 'true';
        var hasZoning    = btn.dataset.hasZoning === 'true';
        var isCompliant  = btn.dataset.isCompliant === 'true';
        var JS_PREREQ_TECH         = btn.dataset.msgTech || '';
        var JS_PREREQ_ZONE         = btn.dataset.msgZone || '';
        var JS_PREREQ_NONCOMPLIANT = btn.dataset.msgNoncompliant ||
            'Zoning result is NON-COMPLIANT — this application cannot be approved. Please set the status to Rejected instead.';

        // Statuses that require both checks
        var restrictedStatuses = ['pending_payment', 'rejected'];

        form.addEventListener('submit', function (e) {
            var statusSelect = form.querySelector('select[name="status"]');
            if (!statusSelect) return;

            var chosen = statusSelect.value;
            if (restrictedStatuses.indexOf(chosen) === -1) return; // 'submitted' always OK

            var missing = [];
            if (!hasTechnical) {
                missing.push({
                    icon: 'bi-clipboard2-pulse-fill',
                    text: JS_PREREQ_TECH
                });
            }
            if (!hasZoning) {
                missing.push({
                    icon: 'bi-geo-alt-fill',
                    text: JS_PREREQ_ZONE
                });
            } else if (chosen === 'pending_payment' && !isCompliant) {
                // Zoning check exists, but failed compliance — Final
                // Approval (and therefore payment) is not allowed.
                missing.push({
                    icon: 'bi-x-octagon-fill',
                    text: JS_PREREQ_NONCOMPLIANT
                });
            }

            if (missing.length === 0) return; // all good, let the form submit

            e.preventDefault();

            var list = document.getElementById('prereqModalList');
            list.innerHTML = missing.map(function (m) {
                return '<li class="d-flex align-items-start gap-2 mb-2">'
                     + '<i class="bi ' + m.icon + ' text-danger mt-1 flex-shrink-0"></i>'
                     + '<span class="small">' + m.text + '</span>'
                     + '</li>';
            }).join('');

            new bootstrap.Modal(document.getElementById('prereqBlockModal')).show();
        });
    });
})();

/* ============================================================
   DOCUMENT VIEWER MODAL + TAB RESTORE + ASSIGN OFFICER TOGGLE
   ============================================================ */
function viewDocument(id, title, fileUrl) {
    document.getElementById('docTitle').innerText = title;
    const img   = document.getElementById('docImage');
    const frame = document.getElementById('docFrame');
    img.style.display   = 'none';
    frame.style.display = 'none';

    // Use the direct URL when provided (bypasses documents/view.php
    // which can fail on Windows-style file_path values stored in the DB).
    // Fall back to the PHP script only if no direct URL was supplied.
    const url = (fileUrl && fileUrl.trim() !== '')
        ? fileUrl
        : '/lgu-urban-planning/documents/view.php?id=' + id;

    if (title.toLowerCase().endsWith('.pdf')) {
        frame.src = url;
        frame.style.display = 'block';
    } else {
        img.src = url;
        img.style.display = 'block';
    }
    new bootstrap.Modal(document.getElementById('docViewerModal')).show();
}

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    if (window.location.hash === '#history' || urlParams.has('page')) {
        const historyTab = document.querySelector('#history-tab');
        if (historyTab) {
            const tab = new bootstrap.Tab(historyTab);
            tab.show();
            document.getElementById('history').scrollIntoView({ behavior: 'smooth' });
        }
    }

    // Show/hide "Assign to Officer" field based on status selection
    const statusSelect = document.querySelector('select[name="status"]');
    const assignField  = document.getElementById('assignOfficerField');
    const assignSelect = document.getElementById('assignOfficerSelect');

    if (statusSelect && assignField) {
        statusSelect.addEventListener('change', function () {
            const isUnderReview = this.value === 'under_review';
            assignField.style.display = isUnderReview ? '' : 'none';
            if (assignSelect) assignSelect.required = isUnderReview;
        });
        // Set required on page load if already under_review
        if (statusSelect.value === 'under_review' && assignSelect) {
            assignSelect.required = true;
        }
    }
});

/* ============================================================
   EXPORT VERIFICATION LOGIC
   Same password-gated flow as admin/users.php: verify the staff member's
   password via admin/verify_action.php, get a one-time token, then trigger
   the token-gated CSV download from this page.
   Translated strings are read from data-* attributes on #exportVerifyModal:
     data-msg-warning-download, data-msg-warning-print,
     data-msg-btn-download, data-msg-btn-print
   ============================================================ */
(function () {
    const _exportModalEl = document.getElementById('exportVerifyModal');
    if (!_exportModalEl) return;

    let _exportType = '', _exportTable = '', _exportUrl = '';

    const _msgWarningDownload = _exportModalEl.dataset.msgWarningDownload || '';
    const _msgWarningPrint    = _exportModalEl.dataset.msgWarningPrint ||
        'You are about to print sensitive assessment records. Please confirm your identity to proceed.';
    const _msgBtnDownload     = _exportModalEl.dataset.msgBtnDownload || '';
    const _msgBtnPrint        = _exportModalEl.dataset.msgBtnPrint || '';

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

    function _showToast(msg, type) {
        var toastEl   = _elmt('exportToast');
        var toastMsg  = _elmt('exportToastMsg');
        var toastIcon = _elmt('exportToastIcon');

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
            ? _msgWarningPrint
            : _msgWarningDownload;
        _elmt('exportBtnLabel').textContent = isPrint
            ? _msgBtnPrint
            : _msgBtnDownload;
        _elmt('exportBtnIcon').className = isPrint ? 'bi bi-printer me-1' : 'bi bi-download me-1';

        bootstrap.Modal.getOrCreateInstance(_exportModalEl).show();
    }
    // Expose for inline onclick="openExportModal(...)" handlers elsewhere in the page
    window.openExportModal = openExportModal;

    _exportModalEl.addEventListener('hide.bs.modal', function () {
        var focused = _exportModalEl.querySelector(':focus');
        if (focused) focused.blur();
    });

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

    window.togglePasswordVisibility = function (inputId, eyeId) {
        const input = document.getElementById(inputId);
        const eye = document.getElementById(eyeId);
        if (input.type === "password") {
            input.type = "text";
            eye.classList.replace("bi-eye-slash", "bi-eye");
        } else {
            input.type = "password";
            eye.classList.replace("bi-eye", "bi-eye-slash");
        }
    };
})();

/* ============================================================
   PARCEL LOCATOR MAP (Leaflet — "Pick on Map" in the
   Update Parcel Information modal)
   ============================================================ */
(function () {
    const defaultLat = 14.7566;
    const defaultLng = 121.0450;
    let parcelMap = null;
    let parcelMarker = null;

    function updateParcelMarker(lat, lng, moveMap) {
        if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;
        const pos = [parseFloat(lat), parseFloat(lng)];
        if (parcelMarker) {
            parcelMarker.setLatLng(pos);
        } else if (parcelMap) {
            parcelMarker = L.marker(pos, { draggable: true }).addTo(parcelMap);
            parcelMarker.on('dragend', function () {
                const p = parcelMarker.getLatLng();
                document.getElementById('parcel-lat').value = p.lat.toFixed(6);
                document.getElementById('parcel-lng').value = p.lng.toFixed(6);
            });
        }
        if (moveMap && parcelMap) parcelMap.setView(pos, 16);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const latInput = document.getElementById('parcel-lat');
        const lngInput = document.getElementById('parcel-lng');
        const mapBtn   = document.getElementById('btn-parcel-map');
        const mapDiv   = document.getElementById('parcel-map-container');
        if (!mapBtn || !mapDiv) return;

        // Sync typed coordinates to marker
        [latInput, lngInput].forEach(function (el) {
            if (el) el.addEventListener('input', function () {
                const lat = latInput.value, lng = lngInput.value;
                if (lat && lng) updateParcelMarker(lat, lng, true);
            });
        });

        mapBtn.addEventListener('click', function () {
            if (mapDiv.style.display === 'none' || mapDiv.style.display === '') {
                mapDiv.style.display = 'block';
                mapBtn.innerHTML = '<i class="bi bi-map-fill"></i> Hide Map';

                if (!parcelMap) {
                    const initLat = parseFloat(latInput.value) || defaultLat;
                    const initLng = parseFloat(lngInput.value) || defaultLng;
                    parcelMap = L.map('parcel-map-container').setView([initLat, initLng], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(parcelMap);

                    parcelMap.on('click', function (e) {
                        const lat = e.latlng.lat.toFixed(6);
                        const lng = e.latlng.lng.toFixed(6);
                        latInput.value = lat;
                        lngInput.value = lng;
                        updateParcelMarker(lat, lng);
                    });

                    // Show existing pin if coords already set
                    if (latInput.value && lngInput.value) {
                        updateParcelMarker(latInput.value, lngInput.value, true);
                    }
                }

                setTimeout(function () { parcelMap.invalidateSize(); }, 200);
            } else {
                mapDiv.style.display = 'none';
                mapBtn.innerHTML = '<i class="bi bi-geo-alt me-1"></i> Pick on Map';
            }
        });
    });
})();
