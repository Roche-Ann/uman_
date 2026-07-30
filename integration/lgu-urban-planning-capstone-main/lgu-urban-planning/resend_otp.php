<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Manila');
require 'vendor/autoload.php';
require_once __DIR__ . '/core/Database.php';

$db = Database::getInstance();
$email = $_GET['email'] ?? '';

if (!$email) {
    header("Location: register.php");
    exit();
}

// 1. Check kung existing ang user at hindi pa verified
$user = $db->fetchOne("SELECT id FROM users WHERE email = ? AND is_verified = 0", [$email]);

if ($user) {
    // 2. Generate new OTP and Expiry
    $new_otp = rand(100000, 999999);
    $new_expiry = date("Y-m-d H:i:s", strtotime("+1 minute"));
    
    // 3. Update Database
    $db->query("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE email = ?", [$new_otp, $new_expiry, $email]);

    // 4. Send Email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'aelousssnexus@gmail.com'; 
        $mail->Password   = 'zuey mjni sbzz gvsm';   
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('aelousssnexus@gmail.com', 'LGU Urban Planning');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your NEW OTP Verification Code';      
        $mail->Body = "
        <div style='background-color: #f9f9f9; padding: 40px 0; font-family: Helvetica, Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                <div style='padding: 30px; text-align: center;'>
                    <h2 style='color: #285ccd; font-size: 24px; margin-bottom: 20px; font-weight: bold;'>Verification Code</h2>
                    <p style='color: #555555; font-size: 16px; margin-bottom: 30px;'>Your New One-Time Password (OTP) for registration is:</p>
                    <div style='background-color: #f4f7ff; border-radius: 10px; padding: 20px; margin: 0 auto 30px auto; width: fit-content;'>
                        <h1 style='letter-spacing: 12px; color: #333333; font-size: 42px; margin: 0; padding-left: 12px;'>$new_otp</h1>
                    </div>
                    <p style='color: #777777; font-size: 14px; margin-bottom: 10px;'>This code is valid for <strong style='color: #333;'>1 minute</strong> only.</p>
                    <p style='color: #999999; font-size: 12px; border-top: 1px solid #eeeeee; padding-top: 20px; margin-top: 20px;'>
                        If you did not request this, please ignore this email.
                    </p>
                </div>
            </div>
        </div>";

        $mail->send();
        
        // I-redirect pabalik sa register.php (Single Page)
        header("Location: register.php?step=otp&email=" . urlencode($email) . "&resend=success");
        exit();

    } catch (Exception $e) {
        header("Location: register.php?step=otp&email=" . urlencode($email) . "&resend=error");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}