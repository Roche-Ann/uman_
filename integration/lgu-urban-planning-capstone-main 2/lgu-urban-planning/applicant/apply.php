<?php
/**
 * Submit Development Permit Application
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../modules/ApplicantSelfService/ApplicantController.php';

$auth = new Auth();
$auth->requireLogin();          // redirect to login if not authenticated
$auth->requireRole('applicant');



$applicantController = new ApplicantController();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'project_name'        => $_POST['project_name'] ?? '',
        'project_type'        => $_POST['project_type'] ?? '',
        'project_description' => $_POST['project_description'] ?? '',
        'lot_number'          => $_POST['lot_number'] ?? '',
        'block'               => $_POST['block'] ?? '',
        'street'              => $_POST['street'] ?? '',
        'barangay'            => $_POST['barangay'] ?? '',
        'district'            => $_POST['district'] ?? '',
        'parcel_id'           => $_POST['parcel_id'] ?? '',
        'latitude'            => $_POST['latitude'] ?? null,
        'longitude'           => $_POST['longitude'] ?? null
    ];

    if (empty($data['project_name'])) {
        $error = _apt('err_project_name');
    } else {
        $applicationId = $applicantController->submitApplication($data);

        if (isset($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
            foreach ($_FILES['documents']['name'] as $key => $name) {
                if (!empty($name)) {
                    $file = [
                        'name'     => $name,
                        'type'     => $_FILES['documents']['type'][$key],
                        'tmp_name' => $_FILES['documents']['tmp_name'][$key],
                        'error'    => $_FILES['documents']['error'][$key],
                        'size'     => $_FILES['documents']['size'][$key]
                    ];
                    $documentType = $_POST['document_types'][$key] ?? 'other';
                    $applicantController->uploadDocument($applicationId, $file, $documentType);
                }
            }
        }

        header('Location: /lgu-urban-planning/applicant/view.php?id=' . $applicationId);
        exit;
    }
}

// ── i18n — reads language saved by settings.php ──────────────────────────────
$_apLang = $_SESSION['locale_language'] ?? 'en_PH';

$_apT = [
    'en_PH' => [
        'page_title'          => 'Submit Application',
        'heading'             => 'Submit Development Permit Application',
        'err_project_name'    => 'Project name is required',
        'sec_project'         => 'Project Information',
        'sec_location'        => 'Location Information',
        'sec_documents'       => 'Required Documents',
        'lbl_project_name'    => 'Project Name',
        'lbl_project_type'    => 'Project Type',
        'lbl_project_desc'    => 'Project Description',
        'opt_select_type'     => 'Select project type',
        'opt_residential'     => 'Residential',
        'opt_commercial'      => 'Commercial',
        'opt_industrial'      => 'Industrial',
        'opt_institutional'   => 'Institutional',
        'lbl_lot_number'      => 'Lot Number',
        'lbl_block'           => 'Block Number',
        'lbl_street'          => 'Street',
        'lbl_barangay'        => 'Barangay',
        'lbl_district'        => 'District',
        'lbl_parcel_id'       => 'Parcel ID (PIN)',
        'lbl_coordinates'     => 'Project Location (Coordinates)',
        'btn_pick_map'        => 'Pick On Map',
        'ph_latitude'         => 'Latitude',
        'ph_longitude'        => 'Longitude',
        'lbl_qc_boundary'     => 'Dashed line marks the Quezon City boundary',
        'lbl_document'        => 'Document',
        'opt_site_plan'       => 'Site Plan',
        'opt_lot_plan'        => 'Lot Plan',
        'opt_ownership_proof' => 'Ownership Proof',
        'opt_building_plan'   => 'Building Plan',
        'opt_other'           => 'Other',
        'btn_add_doc'         => 'Add Another Document',
        'btn_submit'          => 'Submit Application',
        'btn_cancel'          => 'Cancel',
    ],
    'fil' => [
        'page_title'          => 'Magsumite ng Aplikasyon',
        'heading'             => 'Magsumite ng Aplikasyon para sa Development Permit',
        'err_project_name'    => 'Kinakailangan ang pangalan ng proyekto',
        'sec_project'         => 'Impormasyon ng Proyekto',
        'sec_location'        => 'Impormasyon ng Lokasyon',
        'sec_documents'       => 'Mga Kinakailangang Dokumento',
        'lbl_project_name'    => 'Pangalan ng Proyekto',
        'lbl_project_type'    => 'Uri ng Proyekto',
        'lbl_project_desc'    => 'Paglalarawan ng Proyekto',
        'opt_select_type'     => 'Pumili ng uri ng proyekto',
        'opt_residential'     => 'Residential',
        'opt_commercial'      => 'Komersyal',
        'opt_industrial'      => 'Industrial',
        'opt_institutional'   => 'Institusyonal',
        'lbl_lot_number'      => 'Numero ng Lote',
        'lbl_block'           => 'Numero ng Bloke',
        'lbl_street'          => 'Kalye',
        'lbl_barangay'        => 'Barangay',
        'lbl_district'        => 'Distrito',
        'lbl_parcel_id'       => 'ID ng Parsela (PIN)',
        'lbl_coordinates'     => 'Lokasyon ng Proyekto (Koordinada)',
        'btn_pick_map'        => 'Pumili sa Mapa',
        'ph_latitude'         => 'Latitude',
        'ph_longitude'        => 'Longitude',
        'lbl_qc_boundary'     => 'Ang guhit na patuldok-tuldok ay ang hangganan ng Quezon City',
        'lbl_document'        => 'Dokumento',
        'opt_site_plan'       => 'Plano ng Site',
        'opt_lot_plan'        => 'Plano ng Lote',
        'opt_ownership_proof' => 'Patunay ng Pagmamay-ari',
        'opt_building_plan'   => 'Plano ng Gusali',
        'opt_other'           => 'Iba pa',
        'btn_add_doc'         => 'Magdagdag ng Isa pang Dokumento',
        'btn_submit'          => 'Isumite ang Aplikasyon',
        'btn_cancel'          => 'Kanselahin',
    ],
];

function _apt(string $key): string {
    global $_apT, $_apLang;
    return $_apT[$_apLang][$key] ?? $_apT['en_PH'][$key] ?? $key;
}


$pageTitle = _apt('page_title');
$isAuthPage = true;
include __DIR__ . '/../user/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* =============================================
   APPLY PAGE — MODERN CIVIC THEME
   Fully responsive — 1024px | 768px | 480px | 320px
   ============================================= */

