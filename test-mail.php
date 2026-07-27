<?php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

// Load .env
$envPath = __DIR__ . '/.env';
if (is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}

$dsn = getenv('MAILER_DSN');
$transport = Transport::fromDsn($dsn);
$mailer = new Mailer($transport);

$email = (new Email())
    ->from(getenv('MAILER_FROM') ?: 'no-reply@localhost')
    ->to('your-email@gmail.com')
    ->subject('Test Email from LGU Portal')
    ->text('If you see this, Mailtrap is working!');

try {
    $mailer->send($email);
    echo "✅ Email sent! Check your Mailtrap inbox.";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}