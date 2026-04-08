<?php

declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

final class Mail
{
    public static function sendVerification(string $toEmail, string $token): void
    {
        $cfg = require dirname(__DIR__) . '/config/config.php';
        $base = $cfg['app']['base_url'];
        $link = $base . '/verify.php?token=' . urlencode($token);

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $cfg['smtp']['host'];
        $mail->Port = $cfg['smtp']['port'];
        $mail->SMTPAuth = false;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($cfg['smtp']['from'], 'Networking App');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Confirm your registration';
        $mail->Body = '<p>Click to verify your account:</p><p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a></p>';
        $mail->AltBody = "Verify your account: $link";

        try {
            $mail->send();
        } catch (MailException $e) {
            throw new \RuntimeException('Mail send failed: ' . $mail->ErrorInfo, 0, $e);
        }
    }

    public static function sendPasswordReset(string $toEmail, string $token): void
    {
        $cfg = require dirname(__DIR__) . '/config/config.php';
        $base = $cfg['app']['base_url'];
        $link = $base . '/reset-password.php?token=' . urlencode($token);

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $cfg['smtp']['host'];
        $mail->Port = $cfg['smtp']['port'];
        $mail->SMTPAuth = false;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($cfg['smtp']['from'], 'Networking App');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Reset your password';
        $mail->Body = '<p>Click the link below to set a new password. It expires in 30 minutes.</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a></p>';
        $mail->AltBody = "Reset your password (valid 30 minutes): $link";

        try {
            $mail->send();
        } catch (MailException $e) {
            throw new \RuntimeException('Mail send failed: ' . $mail->ErrorInfo, 0, $e);
        }
    }
}
