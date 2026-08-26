let map;
let marker;
const defaultLat = 14.7566;
const defaultLng = 121.0450;

/**
 * Function para i-update ang marker at ang inputs
 * @param {number} lat 
 * @param {number} lng 
 * @param {boolean} moveMap - kung ise-center ang mapa (true para sa manual type)
 */
function updateMarker(lat, lng, moveMap = false) {
    if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;
    
    const pos = [parseFloat(lat), parseFloat(lng)];
    
    if (marker) {
        marker.setLatLng(pos);
    } else if (map) {
        // Gagawa ng draggable marker kung wala pa
        marker = L.marker(pos, {draggable: true}).addTo(map);
        
        // Sync: Kapag d-in-rag ang pin, update ang text inputs
        marker.on('dragend', function() {
            const newPos = marker.getLatLng();
            $('#inp-lat').val(newPos.lat.toFixed(6));
            $('#inp-lng').val(newPos.lng.toFixed(6));
        });
    }

    // Kung galing sa manual typing, dalhin ang view ng mapa sa location
    if (moveMap && map) {
        map.setView(pos, 16);
    }
}

$(document).ready(function() {
    // 1. EVENT: Kapag nag-type manual sa Latitude/Longitude fields
    $('#inp-lat, #inp-lng').on('input change', function() {
        const lat = $('#inp-lat').val();
        const lng = $('#inp-lng').val();
        
        // I-update ang marker at i-center ang map
        if(lat && lng) {
            updateMarker(lat, lng, true);
        }
    });

    // 2. EVENT: Toggle Map Container
    $('#btn-select-map').on('click', function() {
        const container = $('#map-container');
        const btn = $(this);
        
        container.slideToggle(400, function() {
            if (container.is(':visible')) {
                btn.html('<i class="bi bi-map-fill"></i> Hide Map');
                
                // Initialize Map kung first time bubuksan
                if (!map) {
                    map = L.map('map-container').setView([defaultLat, defaultLng], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(map);

                    // Sync: Kapag clinick ang MAPA, update inputs at marker
                    map.on('click', function(e) {
                        const lat = e.latlng.lat.toFixed(6);
                        const lng = e.latlng.lng.toFixed(6);
                        
                        $('#inp-lat').val(lat);
                        $('#inp-lng').val(lng);
                        updateMarker(lat, lng);
                    });
                }
                
                // Importante: I-refresh ang size ng Leaflet para hindi putol ang tiles
                setTimeout(() => { 
                    map.invalidateSize(); 
                    // Kung may laman na ang inputs, ipakita na agad ang pin
                    const existingLat = $('#inp-lat').val();
                    const existingLng = $('#inp-lng').val();
                    if(existingLat && existingLng) updateMarker(existingLat, existingLng, true);
                }, 200);

            } else {
                btn.html('<i class="bi bi-map"></i> Select on Map');
            }
        });
    });

    // 3. Auto-format Parcel ID (Optional helper)
    $('#parcel_id').on('input', function() {
        let val = $(this).val().replace(/[^0-9]/g, '');
        // Dito mo pwedeng dagdagan ng auto-dash logic kung gusto mo
    });

    // 4. Live search for "Select Registered Applicant"
    $('.select2-search').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#manualAddModal'),
        placeholder: function () { return $(this).data('placeholder'); },
        allowClear: true
    });

    // FIX: aria-hidden focus warning — use inert attribute to properly manage focus
    const manualAddModal = document.getElementById('manualAddModal');
    if (manualAddModal) {
        // Before Bootstrap sets aria-hidden, blur focused descendants and set inert
        manualAddModal.addEventListener('hide.bs.modal', function () {
            const focused = manualAddModal.querySelector(':focus');
            if (focused) focused.blur();
            manualAddModal.inert = true;
        });

        // Once fully hidden, clean up — Bootstrap will handle aria-hidden
        manualAddModal.addEventListener('hidden.bs.modal', function () {
            manualAddModal.inert = false;
            // Return focus to the trigger button
            const trigger = document.querySelector('[data-bs-target="#manualAddModal"]');
            if (trigger) trigger.focus();
        });

        // Remove inert when opening so the form is fully interactive
        manualAddModal.addEventListener('show.bs.modal', function () {
            manualAddModal.inert = false;
        });
    }
});

// ── Live Search & Filter ──────────────────────────────────────────────────────
(function () {
    const searchInput  = document.getElementById('appSearchInput');
    const clearBtn     = document.getElementById('appClearSearch');
    const searchIcon   = document.getElementById('appSearchIcon');
    const statusSelect = document.getElementById('appStatusSelect');
    const dateRangeInput = document.getElementById('appDateRangeInput');
    const dateFromHidden = document.getElementById('appDateFrom');
    const dateToHidden   = document.getElementById('appDateTo');
    const clearDateBtn   = document.getElementById('appClearDateRange');
    const sortSelect    = document.getElementById('appSortSelect');
    const form          = document.getElementById('filterForm');
    if (!searchInput || !form) return;

    let debounceTimer = null;

    function submitForm() {
        form.submit();
    }

    // Live search with 500ms debounce
    searchInput.addEventListener('input', function () {
        clearBtn.classList.toggle('d-none', this.value === '');
        searchIcon.className = 'bi bi-arrow-clockwise text-muted';
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            searchIcon.className = 'bi bi-search text-muted';
            submitForm();
        }, 500);
    });

    // Enter key submits immediately
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(debounceTimer);
            searchIcon.className = 'bi bi-search text-muted';
            submitForm();
        }
        if (e.key === 'Escape') {
            searchInput.value = '';
            clearBtn.classList.add('d-none');
            clearTimeout(debounceTimer);
            submitForm();
        }
    });

    // Clear button
    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        clearBtn.classList.add('d-none');
        clearTimeout(debounceTimer);
        submitForm();
    });

    // Status dropdown — submit immediately on change
    statusSelect.addEventListener('change', function () {
        clearTimeout(debounceTimer);
        submitForm();
    });

    // Date range picker — single clickable field showing "from - to"
    if (dateRangeInput && window.flatpickr) {
        const initialDates = [];
        if (dateFromHidden.value) initialDates.push(dateFromHidden.value);
        if (dateToHidden.value)   initialDates.push(dateToHidden.value);

        const dateRangePicker = flatpickr(dateRangeInput, {
            mode: 'range',
            dateFormat: 'M j, Y',
            defaultDate: initialDates.length ? initialDates : undefined,
            onClose: function (selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    dateFromHidden.value = instance.formatDate(selectedDates[0], 'Y-m-d');
                    dateToHidden.value   = instance.formatDate(selectedDates[1], 'Y-m-d');
                    clearDateBtn.classList.remove('d-none');
                    clearTimeout(debounceTimer);
                    submitForm();
                }
            }
        });

        clearDateBtn.addEventListener('click', function () {
            dateRangePicker.clear();
            dateFromHidden.value = '';
            dateToHidden.value = '';
            clearDateBtn.classList.add('d-none');
            clearTimeout(debounceTimer);
            submitForm();
        });
    }

    // Sort — submit immediately on change
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            clearTimeout(debounceTimer);
            submitForm();
        });
    }
})();
