<?php
// includes/mailer.php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

// Minimal .env loader (key=value) for local use
// This ensures environment variables are loaded if this script is called
// in a context where the main application's .env loading hasn't occurred.
$envPath = __DIR__ . '/../.env';
if (is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}

/**
 * Sends an OTP email to the specified recipient.
 *
 * @param string $recipientEmail The email address of the recipient.
 * @param string $recipientName The name of the recipient.
 * @param string $otpCode The One-Time Password code to send.
 * @param int $expiryMinutes The number of minutes the OTP is valid for.
 * @return array An associative array with 'success' (boolean) and 'error' (string, if any).
 */
function sendOtpEmail(string $recipientEmail, string $recipientName, string $otpCode, int $expiryMinutes): array
{
    $smtpHost = getenv('SMTP_HOST');
    $smtpPort = getenv('SMTP_PORT');
    $smtpUser = getenv('SMTP_USERNAME');
    $smtpPass = getenv('SMTP_PASSWORD');
    $smtpEncryption = getenv('SMTP_ENCRYPTION') ?: 'tls'; // 'ssl' or 'tls'
    $fromEmail = getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@lgusystem.com';
    $fromName = getenv('MAIL_FROM_NAME') ?: 'LGU Portal';

    if (!$smtpHost || !$smtpPort || !$smtpUser || !$smtpPass) {
        error_log("Mailer configuration missing: SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD must be set in .env");
        return ['success' => false, 'error' => 'Mailer not configured.'];
    }

    try {
        $dsn = "smtp://{$smtpUser}:{$smtpPass}@{$smtpHost}:{$smtpPort}?encryption={$smtpEncryption}&auth_mode=login";
        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);

        $email = (new Email())
            ->from($fromName . ' <' . $fromEmail . '>')
            ->to($recipientName . ' <' . $recipientEmail . '>')
            ->subject('Your LGU Portal Verification Code (OTP)')
            ->html("<p>Hello {$recipientName},</p><p>Your One-Time Password (OTP) for LGU Portal is: <strong>{$otpCode}</strong></p><p>This code is valid for {$expiryMinutes} minutes.</p><p>If you did not request this, please ignore this email.</p><p>Thank you,<br>LGU Portal Team</p>");

        $mailer->send($email);
        return ['success' => true];
    } catch (Throwable $e) {
        error_log('Mailer error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}