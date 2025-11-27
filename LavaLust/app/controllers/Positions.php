<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Positions extends Controller {
    protected $PositionModel;

    public function __construct()
    {
        parent::__construct();
        $this->PositionModel = new PositionModel();
    }

    /**
     * GET /api/positions
     * Return list of positions, optionally filtered by department
     * Query params: department_id, with_salary (1/0)
     */
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            // Check if department_id query parameter is provided
            $department_id = isset($_GET['department_id']) ? $_GET['department_id'] : null;
            $with_salary = isset($_GET['with_salary']) && $_GET['with_salary'] == '1';
            
            if ($department_id) {
                $rows = $this->PositionModel->get_by_department($department_id, $with_salary);
            } else {
                $rows = $this->PositionModel->get_all($with_salary);
            }
            
            echo json_encode(['positions' => $rows]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch positions', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * GET /api/positions/department/{id}
     * Return positions for a specific department
     */
    public function by_department($id = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Department ID is required']);
            exit;
        }

        try {
            $rows = $this->PositionModel->get_by_department($id);
            echo json_encode(['positions' => $rows]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch positions', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/positions
     * Create new position
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

            if (!isset($data['title']) || empty(trim($data['title']))) {
                http_response_code(400);
                echo json_encode(['error' => 'Position title is required']);
                exit;
            }

            if (!isset($data['department_id']) || empty($data['department_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Department ID is required']);
                exit;
            }

            // Check if position title already exists in this department
            if ($this->PositionModel->title_exists($data['title'], $data['department_id'])) {
                http_response_code(409);
                echo json_encode(['error' => 'Position title already exists in this department']);
                exit;
            }

            $insertData = [
                'title' => trim($data['title']),
                'department_id' => $data['department_id'],
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'salary_grade' => isset($data['salary_grade']) && $data['salary_grade'] ? (int)$data['salary_grade'] : null
            ];

            $id = $this->PositionModel->create_position($insertData);
            if (!$id) {
                throw new Exception('Failed to create position');
            }

            $position = $this->PositionModel->find_by_id($id);
            echo json_encode(['position' => $position]);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create position', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/positions/update
     * Update position
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
                echo json_encode(['error' => 'Position ID is required']);
                exit;
            }

            $id = $data['id'];

            if (!isset($data['title']) || empty(trim($data['title']))) {
                http_response_code(400);
                echo json_encode(['error' => 'Position title is required']);
                exit;
            }

            if (!isset($data['department_id']) || empty($data['department_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Department ID is required']);
                exit;
            }

            // Check if title exists for other positions in this department
            if ($this->PositionModel->title_exists($data['title'], $data['department_id'], $id)) {
                http_response_code(409);
                echo json_encode(['error' => 'Position title already exists in this department']);
                exit;
            }

            $updateData = [
                'title' => trim($data['title']),
                'department_id' => $data['department_id'],
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'salary_grade' => isset($data['salary_grade']) && $data['salary_grade'] ? (int)$data['salary_grade'] : null
            ];

            $success = $this->PositionModel->update_position($id, $updateData);
            if (!$success) {
                throw new Exception('Failed to update position');
            }

            $position = $this->PositionModel->find_by_id($id);
            echo json_encode(['position' => $position]);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update position', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/positions/delete
     * Delete position
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
                echo json_encode(['error' => 'Position ID is required']);
                exit;
            }

            $id = $data['id'];

            $success = $this->PositionModel->delete_position($id);
            if (!$success) {
                throw new Exception('Failed to delete position');
            }

            echo json_encode(['message' => 'Position deleted successfully']);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete position', 'detail' => $e->getMessage()]);
            exit;
        }
    }
}
