<?php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

// Use the DSN from your .env
$dsn = 'smtp://roche.mapait%40gmail.com:lsjlcptanerlvcii@smtp.gmail.com:587?encryption=tls';
$transport = Transport::fromDsn($dsn);
$mailer = new Mailer($transport);

$email = (new Email())
    ->from('roche.mapait@gmail.com')
    ->to('roche.mapait@gmail.com') // Send to yourself
    ->subject('Test Email from LGU Portal')
    ->text('If you see this, your SMTP is working!');

try {
    $mailer->send($email);
    echo "✅ Email sent successfully! Check your inbox.";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}