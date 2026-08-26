/**
 * user-dashboard.js
 * Client-side behaviour for the Applicant Dashboard page.
 * Extracted from dashboard.php — all PHP-derived data is read from data-*
 * attributes on the relevant DOM elements instead of being inlined here,
 * so this file can be served as a plain static asset.
 */

/* ============================================================
   INSPECTION CALENDAR — day click → appointment detail panel
   Reads the date → appointments map from data-appt-dates on
   #apptCalendarTable (JSON-encoded by PHP).
   ============================================================ */
const apptData = (function () {
    const table = document.getElementById('apptCalendarTable');
    if (!table || !table.dataset.apptDates) return {};
    try {
        return JSON.parse(table.dataset.apptDates);
    } catch (e) {
        return {};
    }
})();

function showApptDetail(date) {
    const details = apptData[date];
    const panel = document.getElementById('appt-detail-panel');
    const content = document.getElementById('appt-info-content');
    const noApptMsg = document.getElementById('no-appt-msg');

    if (!details) {
        panel.style.display = 'none';
        if (noApptMsg) noApptMsg.style.display = 'block';
        return;
    }

    if (noApptMsg) noApptMsg.style.display = 'none';
    panel.style.display = 'block';
    content.innerHTML = details.map(a => `
        <div class="mb-3 p-2 appt-item-card rounded border-start border-3 border-primary">
            <div class="text-primary fw-bold mb-1" style="font-size: 0.9rem;">${a.project_name}</div>
            <div class="text-muted small mb-2">
                <i class="bi bi-clock-fill me-1"></i>
                ${new Date(a.scheduled_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: true})}
            </div>
            <button onclick="openRescheduleModal(${a.id}, '${a.application_number}')"
                    class="btn btn-sm btn-danger w-100 py-1 fw-bold shadow-sm" style="font-size: 0.75rem;">
                Request Reschedule
            </button>
        </div>
    `).join('');
}

/* ============================================================
   LIVE CLOCK
   Reads settings from data-* attributes on #live-clock:
     data-use12h ("true"/"false"), data-timezone (IANA tz string)
   ============================================================ */
(function () {
    const el = document.getElementById('live-clock');
    if (!el) return;

    const use12h   = el.dataset.use12h === 'true';
    const timezone = el.dataset.timezone || 'Asia/Manila';

    function tick() {
        const now  = new Date();
        const opts = {
            timeZone: timezone,
            hour:     '2-digit',
            minute:   '2-digit',
            second:   '2-digit',
            hour12:   use12h,
        };
        el.textContent = new Intl.DateTimeFormat('en-PH', opts).format(now);
    }
    tick();
    setInterval(tick, 1000);
})();

/* ============================================================
   RESCHEDULE MODAL
   ============================================================ */
function openRescheduleModal(id, appNum) {
    document.getElementById('modal_appt_id').value = id;
    document.getElementById('modal_app_num').innerText = appNum;
    new bootstrap.Modal(document.getElementById('rescheduleModal')).show();
}
