<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Attendance extends Controller {
    protected $AttendanceModel;

    public function __construct()
    {
        parent::__construct();
        $this->AttendanceModel = new AttendanceModel();
    }

    // GET /api/attendance?date=YYYY-MM-DD or ?start=X&end=Y or ?employee_id=123
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        // Support either single-date (`date=YYYY-MM-DD`) or range (`start=YYYY-MM-DD&end=YYYY-MM-DD`) or employee_id
        $date = isset($_GET['date']) ? $_GET['date'] : null;
        $start = isset($_GET['start']) ? $_GET['start'] : null;
        $end = isset($_GET['end']) ? $_GET['end'] : null;
        $employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
        
        try {
            if ($employee_id) {
                // Filter by employee_id
                $rows = $this->AttendanceModel->get_by_employee($employee_id);
            } elseif ($start && $end) {
                $rows = $this->AttendanceModel->get_between_dates($start, $end);
            } else {
                $d = $date ?? date('Y-m-d');
                $rows = $this->AttendanceModel->get_by_date($d);
            }
            echo json_encode(['attendance' => $rows]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch attendance', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    // GET /api/attendance/employee?id=123
    public function employee()
    {
        header('Content-Type: application/json; charset=utf-8');
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing employee id']);
            exit;
        }
        try {
            $rows = $this->AttendanceModel->get_by_employee($id);
            echo json_encode(['attendance' => $rows]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch attendance', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    // POST /api/attendance/clockin  { employee_id }
    public function clockin()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($method) !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $employee_id = isset($input['employee_id']) ? (int)$input['employee_id'] : 0;
        if (!$employee_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing employee_id']);
            exit;
        }

        $date = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $time = date('H:i:s');

        // Enforce clock-in window: allow only between 06:00:00 and 08:59:59
        if ($time < '06:00:00' || $time > '08:59:59') {
            http_response_code(400);
            echo json_encode(['error' => 'Clock-in allowed only between 06:00 and 09:00']);
            exit;
        }

        // Prevent duplicate clock-in for same day
        $existing = $this->AttendanceModel->find_by_employee_date($employee_id, $date);
        if ($existing) {
            http_response_code(409);
            echo json_encode(['error' => 'Already clocked in for today', 'attendance' => $existing]);
            exit;
        }

        // Determine status: on-time if at or before 08:00, late if after 08:00
        $status = ($time <= '08:00:00') ? 'present' : 'late';

        $data = [
            'employee_id' => $employee_id,
            'attendance_date' => $date,
            'check_in' => $now,
            'status' => $status
        ];

        $res = $this->AttendanceModel->create_attendance($data);
        if ($res === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create attendance']);
            exit;
        }

        $attendance = $this->AttendanceModel->find_by_employee_date($employee_id, $date);
        http_response_code(201);
        echo json_encode(['success' => true, 'attendance' => $attendance]);
        exit;
    }

    // POST /api/attendance/clockout  { employee_id }
    public function clockout()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($method) !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $employee_id = isset($input['employee_id']) ? (int)$input['employee_id'] : 0;
        if (!$employee_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing employee_id']);
            exit;
        }

        $date = date('Y-m-d');
            $now = date('Y-m-d H:i:s');
            $time = date('H:i:s');

        $existing = $this->AttendanceModel->find_by_employee_date($employee_id, $date);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => 'No clock-in found for today']);
            exit;
        }

        if (!empty($existing['check_out'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Already clocked out for today', 'attendance' => $existing]);
            exit;
        }

        // If clock-out is before 17:00 consider half-day
        $updateData = ['check_out' => $now];
        if ($time < '17:00:00') {
            $updateData['status'] = 'half-day';
        } else {
            // keep existing status (present or late)
        }

        $this->AttendanceModel->update_attendance((int)$existing['id'], $updateData);
        $attendance = $this->AttendanceModel->find_by_employee_date($employee_id, $date);
        echo json_encode(['success' => true, 'attendance' => $attendance]);
        exit;
    }
}
