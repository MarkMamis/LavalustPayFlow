<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Departments extends Controller {
    protected $DepartmentModel;

    public function __construct()
    {
        parent::__construct();
        $this->DepartmentModel = new DepartmentModel();
    }

    /**
     * GET /api/departments
     * Return list of departments
     */
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $rows = $this->DepartmentModel->get_all();
            echo json_encode(['departments' => $rows]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch departments', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/departments
     * Creates a department. Accepts JSON or form data: name, description, slug
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

    $name = isset($input['name']) ? trim($input['name']) : '';
        $description = isset($input['description']) ? trim($input['description']) : null;
        $slug = isset($input['slug']) ? trim($input['slug']) : null;

    // DEBUG: log incoming payload
    error_log('[Departments::create] incoming=' . json_encode($input));

        if (empty($name)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Name is required']);
            exit;
        }

        $data = [
            'name' => $name,
            'description' => $description,
            'slug' => $slug
        ];

        try {
            $id = $this->DepartmentModel->create_department($data);
            if ($id === false) {
                // Log a helpful message to php error log for debugging
                error_log('[Departments::create] create_department returned false for data=' . json_encode($data));
                throw new Exception('Insert failed');
            }
            error_log('[Departments::create] inserted id=' . $id);
            $created = $this->DepartmentModel->find_by_id($id);
            http_response_code(201);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'department' => $created]);
            exit;
        } catch (Exception $e) {
            // Log exception for debugging
            error_log('[Departments::create] Exception: ' . $e->getMessage());
            error_log('[Departments::create] trace: ' . $e->getTraceAsString());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to create department', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/departments/delete  (expects id in body or query)
     */
    public function delete()
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
            $ok = $this->DepartmentModel->delete_department($id);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => (bool)$ok]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to delete department', 'detail' => $e->getMessage()]);
            exit;
        }
    }

}
