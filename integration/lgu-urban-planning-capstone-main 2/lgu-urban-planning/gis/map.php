<?php
// GIS Mapping & Zoning Analysis
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../modules/GISMapping/GISController.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor']);


$gisController = new GISController();
$searchResults = [];
$selectedParcel = null;

// Capture Application Data from GET
$targetAppId = $_GET['app_id'] ?? null;
$appLat = $_GET['lat'] ?? null;
$appLng = $_GET['lng'] ?? null;
$urlBarangay = $_GET['brgy'] ?? '';
$urlStreet = $_GET['street'] ?? '';
$urlBlock = $_GET['block'] ?? '';
$urlLot = $_GET['lot'] ?? '';

// Search Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    // Override Lat/Lng if user manually enters them in the search boxes
    if (!empty($_POST['search_lat']) && !empty($_POST['search_lng'])) {
        $appLat = $_POST['search_lat'];
        $appLng = $_POST['search_lng'];
    }

    $criteria = [
        'lot_number' => $_POST['lot_number'] ?? '',
        'block' => $_POST['block'] ?? '',
        'street' => $_POST['street'] ?? '',
        'barangay' => $_POST['barangay'] ?? '',
        'parcel_id' => $_POST['parcel_id'] ?? ''
    ];
    $searchResults = $gisController->searchParcel($criteria);
    if (count($searchResults) === 1) { 
        $selectedParcel = $searchResults[0]; 
    }
}

// Support for direct Parcel ID
if (isset($_GET['parcel_id'])) { 
    $selectedParcel = $gisController->getParcelById($_GET['parcel_id']); 
}

$zoningClassifications = $gisController->getZoningClassifications();
$allParcels = $gisController->getAllParcels();

// ── Language & locale ─────────────────────────────────────────────────────────
$lang = $_SESSION['locale_language'] ?? 'en_PH';

$translations = [
    'en_PH' => [
        'page_heading'          => 'GIS Map',
        'page_subheading'       => 'Interactive zoning map and spatial analysis for urban planning.',
        'search_panel_title'    => 'Location Locator',
        'search_panel_sub'      => 'Spatial Coordinate Search',
        'lbl_geo_coords'        => 'Geographic Coordinates',
        'ph_latitude'           => 'Latitude',
        'ph_longitude'          => 'Longitude',
        'lbl_admin_info'        => 'Administrative Info',
        'ph_barangay'           => 'Barangay',
        'ph_street'             => 'Street Name',
        'ph_block'              => 'Block No.',
        'ph_lot'                => 'Lot No.',
        'btn_locate'            => 'LOCATE COORDINATES',
        'analysis_title'        => 'Technical Analysis',
        'analysis_placeholder'  => 'Select a point on the map to analyze zoning.',
        'overlay_title'         => 'Zoning Overlay',
        'overlay_show_all'      => 'Show All Zoning Types',
        'map_card_title'        => 'GEOSPATIAL INTERFACE',
        'map_card_badge'        => 'Active GIS Node',
        'compliance_title'      => 'Spatial Zoning Compliance',
        'compliance_placeholder'=> 'Select a point to evaluate...',
        'js_zoning_record'      => 'Zoning Record: ',
        'js_custom_area'        => 'Custom Area',
        'js_unknown_zone'       => 'Unknown/Outside Boundary',
        'js_analysis_lat'       => 'Latitude',
        'js_analysis_lng'       => 'Longitude',
        'js_analysis_zone'      => 'Zoning Type',
        'js_buffer_btn'         => 'Show 20m Buffer',
        'js_confirm_send'       => 'CONFIRM & SEND TO APPLICATION',
        'js_no_app_id'          => 'No Application ID Linked',
        'js_coords'             => 'Coordinates',
        'js_zoning_zone'        => 'Zoning Zone',
        'js_land_record'        => 'Land Record',
        'js_status_check'       => 'Status Check: Consistent with LGU Land Use Mapping.',
        'js_point_is'           => 'Point is',
    ],
    'fil' => [
        'page_heading'          => 'GIS Mapa',
        'page_subheading'       => 'Interaktibong mapa ng zoning at spatial analysis para sa urban planning.',
        'search_panel_title'    => 'Tagahanap ng Lokasyon',
        'search_panel_sub'      => 'Paghahanap ng Spatial Coordinates',
        'lbl_geo_coords'        => 'Mga Heograpikong Koordinada',
        'ph_latitude'           => 'Latitude',
        'ph_longitude'          => 'Longitude',
        'lbl_admin_info'        => 'Impormasyon sa Administratibo',
        'ph_barangay'           => 'Barangay',
        'ph_street'             => 'Pangalan ng Kalye',
        'ph_block'              => 'Block Blg.',
        'ph_lot'                => 'Lot Blg.',
        'btn_locate'            => 'HANAPIN ANG KOORDINADA',
        'analysis_title'        => 'Teknikal na Pagsusuri',
        'analysis_placeholder'  => 'Pumili ng punto sa mapa para suriin ang zoning.',
        'overlay_title'         => 'Zoning Overlay',
        'overlay_show_all'      => 'Ipakita ang Lahat ng Uri ng Zoning',
        'map_card_title'        => 'GEOSPATIAL INTERFACE',
        'map_card_badge'        => 'Aktibong GIS Node',
        'compliance_title'      => 'Spatial na Pagsunod sa Zoning',
        'compliance_placeholder'=> 'Pumili ng punto para suriin...',
        'js_zoning_record'      => 'Rekord ng Zoning: ',
        'js_custom_area'        => 'Pasadyang Lugar',
        'js_unknown_zone'       => 'Hindi Kilala/Labas ng Hangganan',
        'js_analysis_lat'       => 'Latitude',
        'js_analysis_lng'       => 'Longitude',
        'js_analysis_zone'      => 'Uri ng Zoning',
        'js_buffer_btn'         => 'Ipakita ang 20m Buffer',
        'js_confirm_send'       => 'KUMPIRMAHIN AT IPADALA SA APLIKASYON',
        'js_no_app_id'          => 'Walang Naka-link na Application ID',
        'js_coords'             => 'Mga Koordinada',
        'js_zoning_zone'        => 'Zone ng Zoning',
        'js_land_record'        => 'Rekord ng Lupa',
        'js_status_check'       => 'Pagsusuri ng Katayuan: Naaayon sa LGU Land Use Mapping.',
        'js_point_is'           => 'Ang punto ay',
    ],
];

