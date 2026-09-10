<?php

/**
 * SMTPMailer - Professional SMTP Email Handler Class
 * 
 * A reusable, professional SMTP mailer class that supports:
 * - Template-based emails with variable replacement
 * - Multiple recipients (To, CC, BCC)
 * - File attachments
 * - HTML and plain text emails
 * - Email queue for bulk sending
 * - Logging for debugging
 * 
 * @author CMSNT.CO
 * @version 2.0.0
 * @since 2024
 */

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class SMTPMailer
{
    /**
     * Database instance
     * @var DB
     */
    private $db;

    /**
     * PHPMailer instance
     * @var PHPMailer
     */
    private $mailer;

    /**
     * SMTP Configuration
     * @var array
     */
    private $config = [];

    /**
     * Error messages
     * @var array
     */
    private $errors = [];

    /**
     * Success status
     * @var bool
     */
    private $success = false;

    /**
     * Last error message
     * @var string
     */
    private $lastError = '';

    /**
     * Enable debug mode
     * @var bool
     */
    private $debug = false;

    /**
     * Email template types
     */
    const TEMPLATE_ORDER_SUCCESS = 'order_success';
    const TEMPLATE_ORDER_COMPLETED = 'order_completed';
    const TEMPLATE_WARNING_LOGIN = 'warning_login';
    const TEMPLATE_OTP_MAIL = 'otp_mail';
    const TEMPLATE_FORGOT_PASSWORD = 'forgot_password';
    const TEMPLATE_WELCOME = 'welcome';
    const TEMPLATE_CUSTOM = 'custom';
    const TEMPLATE_FLASH_SALE_FAVORITE = 'flash_sale_favorite';

    /**
     * Constructor - Initialize SMTP configuration
     * 
     * @param DB|null $db Database instance (optional)
     */
    public function __construct(?object $db = null)
    {
        $this->db = $db ?: new DB();
        $this->loadConfig();
        $this->initMailer();
    }

    /**
     * Load SMTP configuration from database
     * 
     * @return void
     */
    private function loadConfig(): void
    {
        $this->config = [
            'status'     => (int) $this->db->site('smtp_status'),
            'host'       => $this->db->site('smtp_host'),
            'port'       => (int) $this->db->site('smtp_port'),
            'username'   => $this->db->site('smtp_email'),
            'password'   => $this->db->site('smtp_password'),
            'encryption' => $this->db->site('smtp_encryption'),
            'from_email' => $this->db->site('smtp_email'),
            'from_name'  => $this->db->site('title'),
            'charset'    => 'UTF-8'
        ];
    }

    /**
     * Initialize PHPMailer instance
     * 
     * @return void
     */
    private function initMailer(): void
    {
        $this->mailer = new PHPMailer(true);

        try {
            // Server settings
            $this->mailer->SMTPDebug = $this->debug ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
            $this->mailer->Debugoutput = 'html';
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['username'];
            $this->mailer->Password = $this->config['password'];
            $this->mailer->SMTPSecure = $this->config['encryption'];
            $this->mailer->Port = $this->config['port'];
            $this->mailer->CharSet = $this->config['charset'];
            $this->mailer->Encoding = 'base64';

            // Set timeout
            $this->mailer->Timeout = 30;
            $this->mailer->SMTPKeepAlive = true;
        } catch (Exception $e) {
            $this->addError('Failed to initialize mailer: ' . $e->getMessage());
        }
    }

    /**
     * Check if SMTP is enabled
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config['status'] === 1;
    }

    /**
     * Enable debug mode
     * 
     * @param bool $enable
     * @return self
     */
    public function setDebug(bool $enable = true): self
    {
        $this->debug = $enable;
        $this->mailer->SMTPDebug = $enable ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
        return $this;
    }

    /**
     * Reset mailer for new email
     * 
     * @return self
     */
    public function reset(): self
    {
        $this->mailer->clearAddresses();
        $this->mailer->clearCCs();
        $this->mailer->clearBCCs();
        $this->mailer->clearAttachments();
        $this->mailer->clearReplyTos();
        $this->mailer->Subject = '';
        $this->mailer->Body = '';
        $this->mailer->AltBody = '';
        $this->errors = [];
        $this->success = false;
        $this->lastError = '';

        return $this;
    }

    /**
     * Sanitize name to prevent Email Header Injection
     * 
     * @param string $name
     * @return string
     */
    private function sanitizeName(string $name): string
    {
        // Remove newlines and carriage returns to prevent header injection
        $name = str_replace(["\r", "\n", "\t"], '', $name);
        // Remove potentially dangerous characters
        $name = preg_replace('/[<>"\'\x00-\x1f\x7f]/', '', $name);
        // Limit length
        return substr(trim($name), 0, 100);
    }

    /**
     * Set sender information
     * 
     * @param string $email Sender email
     * @param string $name Sender name
     * @return self
     */
    public function setFrom(string $email, string $name = ''): self
    {
        try {
            // Security: Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addError('Invalid sender email format');
                return $this;
            }

            // Security: Sanitize name to prevent header injection
            $safeName = $this->sanitizeName($name ?: $this->config['from_name']);

            $this->mailer->setFrom($email, $safeName);
        } catch (Exception $e) {
            $this->addError('Invalid sender: ' . $e->getMessage());
        }
        return $this;
    }

    /**
     * Add recipient
     * 
     * @param string $email Recipient email
     * @param string $name Recipient name
     * @return self
     */
    public function addTo(string $email, string $name = ''): self
    {
        try {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Security: Sanitize name to prevent header injection
                $safeName = $this->sanitizeName($name);
                $this->mailer->addAddress($email, $safeName);
            } else {
                $this->addError('Invalid email address: ' . htmlspecialchars($email));
            }
        } catch (Exception $e) {
            $this->addError('Failed to add recipient: ' . $e->getMessage());
        }
        return $this;
    }

    /**
     * Add CC recipient
     * 
     * @param string $email
     * @param string $name
     * @return self
     */
    public function addCc(string $email, string $name = ''): self
    {
        try {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $safeName = $this->sanitizeName($name);
                $this->mailer->addCC($email, $safeName);
            }
        } catch (Exception $e) {
            $this->addError('Failed to add CC: ' . $e->getMessage());
        }
        return $this;
    }

    /**
     * Add BCC recipient
     * 
     * @param string $email
     * @param string $name
     * @return self
     */
    public function addBcc(string $email, string $name = ''): self
    {
        try {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $safeName = $this->sanitizeName($name);
                $this->mailer->addBCC($email, $safeName);
            }
        } catch (Exception $e) {
            $this->addError('Failed to add BCC: ' . $e->getMessage());
        }
        return $this;
    }

    /**
     * Add Reply-To address
     * 
     * @param string $email
     * @param string $name
     * @return self
     */
    public function addReplyTo(string $email, string $name = ''): self
    {
        try {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $safeName = $this->sanitizeName($name);
                $this->mailer->addReplyTo($email, $safeName);
            }
        } catch (Exception $e) {
            $this->addError('Failed to add Reply-To: ' . $e->getMessage());
        }
        return $this;
    }

    /**
     * Add attachment with security validation
     * 
     * @param string $path File path
     * @param string $name Custom filename (optional)
     * @return self
     */
    public function addAttachment(string $path, string $name = ''): self
    {
        try {
            // Security: Validate and sanitize file path to prevent Path Traversal
            $realPath = realpath($path);

            if ($realPath === false) {
                $this->addError('Attachment file not found: ' . basename($path));
                return $this;
            }

            // Security: Prevent path traversal - only allow files within allowed directories
            $allowedDirs = [
                realpath(__DIR__ . '/../uploads'),
                realpath(__DIR__ . '/../assets'),
                sys_get_temp_dir()
            ];

            $isAllowed = false;
            foreach ($allowedDirs as $allowedDir) {
                if ($allowedDir && strpos($realPath, $allowedDir) === 0) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                $this->addError('Attachment path not allowed for security reasons');
                return $this;
            }

            // Security: Check file size (max 10MB)
            $maxSize = 10 * 1024 * 1024;
            if (filesize($realPath) > $maxSize) {
                $this->addError('Attachment file too large (max 10MB)');
                return $this;
            }

            // Security: Validate file extension
            $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'gif', 'zip'];
            $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions)) {
                $this->addError('Attachment file type not allowed: ' . $extension);
                return $this;
            }

            // Sanitize custom filename if provided
            if (!empty($name)) {
                $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name);
            }

            $this->mailer->addAttachment($realPath, $name);
        } catch (Exception $e) {
            $this->addError('Failed to add attachment: ' . $e->getMessage());
        }
        return $this;
    }

    /**
     * Maximum subject length
     */
    const MAX_SUBJECT_LENGTH = 998; // RFC 5322 recommends max 998 chars per line

    /**
     * Maximum body size (5MB)
     */
    const MAX_BODY_SIZE = 5 * 1024 * 1024;

    /**
     * Sanitize subject to prevent header injection
     * 
     * @param string $subject
     * @return string
     */
    private function sanitizeSubject(string $subject): string
    {
        // Remove newlines to prevent header injection
        $subject = str_replace(["\r", "\n", "\t"], ' ', $subject);
        // Remove null bytes and control characters
        $subject = preg_replace('/[\x00-\x1f\x7f]/', '', $subject);
        // Limit length
        return substr(trim($subject), 0, self::MAX_SUBJECT_LENGTH);
    }

    /**
     * Set email subject
     * 
     * @param string $subject
     * @return self
     */
    public function setSubject(string $subject): self
    {
        // Security: Sanitize subject to prevent header injection
        $this->mailer->Subject = $this->sanitizeSubject($subject);
        return $this;
    }

    /**
     * Set HTML body
     * 
     * @param string $body HTML content
     * @param string $altBody Plain text alternative
     * @return self
     */
    public function setBody(string $body, string $altBody = ''): self
    {
        // Security: Check body size to prevent memory issues
        if (strlen($body) > self::MAX_BODY_SIZE) {
            $this->addError('Email body too large (max 5MB)');
            return $this;
        }

        $this->mailer->isHTML(true);
        $this->mailer->Body = $body;
        $this->mailer->AltBody = $altBody ?: strip_tags($body);
        return $this;
    }

    /**
     * Set plain text body
     * 
     * @param string $body
     * @return self
     */
    public function setPlainBody(string $body): self
    {
        // Security: Check body size
        if (strlen($body) > self::MAX_BODY_SIZE) {
            $this->addError('Email body too large (max 5MB)');
            return $this;
        }

        $this->mailer->isHTML(false);
        $this->mailer->Body = $body;
        return $this;
    }

    /**
     * Get email template from database
     * 
     * @param string $type Template type
     * @return array ['subject' => string, 'content' => string]
     */
    public function getTemplate(string $type): array
    {
        $templates = [
            self::TEMPLATE_ORDER_SUCCESS => [
                'subject_key' => 'email_temp_subject_order_success',
                'content_key' => 'email_temp_content_order_success'
            ],
            self::TEMPLATE_ORDER_COMPLETED => [
                'subject_key' => 'email_temp_subject_order_completed',
                'content_key' => 'email_temp_content_order_completed'
            ],
            self::TEMPLATE_WARNING_LOGIN => [
                'subject_key' => 'email_temp_subject_warning_login',
                'content_key' => 'email_temp_content_warning_login'
            ],
            self::TEMPLATE_OTP_MAIL => [
                'subject_key' => 'email_temp_subject_otp_mail',
                'content_key' => 'email_temp_content_otp_mail'
            ],
            self::TEMPLATE_FORGOT_PASSWORD => [
                'subject_key' => 'email_temp_subject_forgot_password',
                'content_key' => 'email_temp_content_forgot_password'
            ],
            self::TEMPLATE_FLASH_SALE_FAVORITE => [
                'subject_key' => 'email_temp_subject_flash_sale_favorite',
                'content_key' => 'email_temp_content_flash_sale_favorite'
            ]
        ];

        if (!isset($templates[$type])) {
            return ['subject' => '', 'content' => ''];
        }

        return [
            'subject' => $this->db->site($templates[$type]['subject_key']) ?: '',
            'content' => $this->db->site($templates[$type]['content_key']) ?: ''
        ];
    }

    /**
     * Replace template variables
     * 
     * @param string $content Template content
     * @param array $variables Variables to replace ['{key}' => 'value']
     * @return string
     */
    public function parseTemplate(string $content, array $variables): string
    {
        // Default variables
        $defaultVars = [
            '{domain}' => $_SERVER['SERVER_NAME'] ?? '',
            '{title}' => $this->db->site('title') ?? '',
            '{time}' => date('Y-m-d H:i:s'),
            '{year}' => date('Y'),
            '{logo}' => base_url($this->db->site('logo_light') ?? '')
        ];

        $variables = array_merge($defaultVars, $variables);

        return str_replace(
            array_keys($variables),
            array_values($variables),
            $content
        );
    }

    /**
     * Wrap content in HTML email template
     * 
     * NOTE: Phương thức này CHỈ được sử dụng bởi cron/sending_email.php
     * để wrap nội dung email campaigns trong template HTML.
     * Các email khác (order, flash sale, v.v.) sử dụng template từ
     * Mail Template trong admin settings.
     * 
     * @param string $title Email title
     * @param string $content Email content
     * @return string
     */
    public function wrapInTemplate(string $title, string $content): string
    {
        $themeColor = $this->db->site('theme_color') ?: '#1b9ba3';
        $logo = base_url($this->db->site('logo_light') ?? '');
        $siteName = $this->db->site('title') ?? '';

        return '<!DOCTYPE html>
<html>
<head>
    <title>' . htmlspecialchars($title) . '</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; font-size: inherit !important; font-family: inherit !important; font-weight: inherit !important; line-height: inherit !important; }
        @media screen and (max-width: 480px) {
            .mobile-hide { display: none !important; }
            .mobile-center { text-align: center !important; }
        }
        div[style*="margin: 16px 0;"] { margin: 0 !important; }
    </style>