.apply-page {
    --ap-navy:        #16324F;
    --ap-navy-deep:   #0F2438;
    --ap-navy-tint:   #EAF0F5;
    --ap-gold:        #A9812F;
    --ap-gold-tint:   #F6EFDE;
    --ap-bg:          #F6F7F9;
    --ap-surface:     #FFFFFF;
    --ap-border:      #E2E6EC;
    --ap-text:        #1C2733;
    --ap-text-muted:  #667085;
    --ap-danger:      #B3261E;
    --ap-danger-tint: #FBEAE9;
    --ap-focus:       rgba(22,50,79,.16);

    width: 100%;
    box-sizing: border-box;
    overflow-x: hidden;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ap-text);
}

.apply-page *,
.apply-page *::before,
.apply-page *::after {
    box-sizing: border-box;
    max-width: 100%;
}

/* --- Dark mode ---
   Every rule below already reads colors from the --ap-* custom
   properties, so redefining them here re-themes the whole page. */
[data-bs-theme="dark"] .apply-page {
    --ap-navy:        #4d8eff;
    --ap-navy-deep:   #cfe2ff;
    --ap-navy-tint:   rgba(77, 142, 255, .15);
    --ap-gold:        #e8c568;
    --ap-gold-tint:   rgba(232, 197, 104, .12);
    --ap-bg:          var(--bs-body-bg);
    --ap-surface:     var(--bs-tertiary-bg);
    --ap-border:      var(--bs-border-color);
    --ap-text:        var(--bs-body-color);
    --ap-text-muted:  var(--bs-secondary-color);
    --ap-danger:      #ea868f;
    --ap-danger-tint: rgba(234, 134, 143, .12);
    --ap-focus:       rgba(77, 142, 255, .25);
}

