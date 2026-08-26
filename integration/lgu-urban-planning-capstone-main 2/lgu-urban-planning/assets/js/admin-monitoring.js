const ACTION_PATH = '../modules/MonitoringAndInspection/monitoring_action.php';

function openScheduleModal() {
    const myModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
    myModal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    // === 1. CALENDAR INITIALIZATION ===
    const calendarEl = document.getElementById('inspectionCalendar');
    if (calendarEl) {
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            themeSystem: 'bootstrap5',
            eventDisplay: 'block', 
            displayEventTime: false, 
            headerToolbar: { 
                left: 'prev,next', 
                center: 'title', 
                right: 'today' 
            },
            events: ACTION_PATH + '?action=fetch_events',
            height: 'auto',
            eventMaxStack: 2, 
            dayMaxEvents: true,
            eventClick: function(info) {
                Swal.fire({
                    title: 'Cancel Inspection?',
                    text: "Delete schedule for " + info.event.title + "?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Yes, delete it!',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        const fd = new FormData();
                        fd.append('id', info.event.id);
                        fetch(ACTION_PATH + '?action=delete_event', { method: 'POST', body: fd })
                        .then(res => res.json())
                        .then(data => {
                            if(data.success) {
                                info.event.remove();
                                Swal.fire('Deleted!', 'Schedule removed.', 'success');
                            }
                        });
                    }
                });
            },
            eventDataTransform: function(item) {
                const shortID = item.application_number.split('-').pop();
                return {
                    id: item.id,
                    title: '#' + shortID, 
                    start: item.scheduled_at,
                    allDay: true, 
                    backgroundColor: item.status === 'completed' ? '#198754' : '#ffc107',
                    borderColor: 'transparent',
                    textColor: item.status === 'completed' ? '#ffffff' : '#000000'
                };
            }
        });
        calendar.render();
    }

    // === 2. FORM SUBMISSIONS ===
    const insForm = document.getElementById('inspectionForm');
    if(insForm) {
        insForm.onsubmit = function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveSchedule');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

            fetch(ACTION_PATH + '?action=save_schedule', { method: 'POST', body: new FormData(this) })
            .then(res => res.json())
            .then(data => {
                if(data.success) { 
                    Swal.fire({ icon: 'success', title: 'Schedule Saved!', showConfirmButton: false, timer: 1500 })
                    .then(() => location.reload());
                } else { 
                    Swal.fire('Error', data.message || "Error saving.", 'error');
                    btn.disabled = false;
                    btn.innerText = 'Save Schedule';
                }
            });
        };
    }

// I-update ang onsubmit handler para sa Violation Form
const violForm = document.getElementById('violationForm');
if(violForm) {
    violForm.onsubmit = function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

        // Gagamit ng FormData para masalo ang File Upload
        const fd = new FormData(this);

        fetch(ACTION_PATH + '?action=report_violation', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Violation Reported',
                    text: 'The application has been flagged and the Notice of Violation is now active.',
                    confirmButtonText: 'Print Notice'
                }).then(() => {
                    // Dito pwede mo i-redirect sa isang printable page (Optional sa defense)
                    // window.open('print_notice.php?id=' + fd.get('application_id'), '_blank');
                    location.reload();
                });
            } else {
                Swal.fire('Error', data.message, 'error');
                btn.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Server Error', 'Check your file upload size or directory permissions.', 'error');
            btn.disabled = false;
        });
    };
}
});

// === 3. GLOBAL FUNCTIONS ===

