<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Philippine Payroll Calculation Helper
 * Based on SSL IV (2023) and government deduction tables
 */

if (!function_exists('get_db_connection')) {
    /**
     * Get database connection for helper functions
     * @return PDO|null
     */
    function get_db_connection() {
        try {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbname = getenv('DB_NAME') ?: 'payrolldb';
            $username = getenv('DB_USER') ?: 'root';
            $password = getenv('DB_PASS') ?: '';
            
            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            return new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('calculate_deduction_from_db')) {
    /**
     * Calculate deduction amount from database rates
     * @param string $deduction_type Type of deduction (gsis, philhealth, pagibig, etc.)
     * @param float $salary Employee's basic salary
     * @return float Calculated deduction amount
     */
    function calculate_deduction_from_db(string $deduction_type, float $salary): float
    {
        $db = get_db_connection();
        if (!$db) {
            // Fallback to hardcoded rates if DB unavailable
            return calculate_deduction_fallback($deduction_type, $salary);
        }
        
        try {
            $query = "SELECT * FROM deduction_rates 
                      WHERE deduction_type = :type 
                      AND is_active = 1
                      AND (salary_min IS NULL OR :salary >= salary_min)
                      AND (salary_max IS NULL OR :salary <= salary_max)
                      ORDER BY salary_min DESC
                      LIMIT 1";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':type' => $deduction_type,
                ':salary' => $salary
            ]);
            
            $rate = $stmt->fetch();
            
            if (!$rate) {
                return 0.00;
            }
            
            // Calculate based on rate type
            $amount = 0;
            if ($rate['rate_type'] === 'percentage') {
                $amount = ($salary * floatval($rate['rate_value'])) / 100;
            } else {
                $amount = floatval($rate['rate_value']);
            }
            
            // Apply min/max limits
            if ($rate['min_amount'] !== null && $amount < floatval($rate['min_amount'])) {
                $amount = floatval($rate['min_amount']);
            }
            if ($rate['max_amount'] !== null && $amount > floatval($rate['max_amount'])) {
                $amount = floatval($rate['max_amount']);
            }
            
            return round($amount, 2);
            
        } catch (PDOException $e) {
            error_log("Deduction calculation error: " . $e->getMessage());
            return calculate_deduction_fallback($deduction_type, $salary);
        }
    }
}

if (!function_exists('calculate_deduction_fallback')) {
    /**
     * Fallback deduction calculation when database is unavailable
     * @param string $deduction_type
     * @param float $salary
     * @return float
     */
    function calculate_deduction_fallback(string $deduction_type, float $salary): float
    {
        switch ($deduction_type) {
            case 'gsis':
                return round($salary * 0.09, 2);
            case 'philhealth':
                $contribution = $salary * 0.02;
                return round(min($contribution, 1600), 2);
            case 'pagibig':
                $contribution = $salary * 0.02;
                return round(min($contribution, 100), 2);
            default:
                return 0.00;
        }
    }
}

if (!function_exists('calculate_daily_rate')) {
    /**
     * Calculate daily rate from monthly salary
     * Uses 22 working days per month (standard PH government)
     * @param float $monthly_salary
     * @return float
     */
    function calculate_daily_rate(float $monthly_salary): float
    {
        return $monthly_salary / 22;
    }
}

if (!function_exists('calculate_hourly_rate')) {
    /**
     * Calculate hourly rate from daily rate
     * Uses 8 hours per day
     * @param float $daily_rate
     * @return float
     */
    function calculate_hourly_rate(float $daily_rate): float
    {
        return $daily_rate / 8;
    }
}

