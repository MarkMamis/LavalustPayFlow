<?php
/**
 * Leave Management Schema Migration Script
 * Run this script to create leave tables in the database
 */

// Database connection parameters
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'payrolldb';

// Connect to database
$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to database successfully.\n\n";

// SQL statements to execute
$statements = [
    // 1. Create leave_types table
    "CREATE TABLE IF NOT EXISTS `leave_types` (
        `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `code` varchar(20) NOT NULL UNIQUE,
        `name` varchar(100) NOT NULL,
        `description` text,
        `annual_credits` decimal(5,2) DEFAULT NULL COMMENT 'Annual leave credits (in days). NULL = unlimited',
        `paid_percentage` tinyint UNSIGNED DEFAULT '100' COMMENT 'Percentage of salary paid during leave',
        `requires_approval` boolean DEFAULT TRUE,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 2. Create employee_leave_balance table
    "CREATE TABLE IF NOT EXISTS `employee_leave_balance` (
        `id` int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `employee_id` int UNSIGNED NOT NULL,
        `leave_type_id` tinyint UNSIGNED NOT NULL,
        `year` smallint UNSIGNED NOT NULL,
        `opening_balance` decimal(5,2) DEFAULT '0.00',
        `used_balance` decimal(5,2) DEFAULT '0.00',
        `closing_balance` decimal(5,2) DEFAULT '0.00',
        `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `ux_employee_leave_year` (`employee_id`, `leave_type_id`, `year`),
        FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 3. Create leave_requests table
    "CREATE TABLE IF NOT EXISTS `leave_requests` (
        `id` int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `employee_id` int UNSIGNED NOT NULL,
        `leave_type_id` tinyint UNSIGNED NOT NULL,
        `start_date` date NOT NULL,
        `end_date` date NOT NULL,
        `number_of_days` decimal(5,2) NOT NULL,
        `reason` text,
        `status` enum('draft','submitted','approved','rejected','cancelled') DEFAULT 'draft',
        `approved_by` int UNSIGNED DEFAULT NULL,
        `approved_at` datetime DEFAULT NULL,
        `notes` text,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE RESTRICT,
        FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 4. Add leave_request_id to attendance table if not exists
    "ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `leave_request_id` int UNSIGNED DEFAULT NULL",

    // 5. Add foreign key if not exists
    "ALTER TABLE `attendance` ADD CONSTRAINT `fk_attendance_leave_requests` 
        FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`) ON DELETE SET NULL"
];

// Execute each statement
$executed = 0;
$errors = 0;

foreach ($statements as $index => $sql) {
    echo "Executing statement " . ($index + 1) . "...\n";
    
    if ($conn->query($sql)) {
        echo "✓ Success\n\n";
        $executed++;
    } else {
        // Check if error is about existing constraint (which is OK)
        if (strpos($conn->error, 'Duplicate') !== false || 
            strpos($conn->error, 'already exists') !== false ||
            strpos($conn->error, 'FOREIGN KEY constraint already exists') !== false) {
            echo "⚠ Already exists (OK)\n\n";
            $executed++;
        } else {
            echo "✗ Error: " . $conn->error . "\n\n";
            $errors++;
        }
    }
}

// Insert leave types if they don't exist
echo "Checking and inserting leave types...\n";
$checkTypes = $conn->query("SELECT COUNT(*) as count FROM leave_types");
$result = $checkTypes->fetch_assoc();

if ($result['count'] == 0) {
    $insertTypes = "INSERT INTO `leave_types` (`code`, `name`, `description`, `annual_credits`, `paid_percentage`, `requires_approval`) VALUES
        ('VL', 'Vacation Leave', 'Annual vacation leave (Magna Carta)', 30.00, 100, TRUE),
        ('SL', 'Sick Leave', 'Leave for illness/medical reasons', 15.00, 100, TRUE),
        ('ML', 'Maternity Leave', 'Maternity leave - 60 days', 60.00, 100, TRUE),
        ('PL', 'Paternity Leave', 'Paternity leave - 7 days', 7.00, 100, TRUE),
        ('STL', 'Study Leave', 'Study leave (max 1 school year, 60% pay)', 365.00, 60, TRUE),
        ('EL', 'Emergency Leave', 'Emergency/compassionate leave', 5.00, 100, TRUE),
        ('UL', 'Unpaid Leave', 'Unpaid leave (special requests)', NULL, 0, TRUE)";
    
    if ($conn->query($insertTypes)) {
        echo "✓ Leave types inserted successfully\n";
        $executed++;
    } else {
        echo "✗ Error inserting leave types: " . $conn->error . "\n";
        $errors++;
    }
} else {
    echo "✓ Leave types already exist (" . $result['count'] . " types)\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Migration Summary:\n";
echo "Executed: $executed\n";
echo "Errors: $errors\n";
echo str_repeat("=", 50) . "\n";

if ($errors === 0) {
    echo "\n✓ Leave management schema created successfully!\n";
    echo "Tables created:\n";
    echo "  - leave_types\n";
    echo "  - employee_leave_balance\n";
    echo "  - leave_requests\n";
    echo "\nYou can now use the leave management system.\n";
} else {
    echo "\n⚠ Some errors occurred during migration. Please check the details above.\n";
}

$conn->close();
?>
