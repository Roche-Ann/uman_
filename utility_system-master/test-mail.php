<?php
require_once 'vendor/autoload.php';

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
$from = getenv('MAILER_FROM');

echo "Testing SMTP connection...\n";
echo "DSN: " . $dsn . "\n";
echo "From: " . $from . "\n\n";

try {
    $transport = Transport::fromDsn($dsn);
    $mailer = new Mailer($transport);
    
    $email = (new Email())
        ->from($from)
        ->to('trinidadnicholas1@gmail.com')
        ->subject('SMTP Test')
        ->text('If you see this, SMTP is working perfectly!');
        
    $mailer->send($email);
    echo "SUCCESS: Email sent successfully!\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
