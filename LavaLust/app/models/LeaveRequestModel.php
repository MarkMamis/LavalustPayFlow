<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class LeaveRequestModel extends Model
{
    protected $table = 'leave_requests';
    protected $primary_key = 'id';
    protected $fillable = ['employee_id', 'leave_type_id', 'start_date', 'end_date', 'number_of_days', 'reason', 'status', 'approved_by', 'approved_at', 'notes'];

    /**
     * Get leave requests with joined employee and leave type data
     */
    public function get_requests_with_details($filters = [])
    {
        $query = "SELECT lr.*, 
                         e.employee_code, e.status as emp_status,
                         u.first_name, u.last_name, u.email,
                         lt.code as leave_code, lt.name as leave_name, lt.paid_percentage,
                         ab.first_name as approved_by_name, ab.last_name as approved_by_lastname
                  FROM {$this->table} lr
                  JOIN employees e ON lr.employee_id = e.id
                  JOIN users u ON e.user_id = u.id
                  JOIN leave_types lt ON lr.leave_type_id = lt.id
                  LEFT JOIN users ab ON lr.approved_by = ab.id
                  WHERE 1=1";

        $params = [];

        if (!empty($filters['employee_id'])) {
            $query .= " AND lr.employee_id = ?";
            $params[] = $filters['employee_id'];
        }

        if (!empty($filters['status'])) {
            $query .= " AND lr.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['leave_type_id'])) {
            $query .= " AND lr.leave_type_id = ?";
            $params[] = $filters['leave_type_id'];
        }

        if (!empty($filters['start_date'])) {
            $query .= " AND lr.start_date >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $query .= " AND lr.end_date <= ?";
            $params[] = $filters['end_date'];
        }

        $query .= " ORDER BY lr.created_at DESC";

        if (empty($params)) {
            $stmt = $this->db->raw($query);
        } else {
            $stmt = $this->db->raw($query, $params);
        }
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Get leave requests by employee
     */
    public function get_by_employee($employee_id)
    {
        return $this->get_requests_with_details(['employee_id' => $employee_id]);
    }

    /**
     * Get pending leave requests (for approval)
     */
    public function get_pending()
    {
        return $this->get_requests_with_details(['status' => 'submitted']);
    }

    /**
     * Approve a leave request
     */
    public function approve($id, $approved_by_user_id)
    {
        $leave_request = $this->find($id);
        if (!$leave_request) {
            return false;
        }

        // Update leave request status only (balance is calculated from leave_types annual credits on-the-fly)
        $query = "UPDATE {$this->table} 
                  SET status = 'approved', approved_by = ?, approved_at = NOW() 
                  WHERE id = ?";
        return $this->db->raw($query, [$approved_by_user_id, $id]);
    }

    /**
     * Reject a leave request
     */
    public function reject($id, $reason = null)
    {
        if ($reason) {
            $query = "UPDATE {$this->table} SET status = 'rejected', notes = ? WHERE id = ?";
            return $this->db->raw($query, [$reason, $id]);
        }
        $query = "UPDATE {$this->table} SET status = 'rejected' WHERE id = ?";
        return $this->db->raw($query, [$id]);
    }

    /**
     * Cancel an approved leave request
     */
    public function cancel($id, $reason = null)
    {
        $leave_request = $this->find($id);
        if (!$leave_request || $leave_request['status'] !== 'approved') {
            return false;
        }

        // Update status only (balance is calculated from leave_types annual credits on-the-fly)
        if ($reason) {
            $query = "UPDATE {$this->table} SET status = 'cancelled', notes = ? WHERE id = ?";
            return $this->db->raw($query, [$reason, $id]);
        } else {
            $query = "UPDATE {$this->table} SET status = 'cancelled' WHERE id = ?";
            return $this->db->raw($query, [$id]);
        }
    }
}
