<?php
/**
 * receipt_helper.php
 */
function buildReceiptPdf(array $payment, array $application): array
{
    $refNo    = (string) ($payment['reference_number'] ?? ('PAY-' . ($payment['id'] ?? uniqid())));
    $safeRef  = preg_replace('/[^A-Za-z0-9\-_]/', '_', $refNo);
    $filename = "Receipt_{$safeRef}.pdf";

    $uploadDir = __DIR__ . '/../../uploads/receipts/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $savePath = $uploadDir . $filename;

    if (file_exists($savePath)) {
        return ['path' => $savePath, 'filename' => $filename, 'newly_generated' => false];
    }

    $data = [
        'refNo'         => $refNo,
        'applicationNo' => $application['application_number'] ?? '',
        'payerName'     => trim(($application['applicant_first_name'] ?? '') . ' ' . ($application['applicant_last_name'] ?? '')),
        'amount'        => (float) ($payment['amount'] ?? 0),
        'method'        => $payment['payment_method'] ?? 'Online Payment',
        'transactionId' => $payment['transaction_id'] ?? '',
        'paidAt'        => !empty($payment['paid_at']) ? date('F d, Y g:i A', strtotime($payment['paid_at'])) : date('F d, Y g:i A'),
        'projectName'   => $application['project_name'] ?? '',
        'location'      => 'Barangay ' . ($application['barangay'] ?? 'N/A') . (!empty($application['district']) ? ', ' . $application['district'] : ''),
    ];

    $tcpdfPath = __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';
    $fpdfPath  = __DIR__ . '/../../vendor/fpdf/fpdf.php';

    if (file_exists($tcpdfPath)) {
        require_once $tcpdfPath;
        generateReceiptWithTCPDF($data, $savePath);
    } elseif (file_exists($fpdfPath)) {
        require_once $fpdfPath;
        generateReceiptWithFPDF($data, $savePath);
    } else {
        throw new \RuntimeException('PDF library not found. Run: composer require tecnickcom/tcpdf');
    }

    return ['path' => $savePath, 'filename' => $filename, 'newly_generated' => true];
}