if (!function_exists('calculate_days_worked')) {
    /**
     * Calculate days worked from attendance records
     * Also accounts for approved leaves with paid percentages
     * 
     * NOTE: Paid percentage is applied to daily rate, not monthly salary
     * E.g., Study Leave at 60% paid: Employee gets 60% of their daily rate during that leave
     * 
     * @param array $attendance_records Array of attendance with 'status' field
     * @param array $approved_leaves Array of approved leave records with paid_percentage
     * @return float
     */
    function calculate_days_worked(array $attendance_records, array $approved_leaves = []): float
    {
        $days = 0.0;
        
        foreach ($attendance_records as $record) {
            $status = $record['status'] ?? '';
            
            if (in_array($status, ['present', 'late'])) {
                $days += 1.0;
            } elseif ($status === 'half-day') {
                $days += 0.5;
            }
            // 'absent' adds 0
        }
        
        // Add approved leaves (weighted by paid percentage)
        // E.g., Study Leave at 60% paid: 5 days * 0.60 = 3.0 working days
        // This is calculated per daily rate, not monthly salary
        foreach ($approved_leaves as $leave) {
            $paid_percentage = (float)($leave['paid_percentage'] ?? 100) / 100;
            $leave_days = (float)($leave['number_of_days'] ?? 0);
            $days += ($leave_days * $paid_percentage);
        }
        
        return $days;
    }
}

if (!function_exists('calculate_gsis_contribution')) {
    /**
     * Calculate GSIS contribution using database rates
     * @param float $basic_salary Monthly basic salary
     * @return float
     */
    function calculate_gsis_contribution(float $basic_salary): float
    {
        return calculate_deduction_from_db('gsis', $basic_salary);
    }
}

if (!function_exists('calculate_philhealth_contribution')) {
    /**
     * Calculate PhilHealth contribution using database rates
     * @param float $basic_salary
     * @return float
     */
    function calculate_philhealth_contribution(float $basic_salary): float
    {
        return calculate_deduction_from_db('philhealth', $basic_salary);
    }
}

if (!function_exists('calculate_pagibig_contribution')) {
    /**
     * Calculate Pag-IBIG contribution using database rates
     * @param float $basic_salary
     * @return float
     */
    function calculate_pagibig_contribution(float $basic_salary): float
    {
        return calculate_deduction_from_db('pagibig', $basic_salary);
    }
}

if (!function_exists('calculate_withholding_tax')) {
    /**
     * Calculate withholding tax using database tax brackets
     * @param float $taxable_income Monthly taxable income
     * @return float
     */
    function calculate_withholding_tax(float $taxable_income): float
    {
        $db = get_db_connection();
        if (!$db) {
            // Fallback to hardcoded calculation
            return calculate_tax_fallback($taxable_income);
        }
        
        try {
            $query = "SELECT * FROM tax_brackets 
                      WHERE is_active = 1
                      AND :income >= income_from 
                      AND :income <= income_to
                      LIMIT 1";
            
            $stmt = $db->prepare($query);
            $stmt->execute([':income' => $taxable_income]);
            
            $bracket = $stmt->fetch();
            
            if (!$bracket) {
                return 0.00;
            }
            
            // Calculate tax: base_tax + (income - excess_over) * rate_percentage / 100
            $base_tax = floatval($bracket['base_tax']);
            $rate = floatval($bracket['rate_percentage']);
            $excess_over = floatval($bracket['excess_over']);
            
            $tax = $base_tax + (($taxable_income - $excess_over) * ($rate / 100));
            
            return round(max(0, $tax), 2);
            
        } catch (PDOException $e) {
            error_log("Tax calculation error: " . $e->getMessage());
            return calculate_tax_fallback($taxable_income);
        }
    }
}

if (!function_exists('calculate_tax_fallback')) {
    /**
     * Fallback tax calculation when database is unavailable
     * @param float $taxable_income
     * @return float
     */
    function calculate_tax_fallback(float $taxable_income): float
    {
        if ($taxable_income <= 20833) {
            return 0;
        } elseif ($taxable_income <= 33332) {
            return ($taxable_income - 20833) * 0.15;
        } elseif ($taxable_income <= 66666) {
            return 1875 + (($taxable_income - 33332) * 0.20);
        } elseif ($taxable_income <= 166666) {
            return 8541.80 + (($taxable_income - 66666) * 0.25);
        } elseif ($taxable_income <= 666666) {
            return 33541.80 + (($taxable_income - 166666) * 0.30);
        } else {
            return 183541.80 + (($taxable_income - 666666) * 0.35);
        }
    }
}

if (!function_exists('calculate_absence_deduction')) {
    /**
     * Calculate deduction for absences
     * @param float $daily_rate
     * @param float $days_absent Number of absent days
     * @return float
     */
    function calculate_absence_deduction(float $daily_rate, float $days_absent): float
    {
        return round($daily_rate * $days_absent, 2);
    }
}

