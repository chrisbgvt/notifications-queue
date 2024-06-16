<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use MailerSend\MailerSend;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\Helpers\Builder\EmailParams;

function sendEmail($to, $subject, $body) {
    try {
        $mailersend = new MailerSend(['api_key' => $_ENV['MAILERSEND_TOKEN']]);

        $recipients = [
            new Recipient($to, 'Recipient Name')
        ];

        $emailParams = (new EmailParams())
            ->setFrom($_ENV['MAILERSEND_SENDER'])
            ->setFromName($to)
            ->setRecipients($recipients)
            ->setSubject($subject)
            ->setHtml($body)
            ->setText($body);

        $mailersend->email->send($emailParams);

        echo "Email sent successfully!";
    } catch (Exception $e) {
        echo "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}
?>