[data-bs-theme="dark"] .apply-page .form-control::placeholder {
    color: var(--bs-secondary-color);
}

/* --- Page heading --- */
.apply-page .apply-header {
    margin-bottom: 1.75rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--ap-border);
}

.apply-page .apply-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: var(--ap-gold);
    margin-bottom: 0.5rem;
}

.apply-page .apply-eyebrow::before {
    content: "";
    display: inline-block;
    width: 18px;
    height: 2px;
    background: var(--ap-gold);
}

.apply-page h2 {
    font-size: 1.65rem;
    margin: 0;
    font-weight: 800;
    letter-spacing: -0.015em;
    color: var(--ap-navy-deep);
}

/* --- Cards --- */
.apply-page .card {
    width: 100%;
    background: var(--ap-surface);
    border: 1px solid var(--ap-border);
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(16,24,40,.04);
    overflow: hidden;
}

.apply-page .card + .card {
    margin-top: 1rem;
}

.apply-page .card-header {
    background: var(--ap-surface) !important;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--ap-border);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.apply-page .step-badge {
    flex: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--ap-navy);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.01em;
}

.apply-page .card-header h5 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
    color: var(--ap-navy-deep);
    letter-spacing: -0.01em;
}

.apply-page .card-body {
    padding: 1.5rem;
}

/* --- Alert --- */
.apply-page .alert-danger {
    background: var(--ap-danger-tint);
    border: 1px solid rgba(179,38,30,.25);
    color: var(--ap-danger);
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 500;
}

/* --- Form elements --- */
.apply-page .form-label {
    font-size: 0.84rem;
    font-weight: 600;
    margin-bottom: 0.4rem;
    color: var(--ap-text);
}

.apply-page .form-label .text-danger {
    color: var(--ap-danger) !important;
}

.apply-page .form-control,
.apply-page .form-select {
    font-size: 0.9rem;
    padding: 0.55rem 0.85rem;
    width: 100%;
    background: var(--ap-surface);
    border: 1px solid var(--ap-border);
    border-radius: 8px;
    color: var(--ap-text);
    transition: border-color .15s ease, box-shadow .15s ease;
}

.apply-page .form-control::placeholder {
    color: #9AA3B0;
}

.apply-page .form-control:focus,
.apply-page .form-select:focus {
    border-color: var(--ap-navy);
    box-shadow: 0 0 0 3px var(--ap-focus);
    outline: none;
}

.apply-page textarea.form-control {
    resize: vertical;
    min-height: 90px;
}

/* --- Map --- */
#map-container {
    height: 350px;
    width: 100%;
    border-radius: 10px;
    margin-top: 10px;
    border: 1px solid var(--ap-border);
    display: none;
}

.coord-input {
    background-color: var(--ap-navy-tint);
    border-color: var(--ap-border);
}

/* --- Map legend (Quezon City boundary) --- */
.map-legend {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.6rem;
    font-size: 0.78rem;
    color: var(--ap-text-muted);
}

.map-legend-swatch {
    flex: none;
    display: inline-block;
    width: 22px;
    height: 0;
    border-top: 2px dashed #1a237e;
}

/* --- Coordinates row --- */
.coord-section-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ap-text-muted);
    margin-bottom: 0;
}

#btn-select-map {
    color: var(--ap-navy);
    border-color: var(--ap-border);
    background: var(--ap-surface);
    font-weight: 600;
    font-size: 0.82rem;
    border-radius: 7px;
}

#btn-select-map:hover {
    background: var(--ap-navy-tint);
    border-color: var(--ap-navy);
    color: var(--ap-navy-deep);
}

/* --- Document upload rows --- */
.document-upload-item .row {
    align-items: center;
}

/* --- Buttons --- */
.apply-page .btn {
    border-radius: 8px;
    font-weight: 600;
}

.apply-page .btn-primary {
    background: var(--ap-navy);
    border-color: var(--ap-navy);
}

