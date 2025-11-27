<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class EmployeeLeaveBalanceModel extends Model
{
    protected $table = 'employee_leave_balance';
    protected $primary_key = 'id';
    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'opening_balance', 'used_balance', 'closing_balance'];

    /**
     * Get leave balance for employee in a specific year
     */
    public function get_balance($employee_id, $leave_type_id, $year)
    {
        $query = "SELECT * FROM {$this->table} 
                  WHERE employee_id = ? AND leave_type_id = ? AND year = ?";
        $stmt = $this->db->raw($query, [$employee_id, $leave_type_id, $year]);
        return $stmt ? $stmt->fetch() : null;
    }

    /**
     * Get all leave balances for an employee in current year
     */
    public function get_employee_balances($employee_id, $year = null)
    {
        $year = $year ?? date('Y');
        $query = "SELECT elb.*, lt.code, lt.name, lt.annual_credits, lt.paid_percentage
                  FROM {$this->table} elb
                  JOIN leave_types lt ON elb.leave_type_id = lt.id
                  WHERE elb.employee_id = ? AND elb.year = ?
                  ORDER BY lt.code";
        $stmt = $this->db->raw($query, [$employee_id, $year]);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Initialize leave balances for employee on hire/start of year
     */
    public function initialize_annual_balances($employee_id, $year = null)
    {
        $year = $year ?? date('Y');
        $stmt = $this->db->raw("SELECT * FROM leave_types");
        $leave_types = $stmt ? $stmt->fetchAll() : [];
        
        foreach ($leave_types as $type) {
            // Check if already exists
            $exists = $this->get_balance($employee_id, $type['id'], $year);
            if (!$exists) {
                // Initialize with annual credits
                $this->insert([
                    'employee_id' => $employee_id,
                    'leave_type_id' => $type['id'],
                    'year' => $year,
                    'opening_balance' => $type['annual_credits'] ?? 0,
                    'used_balance' => 0,
                    'closing_balance' => $type['annual_credits'] ?? 0
                ]);
            }
        }
        return true;
    }

    /**
     * Update used balance when leave is approved
     */
    public function deduct_balance($employee_id, $leave_type_id, $days, $year = null)
    {
        $year = $year ?? date('Y');
        $balance = $this->get_balance($employee_id, $leave_type_id, $year);
        
        if ($balance && $balance['closing_balance'] >= $days) {
            $new_used = $balance['used_balance'] + $days;
            $new_closing = $balance['closing_balance'] - $days;
            
            $query = "UPDATE {$this->table} 
                      SET used_balance = ?, closing_balance = ? 
                      WHERE employee_id = ? AND leave_type_id = ? AND year = ?";
            return $this->db->raw($query, [$new_used, $new_closing, $employee_id, $leave_type_id, $year]);
        }
        return false;
    }

    /**
     * Restore balance when leave is rejected/cancelled
     */
    public function restore_balance($employee_id, $leave_type_id, $days, $year = null)
    {
        $year = $year ?? date('Y');
        $balance = $this->get_balance($employee_id, $leave_type_id, $year);
        
        if ($balance) {
            $new_used = max(0, $balance['used_balance'] - $days);
            $new_closing = $balance['closing_balance'] + $days;
            
            $query = "UPDATE {$this->table} 
                      SET used_balance = ?, closing_balance = ? 
                      WHERE employee_id = ? AND leave_type_id = ? AND year = ?";
            return $this->db->raw($query, [$new_used, $new_closing, $employee_id, $leave_type_id, $year]);
        }
        return false;
    }
}