function viewInspectionDetails(data) {
    document.getElementById('view_project_name').innerText = data.project_name || 'Project Name N/A';
    document.getElementById('view_app_number').innerText = 'App #' + data.application_number;
    document.getElementById('view_inspector').innerText = data.inspector_name;
    const scheduledAt = data.scheduled_at;
    const parsedDate = scheduledAt ? new Date(scheduledAt) : null;
    const isValidDate = parsedDate && !isNaN(parsedDate.getTime()) && parsedDate.getFullYear() > 1970;
    document.getElementById('view_date').innerText = isValidDate
        ? parsedDate.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : 'TBD / No Schedule';
    document.getElementById('view_notes').innerText = data.notes || 'No notes recorded for this schedule.';

    if(document.getElementById('checklist_ins_id')) {
        document.getElementById('checklist_ins_id').value = data.id;
    }

    // --- CHECKLIST: editable for inspector, read-only result for other staff/officers ---
    const isEditableChecklist = !!document.getElementById('checklistForm')
        && document.getElementById('checklistForm').querySelector('button[onclick="saveChecklist()"]');

    if (isEditableChecklist) {
        // Inspector view: reset the form for a fresh entry (unchecked, empty remarks)
        const form = document.getElementById('checklistForm');
        form.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        const notesField = form.querySelector('textarea[name="inspection_notes"]');
        if (notesField) notesField.value = '';
    } else {
        // Staff/officer view: show the inspector's already-submitted result
        const checkLand = document.getElementById('check_land');
        const checkPlan = document.getElementById('check_plan');
        const checkExpansion = document.getElementById('check_expansion');
        if (checkLand) checkLand.checked = !!(data.land_use_check == 1 || data.land_use_check === true);
        if (checkPlan) checkPlan.checked = !!(data.plan_consistency == 1 || data.plan_consistency === true);
        if (checkExpansion) checkExpansion.checked = !!(data.expansion_check == 1 || data.expansion_check === true);

        const resultNotes = document.getElementById('result_inspection_notes');
        const pendingMsg = document.getElementById('checklist_pending_msg');
        const checklistDone = data.checklist_status === 'completed' || data.inspection_notes;
        if (resultNotes) resultNotes.innerText = data.inspection_notes || 'No remarks recorded yet.';
        if (pendingMsg) pendingMsg.classList.toggle('d-none', !!checklistDone);
    }

    // --- VIOLATION REPORT: editable for inspector, read-only result for other staff/officers ---
    if (document.getElementById('violationForm')) {
        // Inspector view: reset the form for a fresh report
        document.getElementById('viol_ins_id').value = data.id;
        document.getElementById('viol_app_id').value = data.application_id;
        document.getElementById('violationForm').reset();
    } else if (document.getElementById('violationResultView')) {
        // Staff/officer view: show the violation already filed by the inspector, if any
        const hasViolation = !!data.violation_type;

        const typeEl = document.getElementById('result_violation_type');
        if (typeEl) typeEl.innerText = data.violation_type || 'No violation reported.';

        const notesEl = document.getElementById('result_violation_notes');
        if (notesEl) notesEl.innerText = data.violation_notes || 'No findings recorded.';

        const photoBtn = document.getElementById('result_violation_photo');
        if (photoBtn) {
            if (hasViolation && data.violation_photo) {
                photoBtn.href = data.violation_photo;
                photoBtn.classList.remove('disabled');
            } else {
                photoBtn.href = '#';
                photoBtn.classList.add('disabled');
            }
        }

        const pendingMsg = document.getElementById('violation_pending_msg');
        if (pendingMsg) pendingMsg.classList.toggle('d-none', hasViolation);
    }

    const myModal = new bootstrap.Modal(document.getElementById('viewModal'));
    myModal.show();
}

