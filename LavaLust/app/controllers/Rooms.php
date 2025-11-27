<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Rooms extends Controller {
    protected $RoomModel;

    public function __construct()
    {
        parent::__construct();
        $this->RoomModel = new RoomModel();
    }

    /**
     * GET /api/rooms
     * Return list of rooms
     */
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $rows = $this->RoomModel->get_all();
            echo json_encode(['rooms' => $rows]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch rooms', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/rooms
     * Creates a room. Accepts JSON: code, name, floor, type
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
        $floor = isset($input['floor']) ? trim($input['floor']) : '';
        $type = isset($input['type']) ? trim($input['type']) : '';

        error_log('[Rooms::create] incoming=' . json_encode($input));

        if (empty($code)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Room code is required']);
            exit;
        }

        if (empty($name)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Room name is required']);
            exit;
        }

        // Validate floor
        if (!in_array($floor, ['1st Floor', '2nd Floor'])) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid floor. Must be "1st Floor" or "2nd Floor"']);
            exit;
        }

        // Validate type
        if (!in_array($type, ['Classroom', 'Laboratory'])) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid type. Must be "Classroom" or "Laboratory"']);
            exit;
        }

        $data = [
            'code' => $code,
            'name' => $name,
            'floor' => $floor,
            'type' => $type,
            'is_active' => 1
        ];

        try {
            $id = $this->RoomModel->create_room($data);
            if ($id === false) {
                error_log('[Rooms::create] create_room returned false for data=' . json_encode($data));
                throw new Exception('Insert failed');
            }
            error_log('[Rooms::create] inserted id=' . $id);
            $created = $this->RoomModel->find_by_id($id);
            http_response_code(201);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'room' => $created]);
            exit;
        } catch (Exception $e) {
            error_log('[Rooms::create] Exception: ' . $e->getMessage());
            error_log('[Rooms::create] trace: ' . $e->getTraceAsString());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to create room', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/rooms/update
     * Updates a room. Expects id and fields to update
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

        error_log('[Rooms::update] incoming=' . json_encode($input));

        $data = [];
        if (isset($input['code'])) $data['code'] = trim($input['code']);
        if (isset($input['name'])) $data['name'] = trim($input['name']);
        if (isset($input['floor'])) {
            $floor = trim($input['floor']);
            if (!in_array($floor, ['1st Floor', '2nd Floor'])) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Invalid floor']);
                exit;
            }
            $data['floor'] = $floor;
        }
        if (isset($input['type'])) {
            $type = trim($input['type']);
            if (!in_array($type, ['Classroom', 'Laboratory'])) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Invalid type']);
                exit;
            }
            $data['type'] = $type;
        }
        if (isset($input['is_active'])) $data['is_active'] = (int)$input['is_active'];

        try {
            $ok = $this->RoomModel->update_room($id, $data);
            if ($ok) {
                $updated = $this->RoomModel->find_by_id($id);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'room' => $updated]);
            } else {
                throw new Exception('Update failed');
            }
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to update room', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/rooms/deactivate
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
            $ok = $this->RoomModel->update_room($id, ['is_active' => 0]);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => (bool)$ok]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to deactivate room', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/rooms/toggle-status
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
            $ok = $this->RoomModel->update_room($id, ['is_active' => $is_active]);
            if ($ok) {
                $updated = $this->RoomModel->find_by_id($id);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'room' => $updated]);
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
