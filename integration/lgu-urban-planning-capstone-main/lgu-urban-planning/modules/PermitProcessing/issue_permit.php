<?php
/**
 * issue_permit.php
 *
 * Shared logic for finalizing an application once it is fully cleared to
 * receive its permit. This was previously duplicated inline inside
 * admin/view.php's `update_status` action; it now lives here so it can be
 * called from two places:
 *
 *   1. The payment confirmation flow (modules/PermitProcessing/pay.php),
 *      once the applicant's mock payment succeeds.
 *   2. admin/view.php, retained for cases staff need to manually finalize
 *      an application without going through payment (e.g. fee waived).
 *
 * Behavior is unchanged from the original inline block: sets status to
 * 'approved', logs status history, sends the in-system "congratulations"
 * message, generates the permit PDF (if not already generated), and emails
 * it to the applicant via PHPMailer/Gmail SMTP.
 */

/**
 * @param PDO      $dbConn      Active PDO connection.
 * @param Database  $db          Database wrapper providing ->fetchOne().
 * @param array    $application Application row. Must include: id,
 *                              application_number, project_name, barangay,
 *                              district, applicant_id, applicant_email,
 *                              applicant_first_name, applicant_last_name.
 * @param string   $remarks     Office remarks shown in the message/email.
 * @param int      $officerId   user_id attributed for the status change.
 *                              For payment-triggered approvals (no staff
 *                              member involved), use a designated
 *                              system/admin user id.
 *
 * @return array{success: bool, message: string}
 */