function saveChecklist() {
    const form = document.getElementById('checklistForm');
    if(!form) return;

    const checkboxes = form.querySelectorAll('input[type="checkbox"]');
    let allChecked = true;
    checkboxes.forEach(cb => { if(!cb.checked) allChecked = false; });

    if(!allChecked) {
        Swal.fire({
            icon: 'error',
            title: 'Zoning Non-Compliance',
            text: 'Cannot complete inspection. One or more requirements are NOT compliant.',
            confirmButtonColor: '#d33'
        });
        return; 
    }

    Swal.fire({
        title: 'Submit Zoning Report?',
        text: "Confirming this will mark the project as COMPLIANT and notify the applicant.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        confirmButtonText: 'Yes, Submit Result'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData(form);
            const insID = document.getElementById('checklist_ins_id').value;

            // 1. I-save ang Checklist status
            fetch(ACTION_PATH + '?action=save_checklist', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    // 2. Ipakita ang Success Alert muna
                    Swal.fire({
                        icon: 'success',
                        title: 'Zoning Validated',
                        text: 'Compliance report has been filed and official notice is being sent.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        // 3. I-trigger ang pag-send ng Professional LGU Message
                        const msgData = new FormData();
                        msgData.append('inspection_id', insID);
                        
                        fetch(ACTION_PATH + '?action=send_approval_message', { 
                            method: 'POST', 
                            body: msgData 
                        })
                        .then(() => {
                            location.reload(); // Reload pagkatapos ma-send ang message
                        });
                    });
                } else {
                    Swal.fire('Error', 'Failed to update record.', 'error');
                }
            });
        }
    });
}

// === SEARCHABLE DROPDOWNS ===
function initSearchableDropdown(dropdownId, hiddenId, searchId, listId) {
    const wrap     = document.getElementById(dropdownId);
    const hidden   = document.getElementById(hiddenId);
    const search   = document.getElementById(searchId);
    const list     = document.getElementById(listId);
    const clearBtn = wrap ? wrap.querySelector('.sd-clear') : null;
    if (!wrap || !hidden || !search || !list) return;

    const items = list.querySelectorAll('.sd-item');

    function openList() { list.classList.add('open'); }
    function closeList() { list.classList.remove('open'); }

    search.addEventListener('focus', openList);
    search.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        let hasMatch = false;
        list.querySelectorAll('.sd-no-results').forEach(el => el.remove());
        items.forEach(item => {
            const label = (item.dataset.label || item.textContent).toLowerCase();
            const show  = label.includes(q);
            item.style.display = show ? '' : 'none';
            if (show) hasMatch = true;
        });
        if (!hasMatch) {
            const noRes = document.createElement('div');
            noRes.className = 'sd-no-results';
            noRes.textContent = 'No results found.';
            list.appendChild(noRes);
        }
        if (clearBtn) clearBtn.style.display = this.value ? 'block' : 'none';
        openList();
    });

    list.addEventListener('mousedown', function(e) {
        const item = e.target.closest('.sd-item');
        if (!item) return;
        e.preventDefault();
        const val   = item.dataset.value || '';
        const label = item.dataset.label || item.textContent.trim();
        hidden.value  = val;
        search.value  = val ? label : '';
        if (clearBtn) clearBtn.style.display = val ? 'block' : 'none';
        items.forEach(i => i.classList.remove('selected'));
        item.classList.add('selected');
        closeList();
    });

    document.addEventListener('mousedown', function(e) {
        if (!wrap.contains(e.target)) closeList();
    });
}

function clearSD(dropdownId, hiddenId, searchId) {
    document.getElementById(hiddenId).value = '';
    const s = document.getElementById(searchId);
    s.value = '';
    const wrap = document.getElementById(dropdownId);
    if (wrap) {
        wrap.querySelectorAll('.sd-item').forEach(i => { i.style.display = ''; i.classList.remove('selected'); });
        wrap.querySelectorAll('.sd-no-results').forEach(el => el.remove());
        const clr = wrap.querySelector('.sd-clear');
        if (clr) clr.style.display = 'none';
    }
    s.focus();
}

// Reset dropdowns when modal opens
document.addEventListener('DOMContentLoaded', function() {
    initSearchableDropdown('appDropdown',  'application_id_val', 'appSearch',  'appList');
    initSearchableDropdown('inspDropdown', 'inspector_id_val',   'inspSearch', 'inspList');

    const schedModal = document.getElementById('scheduleModal');
    if (schedModal) {
        schedModal.addEventListener('show.bs.modal', function() {
            clearSD('appDropdown',  'application_id_val', 'appSearch');
            clearSD('inspDropdown', 'inspector_id_val',   'inspSearch');
        });
    }
});