<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class PayrollModel extends Model
{
    protected $table = 'payroll_records';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all payroll records with employee details
     * @return array
     */
    public function get_all_payroll(): array
    {
        $sql = "SELECT 
                    pr.*,
                    e.employee_code,
                    e.salary_grade,
                    e.step_increment,
                    CONCAT(IFNULL(u.first_name,''), ' ', IFNULL(u.last_name,'')) AS employee_name,
                    u.first_name,
                    u.last_name,
                    u.email,
                    p.title as position_title,
                    d.name as department_name,
                    pp.name as period_name,
                    pp.start_date,
                    pp.end_date
                FROM {$this->table} pr
                LEFT JOIN employees e ON pr.employee_id = e.id
                LEFT JOIN users u ON e.user_id = u.id
                LEFT JOIN positions p ON e.position_id = p.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN payroll_periods pp ON pr.period_id = pp.id
                ORDER BY pr.created_at DESC";
        
        $stmt = $this->db->raw($sql);
        if (!$stmt) return [];
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return is_array($result) ? $result : [];
    }

    /**
     * Get payroll records for a specific period
     * @param int $period_id
     * @return array
     */
    public function get_by_period(int $period_id): array
    {
        $sql = "SELECT 
                    pr.*,
                    e.employee_code,
                    e.salary_grade,
                    e.step_increment,
                    CONCAT(IFNULL(u.first_name,''), ' ', IFNULL(u.last_name,'')) AS employee_name,
                    u.first_name,
                    u.last_name,
                    u.email,
                    p.title as position_title,
                    d.name as department_name
                FROM {$this->table} pr
                LEFT JOIN employees e ON pr.employee_id = e.id
                LEFT JOIN users u ON e.user_id = u.id
                LEFT JOIN positions p ON e.position_id = p.id
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE pr.period_id = ?
                ORDER BY u.last_name ASC, u.first_name ASC";
        
        $stmt = $this->db->raw($sql, [$period_id]);
        if (!$stmt) return [];
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return is_array($result) ? $result : [];
    }

    /**
     * Get payroll records for an employee
     * @param int $employee_id
     * @return array
     */
    public function get_by_employee(int $employee_id): array
    {
        $sql = "SELECT 
                    pr.*,
                    pp.name as period_name,
                    pp.start_date,
                    pp.end_date,
                    pp.status as period_status
                FROM {$this->table} pr
                LEFT JOIN payroll_periods pp ON pr.period_id = pp.id
                WHERE pr.employee_id = ?
                ORDER BY pr.period_month DESC";
        
        $stmt = $this->db->raw($sql, [$employee_id]);
        if (!$stmt) return [];
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return is_array($result) ? $result : [];
    }

    /**
     * Get payroll record by ID with details
     * @param int $id
     * @return array|null
     */
    public function get_by_id(int $id)
    {
        $sql = "SELECT 
                    pr.*,
                    e.employee_code,
                    e.salary_grade,
                    e.step_increment,
                    CONCAT(IFNULL(u.first_name,''), ' ', IFNULL(u.last_name,'')) AS employee_name,
                    u.first_name,
                    u.last_name,
                    u.email,
                    p.title as position_title,
                    d.name as department_name,
                    pp.name as period_name,
                    pp.start_date,
                    pp.end_date
                FROM {$this->table} pr
                LEFT JOIN employees e ON pr.employee_id = e.id
                LEFT JOIN users u ON e.user_id = u.id
                LEFT JOIN positions p ON e.position_id = p.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN payroll_periods pp ON pr.period_id = pp.id
                WHERE pr.id = ?";
        
        $stmt = $this->db->raw($sql, [$id]);
        if (!$stmt) return null;
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Create payroll record
     * @param array $data
     * @return bool|int
     */
    public function create_payroll(array $data)
    {
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $data['status'] = $data['status'] ?? 'pending';

        try {
            $res = $this->db->table($this->table)->insert($data);
            $id = 0;
            if (method_exists($this->db, 'last_id')) {
                $id = (int)$this->db->last_id();
            }
            if (!$id && is_numeric($res)) $id = (int)$res;
            return $id ?: false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Update payroll record
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update_payroll(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->table($this->table)->where('id', $id)->update($data);
    }

    /**
     * Delete payroll record
     * @param int $id
     * @return bool
     */
    public function delete_payroll(int $id): bool
    {
        return $this->db->table($this->table)->where('id', $id)->delete();
    }

    /**
     * Check if payroll exists for employee and period
     * @param int $employee_id
     * @param int $period_id
     * @return bool
     */
    public function exists_for_employee_period(int $employee_id, int $period_id): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE employee_id = ? AND period_id = ?";
        $stmt = $this->db->raw($sql, [$employee_id, $period_id]);
        if (!$stmt) return false;
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return !empty($result);
    }

    /**
     * Get payroll summary statistics for a period
     * @param int $period_id
     * @return array
     */
    public function get_period_summary(int $period_id): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_employees,
                    SUM(basic_salary) as total_basic,
                    SUM(net_salary) as total_net,
                    SUM(deduction_gsis + deduction_philhealth + deduction_pagibig + deduction_tax) as total_deductions
                FROM {$this->table}
                WHERE period_id = ?";
        
        $stmt = $this->db->raw($sql, [$period_id]);
        if (!$stmt) return [];
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: [];
    }
}