function issuePermitAndNotifyApplicant(PDO $dbConn, Database $db, array $application, string $remarks, int $officerId): array
{
    $applicationId = (int) $application['id'];
    $applicantId   = (int) $application['applicant_id'];
    $summary       = '';

    // ── 1-3. Status update + history + in-system message (transactional) ──
    try {
        $dbConn->beginTransaction();

        $dbConn->prepare("UPDATE applications SET status = 'approved', updated_at = NOW() WHERE id = :id")
               ->execute([':id' => $applicationId]);

        $dbConn->prepare(
            "INSERT INTO application_status_history (application_id, status, remarks, changed_by) VALUES (?, 'approved', ?, ?)"
        )->execute([$applicationId, $remarks, $officerId]);

        $subject = "CONGRATULATIONS: Approved Locational Clearance / Permit #" . $application['application_number'];

        $messageBody  = "Dear Applicant,\n\n";
        $messageBody .= "We are pleased to inform you that your application for '" . $application['project_name'] . "' has been officially APPROVED.\n\n";
        $messageBody .= "Your Locational Clearance / Permit has been generated. You may download and print the official document from the 'Documents' section of your portal. A copy has also been sent to your registered email address.\n\n";
        $messageBody .= "Permit Details:\n";
        $messageBody .= "- Permit No: " . $application['application_number'] . "\n";
        $messageBody .= "- Location: Barangay " . $application['barangay'] . ($application['district'] ?? '' ? ", " . $application['district'] : '') . "\n\n";
        $messageBody .= "Office Remarks:\n\"" . $remarks . "\"\n\n";
        $messageBody .= "Thank you for your cooperation.\n\n";
        $messageBody .= "Quezon City Urban Planning Department";

        $dbConn->prepare(
            "INSERT INTO messages (application_id, sender_id, receiver_id, subject, message, message_type, created_at) VALUES (?, ?, ?, ?, ?, 'system', NOW())"
        )->execute([$applicationId, $officerId, $applicantId, $subject, $messageBody]);

        $dbConn->commit();
        $summary = 'Application approved and notification sent.';

    } catch (Exception $e) {
        if ($dbConn->inTransaction()) {
            $dbConn->rollBack();
        }
        return ['success' => false, 'message' => 'Approval failed: ' . $e->getMessage()];
    }

    // ── 4. PDF generation (outside the transaction — a PDF/email failure
    //       here should not undo the approval that already committed) ──
    try {
        $safeNo      = preg_replace('/[^A-Za-z0-9\-_]/', '_', $application['application_number']);
        $pdfFilename = "Locational_Clearance_{$safeNo}.pdf";
        $uploadDir   = __DIR__ . '/../../uploads/permits/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $savePath = $uploadDir . $pdfFilename;
        if (!file_exists($savePath)) {
            $_POST['_save_only']     = true;
            $_POST['application_id'] = $applicationId;
            ob_start();
            include __DIR__ . '/generate_permit_pdf.php';
            ob_end_clean();
        }

        $existing = $db->fetchOne(
            "SELECT id FROM application_documents WHERE application_id = ? AND document_type = 'permit_certificate'",
            [$applicationId]
        );
        if (!$existing) {
            $dbConn->prepare(
                "INSERT INTO application_documents (application_id, uploaded_by, document_type, file_name, file_path, created_at)
                 VALUES (?, ?, 'permit_certificate', ?, ?, NOW())"
            )->execute([
                $applicationId,
                $officerId,
                $pdfFilename,
                'uploads/permits/' . $pdfFilename
            ]);
        }

        $summary .= ' <strong>Locational Clearance PDF has been generated and attached to the application.</strong>';

        // ── 5. Email the PDF via PHPMailer (Gmail SMTP) ──
        $applicantEmail = $application['applicant_email'] ?? '';
        if (!empty($applicantEmail) && file_exists($savePath)) {
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
                $mail->addAddress(
                    $applicantEmail,
                    trim($application['applicant_first_name'] . ' ' . $application['applicant_last_name'])
                );
                $mail->addAttachment($savePath, $pdfFilename);

                $mail->isHTML(true);
                $mail->Subject = 'Your Locational Clearance is Ready – Permit #' . $application['application_number'];

                $applicantFullName = htmlspecialchars(
                    trim($application['applicant_first_name'] . ' ' . $application['applicant_last_name'])
                );
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
              Official Notification – Locational Clearance
            </p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:32px;">
            <p style="margin:0 0 16px;">Dear <strong>' . $applicantFullName . '</strong>,</p>
            <p style="margin:0 0 16px;">
              🎉 Congratulations! Your application has been officially <strong style="color:#1a7a3c;">APPROVED</strong>.
              Your <strong>Locational Clearance / Permit</strong> is attached to this email as a PDF file.
            </p>

            <!-- Permit details box -->
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="background:#f0f5ff;border:1px solid #c8d8f5;border-radius:6px;margin:20px 0;">
              <tr>
                <td style="padding:16px 20px;">
                  <p style="margin:0 0 8px;font-size:13px;color:#555;text-transform:uppercase;letter-spacing:.5px;font-weight:bold;">
                    Permit Details
                  </p>
                  <table cellpadding="4" cellspacing="0" style="font-size:14px;width:100%;">
                    <tr>
                      <td style="color:#555;width:140px;">Permit No.</td>
                      <td><strong>' . htmlspecialchars($application['application_number']) . '</strong></td>
                    </tr>
                    <tr>
                      <td style="color:#555;">Project</td>
                      <td><strong>' . htmlspecialchars($application['project_name']) . '</strong></td>
                    </tr>
                    <tr>
                      <td style="color:#555;">Location</td>
                      <td>Barangay ' . htmlspecialchars($application['barangay']) . (!empty($application['district']) ? ', ' . htmlspecialchars($application['district']) : '') . '</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <!-- Remarks -->
            <p style="margin:0 0 8px;font-size:13px;color:#555;">Office Remarks:</p>
            <blockquote style="margin:0 0 20px;padding:10px 16px;background:#f9f9f9;border-left:4px solid #003366;
                               font-style:italic;color:#444;border-radius:0 4px 4px 0;">
              ' . nl2br(htmlspecialchars($remarks)) . '
            </blockquote>

            <p style="margin:0 0 20px;font-size:14px;">
              You may also download the document anytime from the
              <strong>Documents</strong> section of your applicant portal.
            </p>

            <p style="margin:0;font-size:14px;">Thank you for your cooperation.</p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f0f0f0;padding:16px 32px;text-align:center;font-size:11px;color:#888;">
            This is a system-generated email. Please do not reply directly to this message.<br>
            © ' . date('Y') . ' Quezon City Urban Planning Department
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>';

                $mail->AltBody =
                    "Dear " . trim($application['applicant_first_name'] . ' ' . $application['applicant_last_name']) . ",\r\n\r\n" .
                    "Congratulations! Your application for \"" . $application['project_name'] . "\" has been officially APPROVED.\r\n\r\n" .
                    "Permit No : " . $application['application_number'] . "\r\n" .
                    "Project   : " . $application['project_name'] . "\r\n" .
                    "Location  : Barangay " . $application['barangay'] . (!empty($application['district']) ? ', ' . $application['district'] : '') . "\r\n\r\n" .
                    "Office Remarks:\r\n\"" . $remarks . "\"\r\n\r\n" .
                    "The Locational Clearance PDF is attached to this email.\r\n\r\n" .
                    "Quezon City Urban Planning Department";

                $mail->send();
                $summary .= ' <strong>Permit PDF has been emailed to ' . htmlspecialchars($applicantEmail) . '.</strong>';

            } catch (Exception $mailEx) {
                $summary .= ' <span class="text-warning">(Email notice: ' . htmlspecialchars($mailEx->getMessage()) . ')</span>';
            }
        }

    } catch (Exception $pdfEx) {
        $summary .= ' (Note: PDF generation encountered an issue: ' . htmlspecialchars($pdfEx->getMessage()) . ')';
    }

    return ['success' => true, 'message' => $summary];
}