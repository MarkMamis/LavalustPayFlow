<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Leaves extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('LeaveRequestModel');
        $this->call->model('LeaveTypeModel');
    }

    /**
     * Get all leave types
     */
    public function types()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            json_response([], 'Method not allowed', 405);
            return;
        }

        try {
            $leave_types = $this->LeaveTypeModel->get_all_types();
            json_response($leave_types);
            return;
        } catch (Exception $e) {
            json_response([], $e->getMessage(), 500);
            return;
        }
    }

    /**
     * Get employee's leave balance for current year (calculated from leave_types and leave_requests)
     */
    public function balance($employee_id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            json_response([], 'Method not allowed', 405);
            return;
        }

        try {
            $year = $_GET['year'] ?? date('Y');
            
            // Get all leave types
            $leave_types = $this->LeaveTypeModel->get_all_types();
            if (!$leave_types) {
                json_response([], 'No leave types found', 404);
                return;
            }
            
            $balances = [];
            
            foreach ($leave_types as $type) {
                // Calculate used days for this leave type in this year
                $query = "SELECT COALESCE(SUM(number_of_days), 0) as used_days 
                         FROM leave_requests 
                         WHERE employee_id = ? 
                         AND leave_type_id = ? 
                         AND YEAR(start_date) = ? 
                         AND status IN ('approved', 'submitted')";
                $stmt = $this->db->raw($query, [$employee_id, $type['id'], $year]);
                $result = $stmt ? $stmt->fetch() : null;
                $used_days = $result ? (float)$result['used_days'] : 0;
                
                // Calculate available balance
                $annual_credits = $type['annual_credits'] ?? null;
                $closing_balance = $annual_credits !== null ? $annual_credits - $used_days : null;
                
                $balances[] = [
                    'leave_type_id' => $type['id'],
                    'leave_code' => $type['code'],
                    'leave_name' => $type['name'],
                    'annual_credits' => $annual_credits,
                    'used_days' => $used_days,
                    'available_balance' => $closing_balance,
                    'paid_percentage' => $type['paid_percentage']
                ];
            }
            
            json_response($balances);
            return;
        } catch (Exception $e) {
            json_response([], $e->getMessage(), 500);
            return;
        }
    }

    /**
     * Get leave requests for employee
     */
    public function requests($employee_id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            json_response([], 'Method not allowed', 405);
            return;
        }

        try {
            $status = $_GET['status'] ?? null;
            $filters = ['employee_id' => $employee_id];
            if ($status) {
                $filters['status'] = $status;
            }
            $requests = $this->LeaveRequestModel->get_requests_with_details($filters);
            json_response($requests);
            return;
        } catch (Exception $e) {
            json_response([], $e->getMessage(), 500);
            return;
        }
    }

    /**
     * Submit a leave request
     */
    public function submit()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response([], 'Method not allowed', 405);
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $required = ['employee_id', 'leave_type_id', 'start_date', 'end_date', 'number_of_days'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    json_response([], "Missing required field: {$field}", 400);
                    return;
                }
            }

            // Check if employee has sufficient balance (for balance-tracked leaves)
            $leave_type = $this->LeaveTypeModel->find($data['leave_type_id']);
            if ($leave_type && !is_null($leave_type['annual_credits'])) {
                $year = date('Y', strtotime($data['start_date']));
                
                // Calculate used days for this leave type in this year (approved + submitted)
                $query = "SELECT COALESCE(SUM(number_of_days), 0) as used_days 
                         FROM leave_requests 
                         WHERE employee_id = ? 
                         AND leave_type_id = ? 
                         AND YEAR(start_date) = ? 
                         AND status IN ('approved', 'submitted')";
                $stmt = $this->db->raw($query, [$data['employee_id'], $data['leave_type_id'], $year]);
                $result = $stmt ? $stmt->fetch() : null;
                $used_days = $result ? (float)$result['used_days'] : 0;
                
                // Calculate available balance
                $available_balance = $leave_type['annual_credits'] - $used_days;

                if ($available_balance < $data['number_of_days']) {
                    json_response([], 'Insufficient leave balance. Available: ' . $available_balance . ' days', 400);
                    return;
                }
            }

            // Create leave request
            $result = $this->LeaveRequestModel->insert([
                'employee_id' => $data['employee_id'],
                'leave_type_id' => $data['leave_type_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'number_of_days' => $data['number_of_days'],
                'reason' => $data['reason'] ?? null,
                'status' => 'submitted'
            ]);

            if ($result) {
                json_response(['id' => $result], 'Leave request submitted successfully', 201);
                return;
            } else {
                json_response([], 'Failed to create leave request', 500);
                return;
            }
        } catch (Exception $e) {
            json_response([], $e->getMessage(), 500);
            return;
        }
    }

    /**
     * Approve a leave request (admin only)
     */
    public function approve()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response([], 'Method not allowed', 405);
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id']) || empty($data['approved_by'])) {
                json_response([], 'Missing required fields', 400);
                return;
            }

            $result = $this->LeaveRequestModel->approve($data['id'], $data['approved_by']);
            if ($result) {
                json_response([], 'Leave request approved', 200);
                return;
            } else {
                json_response([], 'Failed to approve leave request', 500);
                return;
            }
        } catch (Exception $e) {
            json_response([], $e->getMessage(), 500);
            return;
        }
    }

    /**
     * Reject a leave request (admin only)
     */
    public function reject()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response([], 'Method not allowed', 405);
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id'])) {
                json_response([], 'Missing leave request ID', 400);
                return;
            }

            $result = $this->LeaveRequestModel->reject($data['id'], $data['reason'] ?? null);
            if ($result) {
                json_response([], 'Leave request rejected', 200);
                return;
            } else {
                json_response([], 'Failed to reject leave request', 500);
                return;
            }
        } catch (Exception $e) {
            json_response([], $e->getMessage(), 500);
            return;
        }
    }

    /**
     * Cancel an approved leave request
     */
    public function cancel()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response([], 'Method not allowed', 405);
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id'])) {
                json_response([], 'Missing leave request ID', 400);
                return;
            }

            $result = $this->LeaveRequestModel->cancel($data['id'], $data['reason'] ?? null);
            if ($result) {
                json_response([], 'Leave request cancelled', 200);
                return;
            } else {
                json_response([], 'Failed to cancel leave request', 500);
                return;
            }
        } catch (Exception $e) {
            json_response([], $e->getMessage(), 500);
            return;
        }
    }

    /**
     * Get pending leave requests for approval (admin)
     */
    public function pending()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            json_response([], 'Method not allowed', 405);
            return;
        }

        try {
            $requests = $this->LeaveRequestModel->get_pending();
            json_response($requests);
            return;
        } catch (Exception $e) {
            json_response([], $e->getMessage(), 500);
            return;
        }
    }

    /**
     * Create a new leave type (admin only)
     */
    public function create_type()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response([], 'Method not allowed', 405);
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['name']) || empty($data['code'])) {
                json_response([], 'Missing required fields: name or code', 400);
                return;
            }

            // Normalize data
            $payload = [
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'description' => $data['description'] ?? null,
                'annual_credits' => isset($data['annual_credits']) && $data['annual_credits'] !== '' ? $data['annual_credits'] : null,
                'paid_percentage' => isset($data['paid_percentage']) ? $data['paid_percentage'] : 0,
                'requires_approval' => !empty($data['requires_approval']) ? 1 : 0,
            ];

            $insertId = $this->LeaveTypeModel->insert($payload);
            if ($insertId) {
                json_response(['id' => $insertId], 'Leave type created', 201);
                return;
            } else {
                json_response([], 'Failed to create leave type', 500);
                return;
            }
        } catch (Exception $e) {
            json_response([], $e->getMessage(), 500);
            return;
        }
    }

    /**
     * Update an existing leave type (admin only)
     */
    public function update_type($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
            json_response([], 'Method not allowed', 405);
            return;
        }

        try {
            if (empty($id)) {
                json_response([], 'Missing leave type id', 400);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['name']) || empty($data['code'])) {
                json_response([], 'Missing required fields: name or code', 400);
                return;
            }

            $payload = [
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'description' => $data['description'] ?? null,
                'annual_credits' => isset($data['annual_credits']) && $data['annual_credits'] !== '' ? $data['annual_credits'] : null,
                'paid_percentage' => isset($data['paid_percentage']) ? $data['paid_percentage'] : 0,
                'requires_approval' => !empty($data['requires_approval']) ? 1 : 0,
            ];

            $result = $this->LeaveTypeModel->update($id, $payload);
            if ($result) {
                json_response([], 'Leave type updated', 200);
                return;
            } else {
                json_response([], 'Failed to update leave type', 500);
                return;
            }
        } catch (Exception $e) {
            json_response([], $e->getMessage(), 500);
            return;
        }
    }

    /**
     * Delete a leave type
     */
    public function delete_type($id = null)
    {
        // Allow POST fallback and DELETE
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            json_response([], 'Method not allowed', 405);
            return;
        }

        try {
            if (empty($id)) {
                json_response([], 'Missing leave type id', 400);
                return;
            }

            // Attempt delete
            $result = $this->LeaveTypeModel->delete($id);
            if ($result) {
                json_response([], 'Leave type deleted', 200);
                return;
            } else {
                json_response([], 'Failed to delete leave type', 500);
                return;
            }
        } catch (Exception $e) {
            json_response([], $e->getMessage(), 500);
            return;
        }
    }
}

/**
 * Helper function for JSON responses
 */
function json_response($data = [], $message = '', $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
}
