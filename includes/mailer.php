<?php
/**
 * Shared Symfony Mailer helpers for OTP and transactional email.
 */

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Ensure .env values are loaded into the process environment.
 */
function loadAppEnv(): void
{
    if (!empty($GLOBALS['_ENV_LOADED'])) {
        return;
    }

    $envPath = dirname(__DIR__) . '/.env';
    if (!is_readable($envPath)) {
        $GLOBALS['_ENV_LOADED'] = true;
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);

        // Strip surrounding single/double quotes from values.
        if (
            (str_starts_with($v, '"') && str_ends_with($v, '"')) ||
            (str_starts_with($v, "'") && str_ends_with($v, "'"))
        ) {
            $v = substr($v, 1, -1);
        }

        if ($k === '') {
            continue;
        }

        putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }

    $GLOBALS['_ENV_LOADED'] = true;
}

/**
 * Resolve MAILER_DSN, building a Gmail SMTP DSN from MAIL_* vars when needed.
 */
function resolveMailerDsn(): ?string
{
    loadAppEnv();

    $dsn = trim((string)(getenv('MAILER_DSN') ?: ''));
    if ($dsn !== '' && $dsn !== 'smtp://localhost') {
        return $dsn;
    }

    $host = trim((string)(getenv('MAIL_HOST') ?: 'smtp.gmail.com'));
    $port = trim((string)(getenv('MAIL_PORT') ?: '587'));
    $user = trim((string)(getenv('MAIL_USERNAME') ?: ''));
    $pass = trim((string)(getenv('MAIL_PASSWORD') ?: ''));

    // Gmail app passwords are often pasted with spaces — strip them.
    $pass = preg_replace('/\s+/', '', $pass);

    if ($user === '' || $pass === '') {
        return $dsn !== '' ? $dsn : null;
    }

    $userEnc = rawurlencode($user);
    $passEnc = rawurlencode($pass);

    return sprintf('smtp://%s:%s@%s:%s', $userEnc, $passEnc, $host, $port);
}

/**
 * From address for outbound mail.
 */
function resolveMailerFrom(): string
{
    loadAppEnv();

    $from = trim((string)(getenv('MAILER_FROM') ?: ''));
    if ($from !== '') {
        return $from;
    }

    $user = trim((string)(getenv('MAIL_USERNAME') ?: ''));
    return $user !== '' ? $user : 'no-reply@localhost';
}

/**
 * Send an HTML (and optional text) email.
 *
 * @return array{success: bool, error: ?string}
 */
function sendAppMail(string $to, string $subject, string $html, ?string $text = null): array
{
    loadAppEnv();

    if (!class_exists(Transport::class)) {
        return [
            'success' => false,
            'error' => 'Mailer package is not installed. Run: composer require symfony/mailer',
        ];
    }

    $dsn = resolveMailerDsn();
    if ($dsn === null || $dsn === '') {
        return [
            'success' => false,
            'error' => 'Email is not configured. Set MAILER_DSN or MAIL_USERNAME/MAIL_PASSWORD in .env',
        ];
    }

    try {
        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);
        $from = resolveMailerFrom();
        $appName = trim((string)(getenv('APP_NAME') ?: 'LGU Utilities Management'));

        $email = (new Email())
            ->from(new Address($from, $appName))
            ->to($to)
            ->subject($subject)
            ->html($html);

        if ($text !== null && $text !== '') {
            $email->text($text);
        }

        $mailer->send($email);

        return ['success' => true, 'error' => null];
    } catch (Throwable $e) {
        error_log('Mail send error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Build the standard OTP verification HTML email.
 */
function buildOtpEmailHtml(string $recipientName, string $otp, int $expiresMinutes = 10): string
{
    $name = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
    $code = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $mins = (int)$expiresMinutes;

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Your Verification Code</title>
        <style>
            body, table, td, p, a { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        </style>
    </head>
    <body style="margin:0; padding:0; background-color:#f4f4f4;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" align="center" bgcolor="#f4f4f4" style="background-color:#f4f4f4;">
            <tr>
                <td align="center" style="padding:20px 15px;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:500px; background:#ffffff; border-radius:20px; box-shadow:0 8px 32px rgba(11,61,145,0.08);">
                        <tr>
                            <td align="center" style="padding:30px 30px 20px;">
                                <img src="https://uman.infragovservices.com/assets/images/logocityhall.png" alt="LGU Logo" width="80" height="80" style="display:block; width:80px; height:80px; border-radius:50%; object-fit:cover;">
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding:0 30px 10px;">
                                <h1 style="font-family: 'Urbanist', 'Segoe UI', sans-serif; font-size:28px; font-weight:700; margin:0; color:#0B3D91;">Verify Your Email</h1>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding:0 30px 10px;">
                                <p style="font-size:16px; color:#2F4858; margin:0;">Hello <strong>{$name}</strong>,</p>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding:0 30px 20px;">
                                <p style="font-size:16px; color:#2F4858; margin:0;">Your login verification code is:</p>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding:0 30px 20px;">
                                <table cellpadding="0" cellspacing="0" border="0" style="background:#f0f5ff; border-radius:12px; padding:15px 25px; display:inline-block;">
                                    <tr>
                                        <td>
                                            <span style="font-family: 'Fira Code', monospace; font-size:36px; font-weight:700; letter-spacing:8px; color:#0B3D91;">{$code}</span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding:0 30px 20px;">
                                <p style="font-size:14px; color:#6c757d; margin:0;">This code will expire in <strong>{$mins} minutes</strong>.</p>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding:0 30px;">
                                <hr style="border:0; height:1px; background:#e0e0e2; width:100%;">
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding:20px 30px 30px;">
                                <p style="font-size:13px; color:#6c757d; margin:0;">If you did not attempt to log in, please ignore this email or contact support.</p>
                            </td>
                        </tr>
                    </table>
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:500px; margin-top:20px;">
                        <tr>
                            <td align="center" style="padding:0 15px;">
                                <p style="font-size:12px; color:#6c757d;">© 2026 LGU Utilities Management System · All Rights Reserved</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    HTML;
}

/**
 * Send a login OTP email.
 *
 * @return array{success: bool, error: ?string}
 */
function sendOtpEmail(string $to, string $recipientName, string $otp, int $expiresMinutes = 10): array
{
    $subject = 'Your Login Verification Code';
    $html = buildOtpEmailHtml($recipientName, $otp, $expiresMinutes);
    $text = "Hello {$recipientName},\n\nYour login verification code is: {$otp}\n\nThis code will expire in {$expiresMinutes} minutes.\n\nIf you didn't attempt to log in, please ignore this email.";

    return sendAppMail($to, $subject, $html, $text);
}
