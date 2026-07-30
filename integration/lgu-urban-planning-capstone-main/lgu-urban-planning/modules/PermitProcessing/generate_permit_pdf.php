<?php
/**
 * Generate Permit PDF - Locational Clearance / Zoning Certificate
 * Triggered automatically when application status is set to 'approved'.
 *
 * Usage: Called internally after status update, OR via GET for download.
 *   GET  /lgu-urban-planning/permit/generate_permit_pdf.php?id=<application_id>
 *
 * Dependencies: composer require tecnickcom/tcpdf  (or use FPDF as fallback)
 * Place this file in: /lgu-urban-planning/permit/
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helper.php';

// ─── 1. AUTH & INPUT ──────────────────────────────────────────────────────────
// When included from view.php with $_POST['_save_only'] = true,
// skip auth re-check and browser streaming.
$isSaveOnly = !empty($_POST['_save_only']);

// Applicants may download their OWN approved permit (file must already exist).
// Staff roles regenerate/stream as normal.
// Use session directly — avoids relying on hasRole() which may not exist in Auth.
$sessionRole = $_SESSION['role'] ?? '';
$isApplicant = !$isSaveOnly && $sessionRole === 'applicant';



if (!$isSaveOnly && !$isApplicant) {
    $auth = new Auth();
    $auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor', 'inspector']);
}

$applicationId = (int)($_GET['id'] ?? $_POST['application_id'] ?? 0);
if (!$applicationId) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Missing application ID.']));
}

// ── Applicant shortcut: verify ownership, then stream the saved file ──────────
if ($isApplicant) {
    $db  = Database::getInstance();
    $row = $db->fetchOne(
        "SELECT a.application_number, a.status, a.applicant_id AS user_id
           FROM applications a
          WHERE a.id = ?",
        [$applicationId]
    );

    // Must be their own approved application
    if (!$row || $row['status'] !== 'approved' || (int)$row['user_id'] !== (int)($_SESSION['user_id'] ?? 0)) {
        http_response_code(403);
        die('Access denied.');
    }

    $safeNo   = preg_replace('/[^A-Za-z0-9\-_]/', '_', $row['application_number']);
    $filename = "Locational_Clearance_{$safeNo}.pdf";
    $filePath = __DIR__ . '/../../uploads/permits/' . $filename;

    if (!file_exists($filePath)) {
        http_response_code(404);
        die('Permit file not found. Please contact the office.');
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// ─── 2. FETCH DATA ────────────────────────────────────────────────────────────
// Direct query used here instead of PermitController::getApplicationDetails()
// because that method joins the `documents` table which does not exist.
// The PDF only needs application + applicant fields — no document list required.
$db          = Database::getInstance();
$application = $db->fetchOne(
    "SELECT
        a.id,
        a.application_number,
        a.status,
        a.project_name,
        a.project_type,
        a.project_description,
        a.barangay,
        a.street,
        a.block,
        a.lot_number,
        a.latitude,
        a.longitude,
        a.applicant_id AS user_id,
        u.first_name AS applicant_first_name,
        u.last_name  AS applicant_last_name,
        u.email      AS applicant_email
     FROM applications a
     JOIN users u ON u.id = a.applicant_id
     WHERE a.id = ?",
    [$applicationId]
);
$zoningCheck = $db->fetchOne("SELECT * FROM zoning_compliance_checks WHERE application_id = ?", [$applicationId]);
$impactData  = $db->fetchOne("SELECT * FROM impact_assessments          WHERE application_id = ?", [$applicationId]);

if (!$application || $application['status'] !== 'approved') {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Application is not approved or does not exist.']));
}

// ─── 3. COLLECT VARIABLES ────────────────────────────────────────────────────
$permitNo       = htmlspecialchars($application['application_number']);
$issuedDate     = date('F d, Y');
$expiryDate     = date('F d, Y', strtotime('+1 year'));
$applicantName  = htmlspecialchars(
                    trim($application['applicant_first_name'] . ' ' . $application['applicant_last_name'])
                  );
$projectName    = htmlspecialchars($application['project_name']);
$projectType    = htmlspecialchars($application['project_type'] ?? 'N/A');
$barangay       = htmlspecialchars($application['barangay'] ?? 'N/A');
$street         = htmlspecialchars($application['street']   ?? 'N/A');
$block          = htmlspecialchars($application['block']    ?? 'N/A');
$lotNo          = htmlspecialchars($application['lot_number'] ?? 'N/A');
$lotArea        = 'N/A';
$zoningType     = htmlspecialchars($zoningCheck['zoning_type'] ?? 'N/A');
$compliance     = strtoupper($zoningCheck['compliance_status'] ?? 'N/A');
$gisAnalysis    = htmlspecialchars($zoningCheck['technical_analysis'] ?? 'N/A');
$trafficFlag    = strtoupper($impactData['traffic_flag'] ?? 'N/A');
$energyFlag     = strtoupper($impactData['energy_flag']  ?? 'N/A');
$lat            = htmlspecialchars($application['latitude']  ?? '');
$lng            = htmlspecialchars($application['longitude'] ?? '');
$qrContent      = ''; // unused — QR code removed

// ─── 4. CHOOSE PDF LIBRARY ───────────────────────────────────────────────────
// Supports both TCPDF (preferred) and FPDF (fallback).
// Install TCPDF: composer require tecnickcom/tcpdf
// Install FPDF:  composer require setasign/fpdf
$tcpdfPath = __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';
$fpdfPath  = __DIR__ . '/../../vendor/fpdf/fpdf.php';

if (file_exists($tcpdfPath)) {
    require_once $tcpdfPath;
    generateWithTCPDF(
        $permitNo, $issuedDate, $expiryDate, $applicantName,
        $projectName, $projectType, $barangay, $street, $block, $lotNo,
        $lotArea, $zoningType, $compliance, $gisAnalysis,
        $trafficFlag, $energyFlag, $lat, $lng, $qrContent, $applicationId, $db
    );
} elseif (file_exists($fpdfPath)) {
    require_once $fpdfPath;
    generateWithFPDF(
        $permitNo, $issuedDate, $expiryDate, $applicantName,
        $projectName, $projectType, $barangay, $street, $block, $lotNo,
        $lotArea, $zoningType, $compliance, $gisAnalysis,
        $trafficFlag, $energyFlag, $applicationId, $db
    );
} else {
    die('PDF library not found. Run: composer require tecnickcom/tcpdf');
}


// ════════════════════════════════════════════════════════════════════════════════
// ── TCPDF GENERATOR (Rich layout, QR code, border, watermark) ───────────────
// ════════════════════════════════════════════════════════════════════════════════
function generateWithTCPDF(
    $permitNo, $issuedDate, $expiryDate, $applicantName,
    $projectName, $projectType, $barangay, $street, $block, $lotNo,
    $lotArea, $zoningType, $compliance, $gisAnalysis,
    $trafficFlag, $energyFlag, $lat, $lng, $qrContent, $applicationId, $db
) {
    $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
    $pdf->SetCreator('LGU Urban Planning System');
    $pdf->SetAuthor('Quezon City Urban Planning Department');
    $pdf->SetTitle("Locational Clearance - $permitNo");
    $pdf->SetSubject('Official Permit');
    $pdf->SetPrintHeader(false);
    $pdf->SetPrintFooter(false);
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->AddPage();

    // ── Border ──────────────────────────────────────────────────────────────
    $pdf->SetLineWidth(1.5);
    $pdf->SetDrawColor(0, 51, 102);
    $pdf->Rect(10, 10, 196, 267, 'D');
    $pdf->SetLineWidth(0.4);
    $pdf->Rect(12, 12, 192, 263, 'D');

    // ── Logo placeholder (replace path with actual logo) ──────────────────
    $logoPath = __DIR__ . '/../../assets/img/logo.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 20, 18, 22, 22, 'PNG');
    }

    // ── Header ──────────────────────────────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->SetXY(44, 18);
    $pdf->Cell(0, 5, 'Republic of the Philippines', 0, 1, 'C');
    $pdf->SetX(44);
    $pdf->Cell(0, 5, 'City of Quezon', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetX(44);
    $pdf->Cell(0, 5, 'URBAN PLANNING AND DEVELOPMENT OFFICE', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetX(44);
    $pdf->Cell(0, 4, 'Quezon City Hall, Diliman, Quezon City | updo@quezoncity.gov.ph', 0, 1, 'C');

    // ── Divider ──────────────────────────────────────────────────────────────
    $pdf->SetY($pdf->GetY() + 2);
    $pdf->SetLineWidth(0.8);
    $pdf->SetDrawColor(0, 51, 102);
    $pdf->Line(15, $pdf->GetY(), 201, $pdf->GetY());
    $pdf->Ln(3);

    // ── Title ──────────────────────────────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 15);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 8, 'LOCATIONAL CLEARANCE', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 5, 'Zoning / Land Use Certificate', 0, 1, 'C');
    $pdf->Ln(2);

    // ── Permit Number Badge ──────────────────────────────────────────────────
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, "PERMIT NO: $permitNo", 0, 1, 'C', true);
    $pdf->Ln(4);

    // ── Helper: label + value row ────────────────────────────────────────────
    $labelW = 58;
    $valueW = 120;

    function pdfRow($pdf, $label, $value, $labelW, $valueW) {
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell($labelW, 6, $label . ':', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->MultiCell($valueW, 6, $value, 0, 'L');
    }

    // ── Section: Applicant Info ──────────────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->SetFillColor(230, 240, 255);
    $pdf->Cell(0, 6, '  APPLICANT INFORMATION', 0, 1, 'L', true);
    $pdf->Ln(1);

    pdfRow($pdf, 'Applicant Name',  $applicantName, $labelW, $valueW);
    pdfRow($pdf, 'Application No.', $permitNo,      $labelW, $valueW);
    pdfRow($pdf, 'Date Issued',     $issuedDate,    $labelW, $valueW);
    pdfRow($pdf, 'Valid Until',     $expiryDate,    $labelW, $valueW);
    $pdf->Ln(2);

    // ── Section: Project Info ────────────────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->SetFillColor(230, 240, 255);
    $pdf->Cell(0, 6, '  PROJECT DETAILS', 0, 1, 'L', true);
    $pdf->Ln(1);

    pdfRow($pdf, 'Project Name',  $projectName,              $labelW, $valueW);
    pdfRow($pdf, 'Project Type',  $projectType,              $labelW, $valueW);
    pdfRow($pdf, 'Location',      "Brgy. $barangay, $street, Block $block, Lot $lotNo", $labelW, $valueW);
    pdfRow($pdf, 'Lot Area',      $lotArea . ' sqm',         $labelW, $valueW);
    pdfRow($pdf, 'Coordinates',   "Lat: $lat  |  Long: $lng",$labelW, $valueW);
    $pdf->Ln(2);

    // ── Section: Zoning Classification ──────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->SetFillColor(230, 240, 255);
    $pdf->Cell(0, 6, '  ZONING & COMPLIANCE', 0, 1, 'L', true);
    $pdf->Ln(1);

    pdfRow($pdf, 'Zoning Classification', $zoningType,  $labelW, $valueW);
    pdfRow($pdf, 'Compliance Status',     $compliance,  $labelW, $valueW);
    pdfRow($pdf, 'Roads & Traffic',       $trafficFlag, $labelW, $valueW);
    pdfRow($pdf, 'Utilities / Energy',    $energyFlag,  $labelW, $valueW);

    // GIS Analysis (multiline)
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell($labelW, 6, 'GIS Technical Analysis:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->MultiCell($valueW, 5, $gisAnalysis, 0, 'L');
    $pdf->Ln(2);

    // ── Approval Statement ──────────────────────────────────────────────────
    $pdf->SetFillColor(235, 255, 240);
    $pdf->SetDrawColor(0, 153, 76);
    $pdf->SetFont('helvetica', 'I', 8.5);
    $pdf->SetTextColor(0, 100, 50);
    $pdf->SetLineWidth(0.5);
    $approvalText = "This certifies that the above-described project/land use is APPROVED and found to be in conformity with the Comprehensive Land Use Plan (CLUP) and Zoning Ordinance of Quezon City. This clearance is issued for the purpose of securing a Building Permit or Business Permit as required by law.";
    $pdf->MultiCell(0, 5, $approvalText, 1, 'J', true);
    $pdf->Ln(6);

    // ── Signature Block ──────────────────────────────────────────────────────
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.4);

    $sigY = $pdf->GetY();

    // Left: Prepared by (X 20–90)
    $pdf->SetXY(20, $sigY);
    $pdf->Cell(70, 5, 'Prepared by:', 0, 0, 'L');

    // Right: Approved by (X 115–196)
    $pdf->SetXY(115, $sigY);
    $pdf->Cell(81, 5, 'Approved by:', 0, 1, 'L');

    $pdf->Line(20,  $sigY + 16, 90,  $sigY + 16);
    $pdf->Line(115, $sigY + 16, 196, $sigY + 16);

    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->SetXY(20, $sigY + 17);
    $pdf->Cell(70, 5, 'Reviewing Officer', 0, 0, 'C');
    $pdf->SetXY(115, $sigY + 17);
    $pdf->Cell(81, 5, 'City Planning & Development Officer', 0, 0, 'C');

    // ── Footer text (inside border) ──────────────────────────────────────────
    $pdf->SetXY(15, 253);
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetTextColor(130, 130, 130);
    $pdf->Cell(0, 4, "Generated: $issuedDate  |  System: LGU Urban Planning Portal  |  This document is computer-generated and is valid without a wet signature.", 0, 0, 'C');

    // ─── 5. SAVE PDF + STORE RECORD ─────────────────────────────────────────
    saveAndOutput($pdf, $permitNo, $applicationId, $db, 'tcpdf');
}


// ════════════════════════════════════════════════════════════════════════════════
// ── FPDF FALLBACK GENERATOR ──────────────────────────────────────────────────
// ════════════════════════════════════════════════════════════════════════════════
function generateWithFPDF(
    $permitNo, $issuedDate, $expiryDate, $applicantName,
    $projectName, $projectType, $barangay, $street, $block, $lotNo,
    $lotArea, $zoningType, $compliance, $gisAnalysis,
    $trafficFlag, $energyFlag, $applicationId, $db
) {
    $pdf = new FPDF('P', 'mm', 'Letter');
    $pdf->AddPage();
    $pdf->SetMargins(20, 20, 20);

    // Border
    $pdf->SetLineWidth(1.2);
    $pdf->SetDrawColor(0, 51, 102);
    $pdf->Rect(10, 10, 196, 267);

    // Header
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->SetY(18);
    $pdf->Cell(0, 6, 'Republic of the Philippines - City of Quezon', 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'URBAN PLANNING AND DEVELOPMENT OFFICE', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 5, 'Quezon City Hall, Diliman, Quezon City', 0, 1, 'C');

    $pdf->SetLineWidth(0.6);
    $pdf->SetDrawColor(0, 51, 102);
    $pdf->Line(15, $pdf->GetY() + 2, 201, $pdf->GetY() + 2);
    $pdf->Ln(5);

    // Title
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 8, 'LOCATIONAL CLEARANCE', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 5, 'Zoning / Land Use Certificate', 0, 1, 'C');
    $pdf->Ln(2);

    // Permit banner
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, "PERMIT NO: $permitNo", 0, 1, 'C', true);
    $pdf->Ln(4);

    $lw = 60; $vw = 115;

    $rows = [
        ['APPLICANT INFORMATION'],
        ['Applicant Name',     $applicantName],
        ['Application No.',    $permitNo],
        ['Date Issued',        $issuedDate],
        ['Valid Until',        $expiryDate],
        ['PROJECT DETAILS'],
        ['Project Name',       $projectName],
        ['Project Type',       $projectType],
        ['Location',           "Brgy. $barangay, $street, Block $block, Lot $lotNo"],
        ['Lot Area',           "$lotArea sqm"],
        ['ZONING & COMPLIANCE'],
        ['Zoning Class.',      $zoningType],
        ['Compliance Status',  $compliance],
        ['Roads & Traffic',    $trafficFlag],
        ['Utilities / Energy', $energyFlag],
        ['GIS Analysis',       $gisAnalysis],
    ];

    foreach ($rows as $row) {
        if (count($row) === 1) {
            // Section header
            $pdf->SetFillColor(230, 240, 255);
            $pdf->SetTextColor(0, 51, 102);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 6, '  ' . $row[0], 0, 1, 'L', true);
            $pdf->Ln(1);
        } else {
            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->Cell($lw, 6, $row[0] . ':', 0, 0);
            $pdf->SetFont('Arial', '', 8.5);
            $pdf->SetTextColor(20, 20, 20);
            $pdf->MultiCell($vw, 6, $row[1], 0, 'L');
        }
    }
    $pdf->Ln(3);

    // Approval box
    $pdf->SetFillColor(235, 255, 240);
    $pdf->SetTextColor(0, 100, 50);
    $pdf->SetFont('Arial', 'I', 8.5);
    $text = "This certifies that the above project/land use is APPROVED and found conforming with the Comprehensive Land Use Plan (CLUP) and Zoning Ordinance of Quezon City.";
    $pdf->MultiCell(0, 5, $text, 1, 'J', true);
    $pdf->Ln(6);

    // Signatures
    $sy = $pdf->GetY();
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->Line(20, $sy + 16, 90, $sy + 16);
    $pdf->SetXY(20, $sy + 17);
    $pdf->Cell(70, 5, 'Reviewing Officer', 0, 0, 'C');
    $pdf->Line(115, $sy + 16, 196, $sy + 16);
    $pdf->SetXY(115, $sy + 17);
    $pdf->Cell(81, 5, 'City Planning & Development Officer', 0, 0, 'C');

    // Footer
    $pdf->SetXY(15, 268);
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->SetTextColor(130, 130, 130);
    $pdf->Cell(0, 4, "Generated: $issuedDate  |  LGU Urban Planning Portal  |  Computer-generated document.", 0, 0, 'C');

    saveAndOutput($pdf, $permitNo, $applicationId, $db, 'fpdf');
}


// ════════════════════════════════════════════════════════════════════════════════
// ── SAVE FILE + STORE IN DB + OUTPUT ────────────────────────────────────────
// ════════════════════════════════════════════════════════════════════════════════
function saveAndOutput($pdf, $permitNo, $applicationId, $db, $engine) {
    global $isSaveOnly;

    // Sanitise permit number for filename
    $safeNo   = preg_replace('/[^A-Za-z0-9\-_]/', '_', $permitNo);
    $filename = "Locational_Clearance_{$safeNo}.pdf";
    $uploadDir = __DIR__ . '/../../uploads/permits/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $savePath = $uploadDir . $filename;

    if ($engine === 'tcpdf') {
        $pdf->Output($savePath, 'F');   // Save to file
    } else {
        $pdf->Output('F', $savePath);   // FPDF argument order
    }

    // ── Send internal message notification to applicant ──────────────────────
    $appRecord = $db->fetchOne(
        "SELECT a.applicant_id AS user_id FROM applications a WHERE a.id = ?",
        [$applicationId]
    );
    $admin = $db->fetchOne(
        "SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1"
    );
    if ($appRecord && $admin) {
        $appInfo = $db->fetchOne(
            "SELECT a.project_name, a.barangay FROM applications a WHERE a.id = ?",
            [$applicationId]
        );
        $projectName = $appInfo['project_name'] ?? '';
        $barangay    = $appInfo['barangay']     ?? '';

        $msgBody  = "Dear Applicant,\n\n";
        $msgBody .= "We are pleased to inform you that your application for '{$projectName}' has been officially APPROVED.\n\n";
        $msgBody .= "Your Locational Clearance / Permit has been generated. You may download and print the official document from the 'Documents' section of your portal. A copy has also been sent to your registered email address.\n\n";
        $msgBody .= "Permit Details:\n";
        $msgBody .= "- Permit No: {$permitNo}\n";
        $msgBody .= "- Location: Barangay {$barangay}\n\n";
        $msgBody .= "Thank you for your cooperation.\n\n";
        $msgBody .= "Quezon City Urban Planning Department";

        $db->query(
            "INSERT INTO messages
                (sender_id, receiver_id, subject, message, application_id, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())",
            [
                $admin['id'],
                $appRecord['user_id'],
                "CONGRATULATIONS: Approved Locational Clearance / Permit #{$permitNo}",
                $msgBody,
                $applicationId,
            ]
        );
    }

    // ── If called from view.php as save-only, stop here (no browser output) ─
    if ($isSaveOnly) {
        return;
    }

    // ── Stream to browser ───────────────────────────────────────────────────
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Content-Length: ' . filesize($savePath));
    readfile($savePath);
    exit;
}