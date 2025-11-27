<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Helper: email_template_helper.php
 * 
 * Email template generator for PayFlow HR System
 */

/**
 * Generate email HTML template wrapper
 * 
 * @param string $content Main content HTML
 * @param string $title Email title
 * @return string Complete HTML email
 */
function email_template_wrapper($content, $title = 'PayFlow HR System')
{
    $appName = $_ENV['APP_NAME'] ?? 'PayFlow HR System';
    $appUrl = $_ENV['APP_URL'] ?? 'http://localhost:5173';
    $currentYear = date('Y');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .email-header {
            background: linear-gradient(135deg, #1e4c8c 0%, #2563a8 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .email-logo {
            font-size: 28px;
            font-weight: bold;
            /* Use PayFlow accent green for better contrast on blue gradient */
            color: #a3e635;
            text-decoration: none;
            display: inline-block;
        }
        .email-body {
            padding: 40px 30px;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #1e4c8c 0%, #2563a8 100%);
            color: #a3e635 !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            margin: 20px 0;
        }
        .button:hover {
            opacity: 0.9;
        }
        h1 {
            color: #333333;
            font-size: 24px;
            margin-bottom: 20px;
        }
        h2 {
            color: #1e4c8c;
            font-size: 20px;
            margin: 20px 0 10px 0;
        }
        p {
            margin-bottom: 15px;
            color: #555555;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #1e4c8c;
            padding: 15px;
            margin: 20px 0;
        }
        .credential-box {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .credential-item {
            margin: 10px 0;
        }
        .credential-label {
            font-weight: bold;
            color: #333333;
        }
        .credential-value {
            color: #1e4c8c;
            font-family: monospace;
            font-size: 14px;
        }
        ul {
            margin: 15px 0;
            padding-left: 30px;
        }
        li {
            margin: 8px 0;
            color: #555555;
        }
        hr {
            border: none;
            border-top: 1px solid #e9ecef;
            margin: 20px 0;
        }
        .social-links {
            margin: 15px 0;
        }
        .social-links a {
            color: #a3e635;
            text-decoration: none;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <a href="{$appUrl}" class="email-logo" style="color: #a3e635 !important; text-decoration: none !important; display: inline-block;">PayFlow HR</a>
        </div>
        <div class="email-body">
            {$content}
        </div>
        <div class="email-footer">
            <p>
                <strong>{$appName}</strong><br>
                BSIT Department Faculty Management System<br>
                © {$currentYear} All rights reserved.
            </p>
            <p>
                <a href="{$appUrl}" style="color: #a3e635; text-decoration: none;">Visit Dashboard</a> | 
                <a href="{$appUrl}/help" style="color: #a3e635; text-decoration: none;">Help Center</a>
            </p>
            <p style="font-size: 11px; color: #999999; margin-top: 15px;">
                This is an automated message from the PayFlow HR System.<br>
                Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Welcome email template for new accounts
 * 
 * @param string $name User's full name
 * @param string $email User's email
 * @param string $password Temporary password
 * @param string $role User's role (admin, hr, employee)
 * @return string HTML email content
 */
function email_template_welcome($name, $email, $password, $role = 'employee')
{
    $appUrl = $_ENV['APP_URL'] ?? 'http://localhost:5173';
    $loginUrl = $appUrl . '/auth';
    
    $roleLabel = ucfirst($role);
    $roleDescription = '';
    
    switch ($role) {
        case 'admin':
            $roleDescription = 'You have full system access including department management, user management, and all HR functions.';
            break;
        case 'hr':
            $roleDescription = 'You can manage employees, attendance, payroll, schedules, and generate reports.';
            break;
        case 'employee':
            $roleDescription = 'You can view your schedule, clock in/out for attendance, and check your payroll information.';
            break;
        default:
            $roleDescription = 'You can access the system with your assigned permissions.';
    }
    
    $content = <<<HTML
<h1>Welcome to PayFlow HR System!</h1>

<p>Hello <strong>{$name}</strong>,</p>

<p>Your account has been successfully created in the PayFlow HR System. We're excited to have you on board!</p>

<div class="info-box">
    <p><strong>Account Type:</strong> {$roleLabel}</p>
    <p>{$roleDescription}</p>
</div>

<h2>Your Login Credentials</h2>

<div class="credential-box">
    <div class="credential-item">
        <span class="credential-label">Email:</span><br>
        <span class="credential-value">{$email}</span>
    </div>
    <div class="credential-item">
        <span class="credential-label">Temporary Password:</span><br>
        <span class="credential-value">{$password}</span>
    </div>
</div>

<p style="text-align: center;">
    <a href="{$loginUrl}" class="button">Login to Your Account</a>
</p>

<h2>Security Tips</h2>
<ul>
    <li>Change your password immediately after your first login</li>
    <li>Use a strong password with at least 8 characters</li>
    <li>Never share your credentials with anyone</li>
    <li>Log out when you're done using the system</li>
</ul>

<h2>What You Can Do</h2>
HTML;

    if ($role === 'employee') {
        $content .= <<<HTML
<ul>
    <li><strong>Dashboard:</strong> View your work statistics and recent activity</li>
    <li><strong>My Schedule:</strong> Check your teaching schedule and class assignments</li>
    <li><strong>Attendance:</strong> Clock in/out for your classes</li>
    <li><strong>Payroll:</strong> View your salary information and payment history</li>
</ul>
HTML;
    } elseif ($role === 'hr') {
        $content .= <<<HTML
<ul>
    <li><strong>Employee Management:</strong> Add, edit, and manage employee records</li>
    <li><strong>Attendance Tracking:</strong> Monitor and manage attendance records</li>
    <li><strong>Payroll Processing:</strong> Calculate and process employee salaries</li>
    <li><strong>Schedule Management:</strong> Organize class schedules and room assignments</li>
    <li><strong>Reports:</strong> Generate various HR and payroll reports</li>
</ul>
HTML;
    } elseif ($role === 'admin') {
        $content .= <<<HTML
<ul>
    <li><strong>Full System Access:</strong> Manage all aspects of the HR system</li>
    <li><strong>Department Management:</strong> Create and organize departments</li>
    <li><strong>User Management:</strong> Create and manage user accounts</li>
    <li><strong>Configuration:</strong> Set system settings and preferences</li>
    <li><strong>All HR Functions:</strong> Complete access to employee, attendance, and payroll management</li>
</ul>
HTML;
    }

    $content .= <<<HTML

<hr>

<p>If you have any questions or need assistance, please contact your system administrator or HR department.</p>

<p>Thank you for being part of our team!</p>

<p>
    <strong>Best regards,</strong><br>
    <strong>PayFlow HR Team</strong>
</p>
HTML;

    return email_template_wrapper($content, 'Welcome to PayFlow HR System');
}

/**
 * Password reset email template
 * 
 * @param string $name User's name
 * @param string $resetLink Password reset link
 * @return string HTML email content
 */
function email_template_password_reset($name, $resetLink)
{
    $content = <<<HTML
<h1>Password Reset Request</h1>

<p>Hello <strong>{$name}</strong>,</p>

<p>We received a request to reset your password for your PayFlow HR System account.</p>

<p style="text-align: center;">
    <a href="{$resetLink}" class="button">Reset Your Password</a>
</p>

<div class="info-box">
    <p><strong>This link will expire in 1 hour.</strong></p>
    <p>If the button above doesn't work, copy and paste this link into your browser:</p>
    <p style="word-break: break-all;"><a href="{$resetLink}" style="color: #a3e635; text-decoration: none;">{$resetLink}</a></p>
</div>

<p>If you didn't request a password reset, please ignore this email or contact your system administrator if you have concerns.</p>

<p>
    <strong>Best regards,</strong><br>
    <strong>PayFlow HR Team</strong>
</p>
HTML;

    return email_template_wrapper($content, 'Password Reset Request');
}

/**
 * Account activation email template
 * 
 * @param string $name User's name
 * @param string $activationLink Activation link
 * @return string HTML email content
 */
function email_template_account_activation($name, $activationLink)
{
    $content = <<<HTML
<h1>Activate Your Account</h1>

<p>Hello <strong>{$name}</strong>,</p>

<p>Thank you for registering with PayFlow HR System. Please activate your account by clicking the button below:</p>

<p style="text-align: center;">
    <a href="{$activationLink}" class="button">Activate Account</a>
</p>

<div class="info-box">
    <p>If the button above doesn't work, copy and paste this link into your browser:</p>
    <p style="word-break: break-all;"><a href="{$activationLink}" style="color: #a3e635; text-decoration: none;">{$activationLink}</a></p>
</div>

<p>Once activated, you'll be able to log in and start using the system.</p>

<p>
    <strong>Best regards,</strong><br>
    <strong>PayFlow HR Team</strong>
</p>
HTML;

    return email_template_wrapper($content, 'Activate Your Account');
}

/**
 * Payroll notification email template
 * 
 * @param string $name Employee name
 * @param string $period Payroll period
 * @param float $amount Net pay amount
 * @return string HTML email content
 */
function email_template_payroll_notification($name, $period, $amount)
{
    $formattedAmount = 'PHP ' . number_format($amount, 2);
    
    $content = <<<HTML
<h1>Payroll Notification</h1>

<p>Hello <strong>{$name}</strong>,</p>

<p>Your payroll for <strong>{$period}</strong> has been processed.</p>

<div class="credential-box">
    <div class="credential-item">
        <span class="credential-label">Net Pay:</span><br>
        <span class="credential-value" style="font-size: 24px; color: #28a745;">{$formattedAmount}</span>
    </div>
</div>

<p style="text-align: center;">
    <a href="{$_ENV['APP_URL']}/employee/payroll" class="button">View Payroll Details</a>
</p>

<p>Log in to your account to view the complete payroll breakdown including deductions and allowances.</p>

<p>
    <strong>Best regards,</strong><br>
    <strong>PayFlow HR Team</strong>
</p>
HTML;

    return email_template_wrapper($content, 'Payroll Notification');
}

/**
 * Attendance reminder email template
 * 
 * @param string $name Employee name
 * @param string $date Date
 * @param array $classes Array of class schedules
 * @return string HTML email content
 */
function email_template_attendance_reminder($name, $date, $classes)
{
    $classList = '';
    foreach ($classes as $class) {
        $classList .= "<li>{$class['time']} - {$class['subject']} ({$class['section']}) at {$class['room']}</li>";
    }
    
    $content = <<<HTML
<h1>Attendance Reminder</h1>

<p>Hello <strong>{$name}</strong>,</p>

<p>This is a reminder of your classes scheduled for <strong>{$date}</strong>:</p>

<ul>
    {$classList}
</ul>

<p>Please remember to clock in and out for each class session.</p>

<p style="text-align: center;">
    <a href="{$_ENV['APP_URL']}/employee/attendance" class="button">View Attendance</a>
</p>

<p>
    <strong>Best regards,</strong><br>
    <strong>PayFlow HR Team</strong>
</p>
HTML;

    return email_template_wrapper($content, 'Attendance Reminder');
}