if (!function_exists('calculate_late_minutes')) {
    /**
     * Calculate total late minutes from attendance records
     * @param array $attendance_records
     * @return int
     */
    function calculate_late_minutes(array $attendance_records): int
    {
        $late_minutes_total = 0;
        
        foreach ($attendance_records as $record) {
            if ($record['status'] === 'late' && !empty($record['check_in'])) {
                // Check if clock-in is after 08:00
                $check_in_time = strtotime($record['check_in']);
                $cutoff_time = strtotime(date('Y-m-d', $check_in_time) . ' 08:00:00');
                
                if ($check_in_time > $cutoff_time) {
                    $late_seconds = $check_in_time - $cutoff_time;
                    $late_minutes_total += (int)ceil($late_seconds / 60);
                }
            }
        }
        
        return $late_minutes_total;
    }
}

if (!function_exists('calculate_half_days')) {
    /**
     * Count half-day absences from attendance records
     * @param array $attendance_records
     * @return int Number of half-days
     */
    function calculate_half_days(array $attendance_records): int
    {
        $half_days = 0;
        
        foreach ($attendance_records as $record) {
            if (($record['status'] ?? '') === 'half-day') {
                $half_days++;
            }
        }
        
        return $half_days;
    }
}

if (!function_exists('calculate_undertime_minutes')) {
    /**
     * Calculate total undertime from attendance records (half-days + late arrivals)
     * Undertime = half-day absences (240 min each) + late arrivals
     * @param array $attendance_records
     * @return int Total undertime in minutes
     */
    function calculate_undertime_minutes(array $attendance_records): int
    {
        $undertime_minutes = 0;
        
        foreach ($attendance_records as $record) {
            // Half-day: 4 hours (240 minutes) of undertime
            if (($record['status'] ?? '') === 'half-day') {
                $undertime_minutes += 240;
            }
            // Late arrival: time after 08:00 cutoff counts as undertime
            elseif (($record['status'] ?? '') === 'late' && !empty($record['check_in'])) {
                $check_in_time = strtotime($record['check_in']);
                $cutoff_time = strtotime(date('Y-m-d', $check_in_time) . ' 08:00:00');
                
                if ($check_in_time > $cutoff_time) {
                    $late_seconds = $check_in_time - $cutoff_time;
                    $undertime_minutes += (int)ceil($late_seconds / 60);
                }
            }
        }
        
        return $undertime_minutes;
    }
}

