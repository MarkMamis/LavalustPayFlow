<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Workspaces extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('WorkspaceModel');
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
    }

    // GET /api/workspaces
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        try {
            $rows = $this->WorkspaceModel->get_all_workspaces();
            echo json_encode(['workspaces' => $rows]);
            return;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch workspaces']);
            return;
        }
    }

    // POST /api/workspaces
    public function create()
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $name = isset($input['name']) ? trim($input['name']) : '';
        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Name is required']);
            return;
        }

        $data = [
            'name' => $name,
            'address' => isset($input['address']) ? trim($input['address']) : null,
            'latitude' => isset($input['latitude']) ? (float)$input['latitude'] : 0,
            'longitude' => isset($input['longitude']) ? (float)$input['longitude'] : 0,
            'radius_meters' => isset($input['radius_meters']) ? (int)$input['radius_meters'] : 50,
            'checker_enabled' => 1,
        ];

        try {
            error_log('Workspaces::create - Input data: ' . json_encode($data));
            $id = $this->WorkspaceModel->create_workspace($data);
            
            if ($id === false) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create workspace in database']);
                return;
            }
            http_response_code(201);
            echo json_encode(['success' => true, 'id' => $id, 'workspace' => array_merge($data, ['id' => $id])]);
            return;
        } catch (Exception $e) {
            http_response_code(500);
            error_log('Workspaces::create - Exception: ' . $e->getMessage());
            echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
            return;
        }
    }

    // POST /api/workspaces/update
    public function update()
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Workspace id is required']);
            return;
        }

        $data = [];
        foreach (['name','code','address','latitude','longitude','radius_meters'] as $k) {
            if (isset($input[$k])) $data[$k] = $input[$k];
        }

        try {
            $existing = $this->WorkspaceModel->find_by_id($id);
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['error' => 'Workspace not found']);
                return;
            }
            $ok = $this->WorkspaceModel->update_workspace($id, $data);
            if ($ok) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update workspace']);
            }
            return;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
            return;
        }
    }

    // DELETE /api/workspaces/{id}
    public function delete($id = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $id = (int)$id;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Workspace id is required']);
            return;
        }

        try {
            $existing = $this->WorkspaceModel->find_by_id($id);
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['error' => 'Workspace not found']);
                return;
            }
            $ok = $this->WorkspaceModel->delete_workspace($id);
            if ($ok) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete workspace']);
            }
            return;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
            return;
        }
    }
}
