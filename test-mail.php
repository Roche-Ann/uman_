<?php
/**
 * Quick SMTP smoke test — sends a test message to MAIL_USERNAME / MAILER_FROM.
 * Usage: php test-mail.php [optional-recipient@email.com]
 */
require_once __DIR__ . '/includes/mailer.php';

loadAppEnv();

$to = $argv[1] ?? '';
if ($to === '') {
    $to = getenv('MAIL_USERNAME') ?: getenv('MAILER_FROM') ?: '';
}

if ($to === '') {
    fwrite(STDERR, "No recipient. Pass an email or set MAIL_USERNAME in .env\n");
    exit(1);
}

echo "DSN: " . (resolveMailerDsn() ?: '(missing)') . "\n";
echo "From: " . resolveMailerFrom() . "\n";
echo "To: {$to}\n";

$result = sendAppMail(
    $to,
    'LGU Portal SMTP Test',
    '<p>If you see this, Gmail SMTP is working for OTP delivery.</p>',
    'If you see this, Gmail SMTP is working for OTP delivery.'
);

if ($result['success']) {
    echo "SUCCESS: Email sent. Check inbox (and spam) for {$to}\n";
    exit(0);
}

echo "ERROR: " . ($result['error'] ?? 'unknown') . "\n";
exit(1);
