<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class EmployeeModel extends Model
{
    protected $table = 'employees';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
        // DB is autoloaded by framework
    }

    /**
     * Return all employees with user, department and position details
     * @return array
     */
    public function get_all_employees(): array
    {
        // Join with users, departments and positions tables
        $sql = "SELECT 
                    e.id,
                    e.employee_code,
                    e.user_id,
                    e.department_id,
                    e.position_id,
                    e.salary_grade,
                    e.step_increment,
                    e.join_date,
                    e.status,
                    e.avatar,
                    e.created_at,
                    e.updated_at,
                    COALESCE(u.email, '') as email,
                    COALESCE(u.first_name, '') as first_name,
                    COALESCE(u.last_name, '') as last_name,
                    COALESCE(d.name, 'N/A') as department_name,
                    COALESCE(p.title, 'N/A') as position_title,
                    COALESCE(p.salary_grade, e.salary_grade) as position_salary_grade
                FROM employees e
                LEFT JOIN users u ON e.user_id = u.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN positions p ON e.position_id = p.id
                ORDER BY e.created_at DESC";
        
        $stmt = $this->db->raw($sql);
        
        if (!$stmt) {
            return [];
        }

        // Fetch all results from the PDOStatement
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Compute monthly salary for each employee using SalaryGradeModel
        if (is_array($result) && !empty($result)) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'SalaryGradeModel.php';
            $sgModel = new SalaryGradeModel();

            foreach ($result as &$_row) {
                $grade = isset($_row['position_salary_grade']) && !empty($_row['position_salary_grade']) ? (int)$_row['position_salary_grade'] : (int)($_row['salary_grade'] ?? 0);
                $step = isset($_row['step_increment']) ? (int)$_row['step_increment'] : 1;
                $monthly = $sgModel->get_monthly_salary($grade, $step);
                $_row['salary'] = $monthly !== null ? (float)$monthly : 0.00;
            }
            unset($_row);
        }

        return is_array($result) ? $result : [];
    }

    public function find_by_id(int $id)
    {
        // Return employee with user, department and position details
        $sql = "SELECT 
                    e.id,
                    e.employee_code,
                    e.user_id,
                    e.department_id,
                    e.position_id,
                    e.salary_grade,
                    e.step_increment,
                    e.join_date,
                    e.status,
                    e.avatar,
                    e.created_at,
                    e.updated_at,
                    COALESCE(u.email, '') as email,
                    COALESCE(u.first_name, '') as first_name,
                    COALESCE(u.last_name, '') as last_name,
                    COALESCE(d.name, 'N/A') as department_name,
                    COALESCE(p.title, 'N/A') as position_title,
                    COALESCE(p.salary_grade, e.salary_grade) as position_salary_grade
                FROM employees e
                LEFT JOIN users u ON e.user_id = u.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN positions p ON e.position_id = p.id
                WHERE e.id = ?";
        
        $stmt = $this->db->raw($sql, [$id]);
        
        if (!$stmt) {
            return null;
        }

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'SalaryGradeModel.php';
            $sgModel = new SalaryGradeModel();
            $grade = isset($result['position_salary_grade']) && !empty($result['position_salary_grade']) ? (int)$result['position_salary_grade'] : (int)($result['salary_grade'] ?? 0);
            $step = isset($result['step_increment']) ? (int)$result['step_increment'] : 1;
            $monthly = $sgModel->get_monthly_salary($grade, $step);
            $result['salary'] = $monthly !== null ? (float)$monthly : 0.00;
        }

        return $result ?: null;
    }

    public function find_by_user_id(int $user_id)
    {
        // Return employee by user_id with details
        $sql = "SELECT 
                    e.id,
                    e.employee_code,
                    e.user_id,
                    e.department_id,
                    e.position_id,
                    e.salary_grade,
                    e.step_increment,
                    e.join_date,
                    e.status,
                    e.avatar,
                    e.created_at,
                    e.updated_at,
                    COALESCE(u.email, '') as email,
                    COALESCE(u.first_name, '') as first_name,
                    COALESCE(u.last_name, '') as last_name,
                    COALESCE(d.name, 'N/A') as department_name,
                    COALESCE(p.title, 'N/A') as position_title,
                    COALESCE(p.salary_grade, e.salary_grade) as position_salary_grade
                FROM employees e
                LEFT JOIN users u ON e.user_id = u.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN positions p ON e.position_id = p.id
                WHERE e.user_id = ?";
        
        $stmt = $this->db->raw($sql, [$user_id]);
        
        if (!$stmt) {
            return null;
        }

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'SalaryGradeModel.php';
            $sgModel = new SalaryGradeModel();
            $grade = isset($result['position_salary_grade']) && !empty($result['position_salary_grade']) ? (int)$result['position_salary_grade'] : (int)($result['salary_grade'] ?? 0);
            $step = isset($result['step_increment']) ? (int)$result['step_increment'] : 1;
            $monthly = $sgModel->get_monthly_salary($grade, $step);
            $result['salary'] = $monthly !== null ? (float)$monthly : 0.00;
        }

        return $result ?: null;
    }

    public function find_by_email(string $email)
    {
        // Email is now in users table
        $sql = "SELECT 
                    e.*,
                    u.email,
                    u.first_name,
                    u.last_name
                FROM employees e
                LEFT JOIN users u ON e.user_id = u.id
                WHERE u.email = ?";
        
        $stmt = $this->db->raw($sql, [$email]);
        if (!$stmt) return null;
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function email_exists(string $email, $exclude_user_id = null)
    {
        // Check email in users table
        if ($exclude_user_id !== null) {
            $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt = $this->db->raw($sql, [$email, $exclude_user_id]);
        } else {
            $sql = "SELECT id FROM users WHERE email = ?";
            $stmt = $this->db->raw($sql, [$email]);
        }
        
        if (!$stmt) return false;
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return !empty($result);
    }

    public function create_employee(array $data)
    {
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $data['created_at'] ?? $now;
        
        // Generate employee code if not provided
        if (empty($data['employee_code'])) {
            // Get the next sequential employee code
            $sql = "SELECT employee_code FROM employees WHERE employee_code LIKE 'EMP%' ORDER BY CAST(SUBSTRING(employee_code, 4) AS UNSIGNED) DESC LIMIT 1";
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
                        $row = $lastEmployee->fetch(\PDO::FETCH_ASSOC);
                        if ($row && isset($row['employee_code'])) {
                            $lastCode = $row['employee_code'];
                        }
                    } catch (Exception $e) {
                        $lastCode = null;
                    }
                } elseif (method_exists($lastEmployee, 'fetchAll')) {
                    try {
                        $rows = $lastEmployee->fetchAll(\PDO::FETCH_ASSOC);
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
                // Extract number from code like "EMP001"
                $lastNumber = (int)substr($lastCode, 3);
                $nextNumber = $lastNumber + 1;
            }
            $data['employee_code'] = 'EMP' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        }

        // Set default values
        $data['status'] = $data['status'] ?? 'active';
        $data['step_increment'] = $data['step_increment'] ?? 1;
        
        // Remove fields that shouldn't be directly inserted and handle null values
        $insertData = [];
        $allowedColumns = [
            'employee_code', 'user_id', 'department_id', 'position_id', 
            'join_date', 'status', 'avatar', 'salary_grade', 'step_increment', 'created_at'
        ];
        
        foreach ($allowedColumns as $col) {
            if (array_key_exists($col, $data)) {
                $insertData[$col] = $data[$col];
            }
        }

        try {
            $res = $this->db->table($this->table)->insert($insertData);
            $id = 0;
            if (method_exists($this->db, 'last_id')) {
                $id = (int)$this->db->last_id();
            }
            if (!$id && is_numeric($res)) $id = (int)$res;
            return $id ?: false;
        } catch (Exception $e) {
            error_log("Employee create error: " . $e->getMessage());
            return false;
        }
    }

    public function update_employee(int $id, array $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->table($this->table)->where('id', $id)->update($data);
    }

    public function delete_employee(int $id)
    {
        return $this->db->table($this->table)->where('id', $id)->delete();
    }
}