</head>
<body style="margin: 0 !important; padding: 0 !important; background-color: #eeeeee;" bgcolor="#eeeeee">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="background-color: #eeeeee;" bgcolor="#eeeeee">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;">
                    <tr>
                        <td align="center" valign="top" style="font-size:0; padding: 35px;" bgcolor="' . $themeColor . '">
                            <div style="display:inline-block; max-width:100%; min-width:100px; vertical-align:top; width:100%;">
                                <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:300px;">
                                    <tr>
                                        <td class="mobile-center" align="center">
                                            <img src="' . $logo . '" style="max-width: 200px; height: auto;">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 35px 35px 20px 35px; background-color: #ffffff;" bgcolor="#ffffff">
                            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;">
                                <tr>
                                    <td align="center" style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 400; line-height: 24px; padding-top: 25px;">
                                        <h2 style="font-size: 26px; font-weight: 800; line-height: 36px; color: #333333; margin: 0;">' . htmlspecialchars($title) . '</h2>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 400; line-height: 24px; padding-top: 10px;">
                                        <div style="font-size: 16px; font-weight: 400; line-height: 24px; color: #777777;">' . $content . '</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 20px; background-color: ' . $themeColor . ';" bgcolor="' . $themeColor . '">
                            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;">
                                <tr>
                                    <td align="center" style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 400; line-height: 24px; padding: 15px 0;">
                                        <p style="font-size: 14px; font-weight: 600; line-height: 20px; color: white; margin: 0;">' . htmlspecialchars($siteName) . '</p>
                                        <p style="font-size: 12px; color: rgba(255,255,255,0.8); margin: 5px 0 0 0;">© ' . date('Y') . ' All rights reserved.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Send email
     * 
     * @return bool
     */
    public function send(): bool
    {
        if (!$this->isEnabled()) {
            $this->addError('SMTP is not enabled');
            return false;
        }

        if (empty($this->mailer->getToAddresses())) {
            $this->addError('No recipient specified');
            return false;
        }

        try {
            // Set default from if not set
            if (empty($this->mailer->From)) {
                $this->setFrom($this->config['from_email'], $this->config['from_name']);
            }

            $this->success = $this->mailer->send();

            if ($this->success) {
                $this->logEmail('success');
            }

            return $this->success;
        } catch (Exception $e) {
            $this->addError('Send failed: ' . $e->getMessage());
            $this->logEmail('failed', $e->getMessage());
            return false;
        }
    }

    /**
     * Quick send email method (all-in-one)
     * 
     * @param string $to Recipient email
     * @param string $name Recipient name
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $fromName From name (optional)
     * @param string $attachment Attachment path (optional)
     * @return bool
     */
    public function quickSend(
        string $to,
        string $name,
        string $subject,
        string $body,
        string $fromName = '',
        string $attachment = ''
    ): bool {
        $this->reset();
        $this->setFrom($this->config['from_email'], $fromName ?: $this->config['from_name']);
        $this->addTo($to, $name);
        $this->addReplyTo($this->config['from_email'], $fromName ?: $this->config['from_name']);
        $this->setSubject($subject);
        $this->setBody($body);

        if (!empty($attachment) && file_exists($attachment)) {
            $this->addAttachment($attachment);
        }

        return $this->send();
    }

    /**
     * Send email using template
     * 
     * @param string $templateType Template type constant
     * @param string $toEmail Recipient email
     * @param string $toName Recipient name
     * @param array $variables Template variables
     * @param bool $wrapTemplate Wrap in HTML template
     * @return bool
     */
    public function sendTemplate(
        string $templateType,
        string $toEmail,
        string $toName,
        array $variables = []
    ): bool {
        $template = $this->getTemplate($templateType);

        if (empty($template['subject']) || empty($template['content'])) {
            $this->addError('Template not found or empty: ' . $templateType);
            return false;
        }

        // Add recipient name to variables
        $variables['{username}'] = $variables['{username}'] ?? $toName;

        $subject = $this->parseTemplate($template['subject'], $variables);
        $content = $this->parseTemplate($template['content'], $variables);

        return $this->quickSend($toEmail, $toName, $subject, $content);
    }

    /**
     * Send order success notification email
     * 
     * @param array $user User data
     * @param array $orders Order data array
     * @param float $totalAmount Total payment amount
     * @param float $discountAmount Discount amount
     * @param string $couponCode Coupon code used
     * @return bool
     */
    public function sendOrderSuccessEmail(
        array $user,
        array $orders,
        float $totalAmount,
        float $discountAmount = 0,
        string $couponCode = ''
    ): bool {
        // Check if user has email
        if (empty($user['email'])) {
            $this->addError('User email not found');
            return false;
        }

        // Build order details HTML
        $orderDetailsHtml = '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
        $orderDetailsHtml .= '<tr style="background-color: #f8f9fa;">
            <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">Sản phẩm</th>
            <th style="padding: 10px; border: 1px solid #dee2e6; text-align: center;">SL</th>
            <th style="padding: 10px; border: 1px solid #dee2e6; text-align: right;">Thành tiền</th>
        </tr>';

        foreach ($orders as $order) {
            $orderDetailsHtml .= '<tr>
                <td style="padding: 10px; border: 1px solid #dee2e6;">
                    <strong>' . htmlspecialchars($order['product_name']) . '</strong><br>
                    <small style="color: #666;">Gói: ' . htmlspecialchars($order['plan_name']) . '</small><br>
                    <small style="color: #666;">Mã: ' . htmlspecialchars($order['trans_id']) . '</small>
                </td>
                <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center;">' . $order['quantity'] . '</td>
                <td style="padding: 10px; border: 1px solid #dee2e6; text-align: right;">' . format_currency($order['total']) . '</td>
            </tr>';
        }

        $orderDetailsHtml .= '</table>';

        // Build summary
        $summaryHtml = '<div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px;">';

        if ($discountAmount > 0) {
            $originalTotal = $totalAmount + $discountAmount;
            $summaryHtml .= '<p style="margin: 5px 0;"><strong>Tổng tiền:</strong> ' . format_currency($originalTotal) . '</p>';
            $summaryHtml .= '<p style="margin: 5px 0; color: #28a745;"><strong>Giảm giá' . ($couponCode ? ' (' . $couponCode . ')' : '') . ':</strong> -' . format_currency($discountAmount) . '</p>';
        }

        $summaryHtml .= '<p style="margin: 5px 0; font-size: 18px;"><strong>Tổng thanh toán:</strong> <span style="color: #dc3545;">' . format_currency($totalAmount) . '</span></p>';
        $summaryHtml .= '</div>';

        // Get template variables
        $variables = [
            '{username}' => $user['username'],
            '{email}' => $user['email'],
            '{order_count}' => count($orders),
            '{order_details}' => $orderDetailsHtml,
            '{total_amount}' => format_currency($totalAmount),
            '{discount_amount}' => format_currency($discountAmount),
            '{coupon_code}' => $couponCode,
            '{summary}' => $summaryHtml,
            '{order_link}' => base_url('product-orders'),
            '{ip}' => myip(),
            '{device}' => getUserAgent()
        ];

        return $this->sendTemplate(
            self::TEMPLATE_ORDER_SUCCESS,
            $user['email'],
            $user['username'],
            $variables
        );
    }

    /**
     * Add error message
     * 
     * @param string $message
     * @return void
     */
    private function addError(string $message): void
    {
        $this->errors[] = $message;
        $this->lastError = $message;
        error_log('[SMTPMailer] ' . $message);
    }

    /**
     * Get all errors
     * 
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get last error
     * 
     * @return string
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Check if there are errors
     * 
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get success status
     * 
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Enable email logging to database
     * @var bool
     */
    private $enableLogging = false;

    /**
     * Enable or disable email logging
     * 
     * @param bool $enable
     * @return self
     */
    public function setLogging(bool $enable = true): self
    {
        $this->enableLogging = $enable;
        return $this;
    }

    /**
     * Log email activity (disabled by default to prevent errors if table doesn't exist)
     * 
     * @param string $status
     * @param string $error
     * @return void
     */
    private function logEmail(string $status, string $error = ''): void
    {
        // Skip logging if disabled
        if (!$this->enableLogging) {
            return;
        }

        try {
            // Check if table exists first to prevent fatal error
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'email_logs'");
            if (!$tableCheck || $tableCheck->num_rows === 0) {
                return;
            }

            $toAddresses = $this->mailer->getToAddresses();
            $toEmail = !empty($toAddresses[0][0]) ? $toAddresses[0][0] : '';

            // Security: Sanitize data before insert
            $this->db->insert('email_logs', [
                'to_email' => substr($toEmail, 0, 255),
                'subject' => substr($this->mailer->Subject, 0, 500),
                'status' => in_array($status, ['success', 'failed']) ? $status : 'unknown',
                'error_message' => substr($error, 0, 1000),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // Silently fail if logging fails
            error_log('[SMTPMailer] Failed to log email: ' . $e->getMessage());
        }
    }

    /**
     * Test SMTP connection
     * 
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'SMTP is not enabled in settings'
            ];
        }

        try {
            $this->mailer->SMTPDebug = SMTP::DEBUG_CONNECTION;

            if ($this->mailer->smtpConnect()) {
                $this->mailer->smtpClose();
                return [
                    'success' => true,
                    'message' => 'SMTP connection successful'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to connect to SMTP server'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ];
        } finally {
            $this->mailer->SMTPDebug = $this->debug ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
        }
    }

    /**
     * Static helper method for quick sending
     * 
     * @param string $to
     * @param string $name
     * @param string $subject
     * @param string $body
     * @return bool
     */
    public static function sendMail(string $to, string $name, string $subject, string $body): bool
    {
        $mailer = new self();
        return $mailer->quickSend($to, $name, $subject, $body);
    }
    
    // =========================================================================
    // EMAIL QUEUE METHODS - Async email sending for better performance
    // =========================================================================

    /**
     * Queue an email for later sending (non-blocking)
     * 
     * @param string $toEmail Recipient email
     * @param string $toName Recipient name  
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param int $priority Priority (1=high, 5=low)
     * @param array $metadata Additional data to store
     * @return int|false Queue ID or false on failure
     */
    public function queueEmail(
        string $toEmail,
        string $toName,
        string $subject,
        string $body,
        int $priority = 3,
        array $metadata = []
    ) {
        // Skip if SMTP is disabled
        if ($this->db->site('smtp_status') != 1) {
            return false;
        }

        // Skip if subject is empty (email sending disabled)
        if (empty(trim($subject))) {
            return false;
        }

        // Validate email
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addError('Invalid email for queue: ' . $toEmail);
            return false;
        }

        // Sanitize inputs
        $toEmail = substr($toEmail, 0, 255);
        $toName = $this->sanitizeName($toName);
        $subject = $this->sanitizeSubject($subject);

        // Check body size
        if (strlen($body) > self::MAX_BODY_SIZE) {
            $this->addError('Email body too large for queue');
            return false;
        }

        try {
            $queueId = $this->db->insert('email_queue', [
                'to_email' => $toEmail,
                'to_name' => $toName,
                'subject' => $subject,
                'body' => $body,
                'priority' => max(1, min(5, $priority)),
                'status' => 'pending',
                'attempts' => 0,
                'max_attempts' => 3,
                'metadata' => !empty($metadata) ? json_encode($metadata) : null,
                'created_at' => date('Y-m-d H:i:s'),
                'scheduled_at' => date('Y-m-d H:i:s')
            ]);

            return $queueId;
        } catch (\Exception $e) {
            $this->addError('Failed to queue email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue order success email (non-blocking)
     * 
     * @param array $user User data
     * @param array $orders Order data array
     * @param float $totalAmount Total payment amount
     * @param float $discountAmount Discount amount
     * @param string $couponCode Coupon code used
     * @return int|false Queue ID or false on failure
     */
    public function queueOrderSuccessEmail(
        array $user,
        array $orders,
        float $totalAmount,
        float $discountAmount = 0,
        string $couponCode = ''
    ) {
        // Check if user has email
        if (empty($user['email'])) {
            $this->addError('User email not found');
            return false;
        }

        // Build order details HTML
        $orderDetailsHtml = '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
        $orderDetailsHtml .= '<tr style="background-color: #f8f9fa;">
            <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">Sản phẩm</th>
            <th style="padding: 10px; border: 1px solid #dee2e6; text-align: center;">SL</th>
            <th style="padding: 10px; border: 1px solid #dee2e6; text-align: right;">Thành tiền</th>
        </tr>';

        foreach ($orders as $order) {
            $orderDetailsHtml .= '<tr>
                <td style="padding: 10px; border: 1px solid #dee2e6;">
                    <strong>' . htmlspecialchars($order['product_name']) . '</strong><br>
                    <small style="color: #666;">Gói: ' . htmlspecialchars($order['plan_name']) . '</small><br>
                    <small style="color: #666;">Mã: ' . htmlspecialchars($order['trans_id']) . '</small>
                </td>
                <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center;">' . $order['quantity'] . '</td>
                <td style="padding: 10px; border: 1px solid #dee2e6; text-align: right;">' . format_currency($order['total']) . '</td>
            </tr>';
        }

        $orderDetailsHtml .= '</table>';

        // Build summary
        $summaryHtml = '<div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px;">';

        if ($discountAmount > 0) {
            $originalTotal = $totalAmount + $discountAmount;
            $summaryHtml .= '<p style="margin: 5px 0;"><strong>Tổng tiền:</strong> ' . format_currency($originalTotal) . '</p>';
            $summaryHtml .= '<p style="margin: 5px 0; color: #28a745;"><strong>Giảm giá' . ($couponCode ? ' (' . $couponCode . ')' : '') . ':</strong> -' . format_currency($discountAmount) . '</p>';
        }

        $summaryHtml .= '<p style="margin: 5px 0; font-size: 18px;"><strong>Tổng thanh toán:</strong> <span style="color: #dc3545;">' . format_currency($totalAmount) . '</span></p>';
        $summaryHtml .= '</div>';

        // Get template
        $template = $this->getTemplate(self::TEMPLATE_ORDER_SUCCESS);

        if (empty($template['subject']) || empty($template['content'])) {
            $this->addError('Order success email template not found');
            return false;
        }

        // Build variables
        $variables = [
            '{username}' => $user['username'],
            '{email}' => $user['email'],
            '{order_count}' => count($orders),
            '{order_details}' => $orderDetailsHtml,
            '{total_amount}' => format_currency($totalAmount),
            '{discount_amount}' => format_currency($discountAmount),
            '{coupon_code}' => $couponCode,
            '{summary}' => $summaryHtml,
            '{order_link}' => base_url('product-orders'),
            '{ip}' => myip(),
            '{device}' => getUserAgent()
        ];

        $subject = $this->parseTemplate($template['subject'], $variables);
        $body = $this->parseTemplate($template['content'], $variables);

        // Queue with high priority for order emails
        return $this->queueEmail(
            $user['email'],
            $user['username'],
            $subject,
            $body,
            1, // High priority
            [
                'type' => 'order_success',
                'user_id' => $user['id'] ?? null,
                'order_count' => count($orders)
            ]
        );
    }

    /**
     * Queue order completed notification email (cho đơn hàng thủ công)
     * 
     * @param array $order Order data
     * @param array $user User data (phải có email, username)
     * @return bool|int Queue ID on success, false on failure
     */
    public function queueOrderCompletedEmail(array $order, array $user)
    {
        // Check if user has email
        if (empty($user['email'])) {
            $this->addError('User email not found');
            return false;
        }

        // Get template
        $template = $this->getTemplate(self::TEMPLATE_ORDER_COMPLETED);

        if (empty($template['subject']) || empty($template['content'])) {
            $this->addError('Order completed email template not found or disabled');
            return false;
        }

        // Build variables
        $variables = [
            '{username}' => $user['username'] ?? 'Khách hàng',
            '{email}' => $user['email'],
            '{trans_id}' => $order['trans_id'] ?? '',
            '{product_name}' => $order['product_name'] ?? '',
            '{plan_name}' => $order['plan_name'] ?? '',
            '{quantity}' => $order['quantity'] ?? 1,
            '{total_amount}' => format_currency($order['total'] ?? 0),
            '{delivery_content}' => $order['delivery_content'] ?? '',
            '{order_link}' => base_url('product-order/' . ($order['trans_id'] ?? '')),
            '{time}' => gettime()
        ];

        $subject = $this->parseTemplate($template['subject'], $variables);
        $body = $this->parseTemplate($template['content'], $variables);

        // Queue with high priority for order emails
        return $this->queueEmail(
            $user['email'],
            $user['username'],
            $subject,
            $body,
            1, // High priority
            [
                'type' => 'order_completed',
                'user_id' => $user['id'] ?? null,
                'order_id' => $order['id'] ?? null,
                'trans_id' => $order['trans_id'] ?? null
            ]
        );
    }

    /**
     * Process email queue (called by cron job)
     * 
     * @param int $limit Maximum emails to process
     * @return array Statistics ['processed' => int, 'success' => int, 'failed' => int]
     */
    public function processQueue(int $limit = 10): array
    {
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];

        if (!$this->isEnabled()) {
            return $stats;
        }

        try {
            // Get pending emails ordered by priority and creation time
            $emails = $this->db->get_list_safe(
                "SELECT * FROM `email_queue` 
                 WHERE `status` = 'pending' 
                 AND `scheduled_at` <= NOW()
                 AND `attempts` < `max_attempts`
                 ORDER BY `priority` ASC, `created_at` ASC 
                 LIMIT ?",
                [$limit]
            );

            if (empty($emails)) {
                return $stats;
            }

            foreach ($emails as $email) {
                $stats['processed']++;

                // Mark as processing
                $this->db->update('email_queue', [
                    'status' => 'processing',
                    'attempts' => $email['attempts'] + 1,
                    'last_attempt_at' => date('Y-m-d H:i:s')
                ], "`id` = ?", [$email['id']]);

                // Try to send
                $sent = $this->quickSend(
                    $email['to_email'],
                    $email['to_name'],
                    $email['subject'],
                    $email['body']
                );

                if ($sent) {
                    // Mark as sent
                    $this->db->update('email_queue', [
                        'status' => 'sent',
                        'sent_at' => date('Y-m-d H:i:s'),
                        'error_message' => null
                    ], "`id` = ?", [$email['id']]);

                    $stats['success']++;
                } else {
                    // Check if max attempts reached
                    $newAttempts = $email['attempts'] + 1;
                    $newStatus = ($newAttempts >= $email['max_attempts']) ? 'failed' : 'pending';

                    $this->db->update('email_queue', [
                        'status' => $newStatus,
                        'error_message' => $this->getLastError()
                    ], "`id` = ?", [$email['id']]);

                    if ($newStatus === 'failed') {
                        $stats['failed']++;
                    }
                }

                // Reset mailer for next email
                $this->reset();

                // Small delay to prevent overwhelming SMTP server
                usleep(100000); // 0.1 second
            }
        } catch (\Exception $e) {
            error_log('[SMTPMailer] Queue processing error: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get queue statistics
     * 
     * @return array
     */
    public function getQueueStats(): array
    {
        try {
            $stats = $this->db->get_row(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                 FROM `email_queue`"
            );

            return $stats ?: [
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'sent' => 0,
                'failed' => 0
            ];
        } catch (\Exception $e) {
            return [
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'sent' => 0,
                'failed' => 0
            ];
        }
    }

    /**
     * Clean old queue entries
     * 
     * @param int $days Days to keep
     * @return int Number of deleted entries
     */
    public function cleanQueue(int $days = 30): int
    {
        try {
            // Validate days parameter (security)
            $days = max(1, min(365, intval($days)));

            // Calculate cutoff date
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            // Use remove with safe condition
            $this->db->remove(
                'email_queue',
                "`status` IN ('sent', 'failed') AND `created_at` < ?",
                [$cutoffDate]
            );

            return $this->db->affected_rows ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
