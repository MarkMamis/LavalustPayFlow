<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Users extends Controller {
    protected $UserModel;

    public function __construct()
    {
        parent::__construct();
        // instantiate model reference
        $this->UserModel = new UserModel();
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
        }
        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
            // Preflight request - return early
            exit;
        }
    }
    /**
     * Show registration form (server-rendered)
     * GET /register
     */
    public function register()
    {
        // Render the registration view: app/views/users/register.php
        $this->call->view('users/register');
    }

    /**
     * Create new user account (API or form)
     * POST /api/users  (API)
     * POST /register  (Form)
     * Accepts JSON or form data: email, password, role
     */
    public function create()
    {
        // Only allow POST
        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
            // If form request, redirect back with error
            $is_form = !empty($_POST) && (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false);
            if ($is_form) {
                header('Location: /register?error=' . urlencode('Method Not Allowed'));
                exit;
            }
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $is_json = stripos($contentType, 'application/json') !== false;
        $input = $is_json ? json_decode(file_get_contents('php://input'), true) : $_POST;
        $is_form = !$is_json && !empty($_POST);

        $email = isset($input['email']) ? trim($input['email']) : '';
        $password = isset($input['password']) ? $input['password'] : '';
        $role = isset($input['role']) ? trim($input['role']) : 'employee';

        // Basic validation
        if (empty($email) || empty($password)) {
            if ($is_form) {
                header('Location: /register?error=' . urlencode('Email and password are required'));
                exit;
            }
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Email and password are required']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($is_form) {
                header('Location: /register?error=' . urlencode('Invalid email address'));
                exit;
            }
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid email address']);
            exit;
        }

        $allowed_roles = ['admin', 'hr', 'employee'];
        if (!in_array($role, $allowed_roles, true)) {
            if ($is_form) {
                header('Location: /register?error=' . urlencode('Invalid role specified'));
                exit;
            }
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid role specified']);
            exit;
        }

        // Check existing email via model
        $existing = $this->UserModel->find_by_email($email);
        if ($existing) {
            // If the existing user was created via OAuth and has no password set,
            // allow setting a local password (hybrid login). Otherwise, reject.
            $existingPasswordHash = $existing['password_hash'] ?? null;
            if (empty($existingPasswordHash)) {
                // Update existing user with provided password
                $updateData = ['password' => $password];
                $this->UserModel->update_user((int)$existing['id'], $updateData);
                if ($is_form) {
                    header('Location: /register?success=1');
                    exit;
                }
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'id' => (int)$existing['id'], 'message' => 'Password set for existing account']);
                exit;
            }
            if ($is_form) {
                header('Location: /register?error=' . urlencode('Email already exists'));
                exit;
            }
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Email already exists']);
            exit;
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $data = [
            'email' => $email,
            'password_hash' => $password_hash,
            'role' => $role,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];

        try {
            $id = $this->UserModel->create_user($data);
            if ($is_form) {
                header('Location: /register?success=1');
                exit;
            }
            
            // Send welcome email
            $fullName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
            if (empty($fullName)) {
                $fullName = $email;
            }
            $emailBody = email_template_welcome($fullName, $email, $password, $role);
            send_email($email, 'Welcome to PayFlow HR System', $emailBody, $fullName);
            
            http_response_code(201);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'id' => $id]);
            exit;
        } catch (Exception $e) {
            if ($is_form) {
                header('Location: /register?error=' . urlencode('Failed to create user'));
                exit;
            }
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to create user', 'detail' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Login (supports form and JSON API)
     * GET /login => render form
     * POST /login or POST /api/login => authenticate
     */
    public function login()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $is_json = stripos($contentType, 'application/json') !== false;

        // Render login form on GET
        if (strtoupper($method) === 'GET') {
            $this->call->view('users/login');
            return;
        }

        // Only POST for authentication
        if (strtoupper($method) !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $input = $is_json ? json_decode(file_get_contents('php://input'), true) : $_POST;
        $email = isset($input['email']) ? trim($input['email']) : '';
        $password = isset($input['password']) ? $input['password'] : '';

        if (empty($email) || empty($password)) {
            if ($is_json) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Email and password are required']);
                exit;
            }
            header('Location: /login?error=' . urlencode('Email and password are required'));
            exit;
        }

        // Lookup user via model
        $user = $this->UserModel->find_by_email($email);
    if (!$user) {
            if ($is_json) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Invalid credentials']);
                exit;
            }
            header('Location: /login?error=' . urlencode('Invalid credentials'));
            exit;
        }

        // If user has no local password (e.g., created via OAuth), reject local login
        $passwordHash = $user['password_hash'] ?? '';
        if (empty($passwordHash) || !password_verify($password, $passwordHash)) {
            if ($is_json) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Invalid credentials']);
                exit;
            }
            header('Location: /login?error=' . urlencode('Invalid credentials'));
            exit;
        }

        if (isset($user['is_active']) && !$user['is_active']) {
            if ($is_json) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Account disabled']);
                exit;
            }
            header('Location: /login?error=' . urlencode('Account disabled'));
            exit;
        }

        // Start session with explicit cookie params and set minimal user info
        // Ensure cookie path is '/' and httponly is set so browser will send it on subsequent requests.
        // Use SameSite=Lax which works for same-site requests; if you run frontend on a different origin
        // consider using a dev proxy so cookies are same-site.
        $cookieParams = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
        // Use SameSite=Lax by default. This works when frontend uses the Vite proxy (same-origin).
        // SameSite=None requires Secure in modern browsers; avoid that for local HTTP dev.
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
        ];
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($cookieParams);
        } else {
            // fallback for older PHP versions
            session_set_cookie_params(0, '/', '', false, true);
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Build user session data
        $userSessionData = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'] ?? 'employee'
        ];
        
        // If employee, fetch and add employeeId
        if ($userSessionData['role'] === 'employee') {
            $this->call->model('EmployeeModel');
            $employee = $this->EmployeeModel->find_by_user_id((int)$user['id']);
            if ($employee && isset($employee['id'])) {
                $userSessionData['employeeId'] = (string)$employee['id'];
            }
        }
        
        $_SESSION['user'] = $userSessionData;

        // Regenerate session id to prevent fixation and ensure cookie is set for the new id
        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }

        // Ensure session is written to storage before returning response
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($is_json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
            exit;
        }

        // For form, redirect to home or intended page
        header('Location: /');
        exit;
    }

    /**
     * Logout endpoint (POST /api/logout or GET /logout)
     */
    public function logout()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        // Clear session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
        }
        session_destroy();

        // Respond JSON or redirect
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $is_json = stripos($contentType, 'application/json') !== false;
        if ($is_json || strtoupper($method) === 'POST') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true]);
            exit;
        }
        header('Location: /');
        exit;
    }

    /**
     * Return current authenticated user (GET /api/me)
     */
    public function me()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        header('Content-Type: application/json; charset=utf-8');
        if (!empty($_SESSION['user'])) {
            $userSession = $_SESSION['user'];
            
            // Fetch employeeId from database if user is an employee
            if ($userSession['role'] === 'employee') {
                $this->call->model('EmployeeModel');
                $employee = $this->EmployeeModel->find_by_user_id((int)$userSession['id']);
                if ($employee && isset($employee['id'])) {
                    $userSession['employeeId'] = (string)$employee['id'];
                }
            }
            
            echo json_encode(['user' => $userSession]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
        }
        exit;
    }

    /**
     * Google OAuth login endpoint (POST /api/login/google)
     * Accepts JSON body: { id_token: string }
     * Verifies the ID token with Google and finds or creates a local user,
     * then starts a PHP session like the normal login.
     */
    public function google()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($method) !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $id_token = $input['id_token'] ?? null;
        if (!$id_token) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Missing id_token']);
            exit;
        }

        // Quick verification using Google's tokeninfo endpoint (suitable for dev/testing).
        $tokeninfo_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);
        
        // Use cURL for faster response
        $ch = curl_init($tokeninfo_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($resp === false || $httpCode !== 200) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid ID token']);
            exit;
        }
        $data = json_decode($resp, true);

        // Replace with your Google OAuth client ID
        $expected_aud = '966160341487-0vo684f8u4nfqa4s8prl4n64faspcpo1.apps.googleusercontent.com';
        if (!isset($data['aud']) || $data['aud'] !== $expected_aud) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid token audience']);
            exit;
        }

        $email = $data['email'] ?? null;
        $email_verified = isset($data['email_verified']) && ($data['email_verified'] === 'true' || $data['email_verified'] === true);
        $name = $data['name'] ?? ($data['given_name'] ?? '');

        if (!$email || !$email_verified) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Email not verified']);
            exit;
        }

        // Ensure UserModel is available (constructor already sets it)
        if (!isset($this->UserModel)) {
            $this->call->model('UserModel');
            $this->UserModel = new UserModel();
        }

        $user = $this->UserModel->find_by_email($email);
        if (!$user) {
            // Parse name into firstname and lastname for employee record
            $firstname = '';
            $lastname = '';
            if ($name) {
                $nameParts = explode(',', $name);
                if (count($nameParts) >= 2) {
                    $lastname = trim($nameParts[0]);
                    $firstname = trim($nameParts[1]);
                } else {
                    $nameParts = explode(' ', trim($name));
                    $firstname = $nameParts[0] ?? '';
                    $lastname = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';
                }
            }
            if (empty($firstname)) $firstname = $email;
            if (empty($lastname)) $lastname = '';

            // Load EmployeeModel to generate employee_code
            $this->call->model('EmployeeModel');
            $EmployeeModel = new EmployeeModel();
            
            // Get the next employee number for auto-generated code
            $sql = "SELECT employee_code FROM employees WHERE employee_code LIKE 'EMP%' ORDER BY employee_code DESC LIMIT 1";
            $lastEmployee = $this->db->raw($sql);
            $lastCode = null;
            // Normalize different possible return types from DB wrapper
            if (is_array($lastEmployee)) {
                if (!empty($lastEmployee)) {
                    $first = $lastEmployee[0];
                    $lastCode = $first['employee_code'] ?? null;
                }
            } elseif (is_object($lastEmployee)) {
                // PDOStatement or similar
                if (method_exists($lastEmployee, 'fetch')) {
                    try {
                        $row = $lastEmployee->fetch(PDO::FETCH_ASSOC);
                        if ($row && isset($row['employee_code'])) {
                            $lastCode = $row['employee_code'];
                        }
                    } catch (Exception $e) {
                        // ignore and fallback
                        $lastCode = null;
                    }
                } elseif (method_exists($lastEmployee, 'fetchAll')) {
                    try {
                        $rows = $lastEmployee->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($rows) && isset($rows[0]['employee_code'])) {
                            $lastCode = $rows[0]['employee_code'];
                        }
                    } catch (Exception $e) {
                        $lastCode = null;
                    }
                }
            }

            $nextNumber = 1;
            if (!empty($lastCode)) {
                $lastNumber = (int)substr($lastCode, 3);
                $nextNumber = $lastNumber + 1;
            }
            $employeeCode = 'EMP' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            try {
                // Create user first with first_name, last_name, email
                $userData = [
                    'email' => $email,
                    'first_name' => $firstname,
                    'last_name' => $lastname,
                    'role' => 'employee',
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $newId = $this->UserModel->create_user($userData);
                if ($newId === false) {
                    http_response_code(500);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => 'Failed to create user account', 'detail' => 'Database insert failed']);
                    exit;
                }
                
                // Create employee record with user_id link
                $empData = [
                    'employee_code' => $employeeCode,
                    'user_id' => (int)$newId,
                    'position_id' => null,
                    'department_id' => null,
                    'join_date' => date('Y-m-d'),
                    'salary_grade' => null,
                    'step_increment' => 1,
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $empId = $EmployeeModel->create_employee($empData);
                if ($empId === false) {
                    // Rollback: delete user
                    $this->UserModel->delete_user((int)$newId);
                    http_response_code(500);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => 'Failed to create employee profile', 'detail' => 'Employee insert failed']);
                    exit;
                }
                
                // Build user array directly without re-querying
                $user = [
                    'id' => (int)$newId,
                    'email' => $email,
                    'first_name' => $firstname,
                    'last_name' => $lastname,
                    'role' => 'employee'
                ];
                
                // Send welcome email for Google OAuth signup
                $fullName = trim($firstname . ' ' . $lastname);
                $emailBody = email_template_welcome($fullName, $email, 'Google Sign-In', 'employee');
                send_email($email, 'Welcome to PayFlow HR System', $emailBody, $fullName);
            } catch (Exception $e) {
                error_log("Google OAuth user creation error: " . $e->getMessage());
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Failed to create account', 'detail' => $e->getMessage()]);
                exit;
            }
        }

        // Start session and set user like regular login
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Build user session data
        $userSessionData = [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'role' => $user['role'] ?? 'employee'
        ];
        
        // If employee, fetch and add employeeId
        if ($userSessionData['role'] === 'employee') {
            $this->call->model('EmployeeModel');
            $employee = $this->EmployeeModel->find_by_user_id((int)$user['id']);
            if ($employee && isset($employee['id'])) {
                $userSessionData['employeeId'] = (string)$employee['id'];
            }
        }
        
        $_SESSION['user'] = $userSessionData;
        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }
        session_write_close();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
        exit;
    }
}
