<?php
require_once 'vendor/autoload.php';

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

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

echo "DSN: $dsn\n";

try {
    $transport = Transport::fromDsn($dsn);
    $mailer = new Mailer($transport);
    $email = (new Email())
        ->from($from)
        ->to('trinidadnicholas1@gmail.com')
        ->subject('SMTP test')
        ->text('Localhost test');
    $mailer->send($email);
    echo "SUCCESS\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
