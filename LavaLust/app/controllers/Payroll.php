<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Payroll extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('PayrollModel');
        $this->call->model('PayrollPeriodModel');
        $this->call->model('SalaryGradeModel');
        $this->call->model('EmployeeModel');
        $this->call->model('AttendanceModel');
        $this->call->model('LeaveRequestModel');
        $this->call->helper('payroll');
        $this->call->helper('pdf');
        // mailer and templates for notifications
        $this->call->helper('mailer');
        $this->call->helper('email_template');
    }

    /**
     * GET /api/payroll - Get all payroll records
     */
    public function index()
    {
        header('Content-Type: application/json');
        
        try {
            $payroll = $this->PayrollModel->get_all_payroll();
            
            http_response_code(200);
            echo json_encode(['payroll' => $payroll]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch payroll records']);
        }
    }

    /**
     * GET /api/payroll/period/{id} - Get payroll records for a period
     */
    public function period($period_id = null)
    {
        header('Content-Type: application/json');
        
        if (!$period_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Period ID is required']);
            return;
        }

        try {
            $payroll = $this->PayrollModel->get_by_period((int)$period_id);
            $period = $this->PayrollPeriodModel->get_period_by_id((int)$period_id);
            $summary = $this->PayrollModel->get_period_summary((int)$period_id);
            
            http_response_code(200);
            echo json_encode([
                'payroll' => $payroll,
                'period' => $period,
                'summary' => $summary
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch payroll for period']);
        }
    }

    /**
     * GET /api/payroll/employee/{id} - Get payroll records for an employee
     */
    public function employee($employee_id = null)
    {
        header('Content-Type: application/json');
        
        if (!$employee_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Employee ID is required']);
            return;
        }

        try {
            $payroll = $this->PayrollModel->get_by_employee((int)$employee_id);
            
            http_response_code(200);
            echo json_encode(['payroll' => $payroll]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch employee payroll']);
        }
    }

    /**
     * POST /api/payroll/generate - Generate payroll for a period
     */
    public function generate()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['period_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Period ID is required']);
            return;
        }

        $period_id = (int)$data['period_id'];
        $employee_ids = $data['employee_ids'] ?? null; // Optional: specific employees

        try {
            // Get period details
            $period = $this->PayrollPeriodModel->get_period_by_id($period_id);
            
            if (!$period) {
                http_response_code(404);
                echo json_encode(['error' => 'Period not found']);
                return;
            }

            // Get employees to process
            if ($employee_ids && is_array($employee_ids)) {
                $employees = [];
                foreach ($employee_ids as $emp_id) {
                    $emp = $this->EmployeeModel->find_by_id((int)$emp_id);
                    if ($emp) $employees[] = $emp;
                }
            } else {
                $employees = $this->EmployeeModel->get_all_employees();
            }

            $generated = 0;
            $errors = [];

            foreach ($employees as $employee) {
                // Skip if payroll already exists
                if ($this->PayrollModel->exists_for_employee_period((int)$employee['id'], $period_id)) {
                    continue;
                }

                // Skip if no salary grade
                if (empty($employee['salary_grade']) || empty($employee['step_increment'])) {
                    $errors[] = [
                        'employee_id' => $employee['id'],
                        'employee_name' => ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''),
                        'error' => 'No salary grade assigned'
                    ];
                    continue;
                }

                // Get monthly salary from salary_grades table
                $monthly_salary = $this->SalaryGradeModel->get_monthly_salary(
                    (int)$employee['salary_grade'],
                    (int)$employee['step_increment']
                );

                if (!$monthly_salary) {
                    $errors[] = [
                        'employee_id' => $employee['id'],
                        'employee_name' => ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''),
                        'error' => 'Salary grade not found in salary_grades table'
                    ];
                    continue;
                }

                // Get attendance for period
                $attendance = [];
                $current_date = new DateTime($period['start_date']);
                $end_date = new DateTime($period['end_date']);

                while ($current_date <= $end_date) {
                    $date_str = $current_date->format('Y-m-d');
                    $att = $this->AttendanceModel->find_by_employee_date((int)$employee['id'], $date_str);
                    if ($att) {
                        $attendance[] = $att;
                    }
                    $current_date->modify('+1 day');
                }

                // Get approved leaves for the period with their paid percentages
                $approved_leaves = [];
                $query = "
                    SELECT lr.id, lr.number_of_days, lt.paid_percentage
                    FROM leave_requests lr
                    JOIN leave_types lt ON lr.leave_type_id = lt.id
                    WHERE lr.employee_id = ?
                    AND lr.status = 'approved'
                    AND lr.start_date >= ?
                    AND lr.end_date <= ?
                    ORDER BY lr.start_date
                ";
                $stmt = $this->db->raw($query, [
                    (int)$employee['id'],
                    $period['start_date'],
                    $period['end_date']
                ]);
                if ($stmt) {
                    $approved_leaves = $stmt->fetchAll() ?: [];
                }

                // Calculate payroll (pass period dates and approved leaves)
                $calculation = calculate_payroll_for_employee(
                    $employee,
                    $monthly_salary,
                    $attendance,
                    $approved_leaves,
                    [
                        'allowance_rla' => isset($data['allowance_rla']) ? floatval($data['allowance_rla']) : 1500.00,
                        'honorarium' => isset($data['honorarium']) ? floatval($data['honorarium']) : 0.00,
                        'overtime_pay' => isset($data['overtime_pay']) ? floatval($data['overtime_pay']) : 0.00,
                        'other_deductions' => isset($data['other_deductions']) ? floatval($data['other_deductions']) : 0.00,
                        'period_start' => $period['start_date'],
                        'period_end' => $period['end_date']
                    ]
                );

                // Create payroll record (use safe defaults if calculation keys are missing)
                $payroll_data = [
                    'employee_id' => $employee['id'],
                    'period_id' => $period_id,
                    'period_month' => $period['start_date'],
                    'basic_salary' => $calculation['basic_salary'] ?? 0.00,
                    'days_worked' => $calculation['days_worked'] ?? 0,
                    'days_absent' => $calculation['days_absent'] ?? 0,
                    'days_half_day' => $calculation['days_half_day'] ?? 0,
                    'undertime_minutes' => $calculation['undertime_minutes'] ?? 0,
                    'late_minutes_total' => $calculation['late_minutes_total'] ?? 0,
                    'allowance_rla' => $calculation['allowance_rla'] ?? 0.00,
                    'honorarium' => $calculation['honorarium'] ?? 0.00,
                    'overtime_pay' => $calculation['overtime_pay'] ?? 0.00,
                    'deduction_gsis' => $calculation['deduction_gsis'] ?? 0.00,
                    'deduction_philhealth' => $calculation['deduction_philhealth'] ?? 0.00,
                    'deduction_pagibig' => $calculation['deduction_pagibig'] ?? 0.00,
                    'deduction_tax' => $calculation['deduction_tax'] ?? 0.00,
                    'other_deductions' => $calculation['other_deductions'] ?? 0.00,
                    'net_salary' => $calculation['net_salary'] ?? 0.00,
                    'status' => 'pending'
                ];

                $result = $this->PayrollModel->create_payroll($payroll_data);
                
                if ($result) {
                    $generated++;
                }
            }

            http_response_code(200);
            echo json_encode([
                'message' => "Payroll generated for {$generated} employees",
                'generated' => $generated,
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to generate payroll: ' . $e->getMessage()]);
        }
    }

    /**
     * PUT /api/payroll/{id} - Update payroll record
     */
    public function update($id = null)
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Payroll ID is required']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $result = $this->PayrollModel->update_payroll((int)$id, $data);
            
            if ($result) {
                // If payroll was marked as processed, send notification email with PDF to the employee
                try {
                    if (isset($data['status']) && $data['status'] === 'processed') {
                        $pay = $this->PayrollModel->get_by_id((int)$id);
                        if ($pay && !empty($pay['email'])) {
                            $employeeName = $pay['employee_name'] ?? trim(($pay['first_name'] ?? '') . ' ' . ($pay['last_name'] ?? ''));
                            $periodLabel = $pay['period_name'] ?? ($pay['period_month'] ?? '');
                            $amount = isset($pay['net_salary']) ? floatval($pay['net_salary']) : 0.0;
                            $body = email_template_payroll_notification($employeeName, $periodLabel, $amount);
                            
                            // Generate payroll PDF for attachment
                            $attachments = [];
                            $pdfData = generate_payroll_pdf_binary(['records' => [$pay]], 'payroll_' . date('Ymd') . '.pdf');
                            if ($pdfData) {
                                $attachments[] = [
                                    'data' => $pdfData,
                                    'name' => 'payroll_' . date('Ymd') . '.pdf',
                                    'type' => 'application/pdf'
                                ];
                            }
                            
                            // send_email returns ['success'=>bool, 'message'=>string]
                            $sendRes = send_email($pay['email'], "Payroll Processed: {$periodLabel}", $body, $employeeName, null, false, $attachments);
                            // optionally log $sendRes if a logger exists
                        }
                    }
                } catch (Exception $e) {
                    // Don't fail the update if email sending fails
                }
                http_response_code(200);
                echo json_encode(['message' => 'Payroll updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update payroll']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update payroll']);
        }
    }

    /**
     * DELETE /api/payroll/{id} - Delete payroll record
     */
    public function delete($id = null)
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Payroll ID is required']);
            return;
        }

        try {
            $result = $this->PayrollModel->delete_payroll((int)$id);
            
            if ($result) {
                http_response_code(200);
                echo json_encode(['message' => 'Payroll deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete payroll']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete payroll']);
        }
    }

    /**
     * GET /api/payroll/periods - Get all payroll periods
     */
    public function periods()
    {
        header('Content-Type: application/json');
        
        try {
            $periods = $this->PayrollPeriodModel->get_all_periods();
            
            http_response_code(200);
            echo json_encode(['periods' => $periods]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch periods']);
        }
    }

    /**
     * POST /api/payroll/periods - Create payroll period
     */
    public function create_period()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name']) || empty($data['start_date']) || empty($data['end_date'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Name, start_date, and end_date are required']);
            return;
        }

        try {
            $period_id = $this->PayrollPeriodModel->create_period($data);
            
            if ($period_id) {
                http_response_code(201);
                echo json_encode([
                    'message' => 'Period created successfully',
                    'period_id' => $period_id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create period (check for overlapping dates)']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create period']);
        }
    }

    /**
     * PUT /api/payroll/periods/{id}/status - Update period status
     */
    public function update_period_status($id = null)
    {
        header('Content-Type: application/json');
        // Allow PUT (REST) or POST (dev-proxy fallback / legacy form)
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Read input body (JSON) for both PUT and POST
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // If id wasn't provided via route (legacy fallback), try body or POST
        if (!$id) {
            $id = isset($data['id']) ? $data['id'] : (isset($_POST['id']) ? $_POST['id'] : null);
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Period ID is required']);
            return;
        }

        if (empty($data['status']) && !isset($_POST['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Status is required']);
            return;
        }

        $status = $data['status'] ?? $_POST['status'];

        try {
            $result = $this->PayrollPeriodModel->update_status((int)$id, $status);
            
            if ($result) {
                http_response_code(200);
                echo json_encode(['message' => 'Period status updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update period status']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update period status']);
        }
    }

    /**
     * PUT /api/payroll/periods/{id} - Update period details
     */
    public function update_period($id = null)
    {
        header('Content-Type: application/json');
        // Allow PUT (REST) or POST (dev-proxy fallback / legacy form)
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Read input body (JSON) for both PUT and POST
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // If id wasn't provided via route (legacy fallback), try body or POST
        if (!$id) {
            $id = isset($data['id']) ? $data['id'] : (isset($_POST['id']) ? $_POST['id'] : null);
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Period ID is required']);
            return;
        }

        try {
            $result = $this->PayrollPeriodModel->update_period((int)$id, $data);
            
            if ($result) {
                http_response_code(200);
                echo json_encode(['message' => 'Period updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update period']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update period: ' . $e->getMessage()]);
        }
    }

    /**
     * DELETE /api/payroll/periods/{id} - Delete period
     */
    public function delete_period($id = null)
    {
        header('Content-Type: application/json');
        // Allow DELETE (REST) or POST (dev-proxy fallback / legacy form)
        if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'])) {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Read input body (JSON) if POST fallback
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // If id wasn't provided via route (legacy fallback), try body or POST
        if (!$id) {
            $id = isset($data['id']) ? $data['id'] : (isset($_POST['id']) ? $_POST['id'] : null);
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Period ID is required']);
            return;
        }

        try {
            // Check if period has payroll records
            $payroll = $this->PayrollModel->get_by_period((int)$id);
            if (!empty($payroll)) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot delete period with existing payroll records']);
                return;
            }

            $result = $this->PayrollPeriodModel->delete_period((int)$id);
            
            if ($result) {
                http_response_code(200);
                echo json_encode(['message' => 'Period deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete period']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete period: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/payroll/salary-grades - Get all salary grades
     * Query params: grouped (1/0) - return grouped by salary_grade
     */
    public function salary_grades()
    {
        header('Content-Type: application/json');
        
        try {
            $grouped = isset($_GET['grouped']) && $_GET['grouped'] == '1';
            $grades = $this->SalaryGradeModel->get_all_grades($grouped);
            
            http_response_code(200);
            echo json_encode(['salary_grades' => $grades]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch salary grades', 'detail' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/payroll/salary-grades - Create or update salary grade
     */
    public function create_salary_grade()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['salary_grade']) || empty($data['step']) || !isset($data['monthly_salary'])) {
            http_response_code(400);
            echo json_encode(['error' => 'salary_grade, step, and monthly_salary are required']);
            return;
        }

        try {
            $result = $this->SalaryGradeModel->upsert_salary_grade($data);
            
            if ($result) {
                http_response_code(201);
                echo json_encode([
                    'message' => 'Salary grade saved successfully',
                    'id' => $result
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save salary grade']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save salary grade', 'detail' => $e->getMessage()]);
        }
    }

    /**
     * PUT /api/payroll/salary-grades/{id} - Update specific salary grade entry
     */
    public function update_salary_grade($id = null)
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Salary grade ID is required']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $update_data = [];
            if (isset($data['monthly_salary'])) $update_data['monthly_salary'] = $data['monthly_salary'];
            if (isset($data['effective_date'])) $update_data['effective_date'] = $data['effective_date'];
            
            if (empty($update_data)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                return;
            }

            $result = $this->db->table('salary_grades')->where('id', (int)$id)->update($update_data);
            
            if ($result !== false) {
                http_response_code(200);
                echo json_encode(['message' => 'Salary grade updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update salary grade']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update salary grade', 'detail' => $e->getMessage()]);
        }
    }

    /**
     * DELETE /api/payroll/salary-grades/{id} - Delete salary grade entry
     */
    public function delete_salary_grade($id = null)
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Salary grade ID is required']);
            return;
        }

        try {
            $result = $this->db->table('salary_grades')->where('id', (int)$id)->delete();
            
            if ($result) {
                http_response_code(200);
                echo json_encode(['message' => 'Salary grade deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete salary grade']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete salary grade', 'detail' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/payroll/salary-grades/bulk - Bulk import salary grades
     */
    public function bulk_import_salary_grades()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['grades']) || !is_array($data['grades'])) {
            http_response_code(400);
            echo json_encode(['error' => 'grades array is required']);
            return;
        }

        try {
            $result = $this->SalaryGradeModel->bulk_insert_salary_grades($data['grades']);
            
            if ($result) {
                http_response_code(201);
                echo json_encode([
                    'message' => 'Salary grades imported successfully',
                    'count' => count($data['grades'])
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to import salary grades']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to import salary grades', 'detail' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/payroll/salary-grades/export - Export salary grades as JSON
     */
    public function export_salary_grades()
    {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="salary_grades_export.json"');
        
        try {
            $grades = $this->SalaryGradeModel->get_all_grades(false);
            echo json_encode(['salary_grades' => $grades], JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to export salary grades']);
        }
    }

    /**
     * POST /api/payroll/export-pdf - Export selected payroll records as PDF
     */
    public function export_pdf()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $payroll_ids = $input['payroll_ids'] ?? [];

        if (empty($payroll_ids) || !is_array($payroll_ids)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No payroll records selected']);
            return;
        }

        try {
            // Fetch selected payroll records with full details
            $records = [];
            foreach ($payroll_ids as $id) {
                $record = $this->PayrollModel->get_by_id((int)$id);
                if ($record) {
                    $records[] = $record;
                }
            }

            if (empty($records)) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'No payroll records found']);
                return;
            }

            // Get period name from first record
            $periodName = $records[0]['period_name'] ?? 'Payroll Period';
            $startDate = $records[0]['start_date'] ?? '';
            $endDate = $records[0]['end_date'] ?? '';
            if ($startDate && $endDate) {
                $periodName .= ' (' . date('M d', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)) . ')';
            }

            // Prepare data for PDF
            $data = [
                'records' => $records,
                'period' => $periodName
            ];

            // Generate filename
            $filename = 'payroll_' . date('Ymd_His') . '.pdf';

            // Generate and stream PDF
            generate_payroll_pdf($data, $filename, true);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Failed to generate PDF: ' . $e->getMessage()]);
        }
    }
}