function t_map(string $key, array $translations, string $lang): string {
    return $translations[$lang][$key] ?? $translations['en_PH'][$key] ?? $key;
}

$isAuthPage = true;
include __DIR__ . '/../admin/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>

<style>
    :root {
        --lgu-blue: #1e88e5;
        --lgu-accent: #ffd600;
        --bg-light: #f8f9fc;

        /* Theme-aware tokens (light defaults) — everything below keys off these
           so dark mode is one override block instead of scattered !importants. */
        --lgu-card-bg: #fff;
        --lgu-page-heading: #1e293b;
        --lgu-border: #dee2e6;
        --lgu-input-bg: var(--bg-light);
        --lgu-input-bg-focus: #fff;
        --lgu-input-border: #ced4da;
        --lgu-input-text: #212529;
        --lgu-text-muted: #6c757d;
        --lgu-text-secondary: #495057;
        --lgu-table-border: #eef0f7;
        --lgu-analysis-bg: var(--bg-light);
        --lgu-placeholder-bg: #f8f9fa;
        --lgu-badge-bg: #f8f9fa;

        --lgu-fab-bg: #fff;
        --lgu-fab-color: #1a237e;
        --lgu-fab-hover-bg: #f0f4ff;
        --lgu-popup-bg: #fff;
        --lgu-popup-border: rgba(26,35,126,0.09);
        --lgu-opt-color: #374151;
        --lgu-opt-border: #f0f2fa;
        --lgu-opt-hover-bg: #f0f4ff;
        --lgu-opt-active-bg: linear-gradient(90deg,#e8ecff 0%,#f5f7ff 100%);
        --lgu-opt-icon-bg: #f0f2fa;
        --lgu-opt-icon-color: #5c6bc0;
        --lgu-opt-radio-border: #c7cbdc;
    }

    [data-bs-theme="dark"] {
        --lgu-card-bg: #1e2130;
        --lgu-page-heading: #e9ecef;
        --lgu-border: #3a3f52;
        --lgu-input-bg: #262a3b;
        --lgu-input-bg-focus: #2e3346;
        --lgu-input-border: #3f455c;
        --lgu-input-text: #e9ecef;
        --lgu-text-muted: #9aa0b4;
        --lgu-text-secondary: #b8bdd0;
        --lgu-table-border: #333850;
        --lgu-analysis-bg: #262a3b;
        --lgu-placeholder-bg: #262a3b;
        --lgu-badge-bg: #262a3b;

        --lgu-fab-bg: #262a3b;
        --lgu-fab-color: #b7c0ff;
        --lgu-fab-hover-bg: #323858;
        --lgu-popup-bg: #262a3b;
        --lgu-popup-border: rgba(183,192,255,0.15);
        --lgu-opt-color: #cdd2e6;
        --lgu-opt-border: #333850;
        --lgu-opt-hover-bg: #323858;
        --lgu-opt-active-bg: linear-gradient(90deg,#323858 0%,#2a2e44 100%);
        --lgu-opt-icon-bg: #323858;
        --lgu-opt-icon-color: #b7c0ff;
        --lgu-opt-radio-border: #4b5170;
    }

    /* ── BASE ── */
    #map { height: 750px !important; width: 100%; border-radius: 0 0 15px 15px; z-index: 1; border: 1px solid var(--lgu-border); }
    .search-panel { border-radius: 12px; border: none; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); background: var(--lgu-card-bg); overflow: hidden; }
    .search-header { background: var(--bs-primary, var(--lgu-blue)); color: white; padding: 15px; }
    .section-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #5c6bc0; letter-spacing: 0.5px; margin-bottom: 5px; display: block; }
    .form-control-lgu { border: 1px solid var(--lgu-input-border); border-radius: 6px; padding: 8px 12px; font-size: 0.85rem; background-color: var(--lgu-input-bg); color: var(--lgu-input-text); }
    .form-control-lgu:focus { background-color: var(--lgu-input-bg-focus); border-color: var(--lgu-blue); box-shadow: none; color: var(--lgu-input-text); }
    .form-control-lgu::placeholder { color: var(--lgu-text-muted); opacity: 0.75; }
    .btn-lgu-search {
        background: linear-gradient(135deg, #1c4e9e 0%, #4a7dfc 100%);
        color: white;
        font-weight: 600;
        border: none;
        border-radius: 8px;
        padding: 10px;
        box-shadow: 0 3px 8px rgba(28, 78, 158, 0.3);
        transition: transform 0.12s ease, box-shadow 0.12s ease, color 0.12s ease;
    }
    .btn-lgu-search:hover,
    .btn-lgu-search:focus,
    .btn-lgu-search:active {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 5px 12px rgba(28, 78, 158, 0.4);
    }

    /* Compliance confirm/send button — gradient variants (matches applications.php / view.php gradient style) */
    .btn-compliant-gradient {
        background: linear-gradient(135deg, #0f7a4e 0%, #17a566 100%);
        border: none;
        color: #fff;
        border-radius: 8px;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(23, 165, 102, 0.32);
        transition: transform 0.12s ease, box-shadow 0.12s ease, color 0.12s ease;
    }
    .btn-compliant-gradient:hover,
    .btn-compliant-gradient:focus,
    .btn-compliant-gradient:active {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(23, 165, 102, 0.4);
    }
    .btn-noncompliant-gradient {
        background: linear-gradient(135deg, #a52834 0%, #dc3545 100%);
        border: none;
        color: #fff;
        border-radius: 8px;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.32);
        transition: transform 0.12s ease, box-shadow 0.12s ease, color 0.12s ease;
    }
    .btn-noncompliant-gradient:hover,
    .btn-noncompliant-gradient:focus,
    .btn-noncompliant-gradient:active {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(220, 53, 69, 0.4);
    }
    .analysis-inner { background: var(--lgu-analysis-bg); border-radius: 10px; border-left: 4px solid #4e73df; padding: 15px; }
    .table-analysis td { padding: 8px 0; font-size: 0.85rem; border-bottom: 1px solid var(--lgu-table-border); color: var(--lgu-input-text); }
    .table-analysis tr:last-child td { border-bottom: none; }
    #zoningComplianceCard { display: none; margin-top: 20px; }

    /* Leaflet.Draw's built-in cursor tooltip ("Click to start drawing
       shape.", "Click to continue drawing shape.", etc.) — hidden at
       every width, not just mobile, since it just repeats what the
       toolbar buttons already make clear and gets in the way on touch
       screens where there's no cursor to follow anyway. */
    .leaflet-draw-tooltip,
    .leaflet-draw-tooltip-single,
    .leaflet-draw-tooltip-subtext {
        display: none !important;
    }

    /* ── LAYER PICKER (collapsed toggle, Google Maps style) ── */
    .lgu-layer-fab {
        width: 42px; height: 42px;
        background: var(--lgu-fab-bg);
        border: none;
        border-radius: 10px;
        box-shadow: 0 2px 12px rgba(26,35,126,0.18), 0 1px 4px rgba(0,0,0,0.10);
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        color: var(--lgu-fab-color);
        font-size: 1.1rem;
        transition: background 0.15s, box-shadow 0.15s;
        position: relative;
        z-index: 2;
    }
    .lgu-layer-fab:hover { background: var(--lgu-fab-hover-bg); box-shadow: 0 4px 18px rgba(26,35,126,0.22); }
    .lgu-layer-fab.open { background: #1a237e; color: #fff; }

    .lgu-layer-popup {
        position: fixed;
        background: var(--lgu-popup-bg);
        border-radius: 12px;
        box-shadow: 0 6px 28px rgba(26,35,126,0.16), 0 2px 8px rgba(0,0,0,0.09);
        border: 1px solid var(--lgu-popup-border);
        min-width: 170px;
        transform-origin: top right;
        transform: scale(0.85);
        opacity: 0;
        pointer-events: none;
        transition: transform 0.18s cubic-bezier(.4,0,.2,1), opacity 0.15s ease;
        z-index: 10000;
        max-height: min(280px, calc(100vh - 24px));
        overflow-y: auto;
        overflow-x: hidden;
    }
    .lgu-layer-popup.open {
        transform: scale(1);
        opacity: 1;
        pointer-events: all;
    }
    .lgu-layer-popup-header {
        background: #1a237e;
        color: #fff;
        padding: 8px 13px 7px;
        font-size: 0.65rem;
        font-weight: 800;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        display: flex; align-items: center; gap: 6px;
    }
    .lgu-layer-opt {
        display: flex; align-items: center; gap: 10px;
        width: 100%; border: none;
        background: transparent;
        padding: 9px 13px;
        cursor: pointer;
        font-size: 0.8rem; font-weight: 500;
        color: var(--lgu-opt-color);
        transition: background 0.13s, color 0.13s;
        position: relative; text-align: left;
    }
    .lgu-layer-opt:not(:last-child) { border-bottom: 1px solid var(--lgu-opt-border); }
    .lgu-layer-opt:hover { background: var(--lgu-opt-hover-bg); color: var(--lgu-fab-color); }
    .lgu-layer-opt.active {
        background: var(--lgu-opt-active-bg);
        color: var(--lgu-fab-color); font-weight: 700;
    }
    .lgu-layer-opt.active::before {
        content:''; position:absolute; left:0; top:0; bottom:0;
        width:3px; background: var(--lgu-fab-color); border-radius:0 2px 2px 0;
    }
    .lgu-opt-icon {
        width: 28px; height: 28px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.9rem; flex-shrink: 0;
    }
    .lgu-layer-opt.active .lgu-opt-icon { background: var(--lgu-fab-color); color: #fff; }
    .lgu-layer-opt:not(.active) .lgu-opt-icon { background: var(--lgu-opt-icon-bg); color: var(--lgu-opt-icon-color); }
    .lgu-opt-radio {
        width: 13px; height: 13px; border-radius: 50%;
        border: 2px solid var(--lgu-opt-radio-border); margin-left: auto; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
    }
    .lgu-layer-opt.active .lgu-opt-radio {
        border-color: var(--lgu-fab-color); background: var(--lgu-fab-color);
        box-shadow: 0 0 0 3px rgba(26,35,126,0.12);
    }
    .lgu-layer-opt.active .lgu-opt-radio::after {
        content:''; width:4px; height:4px; border-radius:50%; background:#fff; display:block;
    }


    /* ── DARK MODE: markup-level Bootstrap utility overrides ──
       Bootstrap's .bg-white / .text-dark / .bg-light utilities are fixed
       colors, not theme-aware, so this page's cards/headers/badges need
       explicit dark overrides on top of the token swap above. */
    [data-bs-theme="dark"] .search-panel .card-header.bg-white,
    [data-bs-theme="dark"] .card-header.bg-white {
        background-color: var(--lgu-card-bg) !important;
        border-color: var(--lgu-border) !important;
    }
    [data-bs-theme="dark"] .text-dark { color: var(--lgu-page-heading) !important; }
    [data-bs-theme="dark"] h2[style*="color"] { color: var(--lgu-page-heading) !important; }
    [data-bs-theme="dark"] .text-muted { color: var(--lgu-text-muted) !important; }
    [data-bs-theme="dark"] .text-secondary { color: var(--lgu-text-secondary) !important; }

    [data-bs-theme="dark"] #analysisResults .border.rounded-3.bg-light,
    [data-bs-theme="dark"] .bg-light {
        background-color: var(--lgu-placeholder-bg) !important;
        border-color: var(--lgu-border) !important;
    }

    [data-bs-theme="dark"] .badge.bg-light {
        background-color: var(--lgu-badge-bg) !important;
        border-color: var(--lgu-border) !important;
    }

    [data-bs-theme="dark"] #zoningComplianceCard,
    [data-bs-theme="dark"] #zoningComplianceCard .bg-white {
        background-color: var(--lgu-card-bg) !important;
    }

    [data-bs-theme="dark"] .hover-bg:hover,
    [data-bs-theme="dark"] .overlay-item:hover {
        background-color: var(--lgu-opt-hover-bg) !important;
    }

    /* Zoning legend swatch labels + Leaflet layer controls */
    [data-bs-theme="dark"] .lgu-layer-popup-header { background: #14183a; }
    [data-bs-theme="dark"] .leaflet-control-layers,
    [data-bs-theme="dark"] .leaflet-bar a {
        background-color: var(--lgu-card-bg) !important;
        color: var(--lgu-input-text) !important;
        border-color: var(--lgu-border) !important;
    }
    [data-bs-theme="dark"] .leaflet-bar a:hover { background-color: var(--lgu-opt-hover-bg) !important; }

    /* ================================================
       MOBILE RESPONSIVE
       1024px (Laptop) | 768px (Tablet) |
       425px (Mobile) | 320px (Small Mobile)
       ================================================ */

    /* --- 1024px: Laptop ---
       col-md-4 / col-md-8 stay side-by-side at this width (Bootstrap's
       md breakpoint is 768px), so this tier just tightens spacing and
       shortens the map rather than restacking the layout. */
    @media (max-width: 1024px) {

        .p-4 { padding: 1.25rem !important; }

        /* Page header */
        .d-flex.justify-content-between.align-items-center.mb-4 h2 { font-size: 1.5rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 i { font-size: 1.6rem !important; }

        /* Map: still tall, but not full desktop height */
        #map { height: 600px !important; }

        /* Search panel: col-md-4 is narrower here, give the 2-col
           rows (lat/lng, block/lot) a bit more breathing room */
        .card-body.p-4 { padding: 1.1rem !important; }
        .form-control-lgu { font-size: 0.83rem; padding: 7px 10px; }
        .section-label { font-size: 0.68rem; }
        .btn-lgu-search { font-size: 0.85rem; padding: 9px; }

        /* Analysis / compliance cards */
        .analysis-inner { padding: 13px; }
        .table-analysis td { font-size: 0.82rem; }

        /* Map card header: title can crowd the badge as col-md-8 narrows */
        .col-md-8 .card-header span.fw-bold { font-size: 0.9rem; }
        .col-md-8 .card-header .badge { font-size: 0.7rem; }

        /* Legend row: tighten gap so swatches don't wrap awkwardly */
        .search-panel .d-flex.flex-wrap.justify-content-center { gap: 12px !important; }

        /* Leaflet native controls: plenty of room at this width, just
           tuck the corners in a touch so they don't sit flush against
           the rounded card edge. */
        .leaflet-top.leaflet-left,
        .leaflet-top.leaflet-right { top: 8px !important; }
        .leaflet-top.leaflet-left { left: 8px !important; }
        .leaflet-top.leaflet-right { right: 8px !important; }

        /* Layer picker FAB + popup */
        .lgu-layer-popup { min-width: 165px; }
    }

    /* --- 768px: Tablet --- */
    @media (max-width: 768px) {

        .p-4 { padding: 1rem !important; }

        /* Page header */
        .d-flex.justify-content-between.align-items-center.mb-4 h2 { font-size: 1.3rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 span { width: 36px !important; height: 36px !important; }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 i { font-size: 1.1rem !important; }
        .d-flex.justify-content-between.align-items-center.mb-4 p { font-size: 0.8rem; }

        /* Stack: left panel full width above map */
        .row > .col-md-4,
        .row > .col-md-8 { width: 100%; flex: 0 0 100%; }

        /* Map: shorter on tablet */
        #map { height: 420px !important; border-radius: 10px !important; }

        /* Search header */
        .search-header { padding: 12px 15px; }
        .search-header h6 { font-size: 0.9rem; }

        /* Search panel body */
        .card-body.p-4 { padding: 1rem !important; }

        /* Form inputs */
        .form-control-lgu { font-size: 0.82rem; padding: 7px 10px; }
        .section-label { font-size: 0.68rem; }

        /* Search button */
        .btn-lgu-search { padding: 9px; font-size: 0.875rem; }

        /* Analysis / Zoning cards */
        .analysis-inner { padding: 12px; }
        .table-analysis td { font-size: 0.8rem; padding: 6px 0; }

        /* Zoning overlay select */
        .form-select.form-control-lgu { font-size: 0.82rem; }

        /* Compliance card */
        #zoningComplianceCard .card-body { padding: 1rem !important; }
        #zoningComplianceCard h6 { font-size: 0.82rem; }

        /* Gap between legend and map card */
        .search-panel.mt-3 { margin-bottom: 1rem !important; }

        /* Leaflet native controls */
        .leaflet-top.leaflet-left,
        .leaflet-top.leaflet-right { top: 8px !important; }
        .leaflet-top.leaflet-left { left: 8px !important; }
        .leaflet-top.leaflet-right { right: 8px !important; }
        .leaflet-control-zoom a { width: 30px !important; height: 30px !important; line-height: 30px !important; }
        .leaflet-draw-toolbar { margin-left: 6px !important; margin-top: 0 !important; }
        .leaflet-draw-actions a { font-size: 0.8rem !important; }

        /* Layer picker FAB + popup */
        .lgu-layer-fab { width: 40px; height: 40px; }
        .lgu-layer-popup { min-width: 160px; }

        /* Map card header: title/badge can start crowding once the
           card goes full-width but the map itself is still short */
        .col-md-8 .card-header span.fw-bold { font-size: 0.85rem; }
        .col-md-8 .card-header .badge { font-size: 0.68rem; }
    }

    /* --- 425px: Mobile --- */
    @media (max-width: 425px) {

        .p-4 { padding: 0.65rem !important; }

        /* Page header */
        .d-flex.justify-content-between.align-items-center.mb-4 h2 { font-size: 1.02rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 span { width: 30px !important; height: 30px !important; }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 i { font-size: 0.92rem !important; }
        .d-flex.justify-content-between.align-items-center.mb-4 p { font-size: 0.7rem; }

        /* Map */
        #map { height: 280px !important; border-radius: 7px !important; }

        /* Search header */
        .search-header { padding: 9px 11px; }
        .search-header h6 { font-size: 0.78rem; }
        .search-header small { font-size: 0.58rem !important; }

        /* Form inputs */
        .card-body.p-4 { padding: 0.65rem !important; }
        .form-control-lgu { font-size: 0.75rem; padding: 6px 8px; border-radius: 5px; }
        .section-label { font-size: 0.6rem; margin-bottom: 3px; }
        .mb-2 { margin-bottom: 0.3rem !important; }
        .mb-3 { margin-bottom: 0.45rem !important; }
        .row.g-2 { --bs-gutter-x: 0.3rem; --bs-gutter-y: 0.3rem; }

        /* Search button */
        .btn-lgu-search { padding: 7px; font-size: 0.78rem; }

        /* Analysis card */
        .analysis-inner { padding: 9px; }
        .table-analysis td { font-size: 0.72rem; padding: 4px 0; }
        #analysisResults .text-center.py-4 { padding: 0.6rem !important; }
        #analysisResults .text-center.py-4 p { font-size: 0.72rem; }
        #analysisResults .fs-3 { font-size: 1.1rem !important; }

        /* Zoning overlay */
        .form-select.form-control-lgu { font-size: 0.75rem; padding: 5px 7px; }
        .form-check-label.small { font-size: 0.72rem; }
        .overlay-item { padding: 0.3rem 0.45rem !important; }

        /* Compliance card */
        #zoningComplianceCard { margin-top: 10px; }
        #zoningComplianceCard .card-body { padding: 0.65rem !important; }
        #zoningComplianceCard h6 { font-size: 0.75rem; }
        #zoningComplianceCard .btn { font-size: 0.75rem; padding: 6px 12px; }
        #zoningComplianceCard .badge { font-size: 0.62rem; }

        /* Search panels: reduce spacing */
        .search-panel.mb-4 { margin-bottom: 0.65rem !important; }
        .search-panel.mt-3 { margin-bottom: 0.65rem !important; }

        /* Legend */
        .search-panel .card-body.py-3 { padding: 0.4rem 0.55rem !important; }
        .search-panel .d-flex.flex-wrap.justify-content-center { gap: 6px !important; }
        .search-panel .d-flex.align-items-center > div { width: 19px !important; height: 8px !important; margin-right: 4px !important; }
        .search-panel .d-flex.align-items-center span { font-size: 0.62rem !important; }

        /* Map card header */
        .col-md-8 .card-header {
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding: 8px 9px !important;
            gap: 5px;
        }
        .col-md-8 .card-header span.fw-bold { font-size: 0.7rem; white-space: nowrap; }
        .col-md-8 .card-header span.fw-bold i { margin-left: 0 !important; }
        .col-md-8 .card-header .badge { font-size: 0.6rem !important; padding: 3px 6px !important; margin-right: 0 !important; white-space: nowrap; }

        /* Leaflet native controls */
        .leaflet-top.leaflet-left,
        .leaflet-top.leaflet-right { top: 5px !important; }
        .leaflet-top.leaflet-left { left: 5px !important; }
        .leaflet-top.leaflet-right { right: 5px !important; }
        .leaflet-control-zoom a { width: 26px !important; height: 26px !important; line-height: 26px !important; font-size: 15px !important; }
        .leaflet-draw-toolbar { margin-left: 3px !important; margin-top: 0 !important; }
        .leaflet-draw-actions a { font-size: 0.72rem !important; }

        /* Layer picker FAB + popup */
        .lgu-layer-fab { width: 34px; height: 34px; font-size: 0.95rem; border-radius: 8px; }
        .lgu-layer-popup { min-width: 145px; max-width: calc(100vw - 24px); }
        .lgu-layer-popup-header { padding: 6px 10px 5px; font-size: 0.58rem; }
        .lgu-layer-opt { padding: 7px 10px; font-size: 0.72rem; gap: 7px; }
        .lgu-opt-icon { width: 22px; height: 22px; font-size: 0.75rem; }
    }

    /* --- 320px: Small Mobile --- */
    @media (max-width: 320px) {

        .p-4 { padding: 0.5rem !important; }

        /* Page header */
        .d-flex.justify-content-between.align-items-center.mb-4 h2 { font-size: 0.95rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 span { width: 28px !important; height: 28px !important; }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 i { font-size: 0.85rem !important; }
        .d-flex.justify-content-between.align-items-center.mb-4 p { font-size: 0.68rem; }

        /* Map: minimal height */
        #map { height: 240px !important; border-radius: 6px !important; }

        /* Map card header: stack title above badge */
        .col-md-8 .card-header { flex-direction: column !important; align-items: flex-start !important; gap: 4px; padding: 8px 10px !important; }
        .col-md-8 .card-header span.fw-bold { font-size: 0.7rem; }
        .col-md-8 .card-header span.fw-bold i { margin-left: 0 !important; }
        .col-md-8 .card-header .badge { font-size: 0.6rem !important; padding: 3px 8px !important; margin-right: 0 !important; }

        /* Legend */
        .search-panel .card-body.py-3 { padding: 0.4rem 0.5rem !important; }
        .search-panel .d-flex.flex-wrap.justify-content-center { gap: 5px !important; }
        .search-panel .d-flex.align-items-center > div { width: 16px !important; height: 7px !important; margin-right: 3px !important; }
        .search-panel .d-flex.align-items-center span { font-size: 0.58rem !important; }

        /* Search header */
        .search-header { padding: 8px 10px; }
        .search-header h6 { font-size: 0.75rem; }
        .search-header small { font-size: 0.55rem !important; }

        /* Form */
        .card-body.p-4 { padding: 0.6rem !important; }
        .form-control-lgu { font-size: 0.72rem; padding: 5px 8px; border-radius: 4px; }
        .section-label { font-size: 0.58rem; margin-bottom: 2px; letter-spacing: 0.3px; }
        .mb-2 { margin-bottom: 0.25rem !important; }
        .mb-3 { margin-bottom: 0.4rem !important; }
        .row.g-2 { --bs-gutter-x: 0.25rem; --bs-gutter-y: 0.25rem; }

        /* Search button */
        .btn-lgu-search { padding: 7px; font-size: 0.75rem; }
        .btn-lgu-search i { margin-right: 4px !important; }

        /* Analysis */
        .analysis-inner { padding: 8px; }
        .table-analysis td { font-size: 0.68rem; padding: 4px 0; }
        #analysisResults .text-center.py-4 { padding: 0.5rem !important; }
        #analysisResults .text-center.py-4 p { font-size: 0.68rem; }
        #analysisResults .fs-3 { font-size: 1rem !important; }

        /* Zoning overlay */
        .form-select.form-control-lgu { font-size: 0.72rem; padding: 4px 7px; }
        .form-check-label.small { font-size: 0.68rem; }
        .overlay-item { padding: 0.25rem 0.4rem !important; }

        /* Compliance card: stack info + button */
        #zoningComplianceCard { margin-top: 8px; }
        #zoningComplianceCard .card-body { padding: 0.6rem !important; }
        #zoningComplianceCard .d-flex.justify-content-between { flex-direction: column; gap: 8px; }
        #zoningComplianceCard h6 { font-size: 0.72rem; }
        #zoningComplianceCard #complianceStatusText { font-size: 0.68rem; }
        #zoningComplianceCard #complianceActionBtn .btn { font-size: 0.72rem; padding: 6px 10px; width: 100%; text-align: center; }
        #zoningComplianceCard .badge { font-size: 0.6rem; padding: 2px 5px; }

        /* Card headers */
        .card-header h6 { font-size: 0.72rem; }

        /* Search panels */
        .search-panel.mb-4 { margin-bottom: 0.6rem !important; }
        .search-panel.mt-3 { margin-top: 0.6rem !important; margin-bottom: 0.75rem !important; }

        /* Leaflet native controls: at 320px the map is only 240px tall,
           so zoom + draw toolbar + layer FAB must all shrink further
           to avoid stacking on top of one another */
        .leaflet-top.leaflet-left,
        .leaflet-top.leaflet-right { top: 4px !important; }
        .leaflet-top.leaflet-left { left: 4px !important; }
        .leaflet-top.leaflet-right { right: 4px !important; }
        .leaflet-control-zoom a { width: 24px !important; height: 24px !important; line-height: 24px !important; font-size: 14px !important; }
        /* Native (touch-optimized) size kept for the same reason noted
           in the 425px block above — scaling breaks Leaflet.Draw's own
           action-pill positioning math. */
        .leaflet-draw-toolbar { margin-left: 2px !important; margin-top: 0 !important; }
        .leaflet-draw-toolbar:not(:first-child) { margin-top: 3px !important; }
        .leaflet-draw-actions a { font-size: 0.7rem !important; }
        .leaflet-bar a { line-height: 24px !important; }

        /* Layer picker FAB + popup: keep the popup on-screen and
           readable inside a 320px viewport */
        .lgu-layer-fab { width: 32px; height: 32px; font-size: 0.9rem; border-radius: 8px; }
        .lgu-layer-popup { min-width: 135px; max-width: calc(100vw - 16px); }
        .lgu-layer-popup-header { padding: 6px 9px 5px; font-size: 0.55rem; letter-spacing: 0.8px; }
        .lgu-layer-opt { padding: 7px 9px; font-size: 0.68rem; gap: 7px; }
        .lgu-opt-icon { width: 20px; height: 20px; font-size: 0.7rem; border-radius: 5px; }
        .lgu-opt-radio { width: 10px; height: 10px; margin-left: 6px; }
    }
</style>

<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 d-flex align-items-center gap-2" style="color: #1e293b;">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle">
                    <i class="bi bi-map" style="color:#14b8a6;font-size:1.9rem;"></i>
                </span>
                GIS Map
            </h2>
            <p class="text-muted mb-0"><?php echo t_map('page_subheading', $translations, $lang); ?></p>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4">
            <div class="search-panel mb-4">
                <div class="search-header">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-search me-2"></i><?php echo t_map('search_panel_title', $translations, $lang); ?></h6>
                    <small class="opacity-75" style="font-size: 0.65rem;"><?php echo t_map('search_panel_sub', $translations, $lang); ?></small>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <span class="section-label"><?php echo t_map('lbl_geo_coords', $translations, $lang); ?></span>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <input type="text" class="form-control form-control-lgu" name="search_lat" placeholder="<?php echo t_map('ph_latitude', $translations, $lang); ?>" value="<?= htmlspecialchars($appLat ?? ''); ?>">
                            </div>
                            <div class="col-6">
                                <input type="text" class="form-control form-control-lgu" name="search_lng" placeholder="<?php echo t_map('ph_longitude', $translations, $lang); ?>" value="<?= htmlspecialchars($appLng ?? ''); ?>">
                            </div>
                        </div>

                        <span class="section-label"><?php echo t_map('lbl_admin_info', $translations, $lang); ?></span>
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-lgu" name="barangay" placeholder="<?php echo t_map('ph_barangay', $translations, $lang); ?>" value="<?= htmlspecialchars($_POST['barangay'] ?? $urlBarangay); ?>">
                        </div>
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-lgu" name="street" placeholder="<?php echo t_map('ph_street', $translations, $lang); ?>" value="<?= htmlspecialchars($_POST['street'] ?? $urlStreet); ?>">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6"><input type="text" class="form-control form-control-lgu" name="block" placeholder="<?php echo t_map('ph_block', $translations, $lang); ?>" value="<?= htmlspecialchars($_POST['block'] ?? $urlBlock); ?>"></div>
                            <div class="col-6"><input type="text" class="form-control form-control-lgu" name="lot_number" placeholder="<?php echo t_map('ph_lot', $translations, $lang); ?>" value="<?= htmlspecialchars($_POST['lot_number'] ?? $urlLot); ?>"></div>
                        </div>

                        <button type="submit" name="search" class="btn btn-lgu-search w-100 rounded-3 mt-2 shadow-sm">
                            <i class="bi bi-geo-alt-fill me-2"></i><?php echo t_map('btn_locate', $translations, $lang); ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="search-panel mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="m-0 font-weight-bold text-primary small text-uppercase fw-bold ms-3">
                        <i class="bi bi-graph-up-arrow me-2"></i><?php echo t_map('analysis_title', $translations, $lang); ?>
                    </h6>
                </div>
                <div id="analysisResults" class="card-body pt-0">
                    <div class="text-center py-4 text-muted border rounded-3 bg-light">
                        <i class="bi bi-mouse2 fs-3 d-block mb-2 opacity-50"></i>
                        <p class="small mb-0 px-3"><?php echo t_map('analysis_placeholder', $translations, $lang); ?></p>
                    </div>
                </div>
            </div>

            <div class="search-panel">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="m-0 font-weight-bold text-dark small text-uppercase fw-bold ms-3">
                        <i class="bi bi-layers-half me-2"></i><?php echo t_map('overlay_title', $translations, $lang); ?>
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <select id="zoningFilter" class="form-select form-select-sm form-control-lgu">
                            <option value=""><?php echo t_map('overlay_show_all', $translations, $lang); ?></option>
                            <?php foreach ($zoningClassifications as $z): ?>
                                <option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['code']) ?> (<?= htmlspecialchars($z['name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check form-switch overlay-item d-flex align-items-center justify-content-between p-2 mb-1 rounded hover-bg">
                        <label class="form-check-label small fw-bold text-muted mb-0 cursor-pointer" for="toggleQCBoundary">
                            <i class="bi bi-geo-alt me-1" style="color:#1a237e;"></i>
                            <?php echo ($lang === 'fil') ? 'Hangganan ng Quezon City' : 'QC City Boundary'; ?>
                        </label>
                        <input class="form-check-input cursor-pointer" type="checkbox" id="toggleQCBoundary" checked>
                    </div>
                </div>
            </div>

            <?php
// In-update nating function para kasama ang Color Name at Hex
function getZoneDetails($code) {
    $code = strtoupper($code);
    if (in_array($code, ['R1', 'R2', 'R-3'])) 
        return ['color' => '#ffff00', 'label' => 'Yellow - Residential'];
    if (in_array($code, ['C1', 'C2', 'C-3'])) 
        return ['color' => '#ff0000', 'label' => 'Red - Commercial'];
    if (in_array($code, ['I1', 'I-2'])) 
        return ['color' => '#9c27b0', 'label' => 'Purple - Industrial'];
    if ($code === 'INST') 
        return ['color' => '#0000ff', 'label' => 'Blue - Institutional'];
    if ($code === 'PRK') 
        return ['color' => '#4caf50', 'label' => 'Green - Parks'];
    if ($code === 'S-CZ') 
        return ['color' => '#795548', 'label' => 'Brown - Special Control'];
    
    return ['color' => '#6c757d', 'label' => 'Gray - Other'];
}
?>

<div class="search-panel mt-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <?php foreach ($zoningClassifications as $z): 
                // Kunin ang details (hex at color name) base sa code
                $details = getZoneDetails($z['code']); 
            ?>
                <div class="d-flex align-items-center" title="<?= $details['label'] ?>">
                    <div style="width: 25px; height: 10px; background-color: <?= $details['color'] ?>; border-radius: 2px; margin-right: 8px; border: 1px solid rgba(0,0,0,0.1);"></div>
                    <span class="small text-secondary fw-bold" style="font-size: 0.7rem;">
                        <?= htmlspecialchars($z['code']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
    
        </div>

        <div class="col-md-8">
            <div class="search-panel border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <span class="fw-bold text-dark small"><i class="bi bi-map-fill me-2 text-primary ms-3"></i><?php echo t_map('map_card_title', $translations, $lang); ?></span>
                    <span class="badge bg-light text-primary border border-primary-subtle px-3 py-2 me-3"><?php echo t_map('map_card_badge', $translations, $lang); ?></span>
                </div>
                <div class="card-body p-0">
                    <div id="map"></div>
                </div>
            </div>

            <div id="zoningComplianceCard" class="card border-0 shadow-sm mt-3">
                <div class="card-body p-4 bg-white rounded-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div id="complianceInfo">
                            <h6 class="fw-bold mb-1 text-uppercase small text-primary"><i class="bi bi-shield-check me-2"></i><?php echo t_map('compliance_title', $translations, $lang); ?></h6>
                            <p id="complianceStatusText" class="text-muted small mb-0"><?php echo t_map('compliance_placeholder', $translations, $lang); ?></p>
                        </div>
                        <div id="complianceActionBtn"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>

<script>
window.MAP_CONFIG = {
    lang: <?php echo json_encode([
        'zoningRecord'  => t_map('js_zoning_record', $translations, $lang),
        'customArea'    => t_map('js_custom_area', $translations, $lang),
        'unknownZone'   => t_map('js_unknown_zone', $translations, $lang),
        'analysisLat'   => t_map('js_analysis_lat', $translations, $lang),
        'analysisLng'   => t_map('js_analysis_lng', $translations, $lang),
        'analysisZone'  => t_map('js_analysis_zone', $translations, $lang),
        'bufferBtn'     => t_map('js_buffer_btn', $translations, $lang),
        'confirmSend'   => t_map('js_confirm_send', $translations, $lang),
        'noAppId'       => t_map('js_no_app_id', $translations, $lang),
        'coords'        => t_map('js_coords', $translations, $lang),
        'zoningZone'    => t_map('js_zoning_zone', $translations, $lang),
        'landRecord'    => t_map('js_land_record', $translations, $lang),
        'statusCheck'   => t_map('js_status_check', $translations, $lang),
        'pointIs'       => t_map('js_point_is', $translations, $lang),
    ]); ?>,
    userRole: <?php echo json_encode($_SESSION['role'] ?? ''); ?>,
    allParcels: <?= json_encode($allParcels) ?>,
    targetAppId: <?= json_encode($targetAppId) ?>,
    appLat: <?= json_encode($appLat) ?>,
    appLng: <?= json_encode($appLng) ?>,
    dbLot: <?= json_encode(htmlspecialchars($urlLot)) ?>,
    dbBlock: <?= json_encode(htmlspecialchars($urlBlock)) ?>,
    selectedParcel: <?php if ($selectedParcel): ?>{
        geoRaw: <?= json_encode($selectedParcel['geom_json']) ?>,
        id:     <?= json_encode($selectedParcel['id']) ?>,
        zone:   <?= json_encode($selectedParcel['zoning_name']) ?>,
        lot:    <?= json_encode($selectedParcel['lot_number']) ?>,
        block:  <?= json_encode($selectedParcel['block']) ?>
    }<?php else: ?>null<?php endif; ?>
};
</script>
<script src="/lgu-urban-planning/assets/js/admin-map.js"></script>

<?php include __DIR__ . '/../admin/footer.php'; ?>