<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Schedules extends Controller {
    protected $ScheduleModel;

    public function __construct()
    {
        parent::__construct();
        $this->call->model('ScheduleModel');
        $this->ScheduleModel = new ScheduleModel();
    }

    /**
     * GET /api/schedules
     * Get all schedules with optional filters
     */
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            // Check for query parameters
            $employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
            $section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : null;
            
            if ($employee_id) {
                $schedules = $this->ScheduleModel->get_by_employee($employee_id);
            } elseif ($section_id) {
                $schedules = $this->ScheduleModel->get_by_section($section_id);
            } else {
                $schedules = $this->ScheduleModel->get_all_schedules();
            }
            
            echo json_encode(['schedules' => $schedules]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch schedules', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/schedules
     * Create a new schedule
     */
    public function create()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            // Validate required fields
            $required = ['subject_id', 'employee_id', 'section_id', 'day_of_week', 'start_time', 'end_time', 'room_code'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Field '$field' is required"]);
                    exit;
                }
            }

            // Validate day of week
            $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            if (!in_array($data['day_of_week'], $validDays)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid day of week']);
                exit;
            }

            // Validate time format
            if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['start_time']) || 
                !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['end_time'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid time format. Use HH:MM or HH:MM:SS']);
                exit;
            }

            // Check if end time is after start time
            if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
                http_response_code(400);
                echo json_encode(['error' => 'End time must be after start time']);
                exit;
            }

            // Prepare data
            $scheduleData = [
                'subject_id' => (int)$data['subject_id'],
                'employee_id' => (int)$data['employee_id'],
                'section_id' => (int)$data['section_id'],
                'day_of_week' => $data['day_of_week'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'room_code' => trim($data['room_code']),
                'is_active' => 1
            ];

            // Check for room conflicts
            try {
                if ($this->ScheduleModel->has_conflict($scheduleData)) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Schedule conflict: Room is already booked for this time slot']);
                    exit;
                }
            } catch (Exception $check_conflict_error) {
                throw new Exception('Error in has_conflict: ' . $check_conflict_error->getMessage());
            }

            // Check for teacher conflicts
            try {
                if ($this->ScheduleModel->teacher_has_conflict($scheduleData)) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Schedule conflict: Teacher already has a class at this time']);
                    exit;
                }
            } catch (Exception $check_teacher_error) {
                throw new Exception('Error in teacher_has_conflict: ' . $check_teacher_error->getMessage());
            }

            // Check if teacher is already assigned to this subject+section
            try {
                if ($this->ScheduleModel->teacher_subject_section_exists($scheduleData)) {
                    http_response_code(409);
                    echo json_encode(['error' => 'This teacher is already assigned to this subject and section']);
                    exit;
                }
            } catch (Exception $check_assignment_error) {
                throw new Exception('Error in teacher_subject_section_exists: ' . $check_assignment_error->getMessage());
            }

            // Create schedule
            $id = $this->ScheduleModel->create_schedule($scheduleData);
            
            if (!$id) {
                throw new Exception('Failed to create schedule');
            }

            $schedule = $this->ScheduleModel->find_by_id($id);
            http_response_code(201);
            echo json_encode(['success' => true, 'schedule' => $schedule]);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create schedule', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/schedules/update
     * Update an existing schedule
     */
    public function update()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            if (!isset($data['id']) || empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Schedule ID is required']);
                exit;
            }

            $id = (int)$data['id'];
            $updateData = [];

            // Build update data
            if (isset($data['subject_id'])) $updateData['subject_id'] = (int)$data['subject_id'];
            if (isset($data['employee_id'])) $updateData['employee_id'] = (int)$data['employee_id'];
            if (isset($data['section_id'])) $updateData['section_id'] = (int)$data['section_id'];
            if (isset($data['day_of_week'])) {
                $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                if (!in_array($data['day_of_week'], $validDays)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid day of week']);
                    exit;
                }
                $updateData['day_of_week'] = $data['day_of_week'];
            }
            if (isset($data['start_time'])) $updateData['start_time'] = $data['start_time'];
            if (isset($data['end_time'])) $updateData['end_time'] = $data['end_time'];
            if (isset($data['room_code'])) $updateData['room_code'] = trim($data['room_code']);
            if (isset($data['is_active'])) $updateData['is_active'] = (int)$data['is_active'];

            if (empty($updateData)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                exit;
            }

            // If updating time or room, check for conflicts
            if (isset($updateData['start_time']) || isset($updateData['end_time']) || 
                isset($updateData['room_code']) || isset($updateData['day_of_week']) ||
                isset($updateData['employee_id'])) {
                
                // Get current schedule to merge with updates
                $current = $this->ScheduleModel->find_by_id($id);
                if (!$current) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Schedule not found']);
                    exit;
                }

                $checkData = array_merge([
                    'room_code' => $current['room_code'],
                    'day_of_week' => $current['day_of_week'],
                    'start_time' => $current['start_time'],
                    'end_time' => $current['end_time'],
                    'employee_id' => $current['employee_id']
                ], $updateData);

                // Check room conflict
                if ($this->ScheduleModel->has_conflict($checkData, $id)) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Schedule conflict: Room is already booked for this time slot']);
                    exit;
                }

                // Check teacher conflict
                if ($this->ScheduleModel->teacher_has_conflict($checkData, $id)) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Schedule conflict: Teacher already has a class at this time']);
                    exit;
                }

                // Check if teacher is already assigned to this subject+section
                if (isset($updateData['employee_id']) || isset($updateData['subject_id']) || isset($updateData['section_id'])) {
                    $assignmentCheckData = [
                        'employee_id' => isset($updateData['employee_id']) ? $updateData['employee_id'] : $current['employee_id'],
                        'subject_id' => isset($updateData['subject_id']) ? $updateData['subject_id'] : $current['subject_id'],
                        'section_id' => isset($updateData['section_id']) ? $updateData['section_id'] : $current['section_id'],
                        'day_of_week' => isset($updateData['day_of_week']) ? $updateData['day_of_week'] : $current['day_of_week'],
                        'start_time' => isset($updateData['start_time']) ? $updateData['start_time'] : $current['start_time'],
                        'end_time' => isset($updateData['end_time']) ? $updateData['end_time'] : $current['end_time']
                    ];
                    
                    if ($this->ScheduleModel->teacher_subject_section_exists($assignmentCheckData, $id)) {
                        http_response_code(409);
                        echo json_encode(['error' => 'This teacher is already assigned to this subject and section']);
                        exit;
                    }
                }
            }

            $success = $this->ScheduleModel->update_schedule($id, $updateData);
            
            if (!$success) {
                throw new Exception('Failed to update schedule');
            }

            $schedule = $this->ScheduleModel->find_by_id($id);
            echo json_encode(['success' => true, 'schedule' => $schedule]);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update schedule', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * GET /api/schedules/pdf
     * Generate PDF for teacher's weekly schedule
     */
    public function pdf()
    {
        try {
            // Get employee_id from query parameter
            $employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
            
            if (!$employee_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Employee ID is required']);
                exit;
            }

            // Fetch schedules for the employee
            $schedules = $this->ScheduleModel->get_by_employee($employee_id);
            
            if (empty($schedules)) {
                http_response_code(404);
                echo json_encode(['error' => 'No schedules found for this teacher']);
                exit;
            }

            // Get teacher information
            $this->call->model('EmployeeModel');
            $EmployeeModel = new EmployeeModel();
            $employee = $EmployeeModel->find($employee_id);
            
            if (!$employee) {
                http_response_code(404);
                echo json_encode(['error' => 'Employee not found']);
                exit;
            }

            $teacher_name = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
            
            // Prepare data for PDF
            $pdfData = [
                'teacher_name' => $teacher_name,
                'schedules' => $schedules,
                'school_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => '1st Semester' // You can make this dynamic if needed
            ];

            // Generate PDF using helper
            $this->call->helper('pdf');
            
            // Generate filename
            $filename = 'Weekly_Schedule_' . str_replace(' ', '_', $teacher_name) . '_' . date('Y-m-d') . '.pdf';
            
            // Generate and stream PDF
            generate_schedule_pdf($pdfData, $filename, true);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to generate PDF', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/schedules/delete
     * Soft delete a schedule
     */
    public function delete()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            if (!isset($data['id']) || empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Schedule ID is required']);
                exit;
            }

            $id = (int)$data['id'];
            $success = $this->ScheduleModel->delete_schedule($id);
            
            if (!$success) {
                throw new Exception('Failed to delete schedule');
            }

            echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete schedule', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * GET /api/schedules/excel
     * Export schedules to modern Excel file
     */
    public function excel()
    {
        try {
            $this->call->helper('excel_helper');
            
            $employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
            
            if (!$employee_id) {
                throw new Exception('Employee ID is required');
            }

            // Fetch schedules for the employee
            $schedules = $this->ScheduleModel->get_by_employee($employee_id);
            
            if (!$schedules) {
                $schedules = [];
            }

            // Create spreadsheet
            $spreadsheet = create_excel_spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Class Schedule');

            // Set column widths
            set_excel_column_widths($spreadsheet, [
                'A' => 12,  // Day
                'B' => 12,  // Start Time
                'C' => 12,  // End Time
                'D' => 15,  // Subject Code
                'E' => 25,  // Subject Name
                'F' => 15,  // Section
                'G' => 10,  // Room
                'H' => 12,  // Duration
            ]);

            // Add title
            add_excel_title($spreadsheet, 'Class Schedule', 1, 8);
            set_excel_row_height($spreadsheet, 1, 25);

            // Add metadata
            add_excel_subtitle($spreadsheet, 'Generated', date('Y-m-d H:i:s'), 2, 8);
            add_excel_subtitle($spreadsheet, 'Total Classes', count($schedules), 3, 8);

            // Add headers
            set_excel_row_height($spreadsheet, 5, 20);
            $headers = ['Day', 'Start Time', 'End Time', 'Subject Code', 'Subject Name', 'Section', 'Room', 'Duration (hrs)'];
            add_excel_header($spreadsheet, $headers, 5);

            // Add data rows
            $row = 6;
            $totalHours = 0;
            
            // Sort schedules by day and time
            usort($schedules, function($a, $b) {
                $dayOrder = ['Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2, 'Thursday' => 3, 'Friday' => 4, 'Saturday' => 5];
                $aDayOrder = $dayOrder[$a['day_of_week']] ?? 6;
                $bDayOrder = $dayOrder[$b['day_of_week']] ?? 6;
                
                if ($aDayOrder !== $bDayOrder) {
                    return $aDayOrder - $bDayOrder;
                }
                return strcmp($a['start_time'], $b['start_time']);
            });

            foreach ($schedules as $index => $schedule) {
                if (!$schedule['is_active']) continue;

                // Calculate duration
                $startTime = strtotime($schedule['start_time']);
                $endTime = strtotime($schedule['end_time']);
                $duration = round(($endTime - $startTime) / 3600, 2);
                $totalHours += $duration;

                $data = [
                    $schedule['day_of_week'] ?? 'N/A',
                    date('H:i', $startTime),
                    date('H:i', $endTime),
                    $schedule['subject_code'] ?? 'N/A',
                    $schedule['subject_name'] ?? 'N/A',
                    $schedule['section_name'] ?? 'N/A',
                    $schedule['room_code'] ?? 'N/A',
                    $duration,
                ];

                $isAlternate = $index % 2 === 1;
                add_excel_row($spreadsheet, $data, $row, $isAlternate, [0, 1, 2, 6, 7]);
                $row++;
            }

            // Add summary row
            add_excel_summary_row(
                $spreadsheet,
                ['', '', '', '', '', 'TOTAL HOURS', '', round($totalHours, 2)],
                $row,
                '1E40AF'
            );

            // Freeze header row
            freeze_excel_pane($spreadsheet, 'A6');

            // Add auto filter
            add_excel_autofilter($spreadsheet, "A5:H{$row}");

            // Generate filename with employee ID and current date
            $filename = 'schedule_employee_' . $employee_id . '_' . date('Y-m-d') . '.xlsx';

            // Download
            download_excel_file($spreadsheet, $filename);

        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to export schedule', 'detail' => $e->getMessage()]);
            exit;
        }
    }
}