function sendPaymentReceiptEmail(array $application, array $payment, string $pdfPath, string $pdfFilename): array
{
    $applicantEmail = $application['applicant_email'] ?? '';
    if (empty($applicantEmail)) {
        return ['success' => false, 'message' => 'No applicant email on file.'];
    }
    if (!file_exists($pdfPath)) {
        return ['success' => false, 'message' => 'Receipt PDF file not found.'];
    }

    try {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'aelousssnexus@gmail.com';
        $mail->Password   = 'zuey mjni sbzz gvsm';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('aelousssnexus@gmail.com', 'LGU Urban Planning');

        $applicantFullName = trim(($application['applicant_first_name'] ?? '') . ' ' . ($application['applicant_last_name'] ?? ''));
        $mail->addAddress($applicantEmail, $applicantFullName);
        $mail->addAttachment($pdfPath, $pdfFilename);

        $mail->isHTML(true);
        $mail->Subject = 'Payment Receipt – Permit #' . ($application['application_number'] ?? '');

        $safeFullName = htmlspecialchars($applicantFullName);
        $refNo        = htmlspecialchars((string) ($payment['reference_number'] ?? ''));
        $amountFmt    = number_format((float) ($payment['amount'] ?? 0), 2);
        $method       = htmlspecialchars((string) ($payment['payment_method'] ?? 'Online Payment'));
        $txnId        = htmlspecialchars((string) ($payment['transaction_id'] ?? ''));

        $mail->Body = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#222;margin:0;padding:0;background:#f4f6f9;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:30px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">

        <!-- Header -->
        <tr>
          <td style="background:#003366;padding:24px 32px;text-align:center;">
            <h2 style="color:#fff;margin:0;font-size:18px;letter-spacing:1px;">
              QUEZON CITY URBAN PLANNING DEPARTMENT
            </h2>
            <p style="color:#aac4e8;margin:6px 0 0;font-size:12px;">
              Official Notification – Payment Receipt
            </p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:32px;">
            <p style="margin:0 0 16px;">Dear <strong>' . $safeFullName . '</strong>,</p>
            <p style="margin:0 0 16px;">
              We have received your permit fee payment. Your official receipt is attached to this email as a PDF file.
            </p>

            <!-- Payment summary box -->
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="background:#f0fff4;border:1px solid #c8e6cf;border-radius:6px;margin:20px 0;">
              <tr>
                <td style="padding:16px 20px;">
                  <p style="margin:0 0 8px;font-size:13px;color:#555;text-transform:uppercase;letter-spacing:.5px;font-weight:bold;">
                    Payment Summary
                  </p>
                  <table cellpadding="4" cellspacing="0" style="font-size:14px;width:100%;">
                    <tr>
                      <td style="color:#555;width:140px;">Receipt No.</td>
                      <td><strong>' . $refNo . '</strong></td>
                    </tr>
                    <tr>
                      <td style="color:#555;">Amount Paid</td>
                      <td><strong>&#8369; ' . $amountFmt . '</strong></td>
                    </tr>
                    <tr>
                      <td style="color:#555;">Payment Method</td>
                      <td>' . $method . '</td>
                    </tr>
                    <tr>
                      <td style="color:#555;">Transaction ID</td>
                      <td>' . $txnId . '</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <p style="margin:0 0 20px;font-size:14px;">
              You can also view or download this receipt anytime from your application page in the applicant portal.
            </p>

            <p style="margin:0;font-size:14px;">Thank you for your payment.</p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f0f0f0;padding:16px 32px;text-align:center;font-size:11px;color:#888;">
            This is a system-generated email. Please do not reply directly to this message.<br>
            &copy; ' . date('Y') . ' Quezon City Urban Planning Department
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>';

        $mail->AltBody =
            "Dear " . $applicantFullName . ",\r\n\r\n" .
            "We have received your permit fee payment.\r\n\r\n" .
            "Receipt No     : " . ($payment['reference_number'] ?? '') . "\r\n" .
            "Amount Paid    : PHP " . $amountFmt . "\r\n" .
            "Payment Method : " . ($payment['payment_method'] ?? 'Online Payment') . "\r\n" .
            "Transaction ID : " . ($payment['transaction_id'] ?? '') . "\r\n\r\n" .
            "The official receipt PDF is attached to this email.\r\n\r\n" .
            "Quezon City Urban Planning Department";

        $mail->send();
        return ['success' => true, 'message' => 'Receipt emailed to ' . $applicantEmail . '.'];

    } catch (\Exception $e) {
        return ['success' => false, 'message' => 'Receipt email failed: ' . $e->getMessage()];
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// ── TCPDF GENERATOR ───────────────────────────────────────────────────────────
// ════════════════════════════════════════════════════════════════════════════════
function generateReceiptWithTCPDF(array $data, string $savePath): void
{
    $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
    $pdf->SetCreator('LGU Urban Planning System');
    $pdf->SetAuthor('Quezon City Urban Planning Department');
    $pdf->SetTitle('Official Receipt - ' . $data['refNo']);
    $pdf->SetSubject('Payment Receipt');
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

    // ── "PAID" stamp, top-right corner ─────────────────────────────────────
    $pdf->SetXY(150, 14);
    $pdf->SetFillColor(230, 255, 237);
    $pdf->SetDrawColor(0, 153, 76);
    $pdf->SetLineWidth(0.6);
    $pdf->SetTextColor(0, 128, 0);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(46, 10, 'PAID IN FULL', 1, 1, 'C', true);

    // ── Logo placeholder ────────────────────────────────────────────────────
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
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 8, 'OFFICIAL RECEIPT', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 5, 'Permit Fee Payment', 0, 1, 'C');
    $pdf->Ln(2);

    // ── Receipt Number Badge ───────────────────────────────────────────────
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'RECEIPT NO: ' . $data['refNo'], 0, 1, 'C', true);
    $pdf->Ln(4);

    $labelW = 58;
    $valueW = 120;

    $row = function ($pdf, $label, $value) use ($labelW, $valueW) {
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell($labelW, 6, $label . ':', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->MultiCell($valueW, 6, $value, 0, 'L');
    };

    // ── Section: Payment Details ────────────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->SetFillColor(230, 240, 255);
    $pdf->Cell(0, 6, '  PAYMENT DETAILS', 0, 1, 'L', true);
    $pdf->Ln(1);

    $row($pdf, 'Application No.',   $data['applicationNo']);
    $row($pdf, 'Payer Name',        $data['payerName']);
    $row($pdf, 'Amount Paid',       'PHP ' . number_format($data['amount'], 2));
    $row($pdf, 'Payment Method',    $data['method']);
    $row($pdf, 'Transaction ID',    $data['transactionId']);
    $row($pdf, 'Date & Time Paid',  $data['paidAt']);
    $pdf->Ln(2);

    // ── Section: Application Details ────────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->SetFillColor(230, 240, 255);
    $pdf->Cell(0, 6, '  APPLICATION DETAILS', 0, 1, 'L', true);
    $pdf->Ln(1);

    $row($pdf, 'Project Name', $data['projectName']);
    $row($pdf, 'Location',     $data['location']);
    $pdf->Ln(4);

    // ── Confirmation statement ──────────────────────────────────────────────
    $pdf->SetFillColor(235, 255, 240);
    $pdf->SetDrawColor(0, 153, 76);
    $pdf->SetFont('helvetica', 'I', 8.5);
    $pdf->SetTextColor(0, 100, 50);
    $pdf->SetLineWidth(0.5);
    $text = "This receipt confirms that the permit fee for the above-referenced application has been received in full. Please keep this receipt for your records.";
    $pdf->MultiCell(0, 5, $text, 1, 'J', true);

    // ── Footer ──────────────────────────────────────────────────────────────
    $pdf->SetXY(15, 268);
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetTextColor(130, 130, 130);
    $pdf->Cell(0, 4, 'Generated: ' . date('F d, Y') . '  |  LGU Urban Planning Portal  |  This is a system-generated receipt and does not require a signature.', 0, 0, 'C');

    $pdf->Output($savePath, 'F');
}

// ════════════════════════════════════════════════════════════════════════════════
// ── FPDF FALLBACK GENERATOR ──────────────────────────────────────────────────
// ════════════════════════════════════════════════════════════════════════════════
function generateReceiptWithFPDF(array $data, string $savePath): void
{
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
    $pdf->Cell(0, 8, 'OFFICIAL RECEIPT', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 5, 'Permit Fee Payment', 0, 1, 'C');
    $pdf->Ln(2);

    // Receipt banner
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, 'RECEIPT NO: ' . $data['refNo'], 0, 1, 'C', true);
    $pdf->Ln(4);

    $lw = 60; $vw = 115;

    $rows = [
        ['PAYMENT DETAILS'],
        ['Application No.',    $data['applicationNo']],
        ['Payer Name',         $data['payerName']],
        ['Amount Paid',        'PHP ' . number_format($data['amount'], 2)],
        ['Payment Method',     $data['method']],
        ['Transaction ID',     $data['transactionId']],
        ['Date & Time Paid',   $data['paidAt']],
        ['APPLICATION DETAILS'],
        ['Project Name',       $data['projectName']],
        ['Location',           $data['location']],
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

    // Confirmation box
    $pdf->SetFillColor(235, 255, 240);
    $pdf->SetTextColor(0, 100, 50);
    $pdf->SetFont('Arial', 'I', 8.5);
    $text = "This receipt confirms that the permit fee for the above-referenced application has been received in full. Please keep this receipt for your records.";
    $pdf->MultiCell(0, 5, $text, 1, 'J', true);

    // Footer
    $pdf->SetXY(15, 268);
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->SetTextColor(130, 130, 130);
    $pdf->Cell(0, 4, 'Generated: ' . date('F d, Y') . '  |  LGU Urban Planning Portal  |  System-generated receipt.', 0, 0, 'C');

    $pdf->Output('F', $savePath);
}