if (!function_exists('calculate_payroll_for_employee')) {
    /**
     * Calculate complete payroll for an employee
     * @param array $employee Employee data with salary_grade and step_increment
     * @param float $monthly_salary From salary_grades table
     * @param array $attendance_records Attendance for the period
     * @param array $approved_leaves Approved leave records with paid_percentage for the period
     * @param array $additional Additional earnings (honorarium, overtime, etc.)
     * @return array Payroll calculation breakdown
     */
    function calculate_payroll_for_employee(
        array $employee,
        float $monthly_salary,
        array $attendance_records,
        array $approved_leaves = [],
        array $additional = []
    ): array {
        // Calculate rates
        $daily_rate = calculate_daily_rate($monthly_salary);
        $hourly_rate = calculate_hourly_rate($daily_rate);
        
        // Calculate days worked/absent (including paid leave)
        $days_worked = calculate_days_worked($attendance_records, $approved_leaves);
        
        // Calculate total working days from period (Mon-Fri only)
        $total_days = 0;
        if (!empty($additional['period_start']) && !empty($additional['period_end'])) {
            $start = new DateTime($additional['period_start']);
            $end = new DateTime($additional['period_end']);
            $current = clone $start;
            while ($current <= $end) {
                $dayOfWeek = (int)$current->format('N'); // 1=Mon, 7=Sun
                if ($dayOfWeek <= 5) { // Mon-Fri
                    $total_days++;
                }
                $current->modify('+1 day');
            }
        } else {
            // Fallback: count attendance records (old behavior)
            $total_days = count($attendance_records);
        }
        
        $days_absent = max(0, $total_days - $days_worked);
        
        // Per GSIS IRR Section 4.1.5: If employee worked 0 days, salary and contributions are ₱0
        if ($days_worked <= 0) {
            // No work = no compensation = no contributions
            return [
                'basic_salary' => 0.00,
                'daily_rate' => round($daily_rate, 2),
                'hourly_rate' => round($hourly_rate, 2),
                'days_worked' => 0,
                'days_absent' => $days_absent,
                'late_minutes_total' => 0,
                'allowance_rla' => 0.00,
                'honorarium' => 0.00,
                'overtime_pay' => 0.00,
                'gross_pay' => 0.00,
                'absence_deduction' => 0.00,
                'deduction_gsis' => 0.00,
                'deduction_philhealth' => 0.00,
                'deduction_pagibig' => 0.00,
                'deduction_tax' => 0.00,
                'other_deductions' => 0.00,
                'total_deductions' => 0.00,
                'net_salary' => 0.00
            ];
        }
        
        // Calculate absence deduction FIRST (based on days_absent count)
        $absence_deduction = calculate_absence_deduction($daily_rate, $days_absent);
        
        // Basic salary for period = monthly salary - absence deduction
        $basic_salary = $monthly_salary - $absence_deduction;
        
        // Additional earnings (use isset to allow 0 values)
        $allowance_rla = isset($additional['allowance_rla']) ? floatval($additional['allowance_rla']) : 1500.00;
        $honorarium = isset($additional['honorarium']) ? floatval($additional['honorarium']) : 0.00;
        $overtime_pay = isset($additional['overtime_pay']) ? floatval($additional['overtime_pay']) : 0.00;
        
        // Gross pay
        $gross_pay = $basic_salary + $allowance_rla + $honorarium + $overtime_pay;
        
        // Deductions are calculated on MONTHLY_SALARY (not reduced basic_salary)
        // because government contributions are based on your salary grade, not attendance
        $deduction_gsis = calculate_gsis_contribution($monthly_salary);
        $deduction_philhealth = calculate_philhealth_contribution($monthly_salary);
        $deduction_pagibig = calculate_pagibig_contribution($monthly_salary);
        
        // Taxable income = gross - mandatory contributions
        $taxable_income = $gross_pay - $deduction_gsis - $deduction_philhealth - $deduction_pagibig;
        $deduction_tax = calculate_withholding_tax($taxable_income);
        
        $other_deductions = $additional['other_deductions'] ?? 0.00;
        
        // Count late minutes
        $late_minutes_total = calculate_late_minutes($attendance_records);
        
        // Count half-days and undertime
        $days_half_day = calculate_half_days($attendance_records);
        $undertime_minutes = calculate_undertime_minutes($attendance_records);
        
        // Calculate undertime deduction (half-days + late arrivals)
        $undertime_deduction = 0;
        if ($undertime_minutes > 0) {
            $undertime_deduction = ($undertime_minutes / 480) * ($basic_salary / 22);
        }
        
        // Total deductions (absence already deducted from basic_salary, so don't double-count)
        $total_deductions = $deduction_gsis + $deduction_philhealth + 
                           $deduction_pagibig + $deduction_tax + $undertime_deduction + $other_deductions;
        
        // Net pay
        $net_salary = $gross_pay - $total_deductions;
        
        return [
            'basic_salary' => round($basic_salary, 2),
            'daily_rate' => round($daily_rate, 2),
            'hourly_rate' => round($hourly_rate, 2),
            'days_worked' => $days_worked,
            'days_absent' => $days_absent,
            'days_half_day' => $days_half_day,
            'undertime_minutes' => $undertime_minutes,
            'late_minutes_total' => $late_minutes_total,
            'allowance_rla' => round($allowance_rla, 2),
            'honorarium' => round($honorarium, 2),
            'overtime_pay' => round($overtime_pay, 2),
            'gross_pay' => round($gross_pay, 2),
            'absence_deduction' => round($absence_deduction, 2),
            'deduction_gsis' => round($deduction_gsis, 2),
            'deduction_philhealth' => round($deduction_philhealth, 2),
            'deduction_pagibig' => round($deduction_pagibig, 2),
            'deduction_tax' => round($deduction_tax, 2),
            'other_deductions' => round($other_deductions, 2),
            'total_deductions' => round($total_deductions, 2),
            'net_salary' => round($net_salary, 2)
        ];
    }
}
