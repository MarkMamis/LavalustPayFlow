<?php
// Add missing columns to payroll_records table
$conn = new mysqli('localhost', 'root', '', 'payrolldb');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$results = array();

// Add days_half_day column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM payroll_records LIKE 'days_half_day'");
if ($result && $result->num_rows === 0) {
    if ($conn->query('ALTER TABLE payroll_records ADD COLUMN days_half_day decimal(4,2) DEFAULT 0.00 AFTER days_absent')) {
        $results[] = "✓ Added days_half_day column";
    } else {
        $results[] = "✗ Failed to add days_half_day: " . $conn->error;
    }
} else {
    $results[] = "✓ days_half_day column already exists";
}

// Add undertime_minutes column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM payroll_records LIKE 'undertime_minutes'");
if ($result && $result->num_rows === 0) {
    if ($conn->query('ALTER TABLE payroll_records ADD COLUMN undertime_minutes int DEFAULT 0 AFTER days_half_day')) {
        $results[] = "✓ Added undertime_minutes column";
    } else {
        $results[] = "✗ Failed to add undertime_minutes: " . $conn->error;
    }
} else {
    $results[] = "✓ undertime_minutes column already exists";
}

foreach ($results as $msg) {
    echo $msg . "\n";
}

$conn->close();
?>
