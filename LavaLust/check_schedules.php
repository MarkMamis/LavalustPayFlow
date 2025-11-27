<?php
// Simple script to check and fix schedule day_of_week

try {
    $pdo = new PDO('mysql:host=localhost;dbname=payrolldb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== Current schedules for IDs 14, 15 ===\n";
    $stmt = $pdo->query('SELECT id, day_of_week, start_time, end_time, subject_id, employee_id, section_id FROM class_schedules WHERE id IN (14, 15) ORDER BY id');
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        echo "ID {$row['id']}: {$row['day_of_week']} {$row['start_time']}-{$row['end_time']} (subj: {$row['subject_id']}, emp: {$row['employee_id']}, sec: {$row['section_id']})\n";
    }

    echo "\n=== Checking what these should be ===\n";
    echo "ID 14 (ITC 112 BSIT-1F1 13:00-15:00): Should be TUESDAY\n";
    echo "ID 15 (ITP 223 BSIT-2F4 13:00-16:00): Should be MONDAY\n";

    echo "\n=== Fixing ID 14 to Tuesday ===\n";
    $updateStmt = $pdo->prepare('UPDATE class_schedules SET day_of_week = ? WHERE id = ?');
    $updateStmt->execute(['Tuesday', 14]);
    echo "Updated ID 14 to Tuesday\n";

    echo "\n=== Verifying the fix ===\n";
    $stmt = $pdo->query('SELECT id, day_of_week, start_time, end_time FROM class_schedules WHERE id IN (14, 15) ORDER BY id');
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        echo "ID {$row['id']}: {$row['day_of_week']} {$row['start_time']}-{$row['end_time']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
