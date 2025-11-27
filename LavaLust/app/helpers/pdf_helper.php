<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generate and stream a PDF for weekly schedule
 * 
 * @param array $data Contains teacher info and schedules
 * @param string $filename PDF filename
 * @param bool $download Force download (true) or inline view (false)
 * @return void
 */
function generate_schedule_pdf($data, $filename = 'schedule.pdf', $download = true)
{
    // Configure Dompdf
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isFontSubsettingEnabled', true);
    $options->set('defaultFont', 'Arial');
    
    $dompdf = new Dompdf($options);
    
    // Generate HTML
    $html = generate_weekly_schedule_html($data);
    
    // Load HTML and render
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    // Stream to browser
    $dompdf->stream($filename, ['Attachment' => $download]);
}

/**
 * Generate HTML for weekly schedule PDF
 * 
 * @param array $data Contains teacher info and schedules
 * @return string HTML content for PDF
 */
function generate_weekly_schedule_html($data)
{
    $teacherName = $data['teacher_name'] ?? 'Teacher';
    $schedules = $data['schedules'] ?? [];
    $generatedDate = date('M d, Y');
    $schoolYear = $data['school_year'] ?? date('Y') . '-' . (date('Y') + 1);
    $semester = $data['semester'] ?? '1st Semester';

    // Define days and time slots
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $timeSlots = ['07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];

    // Group schedules by day
    $schedulesByDay = [];
    foreach ($days as $day) {
        $schedulesByDay[$day] = [];
    }

    foreach ($schedules as $schedule) {
        $day = $schedule['day_of_week'] ?? '';
        if (isset($schedulesByDay[$day])) {
            $schedulesByDay[$day][] = $schedule;
        }
    }

    // Format time to 12-hour format
    $formatTime = function($time) {
        return date('g:i A', strtotime($time));
    };

    // Function to calculate row span
    $getRowSpan = function($schedule) {
        $start = strtotime($schedule['start_time']);
        $end = strtotime($schedule['end_time']);
        $hours = ceil(($end - $start) / 3600);
        return $hours;
    };

    // Build HTML
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Weekly Schedule - ' . htmlspecialchars($teacherName) . '</title>
    <style>
        @page {
            margin: 6mm 6mm;
            size: A4 landscape;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1;
            background: white;
        }
        
        .container {
            padding: 3px;
        }
        
        .header-section {
            margin-bottom: 3px;
        }
        
        .title {
            font-size: 12pt;
            font-weight: 700;
            color: #000;
            margin-bottom: 1px;
        }
        
        .subtitle {
            font-size: 9pt;
            color: #000;
            font-weight: 500;
            margin-bottom: 1px;
        }
        
        .meta-info {
            font-size: 7pt;
            color: #000;
            margin-bottom: 2px;
        }
        
        .meta-info span {
            margin-right: 12px;
        }
        
        .meta-label {
            font-weight: 600;
        }
        
        .calendar-grid {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 6.5pt;
            table-layout: fixed;
        }
        
        .calendar-grid thead {
            background: #000;
            color: white;
        }
        
        .calendar-grid th {
            border: 0.8px solid #000;
            padding: 1px 0.5px;
            text-align: center;
            font-weight: 700;
            font-size: 6pt;
            color: white;
            background: #000;
            height: 10px;
            line-height: 1;
        }
        
        .calendar-grid th.time-header {
            width: 35px;
            font-size: 6pt;
        }
        
        .calendar-grid td {
            border: 0.8px solid #000;
            padding: 1px;
            vertical-align: top;
            height: 10px;
            max-height: 12px;
            background: white;
            color: #000;
            overflow: hidden;
        }
        
        .calendar-grid td.time-cell {
            background: #f9f9f9;
            text-align: center;
            font-weight: 600;
            font-size: 6pt;
            color: #000;
            border-right: 1.5px solid #000;
            width: 35px;
            padding: 1px 2px;
        }
        
        .calendar-grid td.empty-cell {
            background: white;
        }
        
        .schedule-block {
            background: white;
            color: #000;
            border: 0.8px solid #000;
            border-radius: 0;
            padding: 1px;
            min-height: 8px;
            display: block;
            overflow: hidden;
            font-size: 4.5pt;
            line-height: 1.02;
        }
        
        .schedule-block .time {
            font-weight: 700;
            font-size: 4.5pt;
            margin-bottom: 0.5px;
            display: block;
            color: #000;
            line-height: 1;
        }
        
        .schedule-block .subject-code {
            font-weight: 700;
            font-size: 5pt;
            display: block;
            color: #000;
            line-height: 1;
        }
        
        .schedule-block .subject-name {
            font-size: 4.5pt;
            display: block;
            font-weight: 500;
            color: #000;
            line-height: 1;
        }
        
        .schedule-block .section {
            font-size: 4.5pt;
            color: #000;
            display: block;
            font-weight: 600;
            line-height: 1;
        }
        
        .schedule-block .room {
            font-size: 4pt;
            color: #000;
            font-weight: 600;
            display: block;
            line-height: 1;
        }
        
        .footer {
            margin-top: 2px;
            padding-top: 1px;
            border-top: 0.8px solid #000;
            text-align: center;
            font-size: 6pt;
            color: #000;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 3px;
            font-size: 6pt;
        }
        
        .signature-box {
            text-align: center;
            flex: 1;
            margin: 0 2px;
        }
        
        .signature-line {
            border-top: 0.8px solid #000;
            margin: 8px 0 0.5px 0;
            height: 6px;
        }
        
        .signature-label {
            font-size: 5pt;
            color: #000;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <div class="title">My Class Schedule | ' . htmlspecialchars($semester) . '</div>
            <div class="subtitle">' . htmlspecialchars($teacherName) . '</div>
            <div class="meta-info">
                <span><span class="meta-label">School Year:</span> ' . htmlspecialchars($schoolYear) . '</span>
                <span><span class="meta-label">Generated:</span> ' . $generatedDate . '</span>
            </div>
        </div>
        
        <table class="calendar-grid">
            <thead>
                <tr>
                    <th class="time-header">Time</th>';
        
        // Day headers
        foreach ($days as $day) {
            $html .= '<th>' . strtoupper(substr($day, 0, 3)) . '</th>';
        }
        
        $html .= '</tr>
            </thead>
            <tbody>';
        
        // Track which cells have been filled (for rowspan)
        $filledCells = [];
        
        // Generate time slots
        foreach ($timeSlots as $timeSlot) {
            $html .= '<tr>';
            $html .= '<td class="time-cell">' . $formatTime($timeSlot) . '</td>';
            
            foreach ($days as $day) {
                $cellKey = $day . '-' . $timeSlot;
                
                // Skip if this cell is already filled by a rowspan
                if (isset($filledCells[$cellKey])) {
                    continue;
                }
                
                // Find schedule that starts at this time slot
                $scheduleAtSlot = null;
                foreach ($schedulesByDay[$day] as $schedule) {
                    $scheduleStartTime = substr($schedule['start_time'], 0, 5);
                    if ($scheduleStartTime === $timeSlot) {
                        $scheduleAtSlot = $schedule;
                        break;
                    }
                }
                
                if ($scheduleAtSlot) {
                    $rowSpan = $getRowSpan($scheduleAtSlot);
                    
                    // Mark cells as filled
                    for ($i = 0; $i < $rowSpan; $i++) {
                        $slotIndex = array_search($timeSlot, $timeSlots);
                        if ($slotIndex !== false && isset($timeSlots[$slotIndex + $i])) {
                            $filledCells[$day . '-' . $timeSlots[$slotIndex + $i]] = true;
                        }
                    }
                    
                    $startTime = $formatTime($scheduleAtSlot['start_time']);
                    $endTime = $formatTime($scheduleAtSlot['end_time']);
                    $subjectCode = htmlspecialchars($scheduleAtSlot['subject_code'] ?? '');
                    $subjectName = htmlspecialchars($scheduleAtSlot['subject_name'] ?? '');
                    $section = htmlspecialchars($scheduleAtSlot['section_name'] ?? '');
                    $room = htmlspecialchars($scheduleAtSlot['room_code'] ?? '');
                    
                    $html .= '<td rowspan="' . $rowSpan . '">
                        <div class="schedule-block">
                            <span class="time">' . $startTime . '-' . $endTime . '</span>
                            <span class="subject-code">' . $subjectCode . '</span>
                            <span class="subject-name">' . $subjectName . '</span>
                            <span class="section">' . $section . '</span>
                            <span class="room">' . $room . '</span>
                        </div>
                    </td>';
                } else {
                    $html .= '<td class="empty-cell"></td>';
                }
            }
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody>
        </table>
        
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Teacher\'s Signature</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Department Head</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Dean / Principal</div>
            </div>
        </div>
        
        <div class="footer">
            PayFlow System | Auto-generated on ' . $generatedDate . '
        </div>
    </div>
</body>
</html>';

    return $html;
}

/**
 * Generate and stream a PDF for payroll records
 * 
 * @param array $data Contains payroll records
 * @param string $filename PDF filename
 * @param bool $download Force download (true) or inline view (false)
 * @return void
 */
function generate_payroll_pdf($data, $filename = 'payroll.pdf', $download = true)
{
    // Configure Dompdf
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isFontSubsettingEnabled', true);
    $options->set('defaultFont', 'Arial');
    
    $dompdf = new Dompdf($options);
    
    // Generate HTML
    $html = generate_payroll_html($data);
    
    // Load HTML and render
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Stream to browser
    $dompdf->stream($filename, ['Attachment' => $download]);
}

/**
 * Generate HTML for payroll PDF
 * 
 * @param array $data Contains payroll records
 * @return string HTML content for PDF
 */
function generate_payroll_html($data)
{
    $records = $data['records'] ?? [];
    $generatedDate = date('F d, Y');
    $period = $data['period'] ?? 'Payroll Period';
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payroll Report</title>
    <style>
        @page {
            margin: 15mm;
            size: A4 portrait;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Arial", sans-serif;
            font-size: 10pt;
            color: #1f2937;
            line-height: 1.4;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #1f2937;
            padding-bottom: 10px;
        }
        
        .header h1 {
            font-size: 18pt;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .header .period {
            font-size: 12pt;
            color: #4b5563;
            margin-bottom: 3px;
        }
        
        .header .generated {
            font-size: 8pt;
            color: #6b7280;
        }
        
        .record {
            page-break-inside: avoid;
            margin-bottom: 25px;
            border: 1px solid #e5e7eb;
            padding: 15px;
            background: #f9fafb;
        }
        
        .record-header {
            background: #1f2937;
            color: white;
            padding: 8px 12px;
            margin: -15px -15px 12px -15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .record-header .employee-info {
            flex: 1;
        }
        
        .record-header .employee-name {
            font-size: 12pt;
            font-weight: 700;
        }
        
        .record-header .employee-code {
            font-size: 8pt;
            opacity: 0.8;
        }
        
        .record-header .position {
            text-align: right;
            font-size: 9pt;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 12px;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-label {
            display: table-cell;
            width: 35%;
            padding: 4px 8px;
            font-weight: 600;
            color: #4b5563;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .info-value {
            display: table-cell;
            padding: 4px 8px;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .section-title {
            font-size: 11pt;
            font-weight: 700;
            color: #1f2937;
            margin: 15px 0 8px 0;
            padding-bottom: 4px;
            border-bottom: 1px solid #d1d5db;
        }
        
        .attendance-summary {
            background: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 12px;
        }
        
        .attendance-grid {
            display: table;
            width: 100%;
        }
        
        .attendance-item {
            display: table-cell;
            text-align: center;
            padding: 8px;
            border-right: 1px solid #e5e7eb;
        }
        
        .attendance-item:last-child {
            border-right: none;
        }
        
        .attendance-value {
            font-size: 18pt;
            font-weight: 700;
            display: block;
            margin-bottom: 2px;
        }
        
        .attendance-label {
            font-size: 8pt;
            color: #6b7280;
            text-transform: uppercase;
        }
        
        .green { color: #10b981; }
        .red { color: #ef4444; }
        .yellow { color: #f59e0b; }
        
        .earnings-section {
            background: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 12px;
        }
        
        .amount-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .amount-row.total {
            font-weight: 700;
            font-size: 11pt;
            border-top: 2px solid #1f2937;
            border-bottom: 2px solid #1f2937;
            padding: 8px 0;
            margin-top: 8px;
        }
        
        .amount-row.highlight {
            background: #fef3c7;
            padding: 6px 8px;
            margin: 0 -8px;
        }
        
        .amount-label {
            color: #4b5563;
        }
        
        .amount-value {
            font-weight: 600;
            color: #1f2937;
        }
        
        .amount-value.negative {
            color: #ef4444;
        }
        
        .amount-value.positive {
            color: #10b981;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 8pt;
            color: #9ca3af;
        }
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Payroll Report</h1>
        <div class="period">' . htmlspecialchars($period) . '</div>
        <div class="generated">Generated on ' . $generatedDate . '</div>
    </div>
';
    
    $recordCount = 0;
    foreach ($records as $record) {
        $recordCount++;
        
        $employeeName = htmlspecialchars(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? ''));
        $employeeCode = htmlspecialchars($record['employee_code'] ?? 'N/A');
        $position = htmlspecialchars($record['position_title'] ?? 'N/A');
        $department = htmlspecialchars($record['department_name'] ?? 'N/A');
        $periodName = htmlspecialchars($record['period_name'] ?? $period);
        
        // Attendance
        $daysWorked = number_format((float)($record['days_worked'] ?? 0), 2);
        $daysAbsent = number_format((float)($record['days_absent'] ?? 0), 0);
        $minutesLate = number_format((float)($record['minutes_late'] ?? 0), 0);
        
        // Earnings
        $monthlySalary = number_format((float)($record['monthly_salary'] ?? 0), 2);
        $absenceDeduction = number_format((float)($record['absence_deduction'] ?? 0), 2);
        $basicSalary = number_format((float)($record['basic_salary'] ?? 0), 2);
        $allowanceRLA = number_format((float)($record['allowance_rla'] ?? 0), 2);
        $allowanceHonorarium = number_format((float)($record['honorarium'] ?? 0), 2);
        $allowanceOvertime = number_format((float)($record['overtime_pay'] ?? 0), 2);
        // Calculate gross pay from components
        $grossPayRaw = (float)($record['basic_salary'] ?? 0) + (float)($record['allowance_rla'] ?? 0) + (float)($record['honorarium'] ?? 0) + (float)($record['overtime_pay'] ?? 0);
        $grossPay = number_format($grossPayRaw, 2);
        
        // Deductions
        $deductionGSIS = number_format((float)($record['deduction_gsis'] ?? 0), 2);
        $deductionPhilHealth = number_format((float)($record['deduction_philhealth'] ?? 0), 2);
        $deductionPagIbig = number_format((float)($record['deduction_pagibig'] ?? 0), 2);
        $deductionTax = number_format((float)($record['deduction_tax'] ?? 0), 2);
        $deductionOther = number_format((float)($record['deduction_other'] ?? 0), 2);
        $totalDeductions = number_format((float)($record['total_deductions'] ?? 0), 2);
        $netSalary = number_format((float)($record['net_salary'] ?? 0), 2);
        
        $html .= '
    <div class="record">
        <div class="record-header">
            <div class="employee-info">
                <div class="employee-name">' . $employeeName . '</div>
                <div class="employee-code">Employee Code: ' . $employeeCode . '</div>
            </div>
            <div class="position">' . $position . '</div>
        </div>
        
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Department:</div>
                <div class="info-value">' . $department . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Period:</div>
                <div class="info-value">' . $periodName . '</div>
            </div>
        </div>
        
        <div class="section-title">Attendance Summary</div>
        <div class="attendance-summary">
            <div class="attendance-grid">
                <div class="attendance-item">
                    <span class="attendance-value green">' . $daysWorked . '</span>
                    <span class="attendance-label">Days Worked</span>
                </div>
                <div class="attendance-item">
                    <span class="attendance-value red">' . $daysAbsent . '</span>
                    <span class="attendance-label">Days Absent</span>
                </div>
                <div class="attendance-item">
                    <span class="attendance-value yellow">' . $minutesLate . '</span>
                    <span class="attendance-label">Minutes Late</span>
                </div>
            </div>
        </div>
        
        <div class="section-title">Earnings</div>
        <div class="earnings-section">
            <div class="amount-row">
                <span class="amount-label">Monthly Salary (Base)</span>
                <span class="amount-value">PHP ' . $monthlySalary . '</span>
            </div>
            <div class="amount-row highlight">
                <span class="amount-label">Absence Deduction (' . $daysAbsent . ' days)</span>
                <span class="amount-value negative">-PHP ' . $absenceDeduction . '</span>
            </div>
            <div class="amount-row">
                <span class="amount-label">Basic Salary (After Absences)</span>
                <span class="amount-value">PHP ' . $basicSalary . '</span>
            </div>
            <div class="amount-row">
                <span class="amount-label">RLA (Representation & Laundry)</span>
                <span class="amount-value positive">PHP ' . $allowanceRLA . '</span>
            </div>
            <div class="amount-row">
                <span class="amount-label">Honorarium</span>
                <span class="amount-value positive">PHP ' . $allowanceHonorarium . '</span>
            </div>
            <div class="amount-row">
                <span class="amount-label">Overtime Pay</span>
                <span class="amount-value positive">PHP ' . $allowanceOvertime . '</span>
            </div>
            <div class="amount-row total">
                <span class="amount-label">Gross Pay</span>
                <span class="amount-value">PHP ' . $grossPay . '</span>
            </div>
        </div>
        
        <div class="section-title">Deductions</div>
        <div class="earnings-section">
            <div class="amount-row">
                <span class="amount-label">GSIS (9%)</span>
                <span class="amount-value negative">PHP ' . $deductionGSIS . '</span>
            </div>
            <div class="amount-row">
                <span class="amount-label">PhilHealth</span>
                <span class="amount-value negative">PHP ' . $deductionPhilHealth . '</span>
            </div>
            <div class="amount-row">
                <span class="amount-label">Pag-IBIG</span>
                <span class="amount-value negative">PHP ' . $deductionPagIbig . '</span>
            </div>
            <div class="amount-row">
                <span class="amount-label">Withholding Tax</span>
                <span class="amount-value negative">PHP ' . $deductionTax . '</span>
            </div>
            <div class="amount-row">
                <span class="amount-label">Other Deductions</span>
                <span class="amount-value negative">PHP ' . $deductionOther . '</span>
            </div>
            <div class="amount-row total">
                <span class="amount-label">Total Deductions</span>
                <span class="amount-value negative">PHP ' . $totalDeductions . '</span>
            </div>
        </div>
        
        <div class="amount-row total" style="background: #10b981; color: white; padding: 12px; margin: 15px -10px -10px -10px; border-radius: 4px;">
            <span class="amount-label" style="color: white; font-size: 12pt;">NET SALARY</span>
            <span class="amount-value" style="color: white; font-size: 14pt;">PHP ' . $netSalary . '</span>
        </div>
    </div>
';
        
        // Add page break after every 2 records except the last one
        if ($recordCount % 2 === 0 && $recordCount < count($records)) {
            $html .= '<div class="page-break"></div>';
        }
    }
    
    $html .= '
    <div class="footer">
        PayFlow HR System | This is a computer-generated document and does not require a signature.
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Generate payroll PDF as binary data (for email attachment)
 * 
 * @param array $data Contains payroll records
 * @param string $filename PDF filename
 * @return string|false Binary PDF data or false on failure
 */
function generate_payroll_pdf_binary($data, $filename = 'payroll.pdf')
{
    try {
        // Configure Dompdf
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultFont', 'Arial');
        
        $dompdf = new Dompdf($options);
        
        // Generate HTML
        $html = generate_payroll_html($data);
        
        // Load HTML and render
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Return PDF as binary data
        return $dompdf->output();
    } catch (Exception $e) {
        return false;
    }
}
