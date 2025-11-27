<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Subjects extends Controller {
    protected $SubjectModel;

    public function __construct()
    {
        parent::__construct();
        $this->SubjectModel = new SubjectModel();
    }

    /**
     * GET /api/subjects
     * Return list of subjects
     */
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $rows = $this->SubjectModel->get_all();
            echo json_encode(['subjects' => $rows]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch subjects', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/subjects
     * Creates a subject. Accepts JSON: code, name, units, hours_per_week, semester, school_year, description
     */
    public function create()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($method) !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $is_json = stripos($contentType, 'application/json') !== false;
        $input = $is_json ? json_decode(file_get_contents('php://input'), true) : $_POST;

        $code = isset($input['code']) ? trim($input['code']) : '';
        $name = isset($input['name']) ? trim($input['name']) : '';
        $units = isset($input['units']) ? (int)$input['units'] : 0;
        $hours_per_week = isset($input['hoursPerWeek']) ? (int)$input['hoursPerWeek'] : 0;
        $semester = isset($input['semester']) ? trim($input['semester']) : '';
        $school_year = isset($input['schoolYear']) ? trim($input['schoolYear']) : '';
        $description = isset($input['description']) ? trim($input['description']) : null;

        error_log('[Subjects::create] incoming=' . json_encode($input));

        if (empty($code)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Subject code is required']);
            exit;
        }

        if (empty($name)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Subject name is required']);
            exit;
        }

        // Validate semester
        if (!in_array($semester, ['1st', '2nd', 'Summer'])) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid semester. Must be "1st", "2nd", or "Summer"']);
            exit;
        }

        // Validate school year format (YYYY-YYYY)
        if (!preg_match('/^\d{4}-\d{4}$/', $school_year)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid school year format. Must be YYYY-YYYY (e.g., 2024-2025)']);
            exit;
        }

        // Validate school year is 1-year interval
        list($year1, $year2) = explode('-', $school_year);
        if ((int)$year2 !== (int)$year1 + 1) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'School year must be a 1-year interval (e.g., 2024-2025)']);
            exit;
        }

        // Validate units and hours_per_week
        if ($units < 0) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Units must be non-negative']);
            exit;
        }

        if ($hours_per_week < 0) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Hours per week must be non-negative']);
            exit;
        }

        $data = [
            'code' => $code,
            'name' => $name,
            'units' => $units,
            'hours_per_week' => $hours_per_week,
            'semester' => $semester,
            'school_year' => $school_year,
            'description' => $description,
            'is_active' => 1
        ];

        try {
            $id = $this->SubjectModel->create_subject($data);
            if ($id === false) {
                error_log('[Subjects::create] create_subject returned false for data=' . json_encode($data));
                throw new Exception('Insert failed');
            }
            error_log('[Subjects::create] inserted id=' . $id);
            $created = $this->SubjectModel->find_by_id($id);
            http_response_code(201);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'subject' => $created]);
            exit;
        } catch (Exception $e) {
            error_log('[Subjects::create] Exception: ' . $e->getMessage());
            error_log('[Subjects::create] trace: ' . $e->getTraceAsString());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to create subject', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/subjects/update
     * Updates a subject. Expects id and fields to update
     */
    public function update()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($method) !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $is_json = stripos($contentType, 'application/json') !== false;
        $input = $is_json ? json_decode(file_get_contents('php://input'), true) : $_POST;

        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Missing id']);
            exit;
        }

        error_log('[Subjects::update] incoming=' . json_encode($input));

        $data = [];
        if (isset($input['code'])) $data['code'] = trim($input['code']);
        if (isset($input['name'])) $data['name'] = trim($input['name']);
        if (isset($input['units'])) {
            $units = (int)$input['units'];
            if ($units < 0) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Units must be non-negative']);
                exit;
            }
            $data['units'] = $units;
        }
        if (isset($input['hoursPerWeek'])) {
            $hours = (int)$input['hoursPerWeek'];
            if ($hours < 0) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Hours per week must be non-negative']);
                exit;
            }
            $data['hours_per_week'] = $hours;
        }
        if (isset($input['semester'])) {
            $semester = trim($input['semester']);
            if (!in_array($semester, ['1st', '2nd', 'Summer'])) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Invalid semester']);
                exit;
            }
            $data['semester'] = $semester;
        }
        if (isset($input['schoolYear'])) {
            $school_year = trim($input['schoolYear']);
            if (!preg_match('/^\d{4}-\d{4}$/', $school_year)) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Invalid school year format']);
                exit;
            }
            list($year1, $year2) = explode('-', $school_year);
            if ((int)$year2 !== (int)$year1 + 1) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'School year must be a 1-year interval']);
                exit;
            }
            $data['school_year'] = $school_year;
        }
        if (isset($input['description'])) $data['description'] = trim($input['description']);
        if (isset($input['is_active'])) $data['is_active'] = (int)$input['is_active'];

        try {
            $ok = $this->SubjectModel->update_subject($id, $data);
            if ($ok) {
                $updated = $this->SubjectModel->find_by_id($id);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'subject' => $updated]);
            } else {
                throw new Exception('Update failed');
            }
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to update subject', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/subjects/deactivate
     * Soft delete - sets is_active to 0
     */
    public function deactivate()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($method) !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $is_json = stripos($contentType, 'application/json') !== false;
        $input = $is_json ? json_decode(file_get_contents('php://input'), true) : $_POST;

        $id = isset($input['id']) ? (int)$input['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        if (!$id) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Missing id']);
            exit;
        }

        try {
            // Soft delete - just set is_active to 0
            $ok = $this->SubjectModel->update_subject($id, ['is_active' => 0]);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => (bool)$ok]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to deactivate subject', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/subjects/toggle-status
     * Toggle is_active status
     */
    public function toggle_status()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($method) !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $is_json = stripos($contentType, 'application/json') !== false;
        $input = $is_json ? json_decode(file_get_contents('php://input'), true) : $_POST;

        $id = isset($input['id']) ? (int)$input['id'] : 0;
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : null;

        if (!$id || $is_active === null) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Missing id or is_active']);
            exit;
        }

        try {
            $ok = $this->SubjectModel->update_subject($id, ['is_active' => $is_active]);
            if ($ok) {
                $updated = $this->SubjectModel->find_by_id($id);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'subject' => $updated]);
            } else {
                throw new Exception('Toggle failed');
            }
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to toggle status', 'detail' => $e->getMessage()]);
            exit;
        }
    }
}