.apply-page .btn-primary:hover,
.apply-page .btn-primary:focus {
    background: var(--ap-navy-deep);
    border-color: var(--ap-navy-deep);
}

.apply-page .btn-outline-secondary {
    color: var(--ap-text-muted);
    border-color: var(--ap-border);
    background: var(--ap-surface);
}

.apply-page .btn-outline-secondary:hover {
    background: var(--ap-bg);
    color: var(--ap-text);
    border-color: var(--ap-text-muted);
}

.apply-page .btn-danger {
    background: var(--ap-danger);
    border-color: var(--ap-danger);
}

.apply-page .btn-light {
    background: var(--ap-surface);
    border-color: var(--ap-border) !important;
    color: var(--ap-text);
}

.apply-page .btn-light:hover {
    background: var(--ap-bg);
}

/* --- Submit buttons --- */
.apply-form-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    margin-top: 1.5rem;
}

.apply-form-actions .btn {
    font-size: 0.92rem;
    padding: 0.6rem 1.75rem;
}

/* =============================================
   1024px — Laptop
   ============================================= */
@media (max-width: 1024px) {

    .apply-page .card-body {
        padding: 1.25rem;
    }

    .apply-page h2 {
        font-size: 1.5rem;
    }

    #map-container { height: 320px; }

    .apply-form-actions .btn {
        font-size: 0.88rem;
        padding: 0.58rem 1.4rem;
    }
}

/* =============================================
   768px — Tablet
   ============================================= */
@media (max-width: 768px) {

    .apply-page h2 {
        font-size: 1.35rem;
    }

    .apply-page .apply-header {
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
    }

    .apply-page .card-header {
        padding: 0.85rem 1rem;
    }

    .apply-page .card-body {
        padding: 1.1rem;
    }

    .apply-page .row {
        margin-left: 0;
        margin-right: 0;
    }

    .apply-page .row > [class*="col-"] {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }

    .apply-page .col-md-6 {
        width: 100%;
        flex: 0 0 100%;
        max-width: 100%;
    }

    #map-container { height: 280px; }

    .coord-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.4rem;
    }

    .coord-inputs .col-md-6 {
        width: 50%;
        flex: 0 0 50%;
        max-width: 50%;
    }

    .document-upload-item .col-md-4,
    .document-upload-item .col-md-7,
    .document-upload-item .col-md-8 {
        width: 100%;
        flex: 0 0 100%;
        max-width: 100%;
        margin-bottom: 0.4rem;
    }

    .document-upload-item .col-md-1 {
        width: auto;
        flex: none;
    }

    .apply-form-actions .btn {
        flex: 1 1 auto;
        text-align: center;
    }
}

/* =============================================
   480px — Large Mobile
   ============================================= */
@media (max-width: 480px) {

    .apply-page h2 {
        font-size: 1.18rem;
    }

    .apply-page .apply-eyebrow {
        font-size: 0.66rem;
    }

    .apply-page .card-header {
        padding: 0.7rem 0.9rem;
        gap: 0.6rem;
    }

    .apply-page .step-badge {
        width: 24px;
        height: 24px;
        font-size: 0.68rem;
    }

    .apply-page .card-header h5 {
        font-size: 0.9rem;
    }

    .apply-page .card-body {
        padding: 0.9rem;
    }

    .apply-page .form-label {
        font-size: 0.8rem;
    }

    .apply-page .form-control,
    .apply-page .form-select {
        font-size: 0.85rem;
        padding: 0.5rem 0.7rem;
    }

    .apply-page textarea.form-control {
        min-height: 80px;
    }

    .coord-inputs .col-md-6 {
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
    }

    .coord-inputs .col-md-6:last-child {
        margin-top: 0.4rem;
    }

    #map-container { height: 240px; }

    #btn-select-map {
        width: 100%;
        margin-top: 0.4rem;
        font-size: 0.8rem;
    }

    .coord-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .document-upload-item {
        background: var(--ap-bg);
        border: 1px solid var(--ap-border);
        border-radius: 8px;
        padding: 0.7rem;
        margin-bottom: 0.65rem !important;
    }

    .document-upload-item .form-label {
        font-size: 0.78rem;
        font-weight: 600;
        margin-bottom: 0.4rem;
    }

    #document-uploads + button,
    .apply-page .btn-outline-secondary {
        width: 100%;
        font-size: 0.85rem;
        margin-top: 0.25rem;
    }

    .apply-form-actions {
        flex-direction: column;
        gap: 0.45rem;
    }

    .apply-form-actions .btn {
        width: 100%;
        padding: 0.6rem;
        font-size: 0.9rem;
    }

    .apply-page .mb-3 {
        margin-bottom: 0.75rem !important;
    }
}

