<?php
/**
 * Email System Test Script
 * 
 * This script tests the email functionality with the updated helpers
 * Run from browser: http://localhost/test_email.php
 */

define('PREVENT_DIRECT_ACCESS', TRUE);
define('ROOT_DIR',  __DIR__ . DIRECTORY_SEPARATOR);
define('SYSTEM_DIR', ROOT_DIR . 'scheme' . DIRECTORY_SEPARATOR);
define('APP_DIR', ROOT_DIR . 'app' . DIRECTORY_SEPARATOR);
define('PUBLIC_DIR', 'public');

// Load Composer Autoloader
require_once APP_DIR . 'vendor/autoload.php';

// Load Environment Variables
if (file_exists(ROOT_DIR . '.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(ROOT_DIR);
    $dotenv->load();
}

// Load helpers
require_once APP_DIR . 'helpers/mailer_helper.php';
require_once APP_DIR . 'helpers/email_template_helper.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email System Test - PayFlow HR</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e4c8c 0%, #2563a8 100%);
            padding: 40px 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e4e8;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #1e4c8c;
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        .btn {
            background: linear-gradient(135deg, #1e4c8c 0%, #2563a8 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn:active {
            transform: translateY(0);
        }
        .result {
            margin-top: 30px;
            padding: 20px;
            border-radius: 8px;
            display: none;
        }
        .result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            display: block;
        }
        .result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            display: block;
        }
        .config-info {
            background: #f8f9fa;
            border-left: 4px solid #1e4c8c;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        .config-info h3 {
            margin-bottom: 10px;
            color: #1e4c8c;
            font-size: 16px;
        }
        .config-info p {
            margin: 5px 0;
            font-size: 14px;
            color: #666;
        }
        .templates {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .template-card {
            padding: 15px;
            border: 2px solid #e1e4e8;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        .template-card:hover {
            border-color: #1e4c8c;
            background: #f8f9ff;
        }
        .template-card.active {
            border-color: #1e4c8c;
            background: #f8f9ff;
        }
        .template-card h4 {
            margin-bottom: 5px;
            color: #333;
        }
        .template-card p {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Email System Test</h1>
        <p class="subtitle">Test the PayFlow HR email functionality</p>

        <div class="config-info">
            <h3>Current Configuration</h3>
            <p><strong>SMTP Host:</strong> <?php echo $_ENV['SMTP_HOST'] ?? 'Not configured'; ?></p>
            <p><strong>SMTP Username:</strong> <?php echo $_ENV['SMTP_USERNAME'] ?? 'Not configured'; ?></p>
            <p><strong>Port:</strong> <?php echo $_ENV['PORT'] ?? 'Not configured'; ?></p>
            <p><strong>App Name:</strong> <?php echo $_ENV['APP_NAME'] ?? 'Not configured'; ?></p>
            <p><strong>App URL:</strong> <?php echo $_ENV['APP_URL'] ?? 'Not configured'; ?></p>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label>Select Email Template</label>
                <div class="templates">
                    <div class="template-card active" onclick="selectTemplate('welcome')">
                        <h4>Welcome</h4>
                        <p>New account welcome</p>
                    </div>
                    <div class="template-card" onclick="selectTemplate('reset')">
                        <h4>Password Reset</h4>
                        <p>Reset password link</p>
                    </div>
                    <div class="template-card" onclick="selectTemplate('payroll')">
                        <h4>Payroll</h4>
                        <p>Payroll notification</p>
                    </div>
                </div>
                <input type="hidden" name="template" id="template" value="welcome">
            </div>

            <div class="form-group">
                <label for="recipient_email">Recipient Email *</label>
                <input type="email" id="recipient_email" name="recipient_email" required 
                       placeholder="user@example.com">
            </div>

            <div class="form-group">
                <label for="recipient_name">Recipient Name *</label>
                <input type="text" id="recipient_name" name="recipient_name" required 
                       placeholder="John Doe">
            </div>

            <div id="password_field" class="form-group">
                <label for="password">Password</label>
                <input type="text" id="password" name="password" 
                       value="demo123" placeholder="demo123">
            </div>

            <div id="role_field" class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="employee">Employee</option>
                    <option value="hr">HR</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <button type="submit" name="send_test" class="btn">Send Test Email</button>
        </form>

        <?php
        if (isset($_POST['send_test'])) {
            $recipientEmail = $_POST['recipient_email'] ?? '';
            $recipientName = $_POST['recipient_name'] ?? '';
            $template = $_POST['template'] ?? 'welcome';
            $password = $_POST['password'] ?? 'demo123';
            $role = $_POST['role'] ?? 'employee';

            if (empty($recipientEmail) || empty($recipientName)) {
                echo '<div class="result error">
                    <strong>Error:</strong> Please provide recipient email and name.
                </div>';
            } else {
                try {
                    // Generate email body based on template
                    switch ($template) {
                        case 'welcome':
                            $emailBody = email_template_welcome($recipientName, $recipientEmail, $password, $role);
                            $subject = 'Welcome to PayFlow HR System';
                            break;
                        case 'reset':
                            $resetLink = ($_ENV['APP_URL'] ?? 'http://localhost:5173') . '/reset-password?token=sample123';
                            $emailBody = email_template_password_reset($recipientName, $resetLink);
                            $subject = 'Password Reset Request';
                            break;
                        case 'payroll':
                            $emailBody = email_template_payroll_notification($recipientName, 'November 2025', 35000.00);
                            $subject = 'Payroll Notification';
                            break;
                        default:
                            $emailBody = email_template_welcome($recipientName, $recipientEmail, $password, $role);
                            $subject = 'Welcome to PayFlow HR System';
                    }

                    // Send email
                    $result = send_email($recipientEmail, $subject, $emailBody, $recipientName);

                    if ($result['success']) {
                        echo '<div class="result success">
                            <strong>Success!</strong><br>
                            Email sent successfully to ' . htmlspecialchars($recipientEmail) . '<br>
                            <small>' . htmlspecialchars($result['message']) . '</small>
                        </div>';
                    } else {
                        echo '<div class="result error">
                            <strong>Error:</strong><br>
                            ' . htmlspecialchars($result['message']) . '
                        </div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="result error">
                        <strong>Exception:</strong><br>
                        ' . htmlspecialchars($e->getMessage()) . '
                    </div>';
                }
            }
        }
        ?>
    </div>

    <script>
        function selectTemplate(template) {
            // Update active state
            document.querySelectorAll('.template-card').forEach(card => {
                card.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Update hidden input
            document.getElementById('template').value = template;
            
            // Show/hide fields based on template
            const passwordField = document.getElementById('password_field');
            const roleField = document.getElementById('role_field');
            
            if (template === 'welcome') {
                passwordField.style.display = 'block';
                roleField.style.display = 'block';
            } else {
                passwordField.style.display = 'none';
                roleField.style.display = 'none';
            }
        }
    </script>
</body>
</html>
