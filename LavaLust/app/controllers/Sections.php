<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Sections extends Controller {
    protected $SectionModel;

    public function __construct()
    {
        parent::__construct();
        $this->SectionModel = new SectionModel();
    }

    /**
     * GET /api/sections
     * Return list of sections
     */
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $rows = $this->SectionModel->get_all();
            echo json_encode(['sections' => $rows]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch sections', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    public function create() {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!isset($data['name']) || empty(trim($data['name']))) {
            http_response_code(400);
            echo json_encode(['error' => 'Section name is required']);
            exit;
        }

        // Check if section already exists
        if ($this->SectionModel->name_exists($data['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Section name already exists']);
            exit;
        }

        $result = $this->SectionModel->create_section([
            'name' => $data['name'],
            'is_active' => 1
        ]);

        if ($result) {
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Section created successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create section']);
        }
        exit;
    }

    public function update() {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Section ID is required']);
            exit;
        }

        if (!isset($data['name']) || empty(trim($data['name']))) {
            http_response_code(400);
            echo json_encode(['error' => 'Section name is required']);
            exit;
        }

        // Check if section exists
        if (!$this->SectionModel->find_by_id($data['id'])) {
            http_response_code(404);
            echo json_encode(['error' => 'Section not found']);
            exit;
        }

        // Check if new name already exists for different section
        if ($this->SectionModel->name_exists($data['name'], $data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Section name already exists']);
            exit;
        }

        $result = $this->SectionModel->update_section($data['id'], [
            'name' => $data['name']
        ]);

        if ($result) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Section updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update section']);
        }
        exit;
    }

    public function toggle_status() {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Section ID is required']);
            exit;
        }

        if (!isset($data['is_active'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Active status is required']);
            exit;
        }

        // Check if section exists
        if (!$this->SectionModel->find_by_id($data['id'])) {
            http_response_code(404);
            echo json_encode(['error' => 'Section not found']);
            exit;
        }

        $result = $this->SectionModel->update_section($data['id'], [
            'is_active' => (int)$data['is_active']
        ]);

        if ($result) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Section status updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update section status']);
        }
        exit;
    }
}