/* =============================================
   320px — Small Mobile
   ============================================= */
@media (max-width: 320px) {

    .apply-page h2 {
        font-size: 1.02rem;
    }

    .apply-page .card-header {
        padding: 0.6rem 0.75rem;
    }

    .apply-page .card-header h5 {
        font-size: 0.82rem;
    }

    .apply-page .step-badge {
        width: 21px;
        height: 21px;
        font-size: 0.62rem;
    }

    .apply-page .card-body {
        padding: 0.7rem;
    }

    .apply-page .row {
        --bs-gutter-x: 0.4rem;
        margin-left: 0;
        margin-right: 0;
    }

    .apply-page .form-label {
        font-size: 0.75rem;
        margin-bottom: 0.25rem;
    }

    .apply-page .form-control,
    .apply-page .form-select {
        font-size: 0.78rem;
        padding: 0.4rem 0.6rem;
    }

    .apply-page textarea.form-control {
        min-height: 70px;
        rows: 2;
    }

    #map-container { height: 200px; border-radius: 8px; }

    .coord-section-label { font-size: 0.68rem; }

    #btn-select-map { font-size: 0.72rem; padding: 0.3rem 0.6rem; }

    .document-upload-item {
        padding: 0.55rem;
        margin-bottom: 0.55rem !important;
    }

    .document-upload-item .form-label {
        font-size: 0.72rem;
    }

    .document-upload-item .btn-danger {
        font-size: 0.72rem;
        padding: 0.3rem 0.55rem;
    }

    .apply-page .btn-outline-secondary {
        font-size: 0.75rem;
        padding: 0.4rem 0.6rem;
    }

    .apply-page .alert {
        font-size: 0.8rem;
        padding: 0.6rem 0.75rem;
    }

    .apply-form-actions .btn {
        font-size: 0.85rem;
        padding: 0.55rem;
    }

    .apply-page .mb-3 {
        margin-bottom: 0.6rem !important;
    }

    .apply-page .card + .card {
        margin-top: 0.75rem;
    }
}
</style>

