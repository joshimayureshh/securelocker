<?php
// mailer.php - Pure PHP Gmail SMTP Mailer for Secure Locker (No external composer dependencies needed)
require_once 'config.php';

class SMTPEmailSender {
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $timeout = 10;
    private $debug = false;

    public function __construct($host, $port, $username, $password, $fromEmail, $fromName) {
        $this->host = $host;
        $this->port = intval($port);
        $this->username = $username;
        $this->password = $password;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    public function send($toEmail, $toName, $subject, $htmlContent) {
        if (empty($this->username) || empty($this->password) || $this->username === 'your-email@gmail.com') {
            error_log("SMTP Error: Gmail credentials are not configured in config.php");
            return false;
        }

        $host_prefix = ($this->port === 465) ? 'ssl://' : '';
        $socket = @fsockopen($host_prefix . $this->host, $this->port, $errno, $errstr, $this->timeout);
        if (!$socket) {
            error_log("SMTP Connection Error: $errstr ($errno)");
            return false;
        }

        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '220') {
            fclose($socket);
            return false;
        }

        // Send EHLO
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        $this->readFullResponse($socket);

        // Send STARTTLS if port 587
        if ($this->port === 587) {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) !== '220') {
                fclose($socket);
                return false;
            }

            // Enable TLS stream crypto
            $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            if (!stream_socket_enable_crypto($socket, true, $crypto_method)) {
                fclose($socket);
                error_log("SMTP Error: Failed to enable TLS encryption");
                return false;
            }

            // Re-send EHLO after TLS
            fputs($socket, "EHLO " . gethostname() . "\r\n");
            $this->readFullResponse($socket);
        }

        // AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '334') {
            fclose($socket);
            return false;
        }

        // Send Base64 Username
        fputs($socket, base64_encode($this->username) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '334') {
            fclose($socket);
            return false;
        }

        // Send Base64 Password
        fputs($socket, base64_encode(str_replace(' ', '', $this->password)) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '235') {
            fclose($socket);
            error_log("SMTP Auth Error: Invalid Gmail username or App Password");
            return false;
        }

        // MAIL FROM
        fputs($socket, "MAIL FROM: <" . $this->fromEmail . ">\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            fclose($socket);
            return false;
        }

        // RCPT TO
        fputs($socket, "RCPT TO: <" . $toEmail . ">\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            fclose($socket);
            return false;
        }

        // DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '354') {
            fclose($socket);
            return false;
        }

        // Build Email Headers & Body
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <" . $this->fromEmail . ">\r\n";
        $headers .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <" . $toEmail . ">\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "X-Mailer: SecureLocker-Mailer\r\n";

        $fullPayload = $headers . "\r\n" . $htmlContent . "\r\n.\r\n";
        fputs($socket, $fullPayload);

        $response = fgets($socket, 515);
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return substr($response, 0, 3) === '250';
    }

    private function readFullResponse($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    }
}

/**
 * Send branded OTP Password Recovery Email
 */
function sendOTPEmail($toEmail, $toName, $otpCode) {
    if (!defined('SMTP_ENABLED') || !SMTP_ENABLED) {
        return false;
    }

    $subject = "Your Secure Locker Password Reset OTP: " . $otpCode;

    // Branded HTML Template
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background-color: #f0f4f9; margin: 0; padding: 30px; }
            .email-card { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; padding: 36px 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
            .logo-wrap { text-align: center; margin-bottom: 20px; }
            .logo-title { font-size: 22px; font-weight: 700; color: #10285d; margin: 8px 0 0 0; }
            .header-text { font-size: 15px; color: #475569; line-height: 1.5; text-align: center; margin-bottom: 24px; }
            .otp-box { background: #f0f7ff; border: 2px dashed #3b82f6; border-radius: 16px; padding: 20px; text-align: center; margin: 24px 0; }
            .otp-label { font-size: 13px; font-weight: 600; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
            .otp-code { font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #1d4ed8; font-family: "Courier New", monospace; margin: 4px 0; }
            .otp-timer { font-size: 12px; color: #60a5fa; margin-top: 6px; }
            .warning-text { font-size: 12.5px; color: #64748b; line-height: 1.5; padding: 14px; background: #f8fafc; border-radius: 12px; border-left: 4px solid #f59e0b; margin-top: 20px; }
            .footer-text { text-align: center; font-size: 12px; color: #94a3b8; margin-top: 24px; }
        </style>
    </head>
    <body>
        <div class="email-card">
            <div class="logo-wrap">
                <div style="font-size: 44px; line-height: 1;">🔒</div>
                <div class="logo-title">Secure Locker</div>
            </div>
            
            <p class="header-text">
                Hello <strong>' . htmlspecialchars($toName) . '</strong>,<br>
                We received a request to reset your password for your Secure Locker account.
            </p>

            <div class="otp-box">
                <div class="otp-label">Verification OTP Code</div>
                <div class="otp-code">' . htmlspecialchars($otpCode) . '</div>
                <div class="otp-timer">⏱️ Valid for 15 minutes only</div>
            </div>

            <div class="warning-text">
                ⚠️ <strong>Security Notice:</strong> If you did not request this password reset, please ignore this email or change your password immediately. Never share this code with anyone.
            </div>

            <div class="footer-text">
                &copy; ' . date('Y') . ' Secure Locker &bull; Digital Cloud Vault
            </div>
        </div>
    </body>
    </html>';

    try {
        $sender = new SMTPEmailSender(
            SMTP_HOST,
            SMTP_PORT,
            SMTP_USER,
            SMTP_PASS,
            SMTP_FROM_EMAIL,
            SMTP_FROM_NAME
        );

        $sent = $sender->send($toEmail, $toName, $subject, $html);
        if ($sent) {
            if (function_exists('logActivity')) {
                logActivity(0, 'email_sent', "OTP reset email sent to $toEmail");
            }
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("OTP Email Sending Exception: " . $e->getMessage());
        return false;
    }
}
