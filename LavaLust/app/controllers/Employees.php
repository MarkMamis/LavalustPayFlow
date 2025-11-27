<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Employees extends Controller {
    protected $EmployeeModel;

    public function __construct()
    {
        parent::__construct();
        $this->EmployeeModel = new EmployeeModel();
        // UserModel will be loaded on demand when creating user account
    }

    /**
     * GET /api/employees
     */
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $rows = $this->EmployeeModel->get_all_employees();
            echo json_encode(['employees' => $rows]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch employees', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/employees
     * Create employee with department, position, salary, etc.
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

        $firstname = isset($input['first_name']) ? trim($input['first_name']) : (isset($input['firstname']) ? trim($input['firstname']) : '');
        $lastname = isset($input['last_name']) ? trim($input['last_name']) : (isset($input['lastname']) ? trim($input['lastname']) : '');
        $email = isset($input['email']) ? trim($input['email']) : '';
        $department_id = isset($input['department_id']) ? (int)$input['department_id'] : null;
        $position_id = isset($input['position_id']) ? (int)$input['position_id'] : null;
        $salary = isset($input['salary']) ? (float)$input['salary'] : 0.00;
        $join_date = isset($input['join_date']) ? trim($input['join_date']) : date('Y-m-d');
        $employee_code = isset($input['employee_code']) ? trim($input['employee_code']) : '';

        if (empty($firstname) || empty($lastname) || empty($email)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Firstname, lastname, and email are required']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid email address']);
            exit;
        }

        // Check existing employee email
        $existingEmp = $this->EmployeeModel->find_by_email($email);
        if ($existingEmp) {
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Employee with this email already exists']);
            exit;
        }

        // Check users table for existing user
        $this->call->model('UserModel');
        $UserModel = new UserModel();
        $existingUser = $UserModel->find_by_email($email);
        if ($existingUser) {
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'User with this email already exists']);
            exit;
        }

        $data = [
            'employee_code' => $employee_code,
            'department_id' => $department_id,
            'position_id' => $position_id,
            'salary' => number_format((float)$salary, 2, '.', ''),
            'salary_grade' => isset($input['salary_grade']) && $input['salary_grade'] ? (int)$input['salary_grade'] : null,
            'step_increment' => isset($input['step_increment']) && $input['step_increment'] ? (int)$input['step_increment'] : 1,
            'join_date' => $join_date,
            'status' => 'active'
        ];

        try {
            // Create user first with first_name, last_name, email
            $userData = [
                'email' => $email,
                'first_name' => $firstname,
                'last_name' => $lastname,
                'password' => 'demo123',
                'role' => 'employee',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $userId = $UserModel->create_user($userData);
            if ($userId === false) {
                throw new Exception('Failed to create user');
            }

            // Create employee with user_id link
            $data['user_id'] = (int)$userId;
            $empId = $this->EmployeeModel->create_employee($data);
            if ($empId === false) {
                // Rollback: delete user
                $UserModel->delete_user((int)$userId);
                throw new Exception('Failed to insert employee');
            }

            $created = $this->EmployeeModel->find_by_id($empId);
            
            // Send welcome email to new employee
            $fullName = trim($firstname . ' ' . $lastname);
            $tempPassword = 'demo123'; // The password we set above
            $emailBody = email_template_welcome($fullName, $email, $tempPassword, 'employee');
            send_email($email, 'Welcome to PayFlow HR System', $emailBody, $fullName);
            
            http_response_code(201);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'employee' => $created, 'user_id' => $userId]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to create employee', 'detail' => $e->getMessage()]);
            exit;
        }
    }

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
            $ok = $this->EmployeeModel->delete_employee($id);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => (bool)$ok]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to delete employee', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/employees/update
     * Update employee information (updates users table for email/names, employees table for job info)
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
                echo json_encode(['error' => 'Employee ID is required']);
                exit;
            }

            $id = $data['id'];
            
            // Get employee to find user_id
            $employee = $this->EmployeeModel->find_by_id($id);
            if (!$employee) {
                http_response_code(404);
                echo json_encode(['error' => 'Employee not found']);
                exit;
            }
            
            $userId = $employee['user_id'];

            // Split updates: users table vs employees table
            $userUpdateData = [];
            $empUpdateData = [];
            
            // Fields that go to users table
            if (isset($data['first_name'])) $userUpdateData['first_name'] = trim($data['first_name']);
            if (isset($data['last_name'])) $userUpdateData['last_name'] = trim($data['last_name']);
            if (isset($data['email'])) {
                $email = trim($data['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid email address']);
                    exit;
                }
                // Check if email exists for other users
                if ($this->EmployeeModel->email_exists($email, $userId)) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Email already exists']);
                    exit;
                }
                $userUpdateData['email'] = $email;
            }
            
            // Fields that go to employees table
            if (isset($data['department_id'])) $empUpdateData['department_id'] = $data['department_id'] ? (int)$data['department_id'] : null;
            if (isset($data['position_id'])) $empUpdateData['position_id'] = $data['position_id'] ? (int)$data['position_id'] : null;
            if (isset($data['salary'])) $empUpdateData['salary'] = $data['salary'] !== null ? number_format((float)$data['salary'], 2, '.', '') : null;
            if (isset($data['salary_grade'])) $empUpdateData['salary_grade'] = $data['salary_grade'] ? (int)$data['salary_grade'] : null;
            if (isset($data['step_increment'])) $empUpdateData['step_increment'] = $data['step_increment'] ? (int)$data['step_increment'] : 1;
            if (isset($data['join_date'])) $empUpdateData['join_date'] = $data['join_date'];
            if (isset($data['status'])) $empUpdateData['status'] = $data['status'];
            if (isset($data['employee_code'])) $empUpdateData['employee_code'] = trim($data['employee_code']);

            if (empty($userUpdateData) && empty($empUpdateData)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                exit;
            }

            // Update users table
            if (!empty($userUpdateData) && $userId) {
                $this->call->model('UserModel');
                $UserModel = new UserModel();

                // remember previous email to detect changes
                $oldEmail = $employee['email'] ?? '';

                $UserModel->update_user((int)$userId, $userUpdateData);

                // If email was changed, send a welcome/notification email to the new address
                if (isset($userUpdateData['email']) && $userUpdateData['email'] !== $oldEmail) {
                    try {
                        $newEmail = $userUpdateData['email'];
                        $fullName = trim((isset($userUpdateData['first_name']) ? $userUpdateData['first_name'] : $employee['first_name']) . ' ' . (isset($userUpdateData['last_name']) ? $userUpdateData['last_name'] : $employee['last_name']));
                        $tempPassword = '(unchanged)';
                        $emailBody = email_template_welcome($fullName, $newEmail, $tempPassword, 'employee');
                        // best-effort send; do not break the update flow if mail fails
                        send_email($newEmail, 'Welcome to PayFlow HR System', $emailBody, $fullName);
                    } catch (Exception $mailEx) {
                        // swallow email errors but optionally log them if a logger exists
                    }
                }
            }
            
            // Update employees table
            if (!empty($empUpdateData)) {
                $success = $this->EmployeeModel->update_employee($id, $empUpdateData);
                if (!$success) {
                    throw new Exception('Failed to update employee');
                }
            }

            $employee = $this->EmployeeModel->find_by_id($id);
            echo json_encode(['employee' => $employee]);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update employee', 'detail' => $e->getMessage()]);
            exit;
        }
    }
}