<div class="apply-page">
    <div class="apply-header">
        <div class="apply-eyebrow"><?php echo _apt('page_title'); ?></div>
        <h2><?php echo _apt('heading'); ?></h2>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <!-- ── Project Information ── -->
        <div class="card mb-3">
            <div class="card-header">
                <span class="step-badge">01</span>
                <h5><?php echo _apt('sec_project'); ?></h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="project_name" class="form-label"><?php echo _apt('lbl_project_name'); ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="project_name" name="project_name" required>
                </div>
                <div class="mb-3">
                    <label for="project_type" class="form-label"><?php echo _apt('lbl_project_type'); ?></label>
                    <select class="form-select" id="project_type" name="project_type">
                        <option value=""><?php echo _apt('opt_select_type'); ?></option>
                        <option value="Residential"><?php echo _apt('opt_residential'); ?></option>
                        <option value="Commercial"><?php echo _apt('opt_commercial'); ?></option>
                        <option value="Industrial"><?php echo _apt('opt_industrial'); ?></option>
                        <option value="Institutional"><?php echo _apt('opt_institutional'); ?></option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="project_description" class="form-label"><?php echo _apt('lbl_project_desc'); ?></label>
                    <textarea class="form-control" id="project_description" name="project_description" rows="3"></textarea>
                </div>
            </div>
        </div>

        <!-- ── Location Information ── -->
        <div class="card mb-3">
            <div class="card-header">
                <span class="step-badge">02</span>
                <h5><?php echo _apt('sec_location'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6 mb-3">
                        <label for="lot_number" class="form-label"><?php echo _apt('lbl_lot_number'); ?></label>
                        <input type="text" class="form-control" id="lot_number" name="lot_number">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="block" class="form-label"><?php echo _apt('lbl_block'); ?></label>
                        <input type="text" class="form-control" id="block" name="block">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="street" class="form-label"><?php echo _apt('lbl_street'); ?></label>
                        <input type="text" class="form-control" id="street" name="street">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="barangay" class="form-label"><?php echo _apt('lbl_barangay'); ?></label>
                        <input type="text" class="form-control" id="barangay" name="barangay">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="district" class="form-label"><?php echo _apt('lbl_district'); ?></label>
                        <input type="text" class="form-control" id="district" name="district">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="parcel_id" class="form-label"><?php echo _apt('lbl_parcel_id'); ?></label>
                    <input type="text" class="form-control" id="parcel_id" name="parcel_id" placeholder="e.g. 123-45-678">
                </div>

                <!-- Coordinates + Map -->
                <div class="mb-3 mt-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 coord-header">
                        <label class="form-label coord-section-label fw-bold small text-uppercase mb-0">
                            <?php echo _apt('lbl_coordinates'); ?>
                        </label>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-select-map">
                            <i class="bi bi-geo-alt me-1"></i> <?php echo _apt('btn_pick_map'); ?>
                        </button>
                    </div>
                    <div class="row g-2 coord-inputs">
                        <div class="col-md-6">
                            <input type="number" step="any" name="latitude" id="inp-lat"
                                   class="form-control coord-input" placeholder="<?php echo _apt('ph_latitude'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <input type="number" step="any" name="longitude" id="inp-lng"
                                   class="form-control coord-input" placeholder="<?php echo _apt('ph_longitude'); ?>" required>
                        </div>
                    </div>
                    <div id="map-container"></div>
                    <div class="map-legend">
                        <span class="map-legend-swatch"></span>
                        <?php echo _apt('lbl_qc_boundary'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Required Documents ── -->
        <div class="card mb-3">
            <div class="card-header">
                <span class="step-badge">03</span>
                <h5><?php echo _apt('sec_documents'); ?></h5>
            </div>
            <div class="card-body">
                <div id="document-uploads">
                    <div class="mb-3 document-upload-item">
                        <label class="form-label"><?php echo _apt('lbl_document'); ?></label>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <select class="form-select" name="document_types[]">
                                    <option value="site_plan"><?php echo _apt('opt_site_plan'); ?></option>
                                    <option value="lot_plan"><?php echo _apt('opt_lot_plan'); ?></option>
                                    <option value="ownership_proof"><?php echo _apt('opt_ownership_proof'); ?></option>
                                    <option value="building_plan"><?php echo _apt('opt_building_plan'); ?></option>
                                    <option value="other"><?php echo _apt('opt_other'); ?></option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <input type="file" class="form-control" name="documents[]"
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addDocumentUpload()">
                    <i class="bi bi-plus"></i> <?php echo _apt('btn_add_doc'); ?>
                </button>
            </div>
        </div>

        <!-- ── Actions ── -->
        <div class="apply-form-actions">
            <button type="submit" class="btn btn-primary"><?php echo _apt('btn_submit'); ?></button>
            <a href="/lgu-urban-planning/user/index.php" class="btn btn-light border"><?php echo _apt('btn_cancel'); ?></a>
        </div>

    </form>
</div>

<script>
window.APPLY_CONFIG = <?php echo json_encode([
    'lbl_document'        => _apt('lbl_document'),
    'opt_site_plan'       => _apt('opt_site_plan'),
    'opt_lot_plan'        => _apt('opt_lot_plan'),
    'opt_ownership_proof' => _apt('opt_ownership_proof'),
    'opt_building_plan'   => _apt('opt_building_plan'),
    'opt_other'           => _apt('opt_other'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="/lgu-urban-planning/assets/js/user-apply.js"></script>

<?php include __DIR__ . '/../user/footer.php'; ?>