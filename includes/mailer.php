<?php
/**
 * Shared mail helpers for OTP and transactional email.
 * Uses Symfony Mailer when vendor/ is installed; otherwise pure PHP SMTP
 * (needed on shared hosting where `composer install` was never run).
 */

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
 * Parse smtp://user:pass@host:port into connection parts.
 *
 * @return array{host:string,port:int,user:string,pass:string}|null
 */
function parseSmtpDsn(string $dsn): ?array
{
    $parts = parse_url($dsn);
    if (!$parts || empty($parts['host'])) {
        return null;
    }

    $user = isset($parts['user']) ? rawurldecode($parts['user']) : '';
    $pass = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';
    $pass = preg_replace('/\s+/', '', $pass);

    return [
        'host' => $parts['host'],
        'port' => (int)($parts['port'] ?? 587),
        'user' => $user,
        'pass' => $pass,
    ];
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
    $pass = preg_replace('/\s+/', '', $pass);

    if ($user === '' || $pass === '') {
        return $dsn !== '' ? $dsn : null;
    }

    return sprintf(
        'smtp://%s:%s@%s:%s',
        rawurlencode($user),
        rawurlencode($pass),
        $host,
        $port
    );
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
 * Read one SMTP response (may be multi-line).
 */
function smtpRead($socket): string
{
    $data = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

/**
 * Send one SMTP command and optionally assert a response code prefix.
 */
function smtpCommand($socket, string $command, ?string $expectPrefix = null): string
{
    fwrite($socket, $command . "\r\n");
    $response = smtpRead($socket);
    if ($expectPrefix !== null && !str_starts_with(trim($response), $expectPrefix)) {
        throw new RuntimeException('SMTP unexpected response for "' . $command . '": ' . trim($response));
    }
    return $response;
}

/**
 * Send email using raw SMTP (STARTTLS on port 587 / implicit TLS on 465).
 *
 * @return array{success: bool, error: ?string}
 */
function sendAppMailSmtpNative(string $to, string $subject, string $html, ?string $text, array $smtp, string $from, string $fromName): array
{
    $host = $smtp['host'];
    $port = $smtp['port'];
    $user = $smtp['user'];
    $pass = $smtp['pass'];

    if ($user === '' || $pass === '') {
        return ['success' => false, 'error' => 'SMTP username/password missing in .env'];
    }

    $remote = ($port === 465 ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ])
    );

    if (!$socket) {
        return ['success' => false, 'error' => "SMTP connect failed: {$errstr} ({$errno})"];
    }

    stream_set_timeout($socket, 30);

    try {
        $greeting = smtpRead($socket);
        if (!str_starts_with(trim($greeting), '220')) {
            throw new RuntimeException('Bad SMTP greeting: ' . trim($greeting));
        }

        $ehloHost = 'localhost';
        smtpCommand($socket, 'EHLO ' . $ehloHost, '250');

        if ($port !== 465) {
            smtpCommand($socket, 'STARTTLS', '220');
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($crypto !== true) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }
            smtpCommand($socket, 'EHLO ' . $ehloHost, '250');
        }

        smtpCommand($socket, 'AUTH LOGIN', '334');
        smtpCommand($socket, base64_encode($user), '334');
        smtpCommand($socket, base64_encode($pass), '235');

        smtpCommand($socket, 'MAIL FROM:<' . $from . '>', '250');
        smtpCommand($socket, 'RCPT TO:<' . $to . '>', '250');
        smtpCommand($socket, 'DATA', '354');

        $boundary = 'b_' . bin2hex(random_bytes(8));
        $textBody = $text !== null && $text !== ''
            ? $text
            : trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $headers = [
            'Date: ' . date('r'),
            'From: ' . sprintf('"%s" <%s>', addcslashes($fromName, '"\\'), $from),
            'To: <' . $to . '>',
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = implode("\r\n", $headers) . "\r\n\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $html . "\r\n";
        $body .= '--' . $boundary . "--\r\n";

        // SMTP data ends with <CRLF>.<CRLF>; escape lines starting with '.'
        $body = preg_replace('/^\./m', '..', $body);
        fwrite($socket, $body . "\r\n.\r\n");
        $dataResp = smtpRead($socket);
        if (!str_starts_with(trim($dataResp), '250')) {
            throw new RuntimeException('SMTP DATA failed: ' . trim($dataResp));
        }

        smtpCommand($socket, 'QUIT');
        fclose($socket);

        return ['success' => true, 'error' => null];
    } catch (Throwable $e) {
        fclose($socket);
        error_log('Native SMTP error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Send an HTML (and optional text) email.
 *
 * @return array{success: bool, error: ?string}
 */
function sendAppMail(string $to, string $subject, string $html, ?string $text = null): array
{
    loadAppEnv();

    $dsn = resolveMailerDsn();
    if ($dsn === null || $dsn === '') {
        return [
            'success' => false,
            'error' => 'Email is not configured. Set MAILER_DSN or MAIL_USERNAME/MAIL_PASSWORD in .env',
        ];
    }

    $from = resolveMailerFrom();
    $appName = trim((string)(getenv('APP_NAME') ?: 'LGU Utilities Management'));
    $smtp = parseSmtpDsn($dsn);

    // Prefer Symfony when available
    if (class_exists(\Symfony\Component\Mailer\Transport::class) && $smtp) {
        try {
            $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);
            $mailer = new \Symfony\Component\Mailer\Mailer($transport);
            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address($from, $appName))
                ->to($to)
                ->subject($subject)
                ->html($html);

            if ($text !== null && $text !== '') {
                $email->text($text);
            }

            $mailer->send($email);
            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            error_log('Symfony mail failed, trying native SMTP: ' . $e->getMessage());
        }
    }

    if (!$smtp) {
        return ['success' => false, 'error' => 'Invalid MAILER_DSN'];
    }

    return sendAppMailSmtpNative($to, $subject, $html, $text, $smtp, $from, $appName);
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
    </head>
    <body style="margin:0; padding:0; background-color:#f4f4f4;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" align="center" bgcolor="#f4f4f4">
            <tr>
                <td align="center" style="padding:20px 15px;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:500px; background:#ffffff; border-radius:20px;">
                        <tr>
                            <td align="center" style="padding:30px 30px 10px;">
                                <h1 style="font-size:28px; font-weight:700; margin:0; color:#0B3D91;">Verify Your Email</h1>
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
                                <span style="font-family: monospace; font-size:36px; font-weight:700; letter-spacing:8px; color:#0B3D91;">{$code}</span>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding:0 30px 30px;">
                                <p style="font-size:14px; color:#6c757d; margin:0;">This code will expire in <strong>{$mins} minutes</strong>.</p>
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
