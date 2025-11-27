<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Helper: mailer_helper.php
 * 
 * Send emails using PHPMailer with environment configuration
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using PHPMailer
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body HTML body content
 * @param string|null $recipientName Recipient's name (optional)
 * @param string|null $altBody Plain text alternative body (optional)
 * @param bool $debug Enable SMTP debug output (default: false)
 * @param array|null $attachments Optional array of attachments. Each element can be:
 *                                  - A file path (string): will attach the file
 *                                  - An array with 'data' (binary string), 'name' (filename), and 'type' (MIME type)
 * @return array ['success' => bool, 'message' => string]
 */
function send_email($to, $subject, $body, $recipientName = null, $altBody = null, $debug = false, $attachments = null)
{
    // Load environment variables
    $dotenv = Dotenv\Dotenv::createImmutable(ROOT_DIR);
    $dotenv->load();
    
    $mail = new PHPMailer(true);

    try {
        // Server settings
        if ($debug) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USERNAME'] ?? '';
        $mail->Password   = $_ENV['SMTP_PASSWORD'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $_ENV['PORT'] ?? 465;

        // Recipients
        $fromEmail = $_ENV['SMTP_USERNAME'] ?? 'noreply@payflow.com';
        $fromName = 'PayFlow HR System';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to, $recipientName ?? '');
        $mail->addReplyTo($fromEmail, $fromName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?? strip_tags($body);

        // Handle attachments
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (is_string($attachment)) {
                    // Attachment is a file path
                    if (file_exists($attachment)) {
                        $mail->addAttachment($attachment);
                    }
                } elseif (is_array($attachment) && isset($attachment['data'], $attachment['name'])) {
                    // Attachment is binary data with a name
                    $type = $attachment['type'] ?? 'application/octet-stream';
                    $mail->addStringAttachment($attachment['data'], $attachment['name'], 'base64', $type);
                }
            }
        }

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"];
    }
}

/**
 * Quick send email (backward compatibility)
 */
function mailer_helper($to, $subject, $body, $recipientName = null)
{
    return send_email($to, $subject, $body, $recipientName);
}
