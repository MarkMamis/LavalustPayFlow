<?php
/**
 * Script to initialize leave balances for all existing employees
 * Run this once to backfill leave balance data for employees hired before leave system was implemented
 */

// Load LavaLust framework
require_once __DIR__ . '/index.php';

// Get database connection from config
$db = new Database();

try {
    echo "Initializing leave balances for all employees...\n";
    
    // Get all employees
    $sql = "SELECT id FROM employees ORDER BY id";
    $stmt = $db->raw($sql);
    $employees = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    if (empty($employees)) {
        echo "No employees found.\n";
        exit;
    }
    
    echo "Found " . count($employees) . " employees.\n";
    
    // Get all leave types
    $sql = "SELECT * FROM leave_types ORDER BY id";
    $stmt = $db->raw($sql);
    $leaveTypes = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    if (empty($leaveTypes)) {
        echo "No leave types found. Please ensure leave_types table is populated first.\n";
        exit;
    }
    
    echo "Found " . count($leaveTypes) . " leave types.\n\n";
    
    $currentYear = date('Y');
    $totalInitialized = 0;
    $totalSkipped = 0;
    
    foreach ($employees as $employee) {
        $empId = $employee['id'];
        $initialized = false;
        
        foreach ($leaveTypes as $type) {
            $typeId = $type['id'];
            $year = $currentYear;
            
            // Check if balance already exists
            $checkSql = "SELECT id FROM employee_leave_balance WHERE employee_id = ? AND leave_type_id = ? AND year = ?";
            $checkStmt = $db->raw($checkSql, [$empId, $typeId, $year]);
            $existing = $checkStmt ? $checkStmt->fetch(PDO::FETCH_ASSOC) : null;
            
            if (!$existing) {
                // Create balance record
                $insertSql = "INSERT INTO employee_leave_balance (employee_id, leave_type_id, year, opening_balance, used_balance, closing_balance, created_at) 
                             VALUES (?, ?, ?, ?, 0, ?, NOW())";
                $credits = $type['annual_credits'] ?? 0;
                $db->raw($insertSql, [$empId, $typeId, $year, $credits, $credits]);
                $initialized = true;
            }
        }
        
        if ($initialized) {
            echo "✓ Employee ID {$empId}: Balances initialized\n";
            $totalInitialized++;
        } else {
            echo "- Employee ID {$empId}: Already has balances\n";
            $totalSkipped++;
        }
    }
    
    echo "\n========== Summary ==========\n";
    echo "Total employees processed: " . count($employees) . "\n";
    echo "New balances initialized: {$totalInitialized}\n";
    echo "Already had balances: {$totalSkipped}\n";
    echo "\nLeave balance initialization complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
