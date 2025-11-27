<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Faculty Schedule Controller
 * Handles faculty teaching schedules, loads, and class sessions
 */
class FacultySchedule extends Controller {
    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->call->database();
    }

    /**
     * GET /api/faculty/{id}/schedule?date=YYYY-MM-DD
     * Get faculty schedule for a specific week
     */
    public function schedule($faculty_id = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $faculty_id = $faculty_id ?? $_GET['id'] ?? null;
        $date = $_GET['date'] ?? date('Y-m-d');
        
        if (!$faculty_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Faculty ID required']);
            exit;
        }

        try {
            // Get schedule from view
            $query = "
                SELECT 
                    employee_id,
                    employee_code,
                    faculty_name,
                    subject_code,
                    subject_name,
                    section,
                    day_of_week,
                    start_time,
                    end_time,
                    room,
                    hours,
                    hourly_rate,
                    school_year,
                    semester
                FROM v_faculty_daily_schedule
                WHERE employee_id = ?
                ORDER BY 
                    FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                    start_time
            ";
            
            $schedule = $this->db->raw($query, [$faculty_id]);
            
            echo json_encode([
                'faculty_id' => (int)$faculty_id,
                'date' => $date,
                'schedule' => $schedule
            ]);
            exit;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch schedule', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * GET /api/faculty/{id}/load?semester=1st&year=2024-2025
     * Get faculty teaching load summary
     */
    public function load($faculty_id = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $faculty_id = $faculty_id ?? $_GET['id'] ?? null;
        $semester = $_GET['semester'] ?? '1st';
        $year = $_GET['year'] ?? date('Y');
        
        if (!$faculty_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Faculty ID required']);
            exit;
        }

        try {
            $query = "
                SELECT 
                    employee_id,
                    employee_code,
                    faculty_name,
                    email,
                    faculty_rank,
                    school_year,
                    semester,
                    total_subjects,
                    total_units,
                    total_hours_per_week,
                    avg_hourly_rate
                FROM v_faculty_weekly_load
                WHERE employee_id = ?
                  AND semester = ?
                  AND school_year = ?
            ";
            
            $load = $this->db->raw($query, [$faculty_id, $semester, $year]);
            
            echo json_encode([
                'faculty_id' => (int)$faculty_id,
                'load' => $load ? $load[0] : null
            ]);
            exit;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch teaching load', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * GET /api/faculty/{id}/today
     * Get faculty's classes scheduled for today
     */
    public function today($faculty_id = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $faculty_id = $faculty_id ?? $_GET['id'] ?? null;
        $date = date('Y-m-d');
        $day_name = date('l'); // Monday, Tuesday, etc.
        
        if (!$faculty_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Faculty ID required']);
            exit;
        }

        try {
            $query = "
                SELECT 
                    cs.id AS class_schedule_id,
                    s.code AS subject_code,
                    s.name AS subject_name,
                    fl.section,
                    cs.start_time,
                    cs.end_time,
                    cs.room,
                    TIMESTAMPDIFF(MINUTE, cs.start_time, cs.end_time) / 60.0 AS hours,
                    fl.hourly_rate,
                    fa.id AS attendance_id,
                    fa.actual_start_time,
                    fa.actual_end_time,
                    fa.status,
                    fa.hours_rendered
                FROM class_schedules cs
                INNER JOIN faculty_loads fl ON cs.faculty_load_id = fl.id
                INNER JOIN subjects s ON fl.subject_id = s.id
                LEFT JOIN faculty_attendance fa ON fa.class_schedule_id = cs.id 
                    AND fa.attendance_date = ?
                WHERE fl.employee_id = ?
                  AND cs.day_of_week = ?
                  AND cs.is_active = 1
                  AND fl.is_active = 1
                ORDER BY cs.start_time
            ";
            
            $classes = $this->db->raw($query, [$date, $faculty_id, $day_name]);
            
            echo json_encode([
                'faculty_id' => (int)$faculty_id,
                'date' => $date,
                'day_of_week' => $day_name,
                'classes' => $classes
            ]);
            exit;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch today\'s schedule', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/faculty/attendance/clockin
     * Clock in for a specific class session
     * Body: { class_schedule_id, attendance_date, actual_start_time }
     */
    public function clockin()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($method) !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        $class_schedule_id = $input['class_schedule_id'] ?? null;
        $attendance_date = $input['attendance_date'] ?? date('Y-m-d');
        $actual_start_time = $input['actual_start_time'] ?? date('H:i:s');
        
        if (!$class_schedule_id) {
            http_response_code(400);
            echo json_encode(['error' => 'class_schedule_id required']);
            exit;
        }

        try {
            // Get faculty ID from class schedule
            $schedule_query = "
                SELECT fl.employee_id, cs.start_time, cs.end_time
                FROM class_schedules cs
                INNER JOIN faculty_loads fl ON cs.faculty_load_id = fl.id
                WHERE cs.id = ?
            ";
            $schedule = $this->db->raw($schedule_query, [$class_schedule_id]);
            
            if (!$schedule || count($schedule) === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Class schedule not found']);
                exit;
            }
            
            $employee_id = $schedule[0]['employee_id'];
            $scheduled_start = $schedule[0]['start_time'];
            $scheduled_end = $schedule[0]['end_time'];
            
            // Check if already clocked in
            $existing_query = "
                SELECT id FROM faculty_attendance
                WHERE class_schedule_id = ? AND attendance_date = ?
            ";
            $existing = $this->db->raw($existing_query, [$class_schedule_id, $attendance_date]);
            
            if ($existing && count($existing) > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'Already clocked in for this class session']);
                exit;
            }
            
            // Determine status based on time
            $status = 'present';
            $scheduled_start_dt = strtotime($attendance_date . ' ' . $scheduled_start);
            $actual_start_dt = strtotime($attendance_date . ' ' . $actual_start_time);
            
            // Late if more than 10 minutes after scheduled start
            if ($actual_start_dt > ($scheduled_start_dt + 600)) {
                $status = 'late';
            }
            
            // Insert attendance record
            $insert_query = "
                INSERT INTO faculty_attendance (
                    employee_id, class_schedule_id, attendance_date,
                    actual_start_time, status
                ) VALUES (?, ?, ?, ?, ?)
            ";
            
            $result = $this->db->raw($insert_query, [
                $employee_id,
                $class_schedule_id,
                $attendance_date,
                $actual_start_time,
                $status
            ]);
            
            // Get the created record
            $attendance_id = $this->db->insert_id();
            $get_query = "SELECT * FROM faculty_attendance WHERE id = ?";
            $attendance = $this->db->raw($get_query, [$attendance_id]);
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Clocked in successfully',
                'attendance' => $attendance[0]
            ]);
            exit;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to clock in', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/faculty/attendance/clockout
     * Clock out from a class session
     * Body: { attendance_id, actual_end_time }
     */
    public function clockout()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($method) !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        $attendance_id = $input['attendance_id'] ?? null;
        $actual_end_time = $input['actual_end_time'] ?? date('H:i:s');
        
        if (!$attendance_id) {
            http_response_code(400);
            echo json_encode(['error' => 'attendance_id required']);
            exit;
        }

        try {
            // Get attendance record
            $get_query = "
                SELECT fa.*, cs.start_time, cs.end_time
                FROM faculty_attendance fa
                INNER JOIN class_schedules cs ON fa.class_schedule_id = cs.id
                WHERE fa.id = ?
            ";
            $attendance = $this->db->raw($get_query, [$attendance_id]);
            
            if (!$attendance || count($attendance) === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Attendance record not found']);
                exit;
            }
            
            $record = $attendance[0];
            
            if ($record['actual_end_time']) {
                http_response_code(409);
                echo json_encode(['error' => 'Already clocked out']);
                exit;
            }
            
            // Calculate hours rendered
            $start_dt = strtotime($record['attendance_date'] . ' ' . $record['actual_start_time']);
            $end_dt = strtotime($record['attendance_date'] . ' ' . $actual_end_time);
            $hours_rendered = round(($end_dt - $start_dt) / 3600, 2);
            
            // Update attendance record
            $update_query = "
                UPDATE faculty_attendance
                SET actual_end_time = ?,
                    hours_rendered = ?,
                    updated_at = NOW()
                WHERE id = ?
            ";
            
            $this->db->raw($update_query, [
                $actual_end_time,
                $hours_rendered,
                $attendance_id
            ]);
            
            // Get updated record
            $updated = $this->db->raw("SELECT * FROM faculty_attendance WHERE id = ?", [$attendance_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Clocked out successfully',
                'attendance' => $updated[0],
                'hours_rendered' => $hours_rendered
            ]);
            exit;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to clock out', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * GET /api/faculty/{id}/payroll?period_start=YYYY-MM-DD&period_end=YYYY-MM-DD
     * Get payroll summary for a period
     */
    public function payroll($faculty_id = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $faculty_id = $faculty_id ?? $_GET['id'] ?? null;
        $period_start = $_GET['period_start'] ?? date('Y-m-01'); // First day of current month
        $period_end = $_GET['period_end'] ?? date('Y-m-t'); // Last day of current month
        
        if (!$faculty_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Faculty ID required']);
            exit;
        }

        try {
            // Get payroll record
            $query = "
                SELECT * FROM faculty_payroll
                WHERE employee_id = ?
                  AND period_start = ?
                  AND period_end = ?
            ";
            
            $payroll = $this->db->raw($query, [$faculty_id, $period_start, $period_end]);
            
            if (!$payroll || count($payroll) === 0) {
                // No payroll record exists, calculate it
                $calc_query = "CALL sp_calculate_faculty_payroll(?, ?, ?)";
                $this->db->raw($calc_query, [$faculty_id, $period_start, $period_end]);
                
                // Fetch the created record
                $payroll = $this->db->raw($query, [$faculty_id, $period_start, $period_end]);
            }
            
            echo json_encode([
                'faculty_id' => (int)$faculty_id,
                'payroll' => $payroll ? $payroll[0] : null
            ]);
            exit;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch payroll', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * GET /api/faculty/{id}/attendance-summary?month=2025-11
     * Get attendance summary for a month
     */
    public function attendance_summary($faculty_id = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $faculty_id = $faculty_id ?? $_GET['id'] ?? null;
        $month = $_GET['month'] ?? date('Y-m');
        
        if (!$faculty_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Faculty ID required']);
            exit;
        }

        try {
            $query = "
                SELECT * FROM v_faculty_attendance_summary
                WHERE employee_id = ? AND period_month = ?
            ";
            
            $summary = $this->db->raw($query, [$faculty_id, $month]);
            
            echo json_encode([
                'faculty_id' => (int)$faculty_id,
                'month' => $month,
                'summary' => $summary ? $summary[0] : null
            ]);
            exit;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch attendance summary', 'detail' => $e->getMessage()]);
            exit;
        }
    }
}
