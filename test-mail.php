<?php
/**
 * Quick SMTP smoke test.
 * Usage: php test-mail.php [optional-recipient@email.com]
 *    or open in browser after deploying (remove in production if desired)
 */
require_once __DIR__ . '/includes/mailer.php';

loadAppEnv();

$to = $argv[1] ?? ($_GET['to'] ?? '');
if ($to === '') {
    $to = getenv('MAIL_USERNAME') ?: getenv('MAILER_FROM') ?: '';
}

header('Content-Type: text/plain; charset=utf-8');

if ($to === '') {
    echo "No recipient. Pass ?to=email@example.com or set MAIL_USERNAME / MAILER_FROM in .env\n";
    exit(1);
}

$dsn = resolveMailerDsn() ?: '(missing)';
$safeDsn = preg_replace('#(smtp://[^:]+:)([^@]+)(@)#', '$1***$3', $dsn);

echo "DSN: {$safeDsn}\n";
echo "From: " . resolveMailerFrom() . "\n";
echo "To: {$to}\n";
echo "Symfony: " . (class_exists(\Symfony\Component\Mailer\Transport::class) ? 'yes' : 'no (using native SMTP)') . "\n\n";

$result = sendAppMail(
    $to,
    'LGU Portal SMTP Test',
    '<p>If you see this, SMTP OTP delivery is working.</p>',
    'If you see this, SMTP OTP delivery is working.'
);

if ($result['success']) {
    echo "SUCCESS: Email sent. Check inbox/spam for {$to}\n";
    exit(0);
}

echo "ERROR: " . ($result['error'] ?? 'unknown') . "\n";
exit(1);
